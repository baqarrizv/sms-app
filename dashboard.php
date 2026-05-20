<?php
require_once 'includes/config.php';
requireLogin();

$user    = currentUser();
$records = [];
$error   = '';
$fileInfo = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['drive_file'])) {
    $file = $_FILES['drive_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed. Please try again.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            $error = 'Only CSV or Excel files are allowed.';
        } else {
            $dest = UPLOAD_DIR . 'upload_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $_SESSION['uploaded_file'] = $dest;
                $_SESSION['file_name']     = $file['name'];
                header('Location: dashboard');
                exit;
            } else {
                $error = 'There was a problem saving the file.';
            }
        }
    }
}

// Parse uploaded file if exists
if (isset($_SESSION['uploaded_file']) && file_exists($_SESSION['uploaded_file'])) {
    $filePath = $_SESSION['uploaded_file'];
    $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $fileInfo = $_SESSION['file_name'] ?? basename($filePath);

    if ($ext === 'csv') {
        $records = parseCSV($filePath);
    } else {
        $records = parseExcel($filePath);
    }
}

function parseCSV(string $path): array {
    $rows   = [];
    $handle = fopen($path, 'r');
    if (!$handle) return $rows;

    $headers = null;
    $lineNum = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;
        if ($lineNum === 1) {
            $headers = array_map(fn($h) => strtolower(trim($h)), $row);
            continue;
        }
        if (empty(array_filter($row))) continue;
        $mapped = array_combine($headers, array_pad($row, count($headers), ''));
        $phone  = findField($mapped, ['phone','number','mobile','tel','contact']);
        $msg    = findField($mapped, ['msg','message','sms','text','body']);
        if ($phone || $msg) {
            $rows[] = ['phone' => trim($phone ?? ''), 'msg' => trim($msg ?? '')];
        }
    }
    fclose($handle);
    return $rows;
}

function parseExcel(string $path): array {
    // Pure PHP Excel parser (no external libs needed for simple xlsx)
    $rows = [];
    if (!file_exists($path)) return $rows;

    // Extract xl/worksheets/sheet1.xml from xlsx zip
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return $rows;

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ssDoc = simplexml_load_string($ssXml);
        foreach ($ssDoc->si as $si) {
            $t = '';
            foreach ($si->r as $r) { $t .= (string)$r->t; }
            if (empty($t)) $t = (string)$si->t;
            $sharedStrings[] = $t;
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetXml) return $rows;

    $doc = simplexml_load_string($sheetXml);
    $sheet = $doc->sheetData;

    $headers = null;
    foreach ($sheet->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $t   = (string)$cell['t'];
            $val = (string)$cell->v;
            if ($t === 's') $val = $sharedStrings[(int)$val] ?? '';
            $cells[] = $val;
        }
        if ($headers === null) {
            $headers = array_map(fn($h) => strtolower(trim($h)), $cells);
            continue;
        }
        if (empty(array_filter($cells))) continue;
        $mapped = array_combine($headers, array_pad($cells, count($headers), ''));
        $phone  = findField($mapped, ['phone','number','mobile','tel','contact']);
        $msg    = findField($mapped, ['msg','message','sms','text','body']);
        if ($phone || $msg) {
            $rows[] = ['phone' => trim($phone ?? ''), 'msg' => trim($msg ?? '')];
        }
    }
    return $rows;
}

function findField(array $row, array $keys): ?string {
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') return $row[$k];
    }
    return null;
}

