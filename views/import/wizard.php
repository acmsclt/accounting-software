<?php require BASE_PATH . '/views/layout/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Data Import</h1>
        <p class="page-subtitle">Import data from Google Sheets or CSV files in seconds</p>
    </div>
    <div class="page-actions">
        <a href="/import" class="btn btn-secondary">
            <span>📋</span> Import History
        </a>
    </div>
</div>

<!-- Import Wizard Steps -->
<div class="import-wizard" id="importWizard">

    <!-- Step indicators -->
    <div class="wizard-steps card" style="padding:20px 24px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:0;">
            <?php $steps = ['Source','Configure','Preview','Map Columns','Import']; ?>
            <?php foreach($steps as $i => $s): $n = $i+1; ?>
            <div class="wizard-step <?= $n === 1 ? 'active' : '' ?>" data-step="<?= $n ?>">
                <div class="wizard-step-circle"><?= $n ?></div>
                <span class="wizard-step-label"><?= $s ?></span>
            </div>
            <?php if($i < count($steps)-1): ?>
            <div class="wizard-step-line"></div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Step 1: Source -->
    <div class="wizard-panel active" id="step1">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">📥 Step 1: Choose Your Data Source</div>
                    <div class="card-subtitle">Import from Google Sheets or upload a CSV file</div>
                </div>
            </div>
            <div class="card-body">
                <!-- Source type selector -->
                <div class="source-type-grid">
                    <label class="source-type-card" id="srcGoogle">
                        <input type="radio" name="source_type" value="google_sheets" checked style="display:none">
                        <div class="source-type-icon">
                            <img src="https://www.google.com/images/about/sheets-icon.svg" onerror="this.style.display='none'" style="width:32px">
                            <span style="font-size:28px">📊</span>
                        </div>
                        <div class="source-type-name">Google Sheets</div>
                        <div class="source-type-desc">Paste any public Google Sheets share link</div>
                        <div class="source-type-check">✓</div>
                    </label>
                    <label class="source-type-card" id="srcCsv">
                        <input type="radio" name="source_type" value="csv" style="display:none">
                        <div class="source-type-icon">📄</div>
                        <div class="source-type-name">CSV File</div>
                        <div class="source-type-desc">Upload a .csv file from your computer</div>
                        <div class="source-type-check">✓</div>
                    </label>
                </div>

                <!-- Google Sheets URL input -->
                <div id="gsInput" style="margin-top:20px;">
                    <div class="form-group">
                        <label class="form-label required">Google Sheets Share URL</label>
                        <div style="display:flex;gap:10px;align-items:center;">
                            <input type="url" id="sheetUrl" class="form-control"
                                placeholder="https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit"
                                style="flex:1">
                            <button type="button" class="btn btn-secondary" id="pasteBtn">
                                📋 Paste
                            </button>
                        </div>
                        <div class="form-hint">
                            💡 Make sure the sheet is shared as <strong>"Anyone with the link can view"</strong>.
                            The sheet does <strong>not</strong> need to be public.
                        </div>
                    </div>

                    <div class="notice-box" style="margin-top:12px;">
                        <div class="notice-icon">ℹ️</div>
                        <div class="notice-content">
                            <strong>How to share your Google Sheet:</strong>
                            Click <em>Share</em> → Change to <em>"Anyone with the link"</em> → Set to <em>"Viewer"</em> → Copy link
                        </div>
                    </div>
                </div>

                <!-- CSV upload input -->
                <div id="csvInput" style="margin-top:20px; display:none;">
                    <div class="form-group">
                        <label class="form-label required">Upload CSV File</label>
                        <div class="file-drop-zone" id="fileDrop">
                            <div class="file-drop-icon">📂</div>
                            <div class="file-drop-text">
                                <strong>Drop your CSV file here</strong>
                                <span>or click to browse</span>
                            </div>
                            <input type="file" id="csvFile" accept=".csv" style="display:none">
                        </div>
                        <div id="fileInfo" class="file-info" style="display:none;"></div>
                        <div class="form-hint">Supported format: .csv (UTF-8 encoding recommended)</div>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:8px; flex-wrap:wrap;">
                        <?php foreach($entities as $slug => $label): ?>
                        <a href="/import/template/<?= $slug ?>" class="btn btn-outline btn-sm">
                            ⬇ <?= $label ?> Template
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex; justify-content:flex-end; margin-top:16px;">
            <button class="btn btn-primary" id="nextStep1">
                Continue <span>→</span>
            </button>
        </div>
    </div>

    <!-- Step 2: Configure -->
    <div class="wizard-panel" id="step2" style="display:none;">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">⚙️ Step 2: Configure Import</div>
                    <div class="card-subtitle">Choose what to import and how</div>
                </div>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">What are you importing?</label>
                        <select id="importEntity" class="form-control">
                            <?php foreach($entities as $slug => $label): ?>
                            <option value="<?= $slug ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assign to Branch</label>
                        <select id="importBranch" class="form-control">
                            <option value="">— All Branches —</option>
                            <?php foreach($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?> (<?= $b['code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Options</label>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <label class="toggle-check">
                                <input type="checkbox" id="optUpdateExisting" value="1">
                                <span class="toggle-check-label">
                                    <strong>Update existing records</strong>
                                    <small>Match by email/SKU and update if found</small>
                                </span>
                            </label>
                            <label class="toggle-check">
                                <input type="checkbox" id="optSkipErrors" value="1" checked>
                                <span class="toggle-check-label">
                                    <strong>Skip failed rows</strong>
                                    <small>Continue import even if individual rows fail</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:16px;">
            <button class="btn btn-secondary" id="prevStep2">← Back</button>
            <button class="btn btn-primary" id="nextStep2">
                <span id="previewBtnText">Fetch & Preview</span>
                <span id="previewSpinner" class="spinner-inline" style="display:none;"></span>
            </button>
        </div>
    </div>

    <!-- Step 3: Preview -->
    <div class="wizard-panel" id="step3" style="display:none;">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">👁 Step 3: Preview Data</div>
                    <div id="previewSubtitle" class="card-subtitle">First 10 rows from your source</div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <span id="totalRowsBadge" class="badge badge-primary"></span>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div id="previewTableWrap" class="table-wrapper" style="max-height:400px; overflow:auto; border-radius:0;">
                    <div class="empty-state" id="previewEmpty">
                        <div class="empty-icon">📊</div>
                        <div>Fetching preview...</div>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:16px;">
            <button class="btn btn-secondary" id="prevStep3">← Back</button>
            <button class="btn btn-primary" id="nextStep3">Map Columns →</button>
        </div>
    </div>

    <!-- Step 4: Column Mapping -->
    <div class="wizard-panel" id="step4" style="display:none;">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">🔗 Step 4: Map Columns</div>
                    <div class="card-subtitle">Match your sheet columns to system fields</div>
                </div>
                <button class="btn btn-outline btn-sm" id="autoMapBtn">✨ Auto-detect</button>
            </div>
            <div class="card-body">
                <div id="mappingGrid" class="mapping-grid"></div>
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:16px;">
            <button class="btn btn-secondary" id="prevStep4">← Back</button>
            <button class="btn btn-primary" id="nextStep4">Review & Import →</button>
        </div>
    </div>

    <!-- Step 5: Import -->
    <div class="wizard-panel" id="step5" style="display:none;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">🚀 Step 5: Run Import</div>
            </div>
            <div class="card-body">
                <!-- Summary -->
                <div id="importSummary" class="import-summary-box">
                    <div class="summary-item"><span class="summary-label">Entity</span><span id="sumEntity" class="summary-val"></span></div>
                    <div class="summary-item"><span class="summary-label">Source</span><span id="sumSource" class="summary-val"></span></div>
                    <div class="summary-item"><span class="summary-label">Total Rows</span><span id="sumRows" class="summary-val"></span></div>
                    <div class="summary-item"><span class="summary-label">Branch</span><span id="sumBranch" class="summary-val"></span></div>
                    <div class="summary-item"><span class="summary-label">Mapped Fields</span><span id="sumMapped" class="summary-val"></span></div>
                </div>

                <!-- Progress (hidden until import starts) -->
                <div id="importProgress" style="display:none; margin-top:20px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <strong>Importing...</strong>
                        <span id="progressPct">0%</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" id="progressBar" style="width:0%"></div>
                    </div>
                    <div id="progressMsg" style="font-size:12px; color:var(--text-secondary); margin-top:8px;">
                        Preparing import...
                    </div>
                </div>

                <!-- Result (hidden until done) -->
                <div id="importResult" style="display:none; margin-top:20px;">
                    <div class="result-grid">
                        <div class="result-card success">
                            <div class="result-num" id="resSuccess">0</div>
                            <div class="result-label">Imported</div>
                        </div>
                        <div class="result-card warning">
                            <div class="result-num" id="resSkipped">0</div>
                            <div class="result-label">Skipped</div>
                        </div>
                        <div class="result-card danger">
                            <div class="result-num" id="resFailed">0</div>
                            <div class="result-label">Failed</div>
                        </div>
                    </div>
                    <div style="text-align:center; margin-top:20px; display:flex; gap:10px; justify-content:center;">
                        <a href="#" id="viewJobLink" class="btn btn-secondary">📋 View Detailed Log</a>
                        <button class="btn btn-primary" onclick="window.location.reload()">🔄 New Import</button>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:16px;" id="step5Nav">
            <button class="btn btn-secondary" id="prevStep5">← Back</button>
            <button class="btn btn-success" id="runImportBtn" style="min-width:160px;">
                <span id="runBtnText">🚀 Start Import</span>
                <span id="runSpinner" class="spinner-inline" style="display:none;"></span>
            </button>
        </div>
    </div>

</div><!-- /.import-wizard -->

<!-- CSRF token -->
<input type="hidden" id="csrfToken" value="<?= \App\Core\Auth::csrfToken() ?>">

<style>
/* Import Wizard Styles */
.wizard-steps > div {
    display: flex;
    align-items: center;
}
.wizard-step {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 500;
}
.wizard-step.active, .wizard-step.done {
    color: var(--primary);
}
.wizard-step-circle {
    width: 30px; height: 30px;
    border-radius: 50%;
    border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700;
    transition: all var(--transition);
    flex-shrink: 0;
}
.wizard-step.active .wizard-step-circle {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}
.wizard-step.done .wizard-step-circle {
    background: var(--success);
    border-color: var(--success);
    color: #fff;
}
.wizard-step-line {
    flex: 1;
    height: 2px;
    background: var(--border);
    margin: 0 8px;
    min-width: 30px;
}
.wizard-step-label { white-space: nowrap; font-size: 12px; }
@media (max-width: 600px) { .wizard-step-label { display: none; } }

.source-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}
.source-type-card {
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    padding: 20px;
    cursor: pointer;
    transition: all var(--transition);
    position: relative;
    text-align: center;
}
.source-type-card:hover { border-color: var(--primary); background: var(--primary-light); }
.source-type-card.selected { border-color: var(--primary); background: var(--primary-light); }
.source-type-icon { font-size: 36px; margin-bottom: 10px; }
.source-type-name { font-weight: 700; font-size: 15px; color: var(--text-primary); margin-bottom: 4px; }
.source-type-desc { font-size: 12px; color: var(--text-secondary); }
.source-type-check {
    position: absolute;
    top: 10px; right: 10px;
    width: 22px; height: 22px;
    background: var(--primary);
    color: #fff;
    border-radius: 50%;
    display: none;
    align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700;
}
.source-type-card.selected .source-type-check { display: flex; }

.notice-box {
    display: flex;
    gap: 10px;
    background: rgba(59,130,246,0.07);
    border: 1px solid rgba(59,130,246,0.2);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 12px;
    color: var(--text-secondary);
}
.notice-icon { font-size: 16px; flex-shrink: 0; }

.file-drop-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 36px;
    text-align: center;
    cursor: pointer;
    transition: all var(--transition);
}
.file-drop-zone:hover, .file-drop-zone.drag-over {
    border-color: var(--primary);
    background: var(--primary-light);
}
.file-drop-icon { font-size: 40px; margin-bottom: 10px; }
.file-drop-text { display: flex; flex-direction: column; gap: 4px; font-size: 14px; }
.file-drop-text strong { color: var(--text-primary); }
.file-drop-text span { color: var(--text-muted); font-size: 12px; }
.file-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: var(--bg-input);
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
}

