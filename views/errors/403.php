<!DOCTYPE html>
<html lang="en" data-theme="<?= $_COOKIE['theme'] ?? 'light' ?>">
<head>
<meta charset="UTF-8">
<title>403 — Forbidden | AccountingPro</title>
<link rel="stylesheet" href="/css/app.css">
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center;gap:16px;padding:40px;">
    <div style="font-size:72px;">🔒</div>
    <h1 style="font-size:32px;font-weight:800;color:var(--text-primary);margin:0;">403 — Forbidden</h1>
    <p style="font-size:15px;color:var(--text-secondary);max-width:400px;margin:0;">
        You don't have permission to access this page or perform this action.
        Contact your administrator to request access.
    </p>
    <div style="display:flex;gap:10px;">
        <a href="/dashboard" class="btn btn-primary">← Back to Dashboard</a>
        <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
    </div>
</div>
</body>
</html>
