<?php require BASE_PATH . '/views/layout/header.php'; ?>

<!-- Report toolbar -->
<div class="page-header" style="align-items:flex-start;">
    <div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/reports" style="color:var(--primary);">360° Reports</a> › <?= htmlspecialchars($meta['group']) ?>
        </div>
        <h1 class="page-title"><?= $meta['icon'] ?> <?= htmlspecialchars($meta['title']) ?></h1>
        <p class="page-subtitle" id="reportInfo">
            <?= count($data) ?> records &nbsp;·&nbsp; <?= $from ?> to <?= $to ?>
            <?php if($branchId): ?>&nbsp;·&nbsp; Branch filtered<?php endif; ?>
        </p>
    </div>
    <div class="page-actions" style="flex-wrap:wrap;gap:8px;">
        <!-- Export buttons -->
        <div class="dropdown">
            <button class="btn btn-primary" id="exportBtn">
                📤 Export <span>▾</span>
            </button>
            <div class="dropdown-menu" id="exportMenu" style="min-width:180px;">
                <div class="dropdown-header">Export As</div>
                <a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['export'=>'pdf'])) ?>">
                    📄 PDF Document
                </a>
                <a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['export'=>'excel'])) ?>">
                    📊 Excel (.xlsx)
                </a>
                <a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">
                    📋 CSV Spreadsheet
                </a>
                <a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['export'=>'json'])) ?>">
                    { } JSON Data
                </a>
                <div class="dropdown-divider"></div>
                <div class="dropdown-item" onclick="printReport()">🖨️ Print / Save PDF</div>
            </div>
        </div>
        <a href="/reports" class="btn btn-secondary">← All Reports</a>
    </div>
</div>

<!-- Filters -->
<div class="card" id="filterCard" style="margin-bottom:20px;">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" action="" id="reportFilterForm">
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($from) ?>">
                </div>
                <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($to) ?>">
                </div>
                <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-control">
                        <option value="">All Branches</option>
                        <?php foreach($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $branchId == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Quick periods -->
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end;">
                    <?php foreach(['This Month','Last Month','This Quarter','This Year','Last Year'] as $p): ?>
                    <button type="button" class="btn btn-outline btn-sm period-btn" data-period="<?= strtolower(str_replace(' ','_',$p)) ?>">
                        <?= $p ?>
                    </button>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary boxes -->
<?php if(!empty($summary)): ?>
<div class="report-summary-bar" style="margin-bottom:20px;">
    <?php foreach($summary as $k => $v): ?>
    <div class="summary-kpi">
        <div class="summary-kpi-label"><?= htmlspecialchars($k) ?></div>
        <div class="summary-kpi-value"><?= htmlspecialchars($v) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Data table -->
<div class="card" id="reportCard">
    <div class="card-header">
        <div>
            <div class="card-title"><?= htmlspecialchars($meta['title']) ?></div>
            <div class="card-subtitle"><?= count($data) ?> records</div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="topbar-search" style="min-width:220px;">
                <span class="topbar-search-icon">🔍</span>
                <input type="text" placeholder="Search table…" id="tableSearch">
            </div>
            <span id="filteredCount" class="badge badge-primary"></span>
        </div>
    </div>

    <?php if(empty($data)): ?>
    <div class="empty-state">
        <div class="empty-icon">📊</div>
        <div class="empty-title">No data for this period</div>
        <div class="empty-desc">Try adjusting the date range or branch filter.</div>
    </div>
    <?php else: ?>
    <div class="table-wrapper" style="max-height:65vh;overflow:auto;">
        <table class="data-table" id="reportTable">
            <thead>
                <tr>
                    <th style="width:32px;">#</th>
                    <?php foreach($columns as $label => $field): ?>
                    <th class="sortable" data-field="<?= $field ?>" onclick="sortTable(this)">
                        <?= htmlspecialchars($label) ?> <span class="sort-icon">↕</span>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="reportBody">
                <?php foreach($data as $i => $row): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:11px;"><?= $i+1 ?></td>
                    <?php foreach($columns as $label => $field): ?>
                    <?php
                        $val = $row[$field] ?? '';
                        $display = htmlspecialchars((string)$val, ENT_QUOTES);
                        $class = '';
                        // Colour coding
                        if(in_array(strtolower($label),['status','bucket'])) {
                            $statusMap = ['paid'=>'badge-paid','overdue'=>'badge-overdue','draft'=>'badge-draft',
                                         'sent'=>'badge-sent','partial'=>'badge-partial','current'=>'badge-active',
                                         '1–30 days'=>'badge-pending','31–60 days'=>'badge-overdue',
                                         '61–90 days'=>'badge-overdue','90+ days'=>'badge-overdue'];
                            $bClass = $statusMap[strtolower($val)] ?? 'badge-draft';
                            $display = "<span class='badge {$bClass}'>{$display}</span>";
                        } elseif(str_contains(strtolower($label), 'amount') || in_array(strtolower($label),['revenue','total','paid','balance','net','expenses','outstanding'])) {
                            $numVal = floatval(str_replace(',','',$val));
                            $class  = $numVal < 0 ? 'amount-negative' : '';
                            $display = is_numeric($val) ? number_format((float)$val, 2) : $display;
                        }
                    ?>
                    <td class="<?= $class ?>"><?= $display ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if(!empty($data)): ?>
    <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:12px;color:var(--text-muted);">
            Showing <span id="shownCount"><?= count($data) ?></span> of <?= count($data) ?> records
        </span>
        <div style="display:flex;gap:8px;">
            <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-outline btn-sm">📋 CSV</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'excel'])) ?>" class="btn btn-outline btn-sm">📊 Excel</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'pdf'])) ?>" class="btn btn-outline btn-sm">📄 PDF</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'json'])) ?>" class="btn btn-outline btn-sm">{ } JSON</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.report-summary-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.summary-kpi {
    flex: 1;
    min-width: 150px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 16px 18px;
    border-left: 3px solid var(--primary);
}
.summary-kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-secondary); margin-bottom: 6px; }
.summary-kpi-value { font-size: 22px; font-weight: 800; color: var(--text-primary); letter-spacing: -.4px; }
.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
.sortable:hover { background: rgba(99,102,241,0.1); }
.sort-icon { opacity: 0.4; font-size: 10px; }
.sortable.asc .sort-icon  { opacity: 1; content: '↑'; }
.sortable.desc .sort-icon { opacity: 1; content: '↓'; }

