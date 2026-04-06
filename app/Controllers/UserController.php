<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Gate;
use App\Core\Database;
use App\Models\User;
use App\Models\Role;
use App\Models\Branch;

class UserController extends Controller
{
    public function index(): void
    {
        Gate::authorize('users', 'view');
        $c = $this->companyId();

        $search   = $_GET['search'] ?? '';
        $roleSlug = $_GET['role']   ?? '';
        $status   = $_GET['status'] ?? '';

        $where = ["cu.company_id = {$c}"];
        $params = [];
        if ($search) { $where[] = "(u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }

        $users = Database::fetchAll(
            "SELECT u.id, u.name, u.email, u.is_active, u.last_login_at, u.created_at,
                    cu.role AS legacy_role,
                    GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles,
                    GROUP_CONCAT(DISTINCT r.color ORDER BY r.name SEPARATOR ',') AS role_colors,
                    GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS branches,
                    COUNT(DISTINCT al.id) AS activity_count
             FROM company_users cu
             JOIN users u ON u.id = cu.user_id
             LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.company_id = cu.company_id
             LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
             LEFT JOIN branch_users bru ON bru.user_id = u.id
             LEFT JOIN branches b ON b.id = bru.branch_id AND b.company_id = {$c}
             LEFT JOIN activity_logs al ON al.user_id = u.id AND al.company_id = {$c}
             WHERE " . implode(' AND ', $where) . " AND u.deleted_at IS NULL
             GROUP BY u.id ORDER BY u.name",
            $params
        );

        $roles    = Role::allForCompany($c);
        $branches = Branch::allForCompany($c);

        // Activity log (last 30)
        $activityLog = Database::fetchAll(
            "SELECT al.*, u.name AS user_name FROM activity_logs al JOIN users u ON u.id = al.user_id
             WHERE al.company_id = ? ORDER BY al.created_at DESC LIMIT 30",
            [$c]
        );

        $this->view('users.index', compact('users', 'roles', 'branches', 'activityLog', 'search', 'roleSlug', 'status'));
    }

    public function create(): void
    {
        Gate::authorize('users', 'create');
        $c = $this->companyId();
        $roles    = Role::allForCompany($c);
        $branches = Branch::allForCompany($c);
        $this->view('users.form', ['user' => null, 'roles' => $roles, 'branches' => $branches, 'userRoles' => [], 'userBranches' => []]);
    }

    public function invite(): void
    {
        Gate::authorize('users', 'create');
        Auth::verifyCsrf();
        $c    = $this->companyId();
        $me   = $this->currentUser();
        $email  = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $roleId = (int)($_POST['role_id'] ?? 0);

        if (!$email) $this->with('error','Valid email required.')->redirect('/users/new');

        // Check not already member
        $exists = Database::fetchColumn("SELECT COUNT(*) FROM users u JOIN company_users cu ON cu.user_id=u.id WHERE u.email=? AND cu.company_id=?", [$email, $c]);
        if ($exists) $this->with('error','User already belongs to this company.')->redirect('/users/new');

        $token = bin2hex(random_bytes(32));
        Database::insert('user_invitations', [
            'company_id' => $c,
            'invited_by' => $me['id'],
            'email'      => $email,
            'role_id'    => $roleId ?: null,
            'branch_ids' => json_encode($_POST['branch_ids'] ?? []),
            'token'      => $token,
            'status'     => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        // Log activity
        self::log($c, $me['id'], 'user.invited', 'users', null, "Invited {$email}");

        // In production: send email with /register?invite={$token}
        $this->with('success', "Invitation sent to {$email}. Token: {$token}")->redirect('/users');
    }

    public function edit(string $id): void
    {
        Gate::authorize('users', 'edit');
        $c     = $this->companyId();
        $user  = $this->findUser((int)$id, $c);
        if (!$user) $this->redirect('/users');

        $roles       = Role::allForCompany($c);
        $branches    = Branch::allForCompany($c);
        $userRoles   = array_column(Database::fetchAll("SELECT role_id FROM user_roles WHERE user_id=? AND company_id=?", [$id, $c]), 'role_id');
        $userBranches= array_column(Database::fetchAll("SELECT branch_id FROM branch_users WHERE user_id=?", [$id]), 'branch_id');

        // Per-module permission overrides for this user
        $overrides   = Database::fetchAll(
            "SELECT p.id, p.module, p.action, up.granted FROM user_permissions up JOIN permissions p ON p.id=up.permission_id WHERE up.user_id=? AND up.company_id=?",
            [$id, $c]
        );

        // Effective permissions (computed)
        $effective   = Gate::all((int)$id, $c);

        $activityLog = Database::fetchAll(
            "SELECT * FROM activity_logs WHERE user_id=? AND company_id=? ORDER BY created_at DESC LIMIT 20",
            [$id, $c]
        );

        $this->view('users.form', compact('user','roles','branches','userRoles','userBranches','overrides','effective','activityLog'));
    }

    public function update(string $id): void
    {
        Gate::authorize('users', 'edit');
        Auth::verifyCsrf();
        $c  = $this->companyId();
        $me = $this->currentUser();

        // Basic profile update
        $profileData = array_filter([
            'name'   => $this->sanitize($_POST['name'] ?? ''),
            'phone'  => $this->sanitize($_POST['phone'] ?? ''),
        ]);
        if (!empty($profileData)) {
            Database::update('users', $profileData, ['id' => (int)$id]);
        }

        // Update active status
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        Database::update('users', ['is_active' => $isActive], ['id' => (int)$id]);

        // Sync roles
        $newRoles = array_map('intval', $_POST['roles'] ?? []);
        Database::delete('user_roles', ['user_id' => (int)$id, 'company_id' => $c]);
        foreach ($newRoles as $rid) {
            Database::insert('user_roles', ['user_id' => (int)$id, 'role_id' => $rid, 'company_id' => $c, 'assigned_by' => $me['id']]);
        }
        Gate::flush((int)$id, $c);

        // Sync branches
        $newBranches = array_map('intval', $_POST['branches'] ?? []);
        Database::delete('branch_users', ['user_id' => (int)$id]);
        foreach ($newBranches as $bid) {
            Database::insert('branch_users', ['branch_id' => $bid, 'user_id' => (int)$id, 'role' => 'staff']);
        }

        // Sync direct permission overrides
        $grants  = $_POST['perm_grant']  ?? [];
        $revokes = $_POST['perm_revoke'] ?? [];
        Database::delete('user_permissions', ['user_id' => (int)$id, 'company_id' => $c]);
        foreach ($grants  as $pid) {
            Database::insert('user_permissions', ['user_id' => (int)$id, 'permission_id' => (int)$pid, 'company_id' => $c, 'granted' => 1]);
        }
        foreach ($revokes as $pid) {
            Database::insert('user_permissions', ['user_id' => (int)$id, 'permission_id' => (int)$pid, 'company_id' => $c, 'granted' => 0]);
        }
        Gate::flush((int)$id, $c);

        self::log($c, $me['id'], 'user.updated', 'users', (int)$id, "Updated user #{$id}");
        $this->with('success', 'User updated successfully.')->redirect('/users');
    }

    public function remove(string $id): void
    {
        Gate::authorize('users', 'delete');
        Auth::verifyCsrf();
        $c  = $this->companyId();
        $me = $this->currentUser();
        if ((int)$id === $me['id']) $this->with('error', 'You cannot remove yourself.')->redirect('/users');

        Database::delete('company_users', ['user_id' => (int)$id, 'company_id' => $c]);
        Database::delete('user_roles',    ['user_id' => (int)$id, 'company_id' => $c]);
        Gate::flush((int)$id, $c);

        self::log($c, $me['id'], 'user.removed', 'users', (int)$id, "Removed user #{$id}");
        $this->with('success', 'User removed from company.')->redirect('/users');
    }

    private function findUser(int $id, int $companyId): ?array
    {
        return Database::fetch(
            "SELECT u.*, cu.role AS legacy_role FROM users u JOIN company_users cu ON cu.user_id=u.id WHERE u.id=? AND cu.company_id=? AND u.deleted_at IS NULL",
            [$id, $companyId]
        ) ?: null;
    }

    private static function log(int $c, int $uid, string $action, string $model, ?int $modelId, string $desc): void
    {
        Database::insert('activity_logs', [
            'company_id'  => $c,
            'user_id'     => $uid,
            'action'      => $action,
            'model'       => $model,
            'model_id'    => $modelId,
            'description' => $desc,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
