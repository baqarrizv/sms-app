<?php
require_once 'includes/config.php';

// Already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Both email and password are required.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user']    = [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                ];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error = 'Database error. Run setup.php first.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ur">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — SMS Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #060608;
    --surface:  #0e0e12;
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

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--mono);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
  }

  /* Animated grid background */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(124,106,247,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(124,106,247,0.04) 1px, transparent 1px);
    background-size: 40px 40px;
    animation: gridMove 20s linear infinite;
  }

  @keyframes gridMove {
    0%   { transform: translate(0,0); }
    100% { transform: translate(40px, 40px); }
  }

  /* Glow orbs */
  .orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
    pointer-events: none;
  }
  .orb-1 { width: 400px; height: 400px; background: var(--accent);  top: -100px; left: -100px; animation: float1 8s ease-in-out infinite; }
  .orb-2 { width: 300px; height: 300px; background: var(--accent2); bottom: -80px; right: -80px; animation: float2 10s ease-in-out infinite; }

  @keyframes float1 { 0%,100% { transform: translate(0,0); } 50% { transform: translate(40px,30px); } }
  @keyframes float2 { 0%,100% { transform: translate(0,0); } 50% { transform: translate(-30px,20px); } }

  .login-wrap {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 420px;
    padding: 1rem;
    animation: slideUp 0.6s cubic-bezier(0.16,1,0.3,1) both;
  }

  @keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .brand {
    text-align: center;
    margin-bottom: 2.5rem;
  }

  .brand-icon {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 1rem;
    box-shadow: 0 0 30px rgba(124,106,247,0.4);
  }

  .brand h1 {
    font-family: var(--display);
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    background: linear-gradient(135deg, var(--text), var(--muted));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .brand p {
    color: var(--muted);
    font-size: 0.78rem;
    margin-top: 0.3rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 2.2rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 0 0 1px rgba(124,106,247,0.05), 0 30px 60px rgba(0,0,0,0.5);
  }

  .field { margin-bottom: 1.2rem; }

  label {
    display: block;
    font-size: 0.72rem;
    color: var(--muted);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
  }

  .input-wrap {
    position: relative;
  }

  .input-wrap svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    pointer-events: none;
    transition: color 0.2s;
  }

  input[type="email"],
  input[type="password"] {
    width: 100%;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    font-family: var(--mono);
    font-size: 0.9rem;
    padding: 0.8rem 1rem 0.8rem 2.8rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(124,106,247,0.15);
  }

  input:focus + svg,
  .input-wrap:focus-within svg {
    color: var(--accent);
  }

  .error-box {
    background: rgba(247,106,138,0.08);
    border: 1px solid rgba(247,106,138,0.25);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    color: var(--error);
    font-size: 0.82rem;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .btn-login {
    width: 100%;
    padding: 0.9rem;
    background: linear-gradient(135deg, var(--accent), #9d8cf8);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-family: var(--display);
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
    box-shadow: 0 4px 20px rgba(124,106,247,0.35);
    margin-top: 0.5rem;
  }

  .btn-login:hover  { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(124,106,247,0.45); }
  .btn-login:active { transform: translateY(0); opacity: 0.9; }

  .footer-note {
    text-align: center;
    margin-top: 1.5rem;
    color: var(--muted);
    font-size: 0.75rem;
  }
</style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="login-wrap">
  <div class="brand">
    <div class="brand-icon">📨</div>
    <h1>SMS Manager</h1>
    <p>Bulk SMS System</p>
  </div>

  <div class="card">
    <?php if ($error): ?>
    <div class="error-box">
      <span>⚠</span> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Email Address</label>
        <div class="input-wrap">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
          </svg>
          <input type="email" name="email" placeholder="admin@gmail.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
        </div>
      </div>

      <div class="field">
        <label>Password</label>
        <div class="input-wrap">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        </div>
      </div>

      <button type="submit" class="btn-login">Login →</button>
    </form>
  </div>

  <p class="footer-note">
    First time? Run <a href="setup.php" style="color:var(--accent);text-decoration:none">setup.php</a> first
  </p>
</div>
</body>
</html>
