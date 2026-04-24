<?php
session_start();
include "db.php";

$login_error      = '';
$register_error   = '';
$register_success = '';
$show_register    = false;

// ─── HANDLE LOGIN ─────────────────────────────────────────────────────────────
if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare($db, "SELECT * FROM user_info WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($password, $row['password'])) {
            // Check if account is suspended
            if (($row['status'] ?? 'active') === 'suspended') {
                $login_error = 'Your account has been suspended. Please contact PILDEMCO Admin for assistance.';
            } else {
            $_SESSION['user_id']   = $row['id'];
            $_SESSION['username']  = $row['username'];
            $_SESSION['email']     = $row['email'];
            $_SESSION['address']   = $row['address'];
            $_SESSION['cellphone'] = $row['cellphone'] ?? '';
            $_SESSION['user_type'] = $row['user_type'];

            if ($row['user_type'] == "admin") {
                header("Location: ADMIN.php"); exit();
            } else {
                header("Location: TERMS_AND_CONDITIONS.php"); exit();
            }
            } // end suspended check
        } else {
            $login_error = 'Wrong password. Please try again.';
        }
    } else {
        $login_error = 'Email not found. Please check your email address.';
    }
    mysqli_stmt_close($stmt);
}

// ─── HANDLE REGISTER ──────────────────────────────────────────────────────────
if (isset($_POST['register'])) {
    $show_register = true;

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']);
    $address   = trim($_POST['address']   ?? '');
    $cellphone = trim($_POST['cellphone'] ?? '');
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'] ?? '';
    $user_type = 'user'; // Always register as farmer - admins are created manually in DB

    // Normalize cellphone
    $cellphone_clean = preg_replace('/\D/', '', $cellphone);
    if (strlen($cellphone_clean) === 10 && $cellphone_clean[0] === '9')
        $cellphone_clean = '0' . $cellphone_clean;
    if (strlen($cellphone_clean) === 12 && substr($cellphone_clean,0,2) === '63')
        $cellphone_clean = '0' . substr($cellphone_clean, 2);

    $username = strtolower(preg_replace('/\s+/', '_', preg_replace('/[^a-zA-Z0-9 ]/', '', $full_name)));
    if (empty($username)) $username = 'user_' . time();

    if (empty($full_name) || empty($email) || empty($password) || empty($address) || empty($user_type)) {
        $register_error = 'All fields are required.';
    } elseif (empty($cellphone_clean) || strlen($cellphone_clean) !== 11) {
        $register_error = 'Please enter a valid Philippine cellphone number (e.g. 09171234567).';
    } elseif (strlen($password) < 7) {
        $register_error = 'Password must be at least 7 characters.';
    } elseif ($password !== $confirm) {
        $register_error = 'Passwords do not match.';
    } else {
        $chk_email = mysqli_prepare($db, "SELECT id FROM user_info WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($chk_email, "s", $email);
        mysqli_stmt_execute($chk_email);
        mysqli_stmt_store_result($chk_email);
        $email_exists = mysqli_stmt_num_rows($chk_email) > 0;
        mysqli_stmt_close($chk_email);

        if ($email_exists) {
            $register_error = 'That email is already registered.';
        } else {
            $base = $username; $counter = 1;
            while (true) {
                $chk = mysqli_prepare($db, "SELECT id FROM user_info WHERE username = ? LIMIT 1");
                mysqli_stmt_bind_param($chk, "s", $username);
                mysqli_stmt_execute($chk);
                mysqli_stmt_store_result($chk);
                $exists = mysqli_stmt_num_rows($chk) > 0;
                mysqli_stmt_close($chk);
                if (!$exists) break;
                $username = $base . $counter++;
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($db,
                "INSERT INTO user_info (username, email, password, address, cellphone, user_type)
                 VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "ssssss", $username, $email, $hashed, $address, $cellphone_clean, $user_type);

            if (mysqli_stmt_execute($ins)) {
                $register_success = 'Registration successful! You can now log in.';
                $show_register = false;
            } else {
                $register_error = 'Registration failed: ' . mysqli_stmt_error($ins);
            }
            mysqli_stmt_close($ins);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PILDEMCO – Login / Register</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --g1:#0d3d28;--g2:#1a5f3f;--g3:#2d8a5e;--g4:#4caf80;
    --k3:#f4a261;--k4:#fde8cc;
    --bg-page:#fff;--text-base:#1a2820;--text-muted:#6b7c72;
    --input-bg:#f8fbf9;--input-border:#d4e4d9;
    --card-bg:#f4f8f5;--shadow:0 24px 80px rgba(0,0,0,.32);
}
[data-theme="dark"]{
    --bg-page:#0b1810;--text-base:#ddeee4;--text-muted:#7aaa8a;
    --input-bg:#162218;--input-border:#234030;
    --card-bg:#111e16;--shadow:0 24px 80px rgba(0,0,0,.7);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Poppins',sans-serif;min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#0d3d28 0%,#1a5f3f 40%,#0a2c1d 70%,#f4a261 100%);
    position:relative;overflow:hidden;transition:background .3s;
}
[data-theme="dark"] body{
    background:linear-gradient(135deg,#050e08 0%,#0a1810 40%,#040c06 70%,#6b3a1a 100%);
}
body::before{content:'';position:absolute;width:600px;height:600px;background:radial-gradient(circle,rgba(244,162,97,.18) 0%,transparent 70%);border-radius:50%;top:-200px;right:-150px;pointer-events:none;}
body::after{content:'';position:absolute;width:400px;height:400px;background:radial-gradient(circle,rgba(44,138,94,.14) 0%,transparent 70%);border-radius:50%;bottom:-100px;left:-100px;pointer-events:none;}

/* ── Top controls ── */
.top-controls{
    position:fixed;top:18px;right:22px;
    display:flex;align-items:center;gap:10px;z-index:600;
}
.theme-toggle{
    width:44px;height:24px;border-radius:12px;
    background:rgba(255,255,255,.22);border:1.5px solid rgba(255,255,255,.35);
    cursor:pointer;position:relative;flex-shrink:0;transition:background .3s;
}
.theme-toggle::after{
    content:'';position:absolute;top:2px;left:2px;
    width:18px;height:18px;border-radius:50%;background:#fff;
    transition:transform .25s;box-shadow:0 1px 4px rgba(0,0,0,.2);
}
[data-theme="dark"] .theme-toggle::after{transform:translateX(20px);}
.theme-icon{font-size:.92rem;line-height:1;color:#fff;}
.lang-toggle{display:flex;align-items:center;background:rgba(255,255,255,.12);border-radius:20px;overflow:hidden;border:1.5px solid rgba(255,255,255,.22);}
.lang-btn{padding:4px 12px;font-size:.64rem;font-weight:700;cursor:pointer;border:none;background:transparent;font-family:'Poppins',sans-serif;color:rgba(255,255,255,.55);letter-spacing:.06em;transition:all .2s;}
.lang-btn.active{background:rgba(255,255,255,.22);color:#fff;border-radius:16px;}

/* ── Container ── */
.container{
    background:var(--card-bg);border-radius:24px;
    box-shadow:var(--shadow);position:relative;overflow:hidden;
    width:960px;max-width:99vw;min-height:600px;
    transition:background .3s;
    border:1.5px solid rgba(255,255,255,.08);
}
.form-container{position:absolute;top:0;height:100%;transition:all .6s ease;background:var(--card-bg);}
.sign-in-container{left:0;width:50%;z-index:2;}
.sign-up-container{left:0;width:50%;opacity:0;z-index:1;}
.container.active .sign-in-container{transform:translateX(100%);}
.container.active .sign-up-container{transform:translateX(100%);opacity:1;z-index:5;animation:showPanel .6s;}
@keyframes showPanel{0%,49.99%{opacity:0;z-index:1}50%,100%{opacity:1;z-index:5}}

.form-inner{
    display:flex;flex-direction:column;align-items:center;
    justify-content:center;padding:32px 38px;height:100%;
    text-align:center;overflow-y:auto;
}
.form-inner h2{font-size:1.55rem;font-weight:800;color:var(--text-base);margin-bottom:14px;font-family:'Nunito',sans-serif;}

/* ── Inputs ── */
.form-inner input,.form-inner select{
    width:100%;padding:11px 14px;
    border:2px solid var(--input-border);border-radius:10px;
    font-size:.82rem;font-family:'Poppins',sans-serif;
    background:var(--input-bg);color:var(--text-base);
    margin-bottom:10px;outline:none;
    transition:border-color .2s,box-shadow .2s;
    text-align:left;
}
.form-inner input:focus,.form-inner select:focus{
    border-color:var(--g3);box-shadow:0 0 0 3px rgba(45,138,94,.14);
}
/* Phone row */
.phone-row-login{display:flex;gap:6px;width:100%;margin-bottom:10px;}
.phone-prefix-login{
    padding:11px 10px;border:2px solid var(--input-border);
    border-radius:10px;background:var(--input-bg);color:var(--text-muted);
    font-size:.8rem;font-family:'Poppins',sans-serif;white-space:nowrap;
    display:flex;align-items:center;gap:4px;flex-shrink:0;
}
.phone-row-login input{margin-bottom:0;flex:1;}

.password-wrapper{position:relative;width:100%;margin-bottom:10px;}
.password-wrapper input{margin-bottom:0;padding-right:40px;}
.toggle-password{position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:.9rem;user-select:none;}
.forgot-link{font-size:.72rem;color:var(--text-muted);text-decoration:none;margin-bottom:14px;display:block;text-align:right;width:100%;transition:color .2s;}
.forgot-link:hover{color:var(--g3);}
.divider{font-size:.7rem;color:var(--text-muted);margin-bottom:12px;}

/* ── Buttons ── */
.btn{padding:11px 42px;border-radius:22px;border:none;font-family:'Poppins',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer;letter-spacing:.06em;text-transform:uppercase;transition:all .2s;}
.btn-solid{background:linear-gradient(135deg,var(--g3),var(--g2));color:#fff;width:100%;margin-top:6px;box-shadow:0 4px 18px rgba(45,138,94,.35);}
.btn-solid:hover{background:linear-gradient(135deg,var(--g2),var(--g1));box-shadow:0 6px 24px rgba(45,138,94,.45);transform:translateY(-1px);}
.btn-ghost{background:transparent;border:2px solid #fff;color:#fff;}
.btn-ghost:hover{background:rgba(255,255,255,.15);}

/* ── Alerts ── */
.msg-error{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:10px;padding:9px 13px;font-size:.76rem;font-weight:600;margin-bottom:10px;text-align:left;width:100%;}
.msg-success{background:#dcfce7;color:#15803d;border:1px solid #86efac;border-radius:10px;padding:9px 13px;font-size:.76rem;font-weight:600;margin-bottom:10px;text-align:left;width:100%;}
.switch-text{font-size:.72rem;color:var(--text-muted);margin-top:14px;}
.switch-text a{color:var(--g3);text-decoration:none;font-weight:700;}

/* ── Pw strength ── */
#pwStrengthBar{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}

/* ── Overlay ── */
.overlay-container{position:absolute;top:0;left:50%;width:50%;height:100%;overflow:hidden;transition:transform .6s ease-in-out;z-index:100;}
.container.active .overlay-container{transform:translateX(-100%);}
.overlay{
    background:linear-gradient(145deg,var(--g1) 0%,var(--g2) 45%,#1e6e47 70%,#b87333 100%);
    color:#fff;position:relative;left:-100%;height:100%;width:200%;
    transform:translateX(0);transition:transform .6s ease-in-out;
}
.container.active .overlay{transform:translateX(50%);}
.overlay-panel{position:absolute;top:0;width:50%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:0 40px;text-align:center;}
.overlay-panel h1{font-size:1.6rem;font-weight:800;margin-bottom:14px;font-family:'Nunito',sans-serif;}
.overlay-panel p{font-size:.78rem;line-height:1.7;color:rgba(255,255,255,.85);margin-bottom:28px;}
.overlay-left{transform:translateX(-20%);transition:transform .6s ease-in-out;}
.overlay-right{right:0;}
.container.active .overlay-left{transform:translateX(0);}
.overlay-logo{width:100px;height:auto;margin-bottom:18px;filter:drop-shadow(0 4px 18px rgba(0,0,0,.28));}

/* ── Decorative separator ── */
.reg-sep{display:flex;align-items:center;gap:8px;width:100%;margin:4px 0 8px;}
.reg-sep span{font-size:.62rem;color:var(--text-muted);font-weight:600;white-space:nowrap;}
.reg-sep::before,.reg-sep::after{content:'';flex:1;height:1px;background:var(--input-border);}

@media(max-width:680px){
    /* ── Body stays centered — gradient background stays visible ── */
    body{
        align-items:center;
        justify-content:center;
        padding:24px 16px;
        overflow-y:auto;
        min-height:100vh;
    }

    /* ── Hide the desktop sliding overlay panel ── */
    .overlay-container{display:none;}

    /* ── Container becomes a compact floating card (like FORGOT_PASSWORD) ── */
    .container{
        position:relative;
        width:100%;
        max-width:390px;
        min-height:auto !important;
        border-radius:22px;
        box-shadow:0 24px 80px rgba(0,0,0,.45);
        overflow:hidden;
    }

    /* ── form-container: out of absolute flow, natural height ── */
    .form-container{
        position:relative !important;
        width:100% !important;
        height:auto !important;
        transform:none !important;
        opacity:1 !important;
        top:auto !important;
        left:auto !important;
    }

    /* ── Panel visibility ── */
    .sign-in-container{ display:block; }
    .sign-up-container{ display:none; }
    .container.active .sign-in-container{ display:none; }
    .container.active .sign-up-container{ display:block; }

    /* ── form-inner: compact, natural height ── */
    .form-inner{
        height:auto !important;
        min-height:auto !important;
        padding:34px 26px 28px;
        justify-content:flex-start;
        overflow-y:visible;
    }
    .form-inner h2{ font-size:1.28rem; margin-bottom:11px; }

    /* ── Inputs — 16px prevents iOS auto-zoom ── */
    .form-inner input,
    .form-inner select{
        font-size:16px;
        margin-bottom:9px;
        padding:11px 13px;
    }
    .phone-row-login{ margin-bottom:9px; }
    .phone-prefix-login{ font-size:.78rem; padding:11px 9px; }
    .password-wrapper{ margin-bottom:9px; }

    /* ── Buttons ── */
    .btn-solid{ padding:13px; border-radius:50px; font-size:.82rem; margin-top:4px; }

    /* ── Small text & helpers ── */
    .divider{ font-size:.68rem; margin-bottom:10px; }
    .forgot-link{ font-size:.7rem; margin-bottom:12px; }
    .switch-text{ font-size:.7rem; margin-top:12px; }
    .msg-error,.msg-success{ font-size:.73rem; padding:8px 12px; }
    .reg-sep{ margin:3px 0 7px; }
    .reg-sep span{ font-size:.6rem; }
    #pwStrengthLabel,#regPhoneMsg,#pwMatchMsg{ font-size:.61rem; }

    /* ── Top controls ── */
    .top-controls{ top:12px; right:14px; gap:7px; }
    .theme-toggle{ width:38px; height:20px; }
    .theme-toggle::after{ width:15px; height:15px; top:2px; left:2px; }
    .lang-btn{ padding:3px 9px; font-size:.6rem; }
}
</style>
</head>
<body>

<div class="top-controls">
    <span class="theme-icon" id="themeIcon">🌙</span>
    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light mode"></button>
    <div class="lang-toggle">
        <button class="lang-btn active" data-lang="en" onclick="setLang('en')">EN</button>
        <button class="lang-btn" data-lang="tl" onclick="setLang('tl')">TL</button>
    </div>
</div>

<div class="container<?= $show_register ? ' active' : '' ?>" id="container">

    <!-- SIGN-UP FORM -->
    <div class="form-container sign-up-container">
        <form class="form-inner" method="POST" action="">
            <h2>Create Account</h2>

            <?php if ($register_error): ?>
                <div class="msg-error">⚠ <?= htmlspecialchars($register_error) ?></div>
            <?php endif; ?>

            <input type="text" name="full_name" placeholder="Full Name (e.g. Juan Dela Cruz)"
                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autocomplete="name">
            <input type="email" name="email" placeholder="Email Address"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            <input type="text" name="address" placeholder="Barangay / Address"
                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>

            <!-- CELLPHONE -->
            <div class="phone-row-login">
                <div class="phone-prefix-login">🇵🇭 +63</div>
                <input type="tel" name="cellphone" id="regPhone"
                       placeholder="9171234567 (10 digits)"
                       maxlength="10" required
                       value="<?= htmlspecialchars(ltrim($_POST['cellphone'] ?? '0', '0')) ?>"
                       oninput="this.value=this.value.replace(/\D/g,'').substring(0,10);checkPhoneReg()">
            </div>
            <div id="regPhoneMsg" style="font-size:.63rem;margin:-6px 0 8px;text-align:left;"></div>

            <select name="user_type" required>
                <option value="">-- Select Account Type --</option>
                <option value="user" selected>🌾 Farmer / User</option>
            </select>

            <div class="reg-sep"><span>Password</span></div>
            <div class="password-wrapper">
                <input type="password" name="password" id="regPassword"
                       placeholder="Password (min. 7 characters)"
                       required minlength="7" oninput="checkPwStrength(this)">
                <span class="toggle-password" onclick="togglePass(this)">👁</span>
            </div>
            <div style="width:100%;height:4px;background:#eee;border-radius:2px;margin:-6px 0 6px;overflow:hidden">
                <div id="pwStrengthBar" style="height:100%;width:0;border-radius:2px;transition:width .3s,background .3s"></div>
            </div>
            <div id="pwStrengthLabel" style="font-size:.63rem;color:#aaa;margin-bottom:8px;text-align:left;margin-top:-2px"></div>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirmPassword"
                       placeholder="Confirm Password" required minlength="7" oninput="checkPwMatch()">
                <span class="toggle-password" onclick="togglePass(this)">👁</span>
            </div>
            <div id="pwMatchMsg" style="font-size:.63rem;margin:-6px 0 8px;text-align:left"></div>
            <button type="submit" name="register" class="btn btn-solid">SIGN UP</button>
            <div class="switch-text">
                Already have an account? <a href="#" id="goLogin">Sign In</a>
            </div>
        </form>
    </div>

    <!-- SIGN-IN FORM -->
    <div class="form-container sign-in-container">
        <form class="form-inner" method="POST" action="">
            <h2>Sign In</h2>
            <p class="divider">Use your registered email &amp; password</p>

            <?php if ($login_error): ?>
                <div class="msg-error">⚠ <?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            <?php if ($register_success): ?>
                <div class="msg-success">✓ <?= $register_success ?></div>
            <?php endif; ?>

            <input type="email" name="email" placeholder="Email Address"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
            <div class="password-wrapper">
                <input type="password" name="password" placeholder="Your Password" required>
                <span class="toggle-password" onclick="togglePass(this)">👁</span>
            </div>
            <a href="FORGOT_PASSWORD.php" class="forgot-link">Forgot your password?</a>
            <button type="submit" name="login" class="btn btn-solid">LOG IN</button>
            <div class="switch-text">
                Don't have an account? <a href="#" id="goRegister">Sign Up</a>
            </div>
        </form>
    </div>

    <!-- OVERLAY -->
    <div class="overlay-container">
        <div class="overlay">
            <div class="overlay-panel overlay-left">
                <img src="LOGO_OF_PILdemCO-removebg-preview.png" alt="PILDEMCO Logo" class="overlay-logo">
                <h1>Welcome Back!</h1>
                <p>Enter your credentials to access your PILDEMCO account and manage your equipment bookings.</p>
                <button class="btn btn-ghost" id="loginBtn">SIGN IN</button>
            </div>
            <div class="overlay-panel overlay-right">
                <img src="LOGO_OF_PILdemCO-removebg-preview.png" alt="PILDEMCO Logo" class="overlay-logo">
                <h1>Hello, Farmer!</h1>
                <p>Register your account to access PILDEMCO's agricultural equipment rental services in San Agustin.</p>
                <button class="btn btn-ghost" id="registerBtn">SIGN UP</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const saved = localStorage.getItem('pildemco_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
})();
function toggleTheme(){
    const cur  = document.documentElement.getAttribute('data-theme') || 'light';
    const next = cur === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('pildemco_theme', next);
    updateThemeIcon();
}
function updateThemeIcon(){
    const icon = document.getElementById('themeIcon');
    if (!icon) return;
    icon.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
}
document.addEventListener('DOMContentLoaded', updateThemeIcon);

const container = document.getElementById('container');
document.getElementById('registerBtn').addEventListener('click', () => container.classList.add('active'));
document.getElementById('loginBtn').addEventListener('click',   () => container.classList.remove('active'));
document.getElementById('goRegister')?.addEventListener('click', e => { e.preventDefault(); container.classList.add('active'); });
document.getElementById('goLogin')?.addEventListener('click',    e => { e.preventDefault(); container.classList.remove('active'); });

function togglePass(icon){
    const input = icon.previousElementSibling;
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.textContent = input.type === 'password' ? '👁' : '🙈';
}

function checkPhoneReg(){
    const v   = document.getElementById('regPhone').value;
    const msg = document.getElementById('regPhoneMsg');
    if (!msg) return;
    if (v.length === 0)      { msg.textContent=''; return; }
    if (/^9\d{9}$/.test(v)) { msg.textContent='✓ Valid number'; msg.style.color='#16a34a'; }
    else                     { msg.textContent='Must be 10 digits starting with 9'; msg.style.color='#ef4444'; }
}

function checkPwStrength(input){
    const v   = input.value;
    const bar = document.getElementById('pwStrengthBar');
    const lbl = document.getElementById('pwStrengthLabel');
    if (!bar) return;
    let score = 0;
    if (v.length >= 7)  score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [
        {w:'20%',bg:'#ef4444',label:'Too short'},
        {w:'40%',bg:'#f97316',label:'Weak'},
        {w:'60%',bg:'#eab308',label:'Fair'},
        {w:'80%',bg:'#22c55e',label:'Good'},
        {w:'100%',bg:'#15803d',label:'Strong 💪'},
    ];
    const l = levels[Math.min(score, 4)];
    bar.style.width      = v.length === 0 ? '0' : l.w;
    bar.style.background = l.bg;
    lbl.textContent      = v.length === 0 ? '' : l.label;
    lbl.style.color      = l.bg;
    checkPwMatch();
}
function checkPwMatch(){
    const pw  = document.getElementById('regPassword')?.value;
    const cpw = document.getElementById('confirmPassword')?.value;
    const msg = document.getElementById('pwMatchMsg');
    if (!msg || !cpw) return;
    if (cpw.length === 0) { msg.textContent = ''; return; }
    if (pw === cpw)  { msg.textContent = '✓ Passwords match'; msg.style.color = '#16a34a'; }
    else             { msg.textContent = '✗ Passwords do not match'; msg.style.color = '#ef4444'; }
}

// Language
const LOGIN_LANG = {
    en: { h2_signin:'Sign In', h2_signup:'Create Account' },
    tl: { h2_signin:'Mag-Sign In', h2_signup:'Gumawa ng Account' }
};
let loginLang = localStorage.getItem('pildemco_lang') || 'en';
function setLang(lang){ loginLang=lang; localStorage.setItem('pildemco_lang',lang); updateLangButtons(); }
function updateLangButtons(){ document.querySelectorAll('.lang-btn').forEach(b => b.classList.toggle('active', b.dataset.lang === loginLang)); }
document.addEventListener('DOMContentLoaded', updateLangButtons);
</script>
</body>
</html>