// SMS count
$smsCount = 0;
$pendingCount = 0;
$sentCount = 0;
$failedCount = 0;
try {
    $db = getDB();
    $smsCount = $db->query("SELECT COUNT(*) FROM sms")->fetchColumn();
    $pendingCount = $db->query("SELECT COUNT(*) FROM sms WHERE current_status = 'pending'")->fetchColumn();
    $sentCount = $db->query("SELECT COUNT(*) FROM sms WHERE current_status = 'sent'")->fetchColumn();
    $failedCount = $db->query("SELECT COUNT(*) FROM sms WHERE current_status = 'failed'")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — SMS Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #060608;
    --surface:  #0e0e12;
    --surface2: #13131a;
    --border:   #1e1e28;
    --accent:   #7c6af7;
    --accent2:  #f76a8a;
    --text:     #e8e8f0;
    --muted:    #5a5a72;
    --success:  #4ade80;
    --error:    #f76a8a;
    --warning:  #fbbf24;
    --font:     'Lato', sans-serif;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font);
    font-size: 16px;
    line-height: 1.6;
    min-height: 100vh;
  }

  /* Navbar */
  nav {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(6,6,8,0.85);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px;
  }

  .nav-brand {
    font-family: var(--font);
    font-weight: 700;
    font-size: 1.25rem;
    letter-spacing: 0.02em;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    text-decoration: none;
    color: var(--text);
  }

  .nav-brand span {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .nav-right { display: flex; align-items: center; gap: 1.5rem; }

  .nav-user {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: var(--muted);
    font-size: 0.95rem;
    font-weight: 500;
  }

  .avatar {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font);
    font-weight: 700;
    font-size: 0.9rem;
    color: #fff;
    box-shadow: 0 2px 10px rgba(124,106,247,0.35);
  }

  .nav-links { display: flex; gap: 0.5rem; }

  .nav-link {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--muted);
    transition: all 0.2s;
    border: 1px solid transparent;
  }

  .nav-link:hover { color: var(--text); border-color: var(--border); background: var(--surface); }
  .nav-link.active { color: var(--accent); border-color: rgba(124,106,247,0.3); background: rgba(124,106,247,0.08); }
  .nav-link.danger { color: var(--error); }
  .nav-link.danger:hover { border-color: rgba(247,106,138,0.3); background: rgba(247,106,138,0.08); }

  /* Main */
  main { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }

  /* Stats row */
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1rem; margin-bottom: 2rem; }

  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.2rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: fadeIn 0.4s ease both;
  }

  .stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
  }

  .stat-icon.purple { background: rgba(124,106,247,0.15); }
  .stat-icon.pink   { background: rgba(247,106,138,0.15); }
  .stat-icon.green  { background: rgba(74,222,128,0.15);  }

  .stat-val { font-family: var(--font); font-size: 1.6rem; font-weight: 700; }
  .stat-lbl { font-size: 0.8rem; color: var(--muted); letter-spacing: 0.06em; text-transform: uppercase; font-weight: 500; }

  /* Section header */
  .section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.8rem;
  }

  .section-title {
    font-family: var(--font);
    font-size: 1.2rem;
    font-weight: 700;
  }

  /* Upload card */
  .upload-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    animation: fadeIn 0.5s ease both;
  }

  .drop-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
  }

  .drop-zone:hover, .drop-zone.dragover {
    border-color: var(--accent);
    background: rgba(124,106,247,0.04);
  }

  .drop-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
  }

  .drop-icon { font-size: 2rem; margin-bottom: 0.6rem; }
  .drop-text { color: var(--muted); font-size: 0.95rem; line-height: 1.5; }
  .drop-text strong { color: var(--accent); }

  .upload-row { display: flex; align-items: center; gap: 1rem; margin-top: 1rem; flex-wrap: wrap; }

  .file-badge {
    flex: 1;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.6rem 1rem;
    font-size: 0.9rem;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
  }

  .file-badge span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

  .btn {
    padding: 0.65rem 1.4rem;
    border: none;
    border-radius: 8px;
    font-family: var(--font);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }

  .btn-primary {
    background: linear-gradient(135deg, var(--accent), #9d8cf8);
    color: #fff;
    font-weight: 500;
    box-shadow: 0 3px 12px rgba(124,106,247,0.3);
  }
  .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(124,106,247,0.4); }

  .btn-danger {
    background: rgba(247,106,138,0.12);
    color: var(--error);
    border: 1px solid rgba(247,106,138,0.25);
  }
  .btn-danger:hover { background: rgba(247,106,138,0.2); }

  .btn-ghost {
    background: var(--surface2);
    color: var(--muted);
    border: 1px solid var(--border);
  }
  .btn-ghost:hover { color: var(--text); }

  .btn-success {
    background: linear-gradient(135deg, #22c55e, #4ade80);
    color: #0a0a0a;
    font-weight: 700;
    box-shadow: 0 3px 12px rgba(74,222,128,0.25);
    font-size: 0.95rem;
    padding: 0.7rem 1.6rem;
  }
  .btn-success:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(74,222,128,0.35); }
  .btn-success:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

  /* Alert */
  .alert {
    padding: 0.9rem 1.2rem;
    border-radius: 10px;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .alert-error   { background: rgba(247,106,138,0.08); border: 1px solid rgba(247,106,138,0.25); color: var(--error); }
  .alert-success { background: rgba(74,222,128,0.08);  border: 1px solid rgba(74,222,128,0.25);  color: var(--success); }

  /* Table */
  .table-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    animation: fadeIn 0.6s ease both;
  }

  .table-toolbar {
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.8rem;
  }

  .selected-badge {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--accent);
    background: rgba(124,106,247,0.1);
    border: 1px solid rgba(124,106,247,0.2);
    padding: 0.25rem 0.7rem;
    border-radius: 20px;
    display: none;
  }

  .selected-badge.visible { display: inline-block; }

  .table-wrap { overflow-x: auto; }

  table { width: 100%; border-collapse: collapse; font-size: 0.95rem; line-height: 1.5; }

  thead tr { background: var(--surface2); }

  th {
    padding: 0.85rem 1.1rem;
    text-align: left;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--muted);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }

  td {
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
    line-height: 1.5;
  }

  tr:last-child td { border-bottom: none; }

  tr.selected td { background: rgba(124,106,247,0.04); }

  tbody tr { transition: background 0.15s; }
  tbody tr:hover td { background: rgba(255,255,255,0.02); }

  .td-msg {
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--muted);
    font-size: 0.92rem;
    line-height: 1.4;
  }

  .td-phone { font-weight: 600; color: var(--text); letter-spacing: 0.02em; font-size: 0.95rem; }

  /* Checkbox */
  input[type="checkbox"] {
    width: 16px; height: 16px;
    accent-color: var(--accent);
    cursor: pointer;
  }

  /* Empty state */
  .empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--muted);
    font-size: 1rem;
  }
  .empty-state .empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.4; }
  .empty-state p { font-size: 1rem; line-height: 1.6; }

  /* Action bar */
  .action-bar {
    padding: 1.2rem;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.8rem;
  }

  .action-info { font-size: 0.95rem; color: var(--muted); font-weight: 500; }
  .action-info strong { color: var(--text); }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
  }