@media print {
    .sidebar, .topbar, .page-actions, #filterCard, .card-footer { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .page-content { padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
    .data-table { font-size: 9pt; }
    .data-table th { background: #4f46e5 !important; color: #fff !important; }
    body::before {
        content: "AccountingPro ERP — <?= addslashes($meta['title']) ?> — <?= $from ?> to <?= $to ?>";
        display: block; font-size: 14pt; font-weight: 800; margin-bottom: 12px;
    }
}
</style>

<script>
// ── Quick period buttons ──────────────────────────────────────
document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const period = this.dataset.period;
        const now    = new Date();
        let from, to;
        const fmt = d => d.toISOString().slice(0,10);

        if (period === 'this_month') {
            from = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
            to   = fmt(new Date(now.getFullYear(), now.getMonth()+1, 0));
        } else if (period === 'last_month') {
            from = fmt(new Date(now.getFullYear(), now.getMonth()-1, 1));
            to   = fmt(new Date(now.getFullYear(), now.getMonth(), 0));
        } else if (period === 'this_quarter') {
            const q = Math.floor(now.getMonth()/3);
            from = fmt(new Date(now.getFullYear(), q*3, 1));
            to   = fmt(new Date(now.getFullYear(), q*3+3, 0));
        } else if (period === 'this_year') {
            from = fmt(new Date(now.getFullYear(), 0, 1));
            to   = fmt(new Date(now.getFullYear(), 11, 31));
        } else if (period === 'last_year') {
            from = fmt(new Date(now.getFullYear()-1, 0, 1));
            to   = fmt(new Date(now.getFullYear()-1, 11, 31));
        }
        document.querySelector('[name=date_from]').value = from;
        document.querySelector('[name=date_to]').value   = to;
        document.getElementById('reportFilterForm').submit();
    });
});

// ── Table search ──────────────────────────────────────────────
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const q    = this.value.toLowerCase();
        const rows = document.querySelectorAll('#reportBody tr');
        let shown  = 0;
        rows.forEach(row => {
            const match = row.textContent.toLowerCase().includes(q);
            row.style.display = match ? '' : 'none';
            if (match) shown++;
        });
        document.getElementById('shownCount').textContent = shown;
        const cnt = document.getElementById('filteredCount');
        if (cnt) cnt.textContent = q ? `${shown} matching` : '';
    });
}

// ── Column sort ───────────────────────────────────────────────
let sortDir = {};
function sortTable(th) {
    const field = th.dataset.field;
    const tbody = document.getElementById('reportBody');
    if (!tbody) return;

    sortDir[field] = sortDir[field] === 'asc' ? 'desc' : 'asc';
    document.querySelectorAll('.sortable').forEach(h => h.classList.remove('asc','desc'));
    th.classList.add(sortDir[field]);
    th.querySelector('.sort-icon').textContent = sortDir[field] === 'asc' ? '↑' : '↓';

    const colIndex = [...th.parentNode.children].indexOf(th);
    const rows     = [...tbody.querySelectorAll('tr')];
    rows.sort((a, b) => {
        const av = a.children[colIndex]?.textContent.trim().replace(/,/g,'') || '';
        const bv = b.children[colIndex]?.textContent.trim().replace(/,/g,'') || '';
        const an = parseFloat(av), bn = parseFloat(bv);
        const cmp = (!isNaN(an) && !isNaN(bn)) ? an - bn : av.localeCompare(bv);
        return sortDir[field] === 'asc' ? cmp : -cmp;
    });
    rows.forEach(r => tbody.appendChild(r));
}

// ── Print ─────────────────────────────────────────────────────
function printReport() { window.print(); }

// ── Export dropdown ───────────────────────────────────────────
document.getElementById('exportBtn')?.addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('exportMenu')?.classList.toggle('open');
});
document.addEventListener('click', () => {
    document.getElementById('exportMenu')?.classList.remove('open');
});
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
