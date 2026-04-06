<?php $title = ($role ? 'Edit Role' : 'New Role') . ' — Role Management'; require BASE_PATH . '/views/layout/header.php'; ?>

<?php
$modules = \App\Core\Gate::$modules;
$moduleIcons = ['dashboard'=>'📊','invoices'=>'🧾','customers'=>'👥','vendors'=>'🏭','products'=>'📦',
                'expenses'=>'💸','accounting'=>'📒','reports'=>'📈','branches'=>'🏢','users'=>'👤',
                'roles'=>'🔐','import'=>'📥','webhooks'=>'🔗','settings'=>'⚙️'];
?>

<div class="page-header">
    <div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/roles" style="color:var(--primary);">Roles</a> › <?= $role ? htmlspecialchars($role['name']) : 'New Role' ?>
        </div>
        <h1 class="page-title">🔐 <?= $role ? 'Edit Role' : 'Create Role' ?></h1>
        <?php if($role && $role['is_system']): ?>
        <p class="page-subtitle">⚠️ System role — name is locked, but you can adjust permissions</p>
        <?php endif; ?>
    </div>
    <div class="page-actions">
        <?php if($role && !$role['is_system'] && \App\Core\Gate::can('roles','create')): ?>
        <form method="POST" action="/roles/<?= $role['id'] ?>/duplicate" style="display:inline;">
            <input type="hidden" name="_token" value="<?= \App\Core\Auth::csrfToken() ?>">
            <button class="btn btn-secondary" type="submit">📋 Duplicate</button>
        </form>
        <?php endif; ?>
        <a href="/roles" class="btn btn-secondary">← Back</a>
    </div>
</div>

