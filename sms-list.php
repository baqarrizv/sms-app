<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();

// Pagination
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Filters
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

// Flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Status helpers
function statusBadge(string $status): string {
    $map = [
        'pending'    => ['label' => 'Pending',    'color' => '#fbbf24'],
        'sent'       => ['label' => 'Sent',        'color' => '#4ade80'],
        'failed'     => ['label' => 'Failed',      'color' => '#f76a8a'],
        'queued'     => ['label' => 'Queued',      'color' => '#7c6af7'],
        'processing' => ['label' => 'Processing',  'color' => '#38bdf8'],
        'done'       => ['label' => 'Done',        'color' => '#4ade80'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'color' => '#5a5a72'];
    return "<span class='badge' style='--bc:{$s['color']}'>{$s['label']}</span>";
}
?>
<!DOCTYPE html>
<html lang="ur">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS List — SMS Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
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
    --mono:     'DM Mono', monospace;
    --display:  'Syne', sans-serif;
  }

  body { background: var(--bg); color: var(--text); font-family: var(--mono); min-height: 100vh; }

  nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(6,6,8,0.85); backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem; display: flex; align-items: center;
    justify-content: space-between; height: 60px;
  }

  .nav-brand {
    font-family: var(--display); font-weight: 800; font-size: 1.1rem;
    display: flex; align-items: center; gap: 0.6rem;
    text-decoration: none; color: var(--text);
  }
  .nav-brand span { background: linear-gradient(135deg, var(--accent), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

  .nav-right { display: flex; align-items: center; gap: 1.5rem; }
  .nav-user  { display: flex; align-items: center; gap: 0.6rem; color: var(--muted); font-size: 0.82rem; }
  .avatar    { width: 30px; height: 30px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: var(--display); font-weight: 700; font-size: 0.75rem; color: #fff; }
  .nav-links { display: flex; gap: 0.5rem; }
  .nav-link  { padding: 0.4rem 0.9rem; border-radius: 8px; text-decoration: none; font-size: 0.8rem; color: var(--muted); transition: all 0.2s; border: 1px solid transparent; }
  .nav-link:hover  { color: var(--text); border-color: var(--border); background: var(--surface); }
  .nav-link.active { color: var(--accent); border-color: rgba(124,106,247,0.3); background: rgba(124,106,247,0.08); }
  .nav-link.danger { color: var(--error); }
  .nav-link.danger:hover { border-color: rgba(247,106,138,0.3); background: rgba(247,106,138,0.08); }

  main { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

  /* Page header */
  .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
  .page-title  { font-family: var(--display); font-size: 1.4rem; font-weight: 800; }
  .page-sub    { color: var(--muted); font-size: 0.78rem; margin-top: 0.2rem; }

  /* Alert */
  .alert { padding: 0.8rem 1.1rem; border-radius: 10px; font-size: 0.83rem; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.5rem; }
  .alert-error   { background: rgba(247,106,138,0.08); border: 1px solid rgba(247,106,138,0.25); color: var(--error); }
  .alert-success { background: rgba(74,222,128,0.08);  border: 1px solid rgba(74,222,128,0.25);  color: var(--success); }

  /* Filters */
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
    font-family: var(--mono); font-size: 0.82rem;
    padding: 0.5rem 0.8rem; outline: none;
    transition: border-color 0.2s;
  }
  input[type="text"]:focus, select:focus { border-color: var(--accent); }
  input[type="text"] { min-width: 200px; }

  .btn { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-family: var(--mono); font-size: 0.82rem; cursor: pointer; transition: all 0.15s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; }
  .btn-primary { background: linear-gradient(135deg, var(--accent), #9d8cf8); color: #fff; }
  .btn-ghost   { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
  .btn-ghost:hover { color: var(--text); }

  /* Table */
  .table-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; animation: fadeIn 0.4s ease both; }

  .table-meta { padding: 0.9rem 1.2rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; font-size: 0.78rem; color: var(--muted); }
  .table-meta strong { color: var(--text); }

  .table-wrap { overflow-x: auto; }

  table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
  thead tr { background: var(--surface2); }
  th { padding: 0.7rem 0.9rem; text-align: left; font-size: 0.68rem; font-weight: 400; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; border-bottom: 1px solid var(--border); white-space: nowrap; }
  td { padding: 0.7rem 0.9rem; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: rgba(255,255,255,0.015); }

  .td-id     { color: var(--muted); font-size: 0.75rem; }
  .td-number { font-weight: 500; letter-spacing: 0.04em; }
  .td-msg    { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); font-size: 0.79rem; }
  .td-ref    { color: var(--muted); font-size: 0.75rem; font-style: italic; }
  .td-by     { color: var(--muted); font-size: 0.75rem; }
  .td-at     { color: var(--muted); font-size: 0.73rem; white-space: nowrap; }

  .badge {
    display: inline-block;
    padding: 0.22rem 0.65rem;
    border-radius: 20px;
    font-size: 0.72rem;
    background: rgba(from var(--bc) r g b / 0.12);
    color: var(--bc);
    border: 1px solid rgba(from var(--bc) r g b / 0.25);
    white-space: nowrap;
  }

  /* Fallback for browsers without relative color syntax */
  .badge { background-color: color-mix(in srgb, var(--bc) 12%, transparent); border-color: color-mix(in srgb, var(--bc) 25%, transparent); }

  /* Empty */
  .empty-state { text-align: center; padding: 4rem 2rem; color: var(--muted); }
  .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 0.8rem; opacity: 0.4; }

  /* Pagination */
  .pagination { display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 1.2rem; border-top: 1px solid var(--border); flex-wrap: wrap; }
  .page-btn { padding: 0.4rem 0.8rem; border-radius: 7px; text-decoration: none; font-size: 0.8rem; color: var(--muted); border: 1px solid var(--border); background: var(--surface2); transition: all 0.15s; }
  .page-btn:hover  { color: var(--text); border-color: var(--accent); }
  .page-btn.active { color: var(--accent); border-color: var(--accent); background: rgba(124,106,247,0.1); }
  .page-btn.disabled { opacity: 0.3; pointer-events: none; }

  @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="nav-brand">📨 <span>SMS</span>Manager</a>
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

  <!-- Filters -->
  <form method="GET" class="filters">
    <div class="filter-group">
      <input type="text" name="search" placeholder="🔍 Number ya message..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="filter-group">
      <select name="status">
        <option value="">Status: All</option>
        <option value="pending"  <?= $statusFilter === 'pending'  ? 'selected' : '' ?>>Pending</option>
        <option value="sent"     <?= $statusFilter === 'sent'     ? 'selected' : '' ?>>Sent</option>
        <option value="failed"   <?= $statusFilter === 'failed'   ? 'selected' : '' ?>>Failed</option>
      </select>
    </div>
    <div class="filter-group">
      <select name="current_status">
        <option value="">Current: All</option>
        <option value="queued"     <?= $cStatusFilter === 'queued'     ? 'selected' : '' ?>>Queued</option>
        <option value="processing" <?= $cStatusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
        <option value="done"       <?= $cStatusFilter === 'done'       ? 'selected' : '' ?>>Done</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($search || $statusFilter || $cStatusFilter): ?>
    <a href="sms-list.php" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Table -->
  <div class="table-card">
    <div class="table-meta">
      <span>Total: <strong><?= number_format($total) ?></strong> records</span>
      <span>Page <strong><?= $page ?></strong> / <strong><?= max(1, $pages) ?></strong></span>
    </div>

    <?php if (!empty($records)): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
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
        <tbody>
          <?php foreach ($records as $r): ?>
          <tr>
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

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
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
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>No SMS records found<?= ($search || $statusFilter || $cStatusFilter) ? ' — clear filters to see more results' : '' ?></p>
    </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
