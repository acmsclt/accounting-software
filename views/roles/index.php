<?php $title = 'Roles & Permissions'; require BASE_PATH . '/views/layout/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">🔐 Roles & Permissions</h1>
        <p class="page-subtitle">Define what each role can access — <?= count($roles) ?> roles</p>
    </div>
    <div class="page-actions">
        <?php if(\App\Core\Gate::can('roles','create')): ?>
        <a href="/roles/new" class="btn btn-primary">+ New Role</a>
        <?php endif; ?>
        <a href="/users" class="btn btn-secondary">👥 Users</a>
    </div>
</div>

<?php $modules = \App\Core\Gate::$modules; ?>

<div class="roles-grid">
    <?php foreach($roles as $r):
        $rolePerms = \App\Models\Role::getPermissions($r['id']);
        $totalPossible = array_sum(array_map('count', $modules));
        $assignedCount = array_sum(array_map('count', $rolePerms));
        $pct = $totalPossible > 0 ? round($assignedCount/$totalPossible*100) : 0;
    ?>
    <div class="role-card" style="--role-color:<?= htmlspecialchars($r['color']) ?>;">
        <div class="role-card-top">
            <div class="role-card-icon"><?= strtoupper(substr($r['slug'],0,2)) ?></div>
            <div>
                <div class="role-card-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="role-card-desc"><?= htmlspecialchars($r['description'] ?? '') ?></div>
            </div>
            <?php if($r['is_system']): ?>
            <span class="badge badge-active" style="font-size:9px;margin-left:auto;">System</span>
            <?php endif; ?>
        </div>

        <!-- Permission coverage bar -->
        <div style="margin:12px 0 8px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-secondary);margin-bottom:4px;">
                <span>Permissions</span>
                <span><strong><?= $assignedCount ?></strong> / <?= $totalPossible ?></span>
            </div>
            <div class="progress-bar-wrapper">
                <div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($r['color']) ?>;"></div>
            </div>
        </div>

        <!-- Module badges -->
        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;">
            <?php foreach($modules as $mod => $acts): ?>
            <?php $hasAny = isset($rolePerms[$mod]) && count($rolePerms[$mod]) > 0; ?>
            <span style="font-size:9px;padding:2px 7px;border-radius:20px;font-weight:700;
                background:<?= $hasAny ? htmlspecialchars($r['color']).'22' : 'var(--border)' ?>;
                color:<?= $hasAny ? htmlspecialchars($r['color']) : 'var(--text-muted)' ?>;
                border:1px solid <?= $hasAny ? htmlspecialchars($r['color']).'44' : 'transparent' ?>;">
                <?= ucfirst($mod) ?>
            </span>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;align-items:center;gap:8px;border-top:1px solid var(--border);padding-top:10px;">
            <div style="font-size:11px;color:var(--text-muted);">👤 <?= $r['user_count'] ?> users</div>
            <div style="margin-left:auto;display:flex;gap:6px;">
                <?php if(\App\Core\Gate::can('roles','edit')): ?>
                <a href="/roles/<?= $r['id'] ?>/edit" class="btn btn-primary btn-sm">Edit Permissions</a>
                <?php endif; ?>
                <?php if(!$r['is_system'] && \App\Core\Gate::can('roles','delete')): ?>
                <form method="POST" action="/roles/<?= $r['id'] ?>/delete" style="display:inline;">
                    <input type="hidden" name="_token" value="<?= \App\Core\Auth::csrfToken() ?>">
                    <button class="btn btn-outline btn-sm" style="color:var(--danger);" data-confirm="Delete this role?">🗑️</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- New Role card -->
    <?php if(\App\Core\Gate::can('roles','create')): ?>
    <a href="/roles/new" class="role-card role-card-new">
        <div style="font-size:36px;margin-bottom:10px;">+</div>
        <div style="font-weight:700;font-size:14px;">Create New Role</div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Define custom access levels</div>
    </a>
    <?php endif; ?>
</div>

<!-- Permission comparison matrix (read-only overview) -->
<div class="card" style="margin-top:28px;">
    <div class="card-header">
        <div class="card-title">📋 Permission Coverage Matrix</div>
        <div class="card-subtitle">All roles vs all modules at a glance</div>
    </div>
    <div class="table-wrapper" style="overflow-x:auto;">
        <table class="data-table" style="min-width:700px;">
            <thead>
                <tr>
                    <th style="width:140px;">Module</th>
                    <?php foreach($roles as $r): ?>
                    <th style="text-align:center;" title="<?= htmlspecialchars($r['description'] ?? '') ?>">
                        <span style="color:<?= htmlspecialchars($r['color']) ?>;font-weight:700;"><?= htmlspecialchars($r['name']) ?></span>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $moduleIcons = ['dashboard'=>'📊','invoices'=>'🧾','customers'=>'👥','vendors'=>'🏭',
                                'products'=>'📦','expenses'=>'💸','accounting'=>'📒','reports'=>'📈',
                                'branches'=>'🏢','users'=>'👤','roles'=>'🔐','import'=>'📥',
                                'webhooks'=>'🔗','settings'=>'⚙️'];
                $allRolePerms = [];
                foreach($roles as $r) $allRolePerms[$r['id']] = \App\Models\Role::getPermissions($r['id']);
                ?>
                <?php foreach($modules as $mod => $acts): ?>
                <tr>
                    <td><span><?= $moduleIcons[$mod] ?? '📌' ?></span> <?= ucfirst($mod) ?></td>
                    <?php foreach($roles as $r): ?>
                    <?php
                    $rPerms = $allRolePerms[$r['id']][$mod] ?? [];
                    $total  = count($acts);
                    $has    = count($rPerms);
                    ?>
                    <td style="text-align:center;">
                        <?php if($has === 0): ?>
                            <span title="No access" style="color:var(--border);font-size:16px;">✕</span>
                        <?php elseif($has === $total): ?>
                            <span title="Full access" style="color:var(--success);font-size:16px;">✓</span>
                        <?php else: ?>
                            <span title="<?= implode(', ',$rPerms) ?>" style="color:<?= htmlspecialchars($r['color']) ?>;font-size:11px;font-weight:700;"><?= $has ?>/<?= $total ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-bottom: 0;
}
.role-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-top: 3px solid var(--role-color, var(--primary));
    border-radius: var(--radius-lg);
    padding: 20px;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    text-decoration: none;
    color: inherit;
}
.role-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.role-card-top  { display:flex;align-items:flex-start;gap:12px;margin-bottom:12px; }
.role-card-icon {
    width:40px;height:40px;border-radius:10px;flex-shrink:0;
    background:var(--role-color, var(--primary));
    color:#fff;font-size:14px;font-weight:800;
    display:flex;align-items:center;justify-content:center;
}
.role-card-name { font-size:15px;font-weight:800;color:var(--text-primary); }
.role-card-desc { font-size:11px;color:var(--text-muted);margin-top:2px; }
.role-card-new  {
    border: 2px dashed var(--border);
    border-top: 2px dashed var(--border);
    align-items:center;justify-content:center;text-align:center;
    cursor:pointer;min-height:200px;
    color:var(--text-secondary);
}
.role-card-new:hover { border-color:var(--primary);color:var(--primary);background:rgba(99,102,241,.04); }
.progress-bar-wrapper { height:5px;background:var(--border);border-radius:3px;overflow:hidden; }
.progress-bar-fill    { height:100%;border-radius:3px;transition:width 0.4s ease; }
</style>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
