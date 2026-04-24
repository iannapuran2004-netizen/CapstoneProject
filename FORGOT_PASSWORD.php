<?php
// ── FORGOT_PASSWORD.php ─────────────────────────────────────────────────────
// Flow: Step 1 – Enter email  |  Step 2 – Verify cellphone  |  Step 3 – New password
session_start();
include "db.php";

$step    = 1;
$error   = '';
$success = '';
$email   = '';

// ── CANCEL: Reset all session state and go back to Step 1 ────────────────────
if (isset($_POST['cancel_reset']) || isset($_GET['cancel_reset'])) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_email'],
          $_SESSION['reset_cellphone'], $_SESSION['reset_phone_verified']);
    header("Location: FORGOT_PASSWORD.php"); exit();
}

// ── STEP 1: Verify email exists ──────────────────────────────────────────────
if (isset($_POST['verify_email'])) {
    // Rate limiting: max 3 attempts per IP per hour
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip_key = 'reset_attempts_' . md5($ip);
    if (!isset($_SESSION[$ip_key])) $_SESSION[$ip_key] = ['count' => 0, 'first' => time()];
    if (time() - $_SESSION[$ip_key]['first'] > 3600) {
        $_SESSION[$ip_key] = ['count' => 0, 'first' => time()];
    }
    if ($_SESSION[$ip_key]['count'] >= 3) {
        $error = 'Too many attempts. Please wait 1 hour before trying again.';
        $step = 1;
    } else {
        $_SESSION[$ip_key]['count']++;
    $email = trim($_POST['email']);
    $stmt  = mysqli_prepare($db, "SELECT id, username, cellphone FROM user_info WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if (empty($row['cellphone'])) {
            $error = 'No cellphone number linked to this account. Please contact the administrator.';
            $step  = 1;
        } else {
            $_SESSION['reset_user_id']   = $row['id'];
            $_SESSION['reset_email']     = $email;
            $_SESSION['reset_cellphone'] = $row['cellphone'];
            $step = 2;
        }
    } else {
        $error = 'No account found with that email address.';
        $step  = 1;
    }
    } // end rate limit check
    mysqli_stmt_close($stmt);
}

// ── STEP 2: Verify cellphone ─────────────────────────────────────────────────
if (isset($_POST['verify_phone'])) {
    if (!isset($_SESSION['reset_user_id'])) {
        $error = 'Session expired. Please start again.';
        $step  = 1;
    } else {
        $entered = trim($_POST['cellphone'] ?? '');
        $normalize = function($n) {
            $n = preg_replace('/\D/', '', $n);
            if (strlen($n) === 10 && $n[0] === '9') $n = '0' . $n;
            if (strlen($n) === 12 && substr($n,0,2) === '63') $n = '0' . substr($n,2);
            return $n;
        };
        $entered_norm = $normalize($entered);
        $stored_norm  = $normalize($_SESSION['reset_cellphone']);
        if ($entered_norm === $stored_norm && strlen($entered_norm) === 11) {
            $_SESSION['reset_phone_verified'] = true;
            $step  = 3;
            $email = $_SESSION['reset_email'];
        } else {
            $error = 'Cellphone number does not match. Please try again.';
            $step  = 2;
            $email = $_SESSION['reset_email'];
        }
    }
}

