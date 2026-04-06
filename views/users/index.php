<?php $title = 'User Management'; require BASE_PATH . '/views/layout/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">👥 User Management</h1>
        <p class="page-subtitle"><?= count($users) ?> members in this company</p>
    </div>
    <div class="page-actions">
        <?php if(\App\Core\Gate::can('users','create')): ?>
        <a href="/users/invite" class="btn btn-primary" onclick="openModal('inviteModal');return false;">✉️ Invite User</a>
        <?php endif; ?>
        <a href="/roles" class="btn btn-secondary">🔐 Manage Roles</a>
    </div>
</div>

<!-- Stats row -->
<div class="stat-grid" style="margin-bottom:20px;">
    <?php
    $total   = count($users);
    $active  = count(array_filter($users, fn($u)=>$u['is_active']));
    $roleSet = count(array_filter($users, fn($u)=>!empty($u['roles'])));
    ?>
    <div class="stat-card revenue">
        <span class="stat-icon revenue">👥</span>
        <div class="stat-info"><div class="stat-label">Total Users</div><div class="stat-value" data-count="<?= $total ?>"><?= $total ?></div></div>
    </div>
    <div class="stat-card profit">
        <span class="stat-icon profit">✅</span>
        <div class="stat-info"><div class="stat-label">Active</div><div class="stat-value" data-count="<?= $active ?>"><?= $active ?></div></div>
    </div>
    <div class="stat-card invoices">
        <span class="stat-icon invoices">🔐</span>
        <div class="stat-info"><div class="stat-label">Roles Assigned</div><div class="stat-value" data-count="<?= $roleSet ?>"><?= $roleSet ?></div></div>
    </div>
    <div class="stat-card expenses">
        <span class="stat-icon expenses">📋</span>
        <div class="stat-info"><div class="stat-label">Custom Roles</div><div class="stat-value"><?= count($roles) ?></div></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

<!-- LEFT: User table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Team Members</div>
        <div style="display:flex;gap:8px;align-items:center;">
            <div class="topbar-search">
                <span class="topbar-search-icon">🔍</span>
                <input type="text" placeholder="Search users…" data-search-table="userTable">
            </div>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="userTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Roles</th>
                    <th>Branches</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th style="width:90px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="user-avatar-sm"><?= strtoupper(substr($u['name'],0,2)) ?></div>
                            <div>
                                <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($u['name']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if($u['roles']): ?>
                        <?php foreach(explode(',',$u['roles']) as $i=>$rn):
                            $colors = explode(',', $u['role_colors'] ?? '#6366f1');
                            $col    = trim($colors[$i] ?? '#6366f1');
                        ?>
                        <span class="badge" style="background:<?= htmlspecialchars($col) ?>22;color:<?= htmlspecialchars($col) ?>;border:1px solid <?= htmlspecialchars($col) ?>44;margin:1px;font-size:10px;">
                            <?= htmlspecialchars(trim($rn)) ?>
                        </span>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <span class="badge badge-draft" style="font-size:10px;">No role</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-secondary);">
                        <?= $u['branches'] ? htmlspecialchars($u['branches']) : '<span style="color:var(--text-muted);">All branches</span>' ?>
                    </td>
                    <td>
                        <span class="badge <?= $u['is_active'] ? 'badge-paid' : 'badge-overdue' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td style="font-size:11px;color:var(--text-muted);">
                        <?= $u['last_login_at'] ? date('d M, H:i', strtotime($u['last_login_at'])) : 'Never' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <?php if(\App\Core\Gate::can('users','edit')): ?>
                            <a href="/users/<?= $u['id'] ?>/edit" class="btn btn-outline btn-xs" title="Edit">✏️</a>
                            <?php endif; ?>
                            <?php if(\App\Core\Gate::can('users','delete')): ?>
                            <form method="POST" action="/users/<?= $u['id'] ?>/remove" style="display:inline;">
                                <input type="hidden" name="_token" value="<?= \App\Core\Auth::csrfToken() ?>">
                                <button class="btn btn-xs" style="background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);"
                                    data-confirm="Remove this user from the company?" title="Remove">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- RIGHT: Activity & Roles sidebar -->
<div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Roles overview -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">🔐 Roles</div>
            <a href="/roles/new" class="btn btn-primary btn-sm">+ New</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php foreach($roles as $r): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);">
                <div style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($r['color']) ?>;flex-shrink:0;"></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12px;font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($r['name']) ?></div>
                    <div style="font-size:10px;color:var(--text-muted);"><?= $r['user_count'] ?> users · <?= $r['permission_count'] ?> permissions</div>
                </div>
                <?php if($r['is_system']): ?>
                <span class="badge badge-active" style="font-size:9px;">System</span>
                <?php endif; ?>
                <a href="/roles/<?= $r['id'] ?>/edit" style="color:var(--text-muted);font-size:12px;">✏️</a>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card-footer" style="text-align:center;">
            <a href="/roles" style="font-size:12px;color:var(--primary);font-weight:600;">View all roles →</a>
        </div>
    </div>

    <!-- Activity log -->
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Recent Activity</div></div>
        <div class="card-body" style="padding:0;max-height:320px;overflow-y:auto;">
            <?php foreach(($activityLog ?? []) as $log): ?>
            <div style="padding:10px 14px;border-bottom:1px solid var(--border);display:flex;gap:8px;align-items:flex-start;">
                <div style="font-size:20px;line-height:1;">
                    <?= match(true) {
                        str_contains($log['action'],'invite')  => '✉️',
                        str_contains($log['action'],'update')  => '✏️',
                        str_contains($log['action'],'remove')  => '🗑️',
                        str_contains($log['action'],'login')   => '🔑',
                        default                                => '📌',
                    } ?>
                </div>
                <div>
                    <div style="font-size:12px;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($log['user_name'] ?? '') ?></div>
                    <div style="font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars($log['description'] ?? $log['action']) ?></div>
                    <div style="font-size:10px;color:var(--text-muted);"><?= date('d M H:i', strtotime($log['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<!-- Invite Modal -->
<div class="modal-overlay" id="inviteModal">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title">✉️ Invite Team Member</div>
            <button onclick="closeModal('inviteModal')" class="modal-close">×</button>
        </div>
        <form method="POST" action="/users/invite">
            <input type="hidden" name="_token" value="<?= \App\Core\Auth::csrfToken() ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="colleague@company.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Assign Role</label>
                    <select name="role_id" class="form-control">
                        <option value="">— Choose role —</option>
                        <?php foreach($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Branch Access</label>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        <?php foreach($branches as $b): ?>
                        <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;">
                            <input type="checkbox" name="branch_ids[]" value="<?= $b['id'] ?>" style="accent-color:var(--primary);">
                            <?= htmlspecialchars($b['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="background:rgba(99,102,241,.08);border-radius:8px;padding:10px 12px;font-size:12px;color:var(--text-secondary);">
                    📧 An invitation link will be sent. It expires in <strong>7 days</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('inviteModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Invitation →</button>
            </div>
        </form>
    </div>
</div>

<style>
.user-avatar-sm {
    width:34px;height:34px;border-radius:10px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    color:#fff;font-size:12px;font-weight:800;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.btn-xs { padding:4px 8px;font-size:11px;border-radius:6px; }
</style>

<script>
// Open modal from invite button
document.querySelector('[onclick*=inviteModal]')?.addEventListener('click', e => {
    e.preventDefault(); openModal('inviteModal');
});
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
