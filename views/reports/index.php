<?php require BASE_PATH . '/views/layout/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">📊 360° Reports</h1>
        <p class="page-subtitle">All your financial data, every angle — export to PDF, Excel, CSV, or JSON</p>
    </div>
</div>

<!-- Quick stats bar -->
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card revenue">
        <span class="stat-icon revenue">📈</span>
        <div class="stat-info">
            <div class="stat-label">Total Reports</div>
            <div class="stat-value"><?= array_sum(array_map('count', $groups)) ?></div>
        </div>
    </div>
    <div class="stat-card profit">
        <span class="stat-icon profit">📤</span>
        <div class="stat-info">
            <div class="stat-label">Export Formats</div>
            <div class="stat-value">4</div>
        </div>
    </div>
    <div class="stat-card invoices">
        <span class="stat-icon invoices">🗂️</span>
        <div class="stat-info">
            <div class="stat-label">Report Groups</div>
            <div class="stat-value"><?= count($groups) ?></div>
        </div>
    </div>
</div>

<!-- Export format pills -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-body" style="padding:16px 20px;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:700;color:var(--text-secondary);">EXPORT FORMATS:</span>
            <span class="badge badge-success" style="font-size:12px;padding:5px 12px;">📄 PDF</span>
            <span class="badge badge-primary" style="font-size:12px;padding:5px 12px;">📊 Excel (.xlsx)</span>
            <span class="badge" style="background:rgba(16,185,129,0.1);color:#059669;font-size:12px;padding:5px 12px;">📋 CSV</span>
            <span class="badge" style="background:rgba(245,158,11,0.1);color:#d97706;font-size:12px;padding:5px 12px;">{ } JSON</span>
            <span class="badge badge-draft" style="font-size:12px;padding:5px 12px;">🖨️ Print</span>
            <div style="margin-left:auto;font-size:12px;color:var(--text-muted);">All reports support date range &amp; branch filtering</div>
        </div>
    </div>
</div>

<!-- Report groups -->
<?php foreach($groups as $groupName => $reports): ?>
<div style="margin-bottom:28px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
        <h2 style="font-size:15px;font-weight:800;color:var(--text-primary);"><?= $groupName ?> Reports</h2>
        <span class="badge badge-primary"><?= count($reports) ?></span>
        <div style="flex:1;height:1px;background:var(--border);margin-left:8px;"></div>
    </div>
    <div class="report-card-grid">
        <?php foreach($reports as $report): ?>
        <div class="report-card" onclick="location.href='/reports/<?= $report['slug'] ?>'">
            <div class="report-card-icon"><?= $report['icon'] ?></div>
            <div class="report-card-title"><?= htmlspecialchars($report['title']) ?></div>
            <div class="report-card-meta">
                <span class="badge badge-draft" style="font-size:10px;">PDF</span>
                <span class="badge badge-primary" style="font-size:10px;">XLS</span>
                <span class="badge badge-success" style="font-size:10px;">CSV</span>
            </div>
            <a href="/reports/<?= $report['slug'] ?>" class="report-card-btn btn btn-primary btn-sm">
                View Report →
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<style>
.report-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 14px;
}
.report-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px 18px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
    overflow: hidden;
}
.report-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.25s ease;
}
.report-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
.report-card:hover::before { transform: scaleX(1); }
.report-card-icon { font-size: 28px; }
.report-card-title { font-size: 14px; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
.report-card-meta { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
.report-card-btn { margin-top: auto; width: 100%; justify-content: center; }
</style>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