</style>
</head>
<body>
<nav>
  <a href="dashboard" class="nav-brand">📨 <span>SIMSIN SMS</span>Manager</a>
  <div class="nav-right">
    <div class="nav-links">
      <a href="dashboard" class="nav-link active">📁 Upload</a>
      <a href="sms-list" class="nav-link">📋 SMS List</a>
    </div>
    <div class="nav-user">
      <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <span><?= htmlspecialchars($user['name']) ?></span>
    </div>
    <a href="logout" class="nav-link danger">Logout</a>
  </div>
</nav>

<main>
  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-icon pink">📨</div>
      <div>
        <div class="stat-val"><?= number_format($smsCount) ?></div>
        <div class="stat-lbl">Total SMS</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon purple">⏳</div>
      <div>
        <div class="stat-val"><?= number_format($pendingCount) ?></div>
        <div class="stat-lbl">Pending SMS</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green">✅</div>
      <div>
        <div class="stat-val"><?= number_format($sentCount) ?></div>
        <div class="stat-lbl">Sent SMS</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon pink">❌</div>
      <div>
        <div class="stat-val"><?= number_format($failedCount) ?></div>
        <div class="stat-lbl">Failed SMS</div>
      </div>
    </div>
  </div>
    </div>
  </div>

  <!-- Error alert -->
  <?php if ($error): ?>
  <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (isset($_GET['cleared'])): ?>
  <div class="alert alert-success">✓ File cleared successfully.</div>
  <?php endif; ?>

  <!-- Upload Section -->
  <div class="upload-card">
    <div class="section-head">
      <div class="section-title">📁 File Upload</div>
      <?php if ($fileInfo): ?>
      <a href="clear-file" class="btn btn-ghost" style="font-size:0.75rem;">✕ File Clear</a>
      <?php endif; ?>
    </div>

    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <div class="drop-zone" id="dropZone">
        <input type="file" name="drive_file" id="fileInput" accept=".csv,.xlsx,.xls" onchange="updateFileName(this)">
        <div class="drop-icon">☁️</div>
        <div class="drop-text">
          <strong>Click or drag here</strong> — CSV or Excel file<br>
          <span style="font-size:0.75rem;opacity:0.6">Columns: phone/number, msg/message</span>
        </div>
      </div>

      <div class="upload-row">
        <div class="file-badge" id="fileBadge">
          <?php if ($fileInfo): ?>
          📄 <span><?= htmlspecialchars($fileInfo) ?></span>
          <?php else: ?>
          📄 <span style="opacity:0.4">No file selected</span>
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">⬆ Upload & Parse</button>
      </div>
    </form>
  </div>

  <!-- Records Table -->
  <?php if (!empty($records)): ?>
  <div class="section-head">
    <div class="section-title">📋 File Records</div>
    <span class="selected-badge" id="selBadge">0 selected</span>
  </div>

  <form method="POST" action="insert-sms" id="smsForm">
    <div class="table-card">
      <div class="table-toolbar">
        <div style="display:flex;align-items:center;gap:0.8rem;">
          <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.95rem;cursor:pointer;color:var(--muted);font-weight:500;">
            <input type="checkbox" id="checkAll"> Select All
          </label>
          <span class="selected-badge" id="selBadge2">0 selected</span>
        </div>
        <div style="font-size:0.9rem;color:var(--muted);font-weight:500;">
          Total: <strong style="color:var(--text)"><?= count($records) ?></strong> records
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:44px"></th>
              <th>#</th>
              <th>Phone Number</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody id="recordsBody">
            <?php foreach ($records as $i => $rec): ?>
            <tr data-row="<?= $i ?>">
              <td>
                <input type="checkbox" name="selected[]" value="<?= $i ?>" class="row-check">
              </td>
              <td style="color:var(--muted)"><?= $i + 1 ?></td>
              <td class="td-phone"><?= htmlspecialchars($rec['phone']) ?></td>
              <td class="td-msg" title="<?= htmlspecialchars($rec['msg']) ?>"><?= htmlspecialchars($rec['msg']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Hidden record data -->
      <?php foreach ($records as $i => $rec): ?>
      <input type="hidden" name="record_phone[<?= $i ?>]" value="<?= htmlspecialchars($rec['phone']) ?>">
      <input type="hidden" name="record_msg[<?= $i ?>]" value="<?= htmlspecialchars($rec['msg']) ?>">
      <?php endforeach; ?>

      <div class="action-bar">
        <div class="action-info">
          <strong id="selCount">0</strong> records selected — will be inserted into SMS table
        </div>
        <button type="submit" class="btn btn-success" id="insertBtn" disabled>
          🚀 Insert Selected SMS
        </button>
      </div>
    </div>
  </form>

  <?php else: ?>
  <!-- Empty state -->
  <div class="table-card">
    <div class="empty-state">
      <div class="empty-icon">📂</div>
      <p>Upload a CSV or Excel file first<br>then records will appear here</p>
    </div>
  </div>
  <?php endif; ?>
</main>

<script>
// File name display
function updateFileName(input) {
  const badge = document.getElementById('fileBadge');
  if (input.files && input.files[0]) {
    badge.innerHTML = '📄 <span>' + input.files[0].name + '</span>';
  }
}

// Drag & Drop
const dropZone = document.getElementById('dropZone');
if (dropZone) {
  dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const fi = document.getElementById('fileInput');
    fi.files = e.dataTransfer.files;
    updateFileName(fi);
  });
}