.preview-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.preview-table th {
    background: var(--primary);
    color: #fff;
    padding: 10px 12px;
    text-align: left;
    white-space: nowrap;
    font-size: 11px;
    font-weight: 600;
    position: sticky; top: 0; z-index: 2;
}
.preview-table td {
    padding: 9px 12px;
    border-bottom: 1px solid var(--border);
    color: var(--text-primary);
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.preview-table tr:hover td { background: var(--bg-input); }
.preview-table tr:nth-child(even) td { background: rgba(0,0,0,0.01); }

.mapping-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}
.mapping-row {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.mapping-sheet-col {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
}
.mapping-sheet-col::before {
    content: '📋';
    font-size: 14px;
}
.mapping-arrow {
    text-align: center;
    color: var(--text-muted);
    font-size: 16px;
}
.mapping-sample {
    font-size: 11px;
    color: var(--text-muted);
    font-style: italic;
    padding: 4px 8px;
    background: var(--bg-card);
    border-radius: 4px;
    margin-top: 2px;
}
.mapping-row.mapped { border-color: rgba(99,102,241,0.3); background: var(--primary-light); }
.mapping-row.required-unmapped { border-color: var(--danger); }

.import-summary-box {
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}
.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}
.summary-item:last-child { border-bottom: none; }
.summary-label { color: var(--text-secondary); font-weight: 600; }
.summary-val { font-weight: 700; color: var(--text-primary); }

