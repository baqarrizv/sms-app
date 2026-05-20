<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_records') {
    $ids = $_POST['selected_ids'] ?? [];
    $newVal = $_POST['new_value'] ?? '';

    if (!empty($ids) && strpos($newVal, ':') !== false) {
        list($col, $val) = explode(':', $newVal, 2);

        $validCols = [
            'current_status' => ['pending', 'processing', 'sent', 'stop'],
            'status' => ['active', 'inactive']
        ];

        if (isset($validCols[$col]) && in_array($val, $validCols[$col])) {
            $db = getDB();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$val], array_map('intval', $ids));
            $stmt = $db->prepare("UPDATE sms SET $col = ?, activity_at = NOW() WHERE id IN ($placeholders)");
            $stmt->execute($params);
            $flash = ['type' => 'success', 'msg' => count($ids) . ' record(s) ' . str_replace('_', ' ', $col) . ' updated to ' . ucfirst($val) . '.'];
        } else {
            $flash = ['type' => 'error', 'msg' => 'Invalid value.'];
        }
    } else {
        $flash = ['type' => 'error', 'msg' => 'No records selected or no update value chosen.'];
    }
}

$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$statusFilter  = $_GET['status']         ?? '';
$cStatusFilter = $_GET['current_status'] ?? '';
$search        = trim($_GET['search']    ?? '');

$where  = ['1=1'];
$params = [];