// Checkbox logic
const checkAll  = document.getElementById('checkAll');
const insertBtn = document.getElementById('insertBtn');
const selCount  = document.getElementById('selCount');
const selBadge2 = document.getElementById('selBadge2');

function updateCount() {
  const checked = document.querySelectorAll('.row-check:checked').length;
  selCount.textContent  = checked;
  selBadge2.textContent = checked + ' selected';
  insertBtn.disabled    = checked === 0;

  document.querySelectorAll('.row-check').forEach(cb => {
    cb.closest('tr').classList.toggle('selected', cb.checked);
  });
}

if (checkAll) {
  checkAll.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateCount();
  });
}

document.querySelectorAll('.row-check').forEach(cb => {
  cb.addEventListener('change', function() {
    const total   = document.querySelectorAll('.row-check').length;
    const checked = document.querySelectorAll('.row-check:checked').length;
    if (checkAll) checkAll.checked = checked === total;
    updateCount();
  });
});

// Form confirm
const smsForm = document.getElementById('smsForm');
if (smsForm) {
  smsForm.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.row-check:checked').length;
    if (checked === 0) { e.preventDefault(); return; }
    if (!confirm(checked + ' records will be inserted into SMS table. Confirm?')) e.preventDefault();
  });
}
</script>
</body>
</html>