<form method="POST" action="<?= $role ? '/roles/' . $role['id'] . '/update' : '/roles' ?>">
    <input type="hidden" name="_token" value="<?= \App\Core\Auth::csrfToken() ?>">

    <div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;">

    <!-- LEFT: Role details -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="card">
            <div class="card-header"><div class="card-title">Role Details</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label required">Role Name</label>
                    <input type="text" name="name" class="form-control" id="roleName"
                           value="<?= htmlspecialchars($role['name'] ?? '') ?>"
                           <?= ($role['is_system'] ?? 0) ? 'readonly style="opacity:.6;"' : '' ?>
                           placeholder="e.g. Finance Manager" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="What can this role do?"><?= htmlspecialchars($role['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Colour</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="color" name="color" id="roleColor" value="<?= htmlspecialchars($role['color'] ?? '#6366f1') ?>"
                               style="width:44px;height:36px;border-radius:8px;border:1px solid var(--border);cursor:pointer;padding:2px;">
                        <div style="flex:1;height:36px;border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;padding:0 12px;font-size:12px;font-weight:700;" id="colorPreview">
                            <?= $role['name'] ?? 'Role Name' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats (edit mode) -->
        <?php if($role): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">📊 Role Stats</div></div>
            <div class="card-body" style="padding:16px;">
                <?php
                $totalPerms  = array_sum(array_map('count', $perms));
                $assignedCnt = array_sum(array_map('count', $assigned));
                ?>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:12px;color:var(--text-secondary);">Permissions assigned</span>
                    <strong style="font-size:13px;"><?= $assignedCnt ?> / <?= $totalPerms ?></strong>
                </div>
                <div class="progress-bar-wrapper">
                    <div class="progress-bar-fill" style="width:<?= $totalPerms>0 ? round($assignedCnt/$totalPerms*100) : 0 ?>%;"></div>
                </div>
                <div style="margin-top:14px;font-size:12px;color:var(--text-secondary);">
                    Users with this role: <strong><?= count($users ?? []) ?></strong>
                </div>
                <?php if(!empty($users)): ?>
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;">
                    <?php foreach(array_slice($users,0,6) as $u): ?>
                    <div class="user-avatar-sm" style="width:28px;height:28px;font-size:10px;" title="<?= htmlspecialchars($u['name']) ?>">
                        <?= strtoupper(substr($u['name'],0,2)) ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if(count($users)>6): ?>
                    <div class="user-avatar-sm" style="width:28px;height:28px;font-size:10px;background:var(--border);">+<?= count($users)-6 ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;">
            <?= $role ? '💾 Save Changes' : '✅ Create Role' ?>
        </button>
    </div>

    <!-- RIGHT: Permission matrix -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Permission Matrix</div>
                <div class="card-subtitle">Toggle access per module and action</div>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="setAll(true)">✅ Grant All</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="setAll(false)">❌ Revoke All</button>
            </div>
        </div>

        <!-- Per-module search -->
        <div style="padding:12px 20px;border-bottom:1px solid var(--border);">
            <input type="text" class="form-control" placeholder="🔍 Filter modules…" id="permSearch" style="max-width:280px;">
        </div>

        <div class="table-wrapper">
            <table class="data-table" id="permTable">
                <thead>
                    <tr>
                        <th style="width:160px;">Module</th>
                        <?php
                        $allActions = ['view','create','edit','delete','export','approve'];
                        foreach($allActions as $a): ?>
                        <th style="text-align:center;width:80px;" class="perm-action-col" data-action="<?= $a ?>">
                            <?= ucfirst($a) ?>
                        </th>
                        <?php endforeach; ?>
                        <th style="width:90px;text-align:center;">All</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($perms as $module => $modulePerms): ?>
                    <?php
                    // Build permission ID lookup for this module
                    $permMap = array_column($modulePerms, 'id', 'action');
                    $hasMod  = isset($moduleIcons[$module]);
                    ?>
                    <tr class="perm-row" data-module="<?= $module ?>">
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:13px;">
                                <span><?= $moduleIcons[$module] ?? '📌' ?></span>
                                <span><?= ucfirst($module) ?></span>
                            </div>
                        </td>
                        <?php foreach($allActions as $a): ?>
                        <td style="text-align:center;">
                            <?php if(isset($permMap[$a])): ?>
                            <?php
                            $pid      = $permMap[$a];
                            $checked  = isset($assigned[$module]) && in_array($a, $assigned[$module]);
                            ?>
                            <label class="perm-toggle" title="<?= ucfirst($module) . ' — ' . ucfirst($a) ?>">
                                <input type="checkbox" name="permissions[]"
                                       value="<?= $pid ?>"
                                       class="perm-cb" data-module="<?= $module ?>" data-action="<?= $a ?>"
                                       <?= $checked ? 'checked' : '' ?>>
                                <span class="perm-toggle-knob"></span>
                            </label>
                            <?php else: ?>
                            <span style="color:var(--border);font-size:16px;">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <!-- Row all-toggle -->
                        <td style="text-align:center;">
                            <label class="perm-toggle perm-all-toggle" title="Toggle all for <?= $module ?>">
                                <input type="checkbox" class="row-all-cb" data-module="<?= $module ?>"
                                    <?= (isset($assigned[$module]) && count($assigned[$module]) === count($modulePerms)) ? 'checked' : '' ?>>
                                <span class="perm-toggle-knob"></span>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Column toggles (toggle all for an action) -->
        <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <span style="font-size:12px;font-weight:700;color:var(--text-secondary);">TOGGLE COLUMN:</span>
            <?php foreach($allActions as $a): ?>
            <button type="button" class="btn btn-outline btn-sm col-all-btn" data-action="<?= $a ?>" onclick="toggleColumn('<?= $a ?>')">
                <?= ucfirst($a) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    </div><!-- /grid -->
</form>

<style>
.perm-toggle { display:inline-flex;align-items:center;cursor:pointer;position:relative; }
.perm-toggle input[type=checkbox] { opacity:0;position:absolute;width:0;height:0; }
.perm-toggle-knob {
    width:36px;height:20px;border-radius:10px;
    background:var(--border);
    display:inline-block;position:relative;
    transition:background 0.2s ease;
}
.perm-toggle-knob::after {
    content:'';position:absolute;top:3px;left:3px;
    width:14px;height:14px;border-radius:50%;background:#fff;
    transition:transform 0.2s ease;box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.perm-toggle input:checked ~ .perm-toggle-knob { background:var(--success); }
.perm-toggle input:checked ~ .perm-toggle-knob::after { transform:translateX(16px); }
.perm-all-toggle .perm-toggle-knob { background:var(--border); }
.perm-all-toggle input:checked ~ .perm-toggle-knob { background:var(--primary); }
.progress-bar-wrapper { height:6px;background:var(--border);border-radius:3px;overflow:hidden; }
.progress-bar-fill { height:100%;background:linear-gradient(90deg,var(--primary),var(--secondary));border-radius:3px;transition:width 0.4s ease; }
.user-avatar-sm {
    width:34px;height:34px;border-radius:10px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    color:#fff;font-size:12px;font-weight:800;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
</style>

<script>
// Colour preview
const colorInput = document.getElementById('roleColor');
const nameInput  = document.getElementById('roleName');
const preview    = document.getElementById('colorPreview');

function updatePreview() {
    const c = colorInput.value;
    preview.style.background = c + '22';
    preview.style.color      = c;
    preview.style.borderColor= c + '44';
    preview.textContent      = nameInput.value || 'Role Name';
}
colorInput?.addEventListener('input', updatePreview);
nameInput?.addEventListener('input', updatePreview);
updatePreview();

// Row all-toggle
document.querySelectorAll('.row-all-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const module = this.dataset.module;
        document.querySelectorAll(`.perm-cb[data-module="${module}"]`).forEach(c => c.checked = this.checked);
    });
});

// Keep row-all in sync
document.querySelectorAll('.perm-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const module   = this.dataset.module;
        const cbs      = document.querySelectorAll(`.perm-cb[data-module="${module}"]`);
        const allCheck = [...cbs].every(c => c.checked);
        const rowAll   = document.querySelector(`.row-all-cb[data-module="${module}"]`);
        if (rowAll) rowAll.checked = allCheck;
    });
});

// Column toggle
function toggleColumn(action) {
    const cbs     = document.querySelectorAll(`.perm-cb[data-action="${action}"]`);
    const anyOff  = [...cbs].some(c => !c.checked);
    cbs.forEach(c => c.checked = anyOff);
}

// Grant/Revoke all
function setAll(state) {
    document.querySelectorAll('.perm-cb').forEach(c => c.checked = state);
    document.querySelectorAll('.row-all-cb').forEach(c => c.checked = state);
}

// Module search filter
document.getElementById('permSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.perm-row').forEach(row => {
        row.style.display = row.dataset.module.includes(q) ? '' : 'none';
    });
});
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