if ($statusFilter)  { $where[] = "s.status = ?";         $params[] = $statusFilter; }
if ($cStatusFilter) { $where[] = "s.current_status = ?"; $params[] = $cStatusFilter; }
if ($search) {
    $where[]  = "(s.number LIKE ? OR s.msg LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = implode(' AND ', $where);

$db       = getDB();
$total    = $db->prepare("SELECT COUNT(*) FROM sms s WHERE $whereSQL");
$total->execute($params);
$total    = (int)$total->fetchColumn();
$pages    = (int)ceil($total / $perPage);

$stmt = $db->prepare("
    SELECT s.*, u.name AS user_name
    FROM sms s
    LEFT JOIN users u ON u.id = s.activity_by
    WHERE $whereSQL
    ORDER BY s.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$records = $stmt->fetchAll();

if ($flash === null) {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
}

function statusBadge(string $status): string {
    $map = [
        'pending'    => ['label' => 'Pending',    'color' => '#fbbf24'],
        'processing' => ['label' => 'Processing',  'color' => '#38bdf8'],
        'sent'       => ['label' => 'Sent',        'color' => '#4ade80'],
        'stop'       => ['label' => 'Stopped',     'color' => '#f76a8a'],
        'active'     => ['label' => 'Active',      'color' => '#4ade80'],
        'inactive'   => ['label' => 'Inactive',    'color' => '#f76a8a'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'color' => '#5a5a72'];
    return "<span class='badge' style='--bc:{$s['color']}'>{$s['label']}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS List — SMS Manager</title>
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
    --body:     'Lato', sans-serif;
    --heading:  'Lato', sans-serif;
  }

  body { background: var(--bg); color: var(--text); font-family: var(--body); font-size: 16px; line-height: 1.6; min-height: 100vh; }

  nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(6,6,8,0.85); backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem; display: flex; align-items: center;
    justify-content: space-between; height: 60px;
  }

  .nav-brand {
    font-family: var(--heading); font-weight: 700; font-size: 1.2rem; letter-spacing: 0.02em;
    display: flex; align-items: center; gap: 0.6rem;
    text-decoration: none; color: var(--text);
  }
  .nav-brand span { background: linear-gradient(135deg, var(--accent), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

  .nav-right { display: flex; align-items: center; gap: 1.5rem; }
  .nav-user  { display: flex; align-items: center; gap: 0.6rem; color: var(--muted); font-size: 0.9rem; font-weight: 500; }
  .avatar    { width: 34px; height: 34px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: var(--heading); font-weight: 700; font-size: 0.85rem; color: #fff; box-shadow: 0 2px 8px rgba(124,106,247,0.3); }
  .nav-links { display: flex; gap: 0.5rem; }
  .nav-link  { padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-size: 0.9rem; font-weight: 500; color: var(--muted); transition: all 0.2s; border: 1px solid transparent; }
  .nav-link:hover  { color: var(--text); border-color: var(--border); background: var(--surface); }
  .nav-link.active { color: var(--accent); border-color: rgba(124,106,247,0.3); background: rgba(124,106,247,0.08); }
  .nav-link.danger { color: var(--error); }
  .nav-link.danger:hover { border-color: rgba(247,106,138,0.3); background: rgba(247,106,138,0.08); }

  main { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

  .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
  .page-title  { font-family: var(--heading); font-size: 1.5rem; font-weight: 700; letter-spacing: 0.01em; }
  .page-sub    { color: var(--muted); font-size: 0.9rem; margin-top: 0.3rem; }

  .alert { padding: 0.9rem 1.2rem; border-radius: 10px; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.5rem; }
  .alert-error   { background: rgba(247,106,138,0.08); border: 1px solid rgba(247,106,138,0.25); color: var(--error); }
  .alert-success { background: rgba(74,222,128,0.08);  border: 1px solid rgba(74,222,128,0.25);  color: var(--success); }

  .filters {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; padding: 1rem 1.2rem;
    display: flex; gap: 0.8rem; flex-wrap: wrap; align-items: center;
    margin-bottom: 1.2rem;
  }
  .filter-group { display: flex; align-items: center; gap: 0.5rem; }

  input[type="text"], select {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text);
    font-family: var(--body); font-size: 0.95rem;
    padding: 0.6rem 0.9rem; outline: none;
    transition: border-color 0.2s;
  }
  input[type="text"]:focus, select:focus { border-color: var(--accent); }
  input[type="text"] { min-width: 200px; }

  .btn { padding: 0.6rem 1.2rem; border: none; border-radius: 8px; font-family: var(--body); font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.15s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; }
  .btn-primary { background: linear-gradient(135deg, var(--accent), #9d8cf8); color: #fff; }
  .btn-ghost   { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
  .btn-ghost:hover { color: var(--text); }
  .btn-warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #060608; }
  .btn-danger  { background: linear-gradient(135deg, #ef4444, #f76a8a); color: #fff; }
  .btn-success { background: linear-gradient(135deg, #22c55e, #4ade80); color: #060608; }

  .table-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; animation: fadeIn 0.4s ease both; }

  .table-toolbar {
    padding: 0.8rem 1.2rem;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.8rem;
  }
  .table-toolbar-left { display: flex; align-items: center; gap: 0.8rem; }
  .table-toolbar-right { display: flex; align-items: center; gap: 0.8rem; }
  .toolbar-label { font-size: 0.9rem; color: var(--muted); font-weight: 500; }

  .action-bar {
    padding: 0.8rem 1.2rem;
    border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.8rem;
    background: var(--surface2);
  }
  .action-info { font-size: 0.95rem; color: var(--muted); font-weight: 500; }
  .action-info strong { color: var(--text); }
  .action-buttons { display: flex; align-items: center; gap: 0.5rem; }
  .action-buttons select { font-size: 0.85rem; padding: 0.45rem 0.7rem; }

  .sel-badge {
    font-size: 0.85rem; font-weight: 600; color: var(--accent);
    background: rgba(124,106,247,0.1); border: 1px solid rgba(124,106,247,0.2);
    padding: 0.25rem 0.7rem; border-radius: 20px; display: none;
  }
  .sel-badge.visible { display: inline-block; }

  .table-meta { padding: 1rem 1.2rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; font-size: 0.85rem; color: var(--muted); }
  .table-meta strong { color: var(--text); }

  .table-wrap { overflow-x: auto; }

  table { width: 100%; border-collapse: collapse; font-size: 0.95rem; line-height: 1.5; }
  thead tr { background: var(--surface2); }
  th { padding: 0.8rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: var(--muted); letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid var(--border); white-space: nowrap; }
  td { padding: 0.8rem 1rem; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; line-height: 1.5; }
  tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: rgba(255,255,255,0.015); }
  tr.selected td { background: rgba(124,106,247,0.05); }

  input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }

  .td-id     { color: var(--muted); font-size: 0.85rem; }
  .td-number { font-weight: 600; letter-spacing: 0.02em; font-size: 0.95rem; }
  .td-msg    { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); font-size: 0.9rem; line-height: 1.4; }
  .td-ref    { color: var(--muted); font-size: 0.85rem; font-style: italic; }
  .td-by     { color: var(--muted); font-size: 0.85rem; }
  .td-at     { color: var(--muted); font-size: 0.85rem; white-space: nowrap; }

  .badge {
    display: inline-block; padding: 0.3rem 0.75rem; border-radius: 20px;
    font-size: 0.8rem; font-weight: 600;
    background-color: color-mix(in srgb, var(--bc) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--bc) 25%, transparent);
    white-space: nowrap;
  }

  .empty-state { text-align: center; padding: 4rem 2rem; color: var(--muted); font-size: 1rem; }
  .empty-state .empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.4; }

  .pagination { display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 1.2rem; border-top: 1px solid var(--border); flex-wrap: wrap; }
  .page-btn { padding: 0.5rem 0.9rem; border-radius: 7px; text-decoration: none; font-size: 0.9rem; font-weight: 500; color: var(--muted); border: 1px solid var(--border); background: var(--surface2); transition: all 0.15s; }
  .page-btn:hover  { color: var(--text); border-color: var(--accent); }
  .page-btn.active { color: var(--accent); border-color: var(--accent); background: rgba(124,106,247,0.1); }
  .page-btn.disabled { opacity: 0.3; pointer-events: none; }

  .update-section { display: flex; gap: 0.5rem; align-items: center; }
  .update-section select { min-width: 160px; }

  .optgroup-label { font-weight: 700; color: var(--text); }

  @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="nav-brand">📨 <span>SIMSIN SMS</span>Manager</a>
  <div class="nav-right">
    <div class="nav-links">
      <a href="dashboard.php" class="nav-link">📁 Upload</a>
      <a href="sms-list.php" class="nav-link active">📋 SMS List</a>
    </div>
    <div class="nav-user">
      <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <span><?= htmlspecialchars($user['name']) ?></span>
    </div>
    <a href="logout.php" class="nav-link danger">Logout</a>
  </div>
</nav>

<main>
  <div class="page-header">
    <div>
      <div class="page-title">📋 SMS Records</div>
      <div class="page-sub">All inserted SMS records</div>
    </div>
    <a href="dashboard.php" class="btn btn-primary">+ Upload New File</a>
  </div>

  <?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] ?>">
    <?= $flash['type'] === 'success' ? '✓' : '⚠' ?> <?= htmlspecialchars($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <form method="GET" class="filters">
    <div class="filter-group">
      <input type="text" name="search" placeholder="🔍 Number or message..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="filter-group">
      <select name="status">
        <option value="">Status: All</option>
        <option value="active"   <?= $statusFilter === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <div class="filter-group">
      <select name="current_status">
        <option value="">Current: All</option>
        <option value="pending"    <?= $cStatusFilter === 'pending'    ? 'selected' : '' ?>>Pending</option>
        <option value="processing" <?= $cStatusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
        <option value="sent"       <?= $cStatusFilter === 'sent'       ? 'selected' : '' ?>>Sent</option>
        <option value="stop"       <?= $cStatusFilter === 'stop'       ? 'selected' : '' ?>>Stopped</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($search || $statusFilter || $cStatusFilter): ?>
    <a href="sms-list.php" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!empty($records)): ?>
  <form method="POST" id="smsForm">
    <input type="hidden" name="action" value="update_records">
    <div class="table-card">
      <div class="table-toolbar">
        <div class="table-toolbar-left">
          <label class="toolbar-label">
            <input type="checkbox" id="checkAll"> Select All
          </label>
          <span class="sel-badge" id="selBadge">0 selected</span>
        </div>
        <div class="table-toolbar-right">
          <span style="font-size:0.9rem;color:var(--muted);font-weight:500;">Total: <strong style="color:var(--text)"><?= count($records) ?></strong> records</span>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:44px"></th>
              <th>ID</th>
              <th>Number</th>
              <th>Message</th>
              <th>Current Status</th>
              <th>Status</th>
              <th>Ref ID</th>
              <th>By</th>
              <th>At</th>
            </tr>
          </thead>
          <tbody id="recordsBody">
            <?php foreach ($records as $r): ?>
            <tr data-id="<?= $r['id'] ?>" class="row-tr">
              <td>
                <input type="checkbox" name="selected_ids[]" value="<?= $r['id'] ?>" class="row-check">
              </td>
              <td class="td-id">#<?= $r['id'] ?></td>
              <td class="td-number"><?= htmlspecialchars($r['number']) ?></td>
              <td class="td-msg" title="<?= htmlspecialchars($r['msg']) ?>"><?= htmlspecialchars($r['msg']) ?></td>
              <td><?= statusBadge($r['current_status']) ?></td>
              <td><?= statusBadge($r['status']) ?></td>
              <td class="td-ref"><?= $r['sms_reference_id'] ? htmlspecialchars($r['sms_reference_id']) : '<span style="opacity:0.3">—</span>' ?></td>
              <td class="td-by"><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
              <td class="td-at"><?= date('d M Y H:i', strtotime($r['activity_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="action-bar">
        <div class="action-info">
          <strong id="selCount">0</strong> records selected
        </div>
        <div class="action-buttons">
          <select name="new_value" id="newValue">
            <option value="">Update to...</option>
            <optgroup label="Current Status">
              <option value="current_status:pending">Pending</option>
              <option value="current_status:stop">Stop</option>
            </optgroup>
            <optgroup label="Status">
              <option value="status:inactive">Inactive</option>
            </optgroup>
          </select>
          <button type="submit" class="btn btn-success" id="updateBtn" disabled>Update</button>
        </div>
      </div>
    </div>
  </form>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="margin-top:1.2rem;">
    <div class="table-card">
      <div class="pagination">
        <?php
          $qs = http_build_query(['search' => $search, 'status' => $statusFilter, 'current_status' => $cStatusFilter]);
          $qs = $qs ? "&$qs" : '';
        ?>
        <a href="?page=<?= $page - 1 . $qs ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">← Prev</a>
        <?php
          $start = max(1, $page - 2);
          $end   = min($pages, $page + 2);
          if ($start > 1)  echo "<a href='?page=1$qs' class='page-btn'>1</a>" . ($start > 2 ? "<span style='color:var(--muted);padding:0 0.3rem'>…</span>" : '');
          for ($p = $start; $p <= $end; $p++) {
              echo "<a href='?page=$p$qs' class='page-btn " . ($p === $page ? 'active' : '') . "'>$p</a>";
          }
          if ($end < $pages) echo ($end < $pages - 1 ? "<span style='color:var(--muted);padding:0 0.3rem'>…</span>" : '') . "<a href='?page=$pages$qs' class='page-btn'>$pages</a>";
        ?>
        <a href="?page=<?= $page + 1 . $qs ?>" class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>">Next →</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="table-card">
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>No SMS records found<?= ($search || $statusFilter || $cStatusFilter) ? ' — clear filters to see more results' : '' ?></p>
    </div>
  </div>
  <?php endif; ?>
</main>

<script>
const checkAll   = document.getElementById('checkAll');
const selCount   = document.getElementById('selCount');
const selBadge   = document.getElementById('selBadge');
const updateBtn  = document.getElementById('updateBtn');
const smsForm    = document.getElementById('smsForm');
const newValue   = document.getElementById('newValue');

function updateCount() {
  const checked = document.querySelectorAll('.row-check:checked').length;
  selCount.textContent = checked;
  selBadge.textContent = checked + ' selected';
  selBadge.classList.toggle('visible', checked > 0);
  updateBtn.disabled = checked === 0 || !newValue.value;

  document.querySelectorAll('.row-check').forEach(cb => {
    cb.closest('tr').classList.toggle('selected', cb.checked);
  });
}

if (checkAll) {
  checkAll.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => { cb.checked = this.checked; });
    updateCount();
  });
}

document.querySelectorAll('.row-check').forEach(cb => {
  cb.addEventListener('change', function() {
    const total = document.querySelectorAll('.row-check').length;
    const checked = document.querySelectorAll('.row-check:checked').length;
    if (checkAll) checkAll.checked = checked === total;
    updateCount();
  });
});

if (newValue) {
  newValue.addEventListener('change', updateCount);
}

if (smsForm) {
  smsForm.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.row-check:checked').length;
    if (checked === 0) { e.preventDefault(); return; }
    if (!newValue.value) {
      e.preventDefault();
      alert('Please select a value to update.');
      return;
    }
    const parts = newValue.value.split(':');
    const actionType = parts[0];
    const label = parts[1];
    if (!confirm(checked + ' record(s) will be updated to "' + label + '". Continue?')) {
      e.preventDefault();
    }
  });
}
</script>
</body>
</html>