.progress-bar-track {
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 4px;
    transition: width 0.3s ease;
}

.result-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    text-align: center;
}
.result-card { padding: 20px; border-radius: 12px; }
.result-card.success { background: rgba(16,185,129,0.1); }
.result-card.warning { background: rgba(245,158,11,0.1); }
.result-card.danger  { background: rgba(239,68,68,0.1); }
.result-num { font-size: 36px; font-weight: 800; line-height: 1; }
.result-card.success .result-num { color: var(--success); }
.result-card.warning .result-num { color: var(--warning); }
.result-card.danger .result-num  { color: var(--danger); }
.result-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-top: 6px; text-transform: uppercase; }

.toggle-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 14px;
    transition: border-color var(--transition);
}
.toggle-check:hover { border-color: var(--primary); }
.toggle-check input[type=checkbox] { margin-top: 3px; width: 16px; height: 16px; accent-color: var(--primary); flex-shrink: 0; }
.toggle-check-label { display: flex; flex-direction: column; gap: 2px; }
.toggle-check-label strong { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.toggle-check-label small { font-size: 11px; color: var(--text-muted); }
</style>

<script>
// ============================================================
// Import Wizard JavaScript
// ============================================================
const csrf = document.getElementById('csrfToken').value;
let currentStep  = 1;
let previewData  = { headers: [], rows: [], total: 0 };
let autoMapping  = {};
let fieldOptions = [];
let jobId        = null;

// ── Step navigation ──────────────────────────────────────────
function goStep(n) {
    document.querySelectorAll('.wizard-panel').forEach(p => p.style.display = 'none');
    document.querySelector(`#step${n}`).style.display = 'block';

    document.querySelectorAll('.wizard-step').forEach((s, i) => {
        s.classList.remove('active','done');
        const sn = i + 1;
        if (sn < n) s.classList.add('done'), (s.querySelector('.wizard-step-circle').textContent = '✓');
        else if (sn === n) s.classList.add('active');
    });

    currentStep = n;
}

// Source type toggle
document.querySelectorAll('input[name=source_type]').forEach(r => {
    r.closest('.source-type-card').addEventListener('click', function() {
        document.querySelectorAll('.source-type-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        const val = this.querySelector('input').value;
        document.getElementById('gsInput').style.display  = val === 'google_sheets' ? '' : 'none';
        document.getElementById('csvInput').style.display = val === 'csv'           ? '' : 'none';
    });
    if (r.checked) r.closest('.source-type-card').classList.add('selected');
});

// Paste button
document.getElementById('pasteBtn').addEventListener('click', async () => {
    try {
        const txt = await navigator.clipboard.readText();
        document.getElementById('sheetUrl').value = txt;
    } catch { showToast('Could not access clipboard. Please paste manually.', 'warning'); }
});

// File drop zone
const dropZone = document.getElementById('fileDrop');
const csvFile  = document.getElementById('csvFile');
if (dropZone) {
    dropZone.addEventListener('click',       () => csvFile.click());
    dropZone.addEventListener('dragover',    e  => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave',   ()  => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop',        e  => {
        e.preventDefault(); dropZone.classList.remove('drag-over');
        if (e.dataTransfer.files[0]) { csvFile.files = e.dataTransfer.files; showFileInfo(e.dataTransfer.files[0]); }
    });
    csvFile.addEventListener('change', e => e.target.files[0] && showFileInfo(e.target.files[0]));
}
function showFileInfo(file) {
    const info = document.getElementById('fileInfo');
    info.style.display = 'flex';
    info.innerHTML = `<span>📄</span> <strong>${file.name}</strong> <span class="text-muted">(${(file.size/1024).toFixed(1)} KB)</span>`;
}

// ── Step 1 → 2 ──────────────────────────────────────────────
document.getElementById('nextStep1').addEventListener('click', () => {
    const srcType = document.querySelector('input[name=source_type]:checked').value;
    if (srcType === 'google_sheets' && !document.getElementById('sheetUrl').value.trim()) {
        showToast('Please enter a Google Sheets URL.', 'error'); return;
    }
    if (srcType === 'csv' && !csvFile.value) {
        showToast('Please upload a CSV file.', 'error'); return;
    }
    goStep(2);
});
document.getElementById('prevStep2').addEventListener('click', () => goStep(1));

// ── Step 2 → 3 (fetch preview) ──────────────────────────────
document.getElementById('nextStep2').addEventListener('click', async () => {
    const btn     = document.getElementById('nextStep2');
    const spinner = document.getElementById('previewSpinner');
    document.getElementById('previewBtnText').textContent = 'Fetching...';
    spinner.style.display = 'inline-block';
    btn.disabled = true;

    try {
        const fd = buildFormData(true);
        const resp = await fetch('/import/preview', { method: 'POST', body: fd });
        const data = await resp.json();

        if (!data.success) { showToast(data.message, 'error'); return; }

        previewData  = { headers: data.headers, rows: data.rows, total: data.total };
        autoMapping  = data.auto_map;
        fieldOptions = data.fields;

        renderPreview(data.headers, data.rows, data.total);
        goStep(3);
    } catch (e) {
        showToast('Failed to fetch preview: ' + e.message, 'error');
    } finally {
        document.getElementById('previewBtnText').textContent = 'Fetch & Preview';
        spinner.style.display = 'none';
        btn.disabled = false;
    }
});
document.getElementById('prevStep3').addEventListener('click', () => goStep(2));

// ── Render preview table ─────────────────────────────────────
function renderPreview(headers, rows, total) {
    document.getElementById('totalRowsBadge').textContent = `${total.toLocaleString()} total rows`;
    document.getElementById('previewSubtitle').textContent =
        `Showing first ${rows.length} of ${total.toLocaleString()} rows`;

    let th = headers.map(h => `<th title="${h}">${h}</th>`).join('');
    let tbody = rows.map(row => {
        let cells = headers.map(h => {
            const val = String(row[h] ?? '');
            return `<td title="${val}">${val.length > 30 ? val.substring(0,30)+'…' : val}</td>`;
        }).join('');
        return `<tr>${cells}</tr>`;
    }).join('');

    document.getElementById('previewTableWrap').innerHTML =
        `<table class="preview-table"><thead><tr>${th}</tr></thead><tbody>${tbody}</tbody></table>`;
}

// ── Step 3 → 4 (column mapping) ──────────────────────────────
document.getElementById('nextStep3').addEventListener('click', () => {
    renderMappingGrid();
    goStep(4);
});
document.getElementById('prevStep4').addEventListener('click', () => goStep(3));
document.getElementById('autoMapBtn').addEventListener('click', applyAutoMap);

function renderMappingGrid() {
    const entity  = document.getElementById('importEntity').value;
    const grid    = document.getElementById('mappingGrid');

    const systemFields = [{value:'', label:'— Skip this column —'}, ...fieldOptions.map(f => ({value:f, label:f.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}))];

    grid.innerHTML = previewData.headers.map((header, idx) => {
        const sample   = previewData.rows.slice(0,3).map(r => r[header]).filter(Boolean).join(', ');
        const mapped   = autoMapping[header] || '';
        const opts     = systemFields.map(f =>
            `<option value="${f.value}" ${f.value === mapped ? 'selected' : ''}>${f.label}</option>`
        ).join('');

        return `<div class="mapping-row ${mapped ? 'mapped' : ''}" id="mr_${idx}">
            <div class="mapping-sheet-col">${header}</div>
            <div class="mapping-sample" title="Sample: ${sample}">${sample ? 'e.g. ' + sample.substring(0,50) : 'empty'}</div>
            <div class="mapping-arrow">↓ maps to</div>
            <select class="form-control map-select" data-header="${header}" onchange="onMapChange(this,'${idx}')">
                ${opts}
            </select>
        </div>`;
    }).join('');
}

function applyAutoMap() {
    document.querySelectorAll('.map-select').forEach(sel => {
        const header  = sel.dataset.header;
        const mapped  = autoMapping[header] || '';
        sel.value     = mapped;
        const row     = sel.closest('.mapping-row');
        row.classList.toggle('mapped', !!mapped);
    });
    showToast('Auto-detected ' + Object.values(autoMapping).filter(Boolean).length + ' column mappings.', 'success');
}

function onMapChange(sel, idx) {
    const row = document.getElementById(`mr_${idx}`);
    row.classList.toggle('mapped', !!sel.value);
}

function getColumnMapping() {
    const mapping = {};
    document.querySelectorAll('.map-select').forEach(sel => {
        if (sel.value) mapping[sel.dataset.header] = sel.value;
    });
    return mapping;
}

// ── Step 4 → 5 (review) ──────────────────────────────────────
document.getElementById('nextStep4').addEventListener('click', () => {
    const mapping = getColumnMapping();
    if (!Object.keys(mapping).length) {
        showToast('Please map at least one column.', 'error'); return;
    }

    const entity   = document.getElementById('importEntity').value;
    const branchEl = document.getElementById('importBranch');
    const srcType  = document.querySelector('input[name=source_type]:checked').value;
    const src      = srcType === 'google_sheets'
        ? '🔗 ' + (document.getElementById('sheetUrl').value.substring(0,60) + '...')
        : '📄 ' + (document.getElementById('csvFile').files[0]?.name || 'Uploaded CSV');

    document.getElementById('sumEntity').textContent  = entity.charAt(0).toUpperCase() + entity.slice(1);
    document.getElementById('sumSource').textContent  = src;
    document.getElementById('sumRows').textContent    = previewData.total.toLocaleString() + ' rows';
    document.getElementById('sumBranch').textContent  = branchEl.options[branchEl.selectedIndex].text;
    document.getElementById('sumMapped').textContent  = Object.keys(mapping).length + ' columns';

    goStep(5);
});
document.getElementById('prevStep5').addEventListener('click', () => goStep(4));

// ── Run Import ───────────────────────────────────────────────
document.getElementById('runImportBtn').addEventListener('click', async () => {
    const btn     = document.getElementById('runImportBtn');
    const spinner = document.getElementById('runSpinner');
    document.getElementById('runBtnText').textContent = 'Importing...';
    spinner.style.display = 'inline-block';
    btn.disabled = true;
    document.getElementById('step5Nav').style.display = 'none';

    document.getElementById('importProgress').style.display = 'block';

    // Animate progress bar (simulated until response)
    let pct = 0;
    const tick = setInterval(() => {
        if (pct < 85) { pct += Math.random() * 3; setProgress(pct); }
    }, 200);

    try {
        const fd = buildFormData(false);
        fd.append('column_mapping', JSON.stringify(getColumnMapping()));
        fd.append('update_existing',  document.getElementById('optUpdateExisting').checked ? '1' : '');
        fd.append('skip_errors',      document.getElementById('optSkipErrors').checked ? '1' : '');

        const resp = await fetch('/import/run', { method: 'POST', body: fd });
        const data = await resp.json();

        clearInterval(tick);
        setProgress(100);

        if (data.success) {
            jobId = data.job_id;
            document.getElementById('resSuccess').textContent = data.result.success;
            document.getElementById('resSkipped').textContent = data.result.skipped;
            document.getElementById('resFailed').textContent  = data.result.failed;
            document.getElementById('viewJobLink').href       = `/import/${jobId}`;
            document.getElementById('importResult').style.display = 'block';
            showToast(data.message, data.result.failed > 0 ? 'warning' : 'success');
        } else {
            showToast(data.message, 'error');
            document.getElementById('step5Nav').style.display = 'flex';
            btn.disabled = false;
        }
    } catch (e) {
        clearInterval(tick);
        showToast('Import failed: ' + e.message, 'error');
        document.getElementById('step5Nav').style.display = 'flex';
        btn.disabled = false;
    } finally {
        document.getElementById('runBtnText').textContent = 'Start Import';
        spinner.style.display = 'none';
    }
});

function setProgress(pct) {
    pct = Math.min(100, pct);
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressPct').textContent = Math.round(pct) + '%';
    if (pct < 100) {
        document.getElementById('progressMsg').textContent = 'Processing rows...';
    } else {
        document.getElementById('progressMsg').textContent = 'Finalising...';
    }
}

// ── Build FormData ───────────────────────────────────────────
function buildFormData(previewOnly) {
    const fd = new FormData();
    fd.append('_token',      csrf);
    fd.append('source_type', document.querySelector('input[name=source_type]:checked').value);
    fd.append('entity',      document.getElementById('importEntity').value);
    fd.append('branch_id',   document.getElementById('importBranch').value);

    const srcType = document.querySelector('input[name=source_type]:checked').value;
    if (srcType === 'google_sheets') {
        fd.append('sheet_url', document.getElementById('sheetUrl').value.trim());
    } else if (srcType === 'csv') {
        const file = document.getElementById('csvFile').files[0];
        if (file) fd.append('csv_file', file);
    }
    return fd;
}

// ── Toast helper ─────────────────────────────────────────────
function showToast(msg, type='info') {
    const icons = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };
    const tc = document.querySelector('.toast-container') || (() => {
        const d = document.createElement('div');
        d.className = 'toast-container';
        document.body.appendChild(d);
        return d;
    })();
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<div class="toast-icon">${icons[type]||'ℹ️'}</div>
        <div class="toast-content"><div class="toast-title">${msg}</div></div>
        <span class="toast-close" onclick="this.closest('.toast').remove()">×</span>`;
    tc.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.add('hide'); setTimeout(() => t.remove(), 400); }, 5000);
}
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
