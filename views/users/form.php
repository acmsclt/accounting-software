<?php $title = $user ? 'Edit User' : 'Invite User'; require BASE_PATH . '/views/layout/header.php'; ?>
<?php $modules = \App\Core\Gate::$modules; ?>

<div class="page-header">
    <div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/users" style="color:var(--primary);">Users</a> › <?= $user ? htmlspecialchars($user['name']) : 'Invite' ?>
        </div>
        <h1 class="page-title">👤 <?= $user ? 'Edit User' : 'Invite User' ?></h1>
    </div>
    <a href="/users" class="btn btn-secondary">← Back</a>
</div>

<form method="POST" action="<?= $user ? '/users/'.$user['id'].'/update' : '/users/invite' ?>">
    <input type="hidden" name="_token" value="<?= \App\Core\Auth::csrfToken() ?>">

    <div style="display:grid;grid-template-columns:360px 1fr;gap:20px;align-items:start;">

    <!-- LEFT -->
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Profile card -->
        <div class="card">
            <div class="card-header"><div class="card-title">Profile</div></div>
            <div class="card-body">
                <?php if($user): ?>
                <!-- Avatar -->
                <div style="text-align:center;margin-bottom:16px;">
                    <div style="width:72px;height:72px;border-radius:20px;margin:0 auto 10px;
                        background:linear-gradient(135deg,var(--primary),var(--secondary));
                        display:flex;align-items:center;justify-content:center;
                        font-size:24px;font-weight:800;color:#fff;">
                        <?= strtoupper(substr($user['name'],0,2)) ?>
                    </div>
                    <div style="font-weight:700;"><?= htmlspecialchars($user['name']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($user['email']) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" <?= $user['is_active'] ? 'checked' : '' ?> style="accent-color:var(--primary);">
                        <span style="font-size:13px;">Account Active</span>
                    </label>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label required">Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="colleague@company.com">
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Role assignment -->
        <div class="card">
            <div class="card-header"><div class="card-title">🔐 Assigned Roles</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach($roles as $r): ?>
                <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;
                              border:1px solid var(--border);cursor:pointer;transition:all 0.15s;"
                       class="role-checkbox-row"
                       onmouseover="this.style.borderColor='<?= htmlspecialchars($r['color']) ?>'"
                       onmouseout="this.style.borderColor='var(--border)'">
                    <input type="checkbox" name="roles[]" value="<?= $r['id'] ?>"
                           <?= in_array($r['id'], $userRoles ?? []) ? 'checked' : '' ?>
                           style="accent-color:<?= htmlspecialchars($r['color']) ?>;width:16px;height:16px;">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($r['color']) ?>;flex-shrink:0;"></div>
                    <div>
                        <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($r['name']) ?></div>
                        <div style="font-size:10px;color:var(--text-muted);"><?= htmlspecialchars($r['description'] ?? '') ?></div>
                    </div>
                    <?php if($r['is_system']): ?>
                    <span class="badge badge-active" style="font-size:9px;margin-left:auto;">System</span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Branch access -->
        <div class="card">
            <div class="card-header"><div class="card-title">🏢 Branch Access</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:6px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:12px;margin-bottom:4px;">
                    <input type="checkbox" id="allBranchesToggle" style="accent-color:var(--primary);">
                    <strong>All Branches</strong>
                </label>
                <?php foreach($branches as $b): ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;padding:6px 8px;border-radius:6px;cursor:pointer;" class="branch-cb-row">
                    <input type="checkbox" name="branches[]" value="<?= $b['id'] ?>"
                           class="branch-cb" <?= in_array($b['id'], $userBranches ?? []) ? 'checked' : '' ?>
                           style="accent-color:var(--primary);">
                    <span class="badge badge-draft" style="font-size:10px;"><?= htmlspecialchars($b['code'] ?? '') ?></span>
                    <?= htmlspecialchars($b['name']) ?>
                    <?php if($b['is_default']): ?><span class="badge badge-active" style="font-size:9px;">Default</span><?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;">
            💾 Save Changes
        </button>
    </div>

    <!-- RIGHT: Permission overrides + Activity -->
    <?php if($user): ?>
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Direct permission overrides -->
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">⚡ Permission Overrides</div>
                    <div class="card-subtitle">Override individual permissions beyond their assigned roles</div>
                </div>
                <div style="display:flex;gap:6px;">
                    <span class="badge badge-paid" style="font-size:10px;">● Granted</span>
                    <span class="badge badge-overdue" style="font-size:10px;">● Revoked</span>
                    <span class="badge badge-draft" style="font-size:10px;">○ Role default</span>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <?php foreach(['view','create','edit','delete','export','approve'] as $a): ?>
                            <th style="text-align:center;"><?= ucfirst($a) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Build override lookup
                        $overrideMap = [];
                        foreach(($overrides ?? []) as $o) {
                            $overrideMap[$o['module']][$o['action']] = ['id' => $o['id'], 'granted' => $o['granted']];
                        }
                        // Effective role perms
                        $rolePerm = $effective['role'] ?? [];

                        // All permission IDs from DB
                        $allPerms = \App\Core\Database::fetchAll("SELECT * FROM permissions WHERE company_id=? ORDER BY module,action", [$this->companyId() ?? \App\Core\Auth::companyId()]);
                        $permsByModAct = [];
                        foreach($allPerms as $p) $permsByModAct[$p['module']][$p['action']] = $p['id'];
                        ?>
                        <?php foreach($modules as $mod => $acts): ?>
                        <tr>
                            <td style="font-weight:700;font-size:12px;"><?= ucfirst($mod) ?></td>
                            <?php foreach(['view','create','edit','delete','export','approve'] as $a): ?>
                            <td style="text-align:center;">
                                <?php if(isset($permsByModAct[$mod][$a])): ?>
                                <?php
                                $pid        = $permsByModAct[$mod][$a];
                                $override   = $overrideMap[$mod][$a] ?? null;
                                $fromRole   = in_array("{$mod}.{$a}", $rolePerm);
                                $state      = $override ? ($override['granted'] ? 'grant' : 'deny') : 'default';
                                ?>
                                <div class="override-cell" data-mod="<?= $mod ?>" data-act="<?= $a ?>" data-pid="<?= $pid ?>" data-state="<?= $state ?>"
                                     onclick="cycleOverride(this)" title="Click to cycle: default → grant → revoke">
                                    <?php if($state === 'grant'): ?>
                                        <span class="override-btn grant">✓ Grant</span>
                                        <input type="hidden" name="perm_grant[]" value="<?= $pid ?>">
                                    <?php elseif($state === 'deny'): ?>
                                        <span class="override-btn deny">✕ Revoke</span>
                                        <input type="hidden" name="perm_revoke[]" value="<?= $pid ?>">
                                    <?php else: ?>
                                        <span class="override-btn <?= $fromRole ? 'from-role' : 'no-access' ?>">
                                            <?= $fromRole ? '~ Role' : '—' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span style="color:var(--border);">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Activity log -->
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Activity Log</div></div>
            <div class="card-body" style="padding:0;max-height:280px;overflow-y:auto;">
                <?php if(empty($activityLog)): ?>
                <div class="empty-state" style="padding:20px;"><div class="empty-icon">📋</div><div>No activity recorded yet</div></div>
                <?php else: ?>
                <?php foreach($activityLog as $log): ?>
                <div style="padding:10px 14px;border-bottom:1px solid var(--border);">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div style="font-size:12px;font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($log['action']) ?></div>
                        <div style="font-size:10px;color:var(--text-muted);"><?= date('d M H:i', strtotime($log['created_at'])) ?></div>
                    </div>
                    <?php if($log['description']): ?>
                    <div style="font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars($log['description']) ?></div>
                    <?php endif; ?>
                    <?php if($log['ip_address']): ?>
                    <div style="font-size:10px;color:var(--text-muted);">IP: <?= htmlspecialchars($log['ip_address']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    </div><!-- /grid -->
</form>

<style>
.override-btn {
    display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;cursor:pointer;
    transition:all 0.15s;
}
.override-btn.grant     { background:rgba(16,185,129,.12);color:#059669;border:1px solid rgba(16,185,129,.3); }
.override-btn.deny      { background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3); }
.override-btn.from-role { background:rgba(99,102,241,.1);color:var(--primary);border:1px solid rgba(99,102,241,.2); }
.override-btn.no-access { background:var(--bg-body);color:var(--text-muted);border:1px solid var(--border); }
.override-cell          { cursor:pointer; }
.override-cell:hover .override-btn { filter:brightness(1.15); }
</style>

<script>
// Three-state cycle: default → grant → deny → default
function cycleOverride(cell) {
    const state  = cell.dataset.state;
    const pid    = cell.dataset.pid;
    const states = { 'default': 'grant', 'grant': 'deny', 'deny': 'default' };
    const labels = { 'grant': '✓ Grant', 'deny': '✕ Revoke', 'default': '—' };
    const next   = states[state];
    cell.dataset.state = next;
    cell.innerHTML = `<span class="override-btn ${next === 'default' ? 'no-access' : next}">${labels[next]}</span>`;
    if (next === 'grant')  cell.innerHTML += `<input type="hidden" name="perm_grant[]" value="${pid}">`;
    if (next === 'deny')   cell.innerHTML += `<input type="hidden" name="perm_revoke[]" value="${pid}">`;
}

// Branch select-all toggle
document.getElementById('allBranchesToggle')?.addEventListener('change', function() {
    document.querySelectorAll('.branch-cb').forEach(cb => cb.checked = this.checked);
});
// Re-check if all branches selected
const branchCbs = document.querySelectorAll('.branch-cb');
const allToggle = document.getElementById('allBranchesToggle');
if (allToggle && branchCbs.length > 0) allToggle.checked = [...branchCbs].every(c => c.checked);
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
