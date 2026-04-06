<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Gate;
use App\Core\Database;
use App\Models\Role;

class RoleController extends Controller
{
    public function index(): void
    {
        Gate::authorize('roles', 'view');
        $roles = Role::allForCompany($this->companyId());
        $this->view('roles.index', compact('roles'));
    }

    public function create(): void
    {
        Gate::authorize('roles', 'create');
        $c    = $this->companyId();
        $perms = $this->allPermissions($c);
        $this->view('roles.form', ['role' => null, 'perms' => $perms, 'assigned' => []]);
    }

    public function store(): void
    {
        Gate::authorize('roles', 'create');
        Auth::verifyCsrf();
        $c = $this->companyId();

        $name = $this->sanitize($_POST['name'] ?? '');
        $slug = Role::makeSlug($name);
        $errors = $this->validate(['name' => $name], ['name' => 'required|min:2']);
        if (!empty($errors)) $this->with('errors', $errors)->redirect('/roles/new');

        // Prevent duplicate slug
        $exists = Database::fetchColumn("SELECT COUNT(*) FROM roles WHERE company_id=? AND slug=? AND deleted_at IS NULL", [$c, $slug]);
        if ($exists) $this->with('error', "A role with that name already exists.")->redirect('/roles/new');

        $id = Role::create($c, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $this->sanitize($_POST['description'] ?? ''),
            'color'       => $_POST['color'] ?? '#6366f1',
            'is_system'   => 0,
        ]);

        // Sync permissions
        $permIds = array_map('intval', $_POST['permissions'] ?? []);
        $permIds = array_filter($permIds);
        Role::syncPermissions($id, $permIds);

        $this->with('success', "Role '{$name}' created.")->redirect('/roles');
    }

    public function edit(string $id): void
    {
        Gate::authorize('roles', 'edit');
        $c    = $this->companyId();
        $role = Role::findById((int)$id, $c);
        if (!$role) $this->redirect('/roles');

        $perms    = $this->allPermissions($c);
        $assigned = Role::getPermissions((int)$id);
        $users    = Role::getUsers((int)$id);

        $this->view('roles.form', compact('role', 'perms', 'assigned', 'users'));
    }

    public function update(string $id): void
    {
        Gate::authorize('roles', 'edit');
        Auth::verifyCsrf();
        $c    = $this->companyId();
        $role = Role::findById((int)$id, $c);
        if (!$role) $this->redirect('/roles');

        if (!$role['is_system']) {
            $name = $this->sanitize($_POST['name'] ?? $role['name']);
            Role::update((int)$id, $c, [
                'name'        => $name,
                'slug'        => Role::makeSlug($name),
                'description' => $this->sanitize($_POST['description'] ?? ''),
                'color'       => $_POST['color'] ?? '#6366f1',
            ]);
        }

        // Always allow permission update (even for system roles)
        $permIds = array_map('intval', array_filter($_POST['permissions'] ?? []));
        Role::syncPermissions((int)$id, $permIds);

        $this->with('success', 'Role updated.')->redirect('/roles');
    }

    public function delete(string $id): void
    {
        Gate::authorize('roles', 'delete');
        Auth::verifyCsrf();
        $result = Role::delete((int)$id, $this->companyId());
        if (!$result) $this->with('error', 'Cannot delete a system role.')->redirect('/roles');
        $this->with('success', 'Role deleted.')->redirect('/roles');
    }

    /** Duplicate a role */
    public function duplicate(string $id): void
    {
        Gate::authorize('roles', 'create');
        Auth::verifyCsrf();
        $c    = $this->companyId();
        $role = Role::findById((int)$id, $c);
        if (!$role) $this->redirect('/roles');

        $newName = $role['name'] . ' (Copy)';
        $newId   = Role::create($c, [
            'name'        => $newName,
            'slug'        => Role::makeSlug($newName . '_' . time()),
            'description' => $role['description'],
            'color'       => $role['color'],
            'is_system'   => 0,
        ]);

        $perms = Database::fetchAll("SELECT permission_id FROM role_permissions WHERE role_id=?", [$id]);
        Role::syncPermissions($newId, array_column($perms, 'permission_id'));

        $this->with('success', "Role duplicated as '{$newName}'.")->redirect('/roles/' . $newId . '/edit');
    }

    /** AJAX: get permission count for a role */
    public function permissionsJson(string $id): void
    {
        $role  = Role::findById((int)$id, $this->companyId());
        $perms = Role::getPermissions((int)$id);
        $this->json(['role' => $role, 'permissions' => $perms]);
    }

    private function allPermissions(int $companyId): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM permissions WHERE company_id=? ORDER BY module, action",
            [$companyId]
        );
        $grouped = [];
        foreach ($rows as $p) {
            $grouped[$p['module']][] = $p;
        }
        return $grouped;
    }
}