// ── STEP 3: Set new password ─────────────────────────────────────────────────
if (isset($_POST['reset_password'])) {
    if (!isset($_SESSION['reset_user_id']) || empty($_SESSION['reset_phone_verified'])) {
        $error = 'Session expired or phone not verified. Please start again.';
        $step  = 1;
    } else {
        $new_pw  = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        if (strlen($new_pw) < 7) {
            $error = 'Password must be at least 7 characters.'; $step = 3; $email = $_SESSION['reset_email'];
        } elseif ($new_pw !== $confirm) {
            $error = 'Passwords do not match.'; $step = 3; $email = $_SESSION['reset_email'];
        } else {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
            $upd    = mysqli_prepare($db, "UPDATE user_info SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $hashed, $_SESSION['reset_user_id']);
            if (mysqli_stmt_execute($upd)) {
                $success = 'Password updated successfully!';
                unset($_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_cellphone'], $_SESSION['reset_phone_verified']);
                $step = 4;
            } else {
                $error = 'Failed to update password. Please try again.'; $step = 3; $email = $_SESSION['reset_email'];
            }
            mysqli_stmt_close($upd);
        }
    }
}
if ($step === 1 && isset($_SESSION['reset_user_id'])) {
    $step  = !empty($_SESSION['reset_phone_verified']) ? 3 : 2;
    $email = $_SESSION['reset_email'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PILDEMCO – Forgot Password</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --g1:#0d3d28;--g2:#1a5f3f;--g3:#2d8a5e;--k3:#f4a261;
    --text-base:#1a2820;--text-muted:#6b7c72;
    --input-bg:#f8fbf9;--input-border:#d4e4d9;
    --card-bg:#fff;--shadow:0 24px 80px rgba(0,0,0,.32);
}
[data-theme="dark"]{
    --text-base:#ddeee4;--text-muted:#7aaa8a;
    --input-bg:#162218;--input-border:#234030;
    --card-bg:#111e16;--shadow:0 24px 80px rgba(0,0,0,.65);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Poppins',sans-serif;min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#0d3d28 0%,#1a5f3f 40%,#0a2c1d 70%,#f4a261 100%);
    position:relative;overflow:hidden;
}
[data-theme="dark"] body{background:linear-gradient(135deg,#050e08 0%,#0a1810 40%,#040c06 70%,#6b3a1a 100%);}
body::before{content:'';position:absolute;width:600px;height:600px;background:radial-gradient(circle,rgba(244,162,97,.15) 0%,transparent 70%);border-radius:50%;top:-200px;right:-100px;pointer-events:none;}
body::after{content:'';position:absolute;width:400px;height:400px;background:radial-gradient(circle,rgba(44,138,94,.12) 0%,transparent 70%);border-radius:50%;bottom:-100px;left:-80px;pointer-events:none;}

.top-controls{position:fixed;top:18px;right:22px;display:flex;align-items:center;gap:10px;z-index:600;}
.theme-toggle{width:44px;height:24px;border-radius:12px;background:rgba(255,255,255,.22);border:1.5px solid rgba(255,255,255,.35);cursor:pointer;position:relative;flex-shrink:0;}
.theme-toggle::after{content:'';position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .25s;}
[data-theme="dark"] .theme-toggle::after{transform:translateX(20px);}
.theme-icon{font-size:.92rem;color:#fff;}

/* ── Card ── */
.card{
    background:var(--card-bg);border-radius:24px;
    box-shadow:var(--shadow);
    width:460px;max-width:97vw;
    padding:44px 42px 38px;
    display:flex;flex-direction:column;align-items:center;
    position:relative;z-index:10;
    animation:fadeUp .4s ease;
    border:1.5px solid rgba(255,255,255,.06);
}
@keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
.logo{width:82px;height:auto;margin-bottom:14px;filter:drop-shadow(0 4px 16px rgba(0,0,0,.22));}
.card h2{font-size:1.45rem;font-weight:800;color:var(--text-base);margin-bottom:6px;text-align:center;font-family:'Nunito',sans-serif;}
.card .sub{font-size:.74rem;color:var(--text-muted);margin-bottom:24px;text-align:center;line-height:1.65;}

/* ── Steps indicator ── */
.steps{display:flex;align-items:center;gap:0;margin-bottom:28px;width:100%;justify-content:center;}
.step-dot{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;border:2.5px solid #ddd;color:#ccc;background:transparent;transition:all .3s;flex-shrink:0;font-family:'Nunito',sans-serif;}
.step-dot.active{border-color:var(--g3);color:#fff;background:linear-gradient(135deg,var(--g3),var(--g2));box-shadow:0 4px 14px rgba(45,138,94,.35);}
.step-dot.done{border-color:var(--g2);color:#fff;background:var(--g2);}
.step-line{flex:1;height:3px;background:#ddd;max-width:60px;border-radius:2px;}
.step-line.done{background:var(--g2);}

/* ── Inputs ── */
.card input{
    width:100%;padding:12px 15px;
    border:2px solid var(--input-border);border-radius:10px;
    font-size:.83rem;font-family:'Poppins',sans-serif;
    background:var(--input-bg);color:var(--text-base);
    margin-bottom:12px;outline:none;
    transition:border-color .2s,box-shadow .2s;
}
.card input:focus{border-color:var(--g3);box-shadow:0 0 0 3px rgba(45,138,94,.14);}
.password-wrapper{position:relative;width:100%;margin-bottom:12px;}
.password-wrapper input{margin-bottom:0;padding-right:42px;}
.toggle-password{position:absolute;right:13px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:.92rem;user-select:none;}

/* Phone row */
.phone-row{display:flex;gap:6px;width:100%;margin-bottom:12px;}
.phone-prefix{padding:12px 11px;border:2px solid var(--input-border);border-radius:10px;background:var(--input-bg);color:var(--text-muted);font-size:.82rem;font-family:'Poppins',sans-serif;white-space:nowrap;flex-shrink:0;display:flex;align-items:center;gap:5px;}
.phone-row input{margin-bottom:0;flex:1;}

/* ── Button ── */
.btn{width:100%;padding:12px;border-radius:22px;border:none;font-family:'Poppins',sans-serif;font-size:.83rem;font-weight:700;cursor:pointer;letter-spacing:.06em;text-transform:uppercase;background:linear-gradient(135deg,var(--g3),var(--g2));color:#fff;margin-top:4px;transition:all .2s;box-shadow:0 4px 18px rgba(45,138,94,.32);}
.btn:hover{background:linear-gradient(135deg,var(--g2),var(--g1));transform:translateY(-1px);box-shadow:0 6px 24px rgba(45,138,94,.4);}

/* ── Alerts ── */
.msg-error{background:#fee2e2;color:#b91c1c;border:1.5px solid #fca5a5;border-radius:10px;padding:10px 14px;font-size:.76rem;font-weight:600;margin-bottom:12px;text-align:left;width:100%;}
.msg-success{background:#dcfce7;color:#15803d;border:1.5px solid #86efac;border-radius:10px;padding:10px 14px;font-size:.76rem;font-weight:600;margin-bottom:12px;text-align:center;width:100%;}

/* ── Hint box ── */
.hint-box{background:rgba(45,138,94,.08);border:1.5px solid rgba(45,138,94,.2);border-radius:10px;padding:11px 15px;font-size:.73rem;color:var(--text-muted);margin-bottom:14px;width:100%;line-height:1.65;}
.hint-box strong{color:var(--g3);}

.back-link{display:inline-flex;align-items:center;gap:5px;margin-top:20px;font-size:.74rem;color:var(--text-muted);text-decoration:none;transition:color .2s;font-weight:600;}
.back-link:hover{color:var(--g3);}
.success-icon{font-size:3.2rem;margin-bottom:14px;}

.pw-strength-bar-wrap{width:100%;height:5px;background:#eee;border-radius:3px;margin:-8px 0 8px;overflow:hidden;}
.pw-strength-bar{height:100%;width:0;border-radius:3px;transition:width .3s,background .3s;}
.pw-strength-label{font-size:.64rem;color:#aaa;margin-bottom:8px;text-align:left;}
.pw-match-msg{font-size:.64rem;margin:-8px 0 10px;text-align:left;}
</style>
</head>
<body>

<div class="top-controls">
    <span class="theme-icon" id="themeIcon">🌙</span>
    <button class="theme-toggle" onclick="toggleTheme()"></button>
</div>

<div class="card">
    <img src="LOGO_OF_PILdemCO-removebg-preview.png" alt="PILDEMCO Logo" class="logo">

    <?php if ($step === 4): ?>
        <div class="success-icon">✅</div>
        <h2>Password Reset!</h2>
        <p class="sub">Your password has been updated successfully.</p>
        <div class="msg-success">You can now log in with your new password.</div>
        <a href="LOGIN_PAGE.php" class="btn" style="text-align:center;text-decoration:none;display:block;">Go to Login</a>

    <?php else: ?>
        <!-- Steps indicator -->
        <div class="steps">
            <div class="step-dot <?= $step>=1?($step>1?'done':'active'):'' ?>">1</div>
            <div class="step-line <?= $step>1?'done':'' ?>"></div>
            <div class="step-dot <?= $step>=2?($step>2?'done':'active'):'' ?>">2</div>
            <div class="step-line <?= $step>2?'done':'' ?>"></div>
            <div class="step-dot <?= $step>=3?'active':'' ?>">3</div>
        </div>

        <?php if ($step === 1): ?>
            <h2>Forgot Password?</h2>
            <p class="sub">Enter your registered email address to start the account recovery process.</p>
            <?php if ($error): ?><div class="msg-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" action="" style="width:100%">
                <input type="email" name="email" placeholder="Enter your email address"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
                <button type="submit" name="verify_email" class="btn">Find My Account</button><br><br>
                <form method="POST" action="" style="width:100%;margin-top:18px;">
                    <button type="submit" name="cancel_reset" style="display:block;width:100%;text-align:center;padding:11px;border-radius:22px;border:2px solid #ddd;color:#888;font-size:.8rem;font-weight:700;background:transparent;cursor:pointer;font-family:'Poppins',sans-serif;letter-spacing:.05em;text-transform:uppercase;transition:all .2s;"
                        onmouseover="this.style.borderColor='#aaa';this.style.color='#555'"
                        onmouseout="this.style.borderColor='#ddd';this.style.color='#888'">✕ Cancel</button>
                </form>
            </form>

        <?php elseif ($step === 2): ?>
            <h2>Verify Your Identity</h2>
            <p class="sub">Enter the cellphone number linked to <strong><?= htmlspecialchars($email) ?></strong></p>
            <div class="hint-box">
                📱 For your security, enter the <strong>cellphone number</strong> registered to this account (e.g. 09171234567). This confirms you are the account owner.
            </div>
            <?php if ($error): ?><div class="msg-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" action="" style="width:100%">
                <div class="phone-row">
                    <div class="phone-prefix">🇵🇭 +63</div>
                    <input type="tel" name="cellphone" id="verifyPhone"
                           placeholder="9171234567 (10 digits)"
                           maxlength="10" required
                           oninput="this.value=this.value.replace(/\D/g,'').substring(0,10);checkVerifyPhone()">
                </div>
                <div id="phoneVerifyMsg" style="font-size:.64rem;margin:-8px 0 10px;text-align:left;"></div>
                <button type="submit" name="verify_phone" class="btn">Verify Number</button>
                <form method="POST" action="" style="width:100%;margin-top:10px;">
                    <button type="submit" name="cancel_reset" style="display:block;width:100%;text-align:center;padding:11px;border-radius:22px;border:2px solid #ddd;color:#888;font-size:.8rem;font-weight:700;background:transparent;cursor:pointer;font-family:'Poppins',sans-serif;letter-spacing:.05em;text-transform:uppercase;transition:all .2s;"
                        onmouseover="this.style.borderColor='#aaa';this.style.color='#555'"
                        onmouseout="this.style.borderColor='#ddd';this.style.color='#888'">✕ Cancel &amp; Start Over</button>
                </form>
            </form>

        <?php elseif ($step === 3): ?>
            <h2>Set New Password</h2>
            <p class="sub">Choose a strong password for <strong><?= htmlspecialchars($email) ?></strong></p>
            <?php if ($error): ?><div class="msg-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" action="" style="width:100%">
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="newPassword"
                           placeholder="New Password (min. 7 characters)"
                           required minlength="7" oninput="checkPwStrength(this)">
                    <span class="toggle-password" onclick="togglePass(this)">👁</span>
                </div>
                <div class="pw-strength-bar-wrap"><div class="pw-strength-bar" id="pwStrengthBar"></div></div>
                <div class="pw-strength-label" id="pwStrengthLabel"></div>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirmPassword"
                           placeholder="Confirm New Password" required minlength="7" oninput="checkPwMatch()">
                    <span class="toggle-password" onclick="togglePass(this)">👁</span>
                </div>
                <div class="pw-match-msg" id="pwMatchMsg"></div>
                <button type="submit" name="reset_password" class="btn">Reset Password</button><br><br>
                <form method="POST" action="" style="width:100%;margin-top:10px;">
                    <button type="submit" name="cancel_reset" style="display:block;width:100%;text-align:center;padding:11px;border-radius:22px;border:2px solid #ddd;color:#888;font-size:.8rem;font-weight:700;background:transparent;cursor:pointer;font-family:'Poppins',sans-serif;letter-spacing:.05em;text-transform:uppercase;transition:all .2s;"
                        onmouseover="this.style.borderColor='#aaa';this.style.color='#555'"
                        onmouseout="this.style.borderColor='#ddd';this.style.color='#888'">✕ Cancel &amp; Start Over</button>
                </form>
            </form>
        <?php endif; ?>

        <a href="LOGIN_PAGE.php" class="back-link">← Back to Login</a>
    <?php endif; ?>
</div>

<script>
(function(){
    const saved = localStorage.getItem('pildemco_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.textContent = saved === 'dark' ? '☀️' : '🌙';
})();
function toggleTheme(){
    const cur  = document.documentElement.getAttribute('data-theme') || 'light';
    const next = cur === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('pildemco_theme', next);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.textContent = next === 'dark' ? '☀️' : '🌙';
}
function togglePass(icon){
    const input = icon.previousElementSibling;
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.textContent = input.type === 'password' ? '👁' : '🙈';
}
function checkVerifyPhone(){
    const v   = document.getElementById('verifyPhone').value;
    const msg = document.getElementById('phoneVerifyMsg');
    if (!msg) return;
    if (v.length === 0)      { msg.textContent = ''; return; }
    if (/^9\d{9}$/.test(v)) { msg.textContent = '✓ Valid format'; msg.style.color = '#16a34a'; }
    else                     { msg.textContent = 'Must be 10 digits starting with 9'; msg.style.color = '#ef4444'; }
}
function checkPwStrength(input){
    const v   = input.value;
    const bar = document.getElementById('pwStrengthBar');
    const lbl = document.getElementById('pwStrengthLabel');
    if (!bar) return;
    let score = 0;
    if (v.length >= 7)       score++;
    if (v.length >= 10)      score++;
    if (/[A-Z]/.test(v))    score++;
    if (/[0-9]/.test(v))    score++;
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
    const pw  = document.getElementById('newPassword')?.value;
    const cpw = document.getElementById('confirmPassword')?.value;
    const msg = document.getElementById('pwMatchMsg');
    if (!msg || !cpw) return;
    if (cpw.length === 0) { msg.textContent = ''; return; }
    if (pw === cpw) { msg.textContent = '✓ Passwords match'; msg.style.color = '#16a34a'; }
    else            { msg.textContent = '✗ Passwords do not match'; msg.style.color = '#ef4444'; }
}
</script>
</body>
</html>