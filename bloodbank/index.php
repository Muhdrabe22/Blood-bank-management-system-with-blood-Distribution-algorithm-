<?php
require_once 'config.php';
require_once 'includes/functions.php';

if(session_status() === PHP_SESSION_NONE) session_start();

$auth = new Auth();
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if($auth->login($username, $password)) {
        header('Location: pages/dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Lugga Clinic Blood Bank</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@700;800;900&display=swap" rel="stylesheet">
<style>
:root {
    --red:#C1121F; --red-dark:#890D14; --red-light:#FF4757;
    --red-glow:rgba(193,18,31,0.2);
    --bg:#0A0D13; --bg2:#111520; --bg3:#181D2A;
    --card:#1C2130; --border:rgba(255,255,255,0.07);
    --text:#EEF0F5; --text2:#8B90A4; --text3:#555B6E;
}
*{margin:0;padding:0;box-sizing:border-box;}
body {
    font-family:'DM Sans',sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    overflow:hidden;
}
.bg-art {
    position:fixed;
    inset:0;
    z-index:0;
    background:
        radial-gradient(ellipse 800px 600px at 80% 50%, rgba(193,18,31,0.08) 0%, transparent 60%),
        radial-gradient(ellipse 600px 400px at 10% 20%, rgba(59,130,246,0.04) 0%, transparent 50%);
}
.drops {
    position:fixed;
    inset:0;
    z-index:0;
    overflow:hidden;
}
.drop {
    position:absolute;
    width:4px;
    background:linear-gradient(to bottom, transparent, var(--red));
    border-radius:50%;
    opacity:0.15;
    animation:fall linear infinite;
}
@keyframes fall {
    0%{transform:translateY(-100px);opacity:0}
    10%{opacity:.15}
    90%{opacity:.1}
    100%{transform:translateY(110vh);opacity:0}
}

.login-wrap {
    position:relative;
    z-index:10;
    display:grid;
    grid-template-columns:1fr 440px;
    min-height:100vh;
    width:100%;
}
.login-left {
    display:flex;
    flex-direction:column;
    justify-content:center;
    padding:60px;
}
.brand { display:flex; align-items:center; gap:16px; margin-bottom:60px; }
.brand-logo {
    width:56px; height:56px;
    background:var(--red);
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:26px;
    box-shadow:0 0 30px var(--red-glow);
}
.brand-name { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; }
.brand-name span { color:var(--red); }
.brand-sub { font-size:12px; color:var(--text3); text-transform:uppercase; letter-spacing:.12em; margin-top:2px; }

.hero-title {
    font-family:'Syne',sans-serif;
    font-size:52px;
    font-weight:900;
    line-height:1.1;
    margin-bottom:20px;
}
.hero-title .hl { color:var(--red); }
.hero-desc { color:var(--text2); font-size:16px; line-height:1.7; max-width:440px; margin-bottom:40px; }

.features { display:flex; flex-direction:column; gap:14px; }
.feat { display:flex; align-items:center; gap:14px; }
.feat-dot {
    width:32px; height:32px;
    background:var(--red-glow);
    border:1px solid rgba(193,18,31,.4);
    border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    color:var(--red-light);
    font-size:14px;
    flex-shrink:0;
}
.feat-text { font-size:14px; color:var(--text2); }

.login-right {
    background:var(--bg2);
    border-left:1px solid var(--border);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px;
}
.login-box { width:100%; max-width:360px; }
.login-box h2 { font-family:'Syne',sans-serif; font-size:28px; font-weight:800; margin-bottom:6px; }
.login-box p { color:var(--text2); font-size:14px; margin-bottom:32px; }
.form-group { margin-bottom:18px; }
label { display:block; font-size:12px; font-weight:600; color:var(--text2); text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; }
.input-wrap { position:relative; }
.input-wrap input {
    width:100%;
    background:var(--bg3);
    border:1px solid var(--border);
    border-radius:10px;
    padding:12px 12px 12px 42px;
    color:var(--text);
    font-size:14px;
    font-family:'DM Sans',sans-serif;
    outline:none;
    transition:.2s;
}
.input-wrap input:focus { border-color:var(--red); box-shadow:0 0 0 3px var(--red-glow); }
.input-icon {
    position:absolute;
    left:14px; top:50%;
    transform:translateY(-50%);
    color:var(--text3);
    font-size:14px;
}
.btn-login {
    width:100%;
    padding:13px;
    background:var(--red);
    color:#fff;
    border:none;
    border-radius:10px;
    font-size:15px;
    font-weight:700;
    font-family:'Syne',sans-serif;
    cursor:pointer;
    transition:.2s;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    margin-top:8px;
}
.btn-login:hover { background:var(--red-dark); box-shadow:0 0 30px var(--red-glow); }
.error-box {
    background:rgba(239,68,68,.1);
    border:1px solid rgba(239,68,68,.3);
    color:#EF4444;
    padding:12px 16px;
    border-radius:8px;
    font-size:13px;
    margin-bottom:18px;
    display:flex;
    align-items:center;
    gap:8px;
}
.login-footer { margin-top:30px; text-align:center; font-size:12px; color:var(--text3); }
.demo-creds {
    margin-top:20px;
    background:var(--bg3);
    border:1px solid var(--border);
    border-radius:8px;
    padding:12px 14px;
    font-size:12px;
    color:var(--text2);
}
.demo-creds strong { color:var(--text); }
.timeout-msg {
    background:rgba(245,158,11,.1);
    border:1px solid rgba(245,158,11,.3);
    color:#F59E0B;
    padding:12px 16px;
    border-radius:8px;
    font-size:13px;
    margin-bottom:18px;
}
@media(max-width:900px){.login-left{display:none;}.login-wrap{grid-template-columns:1fr;}.login-right{padding:40px 24px;}}
</style>
</head>
<body>
<div class="bg-art"></div>
<div class="drops" id="drops"></div>

<div class="login-wrap">
    <div class="login-left">
        <div class="brand">
            <div class="brand-logo">🩸</div>
            <div>
                <div class="brand-name">Lugga <span>Clinic</span></div>
                <div class="brand-sub">Blood Bank Management System</div>
            </div>
        </div>
        <h1 class="hero-title">Smarter <span class="hl">Blood Bank</span> Management</h1>
        <p class="hero-desc">Lugga Clinic's integrated platform for donor management, blood inventory tracking, smart distribution algorithms, and real-time analytics — all in one secure system.</p>
        <div class="features">
            <div class="feat"><div class="feat-dot"><i class="fa-solid fa-droplet"></i></div><div class="feat-text">Intelligent blood distribution algorithm with compatibility matching</div></div>
            <div class="feat"><div class="feat-dot"><i class="fa-solid fa-chart-bar"></i></div><div class="feat-text">Real-time stock monitoring with low-stock and expiry alerts</div></div>
            <div class="feat"><div class="feat-dot"><i class="fa-solid fa-shield-halved"></i></div><div class="feat-text">Full audit trail, role-based access and secure authentication</div></div>
            <div class="feat"><div class="feat-dot"><i class="fa-solid fa-flask"></i></div><div class="feat-text">Integrated screening, crossmatch and lab test management</div></div>
        </div>
    </div>

    <div class="login-right">
        <div class="login-box">
            <h2>Welcome Back</h2>
            <p>Sign in to access the blood bank portal</p>

            <?php if(isset($_GET['timeout'])): ?>
            <div class="timeout-msg"><i class="fa-solid fa-clock"></i> Your session expired. Please log in again.</div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="error-box"><i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" name="username" placeholder="Enter username" required value="<?= htmlspecialchars($_POST['username']??'') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" name="password" id="passInput" placeholder="Enter password" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <div class="demo-creds">
                <strong>Demo credentials:</strong><br>
                Admin: <strong>admin</strong> / <strong>password</strong><br>
                Doctor: <strong>dr.lugga</strong> / <strong>password</strong><br>
                Lab Tech: <strong>lab.tech</strong> / <strong>password</strong>
            </div>

            <div class="login-footer">
                &copy; <?= date('Y') ?> Lugga Clinic · Blood Bank Management System v<?= APP_VERSION ?>
            </div>
        </div>
    </div>
</div>

<script>
// Animated background drops
const container = document.getElementById('drops');
for(let i = 0; i < 20; i++) {
    const d = document.createElement('div');
    d.className = 'drop';
    d.style.left = Math.random()*100 + '%';
    d.style.height = (Math.random()*80+40) + 'px';
    d.style.animationDuration = (Math.random()*8+4) + 's';
    d.style.animationDelay = (Math.random()*10) + 's';
    container.appendChild(d);
}
</script>
</body>
</html>
