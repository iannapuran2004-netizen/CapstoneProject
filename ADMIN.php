<?php
// ════════════════════════════════════════════════════════════════════════════
//  ADMIN.php — PILDEMCO Admin Dashboard (Enhanced v2.0)
//  Changes: Wider header, borders/margins, cellphone in users+bookings,
//           "sack" instead of "sako", maximized screen usage
// ════════════════════════════════════════════════════════════════════════════
session_start();
include "db.php";

if (!isset($_SESSION['username']) || $_SESSION['user_type'] != "admin") {
    header("Location: LOGIN_PAGE.php"); exit();
}

$admin_name = htmlspecialchars($_SESSION['username']);
$section    = $_GET['section'] ?? 'dashboard';
$flash      = '';
$my_id      = intval($_SESSION['user_id'] ?? 0);
$my_type    = 'admin';

// ── Add Equipment ─────────────────────────────────────────────────────────────
if (isset($_POST['add_equipment'])) {
    $name      = trim($_POST['equip_name']);
    $category  = trim($_POST['equip_category']);
    $desc      = trim($_POST['equip_desc']);
    $rate_cash = floatval($_POST['rate_cash']);
    $rate_pal  = floatval($_POST['rate_palay']);
    $rate_unit = $_POST['rate_unit'];
    $status    = $_POST['equip_status'];
    $img       = '';
    if (!empty($_FILES['equip_image']['name'])) {
        if (!is_dir('uploads')) mkdir('uploads', 0755, true);
        $ext = pathinfo($_FILES['equip_image']['name'], PATHINFO_EXTENSION);
        $img = 'uploads/' . uniqid('equip_') . '.' . $ext;
        move_uploaded_file($_FILES['equip_image']['tmp_name'], $img);
    }
    $sql  = "INSERT INTO equipment (name,category,description,rate_cash,rate_palay,rate_unit,status,image_path) VALUES (?,?,?,?,?,?,?,?)";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "sssddsss", $name, $category, $desc, $rate_cash, $rate_pal, $rate_unit, $status, $img);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=equipment"); exit();
}

// ── Edit Equipment ────────────────────────────────────────────────────────────
if (isset($_POST['edit_equipment'])) {
    $id=intval($_POST['equip_id'] ?? 0);
    $name=trim($_POST['equip_name']);
    $category=trim($_POST['equip_category']);$desc=trim($_POST['equip_desc']);
    $rate_cash=floatval($_POST['rate_cash']);$rate_pal=floatval($_POST['rate_palay']);
    $rate_unit=$_POST['rate_unit'];$status=$_POST['equip_status'];
    $stmt=mysqli_prepare($db,"UPDATE equipment SET name=?,category=?,description=?,rate_cash=?,rate_palay=?,rate_unit=?,status=? WHERE equipment_id=?");
    mysqli_stmt_bind_param($stmt,"sssddssi",$name,$category,$desc,$rate_cash,$rate_pal,$rate_unit,$status,$id);
    mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=equipment"); exit();
}

// ── Delete Equipment ──────────────────────────────────────────────────────────
if (isset($_GET['delete_equipment'])) {
    $id=intval($_GET['delete_equipment']);
    $stmt=mysqli_prepare($db,"DELETE FROM equipment WHERE equipment_id=?");
    mysqli_stmt_bind_param($stmt,"i",$id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=equipment"); exit();
}

// ── Approve / Reject / Complete Booking ──────────────────────────────────────
if (isset($_GET['approve_booking'])) {
    $id=intval($_GET['approve_booking']);
    $stmt=mysqli_prepare($db,"UPDATE bookings SET status='approved' WHERE booking_id=?");
    mysqli_stmt_bind_param($stmt,"i",$id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=bookings"); exit();
}
if (isset($_GET['reject_booking'])) {
    $id=intval($_GET['reject_booking']);
    $stmt=mysqli_prepare($db,"UPDATE bookings SET status='rejected' WHERE booking_id=?");
    mysqli_stmt_bind_param($stmt,"i",$id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=bookings"); exit();
}
if (isset($_GET['complete_booking'])) {
    $id=intval($_GET['complete_booking']);
    $stmt=mysqli_prepare($db,"UPDATE bookings SET status='completed' WHERE booking_id=?");
    mysqli_stmt_bind_param($stmt,"i",$id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    // Auto-record payment based on user's chosen method
    $chk=mysqli_prepare($db,"SELECT COUNT(*) FROM payments WHERE booking_id=?");
    mysqli_stmt_bind_param($chk,"i",$id);mysqli_stmt_execute($chk);
    $already=mysqli_fetch_row(mysqli_stmt_get_result($chk))[0]??0;mysqli_stmt_close($chk);
    if(!$already){
        $bdata_stmt=mysqli_prepare($db,"SELECT farmer_id, total_amount, payment_method FROM bookings WHERE booking_id=?");
        mysqli_stmt_bind_param($bdata_stmt,"i",$id);mysqli_stmt_execute($bdata_stmt);
        $bdata=mysqli_fetch_assoc(mysqli_stmt_get_result($bdata_stmt));mysqli_stmt_close($bdata_stmt);
        if($bdata){
            $pay_method = $bdata['payment_method']; // 'cash' or 'palay' — use actual user selection
            $total_val  = floatval($bdata['total_amount']);
            // If user selected cash → record as cash amount; if palay → record as palay sacks
            $ac = ($pay_method === 'cash')  ? $total_val : 0.00;
            $ap = ($pay_method === 'palay') ? $total_val : 0.00;
            $auto2=mysqli_prepare($db,"INSERT INTO payments (booking_id,farmer_id,amount_cash,kg_palay,payment_method,status) VALUES (?,?,?,?,?,'auto-recorded')");
            mysqli_stmt_bind_param($auto2,"iidds",$id,$bdata['farmer_id'],$ac,$ap,$pay_method);
            mysqli_stmt_execute($auto2);mysqli_stmt_close($auto2);
        }
    }
    header("Location: ADMIN.php?section=bookings"); exit();
}

// ── Edit Booking (Emergency / Reschedule) ────────────────────────────────────
if (isset($_POST['edit_booking'])) {
    $bid       = intval($_POST['booking_id'] ?? 0);
    $new_start = $_POST['new_start_date'];
    $new_end   = $_POST['new_end_date'];
    $new_rmk   = trim($_POST['new_remarks'] ?? '');
    $new_status= $_POST['new_status'] ?? '';
    $stmt = mysqli_prepare($db, "UPDATE bookings SET start_date=?, end_date=?, remarks=?, status=? WHERE booking_id=?");
    mysqli_stmt_bind_param($stmt, "ssssi", $new_start, $new_end, $new_rmk, $new_status, $bid);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=bookings&edited=1"); exit();
}

// ── Record Payment ────────────────────────────────────────────────────────────
if (isset($_POST['record_payment'])) {
    $booking_id=intval($_POST['booking_id'] ?? 0);
    $farmer_id=intval($_POST['farmer_id']);
    $method=$_POST['pay_method'];
    $amount_cash=floatval($_POST['amount_cash']??0);
    $kg_palay=floatval($_POST['kg_palay']??0);
    $stmt=mysqli_prepare($db,"INSERT INTO payments (booking_id,farmer_id,amount_cash,kg_palay,payment_method,status) VALUES (?,?,?,?,?,'verified')");
    mysqli_stmt_bind_param($stmt,"iidds",$booking_id,$farmer_id,$amount_cash,$kg_palay,$method);
    mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    $s2=mysqli_prepare($db,"UPDATE bookings SET status='completed' WHERE booking_id=?");
    mysqli_stmt_bind_param($s2,"i",$booking_id);mysqli_stmt_execute($s2);mysqli_stmt_close($s2);
    header("Location: ADMIN.php?section=payments"); exit();
}

// ── Suspend / Unsuspend User ──────────────────────────────────────────────────
if (isset($_GET['suspend_user'])) {
    $id=intval($_GET['suspend_user']);
    $stmt=mysqli_prepare($db,"UPDATE user_info SET status='suspended' WHERE id=? AND user_type='user'");
    mysqli_stmt_bind_param($stmt,"i",$id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=users&suspended=1"); exit();
}
if (isset($_GET['unsuspend_user'])) {
    $id=intval($_GET['unsuspend_user']);
    $stmt=mysqli_prepare($db,"UPDATE user_info SET status='active' WHERE id=? AND user_type='user'");
    mysqli_stmt_bind_param($stmt,"i",$id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=users&unsuspended=1"); exit();
}

// ── Delete User ───────────────────────────────────────────────────────────────
if (isset($_GET['delete_user'])) {
    $id=intval($_GET['delete_user']);
    $stmt=mysqli_prepare($db,"DELETE FROM user_info WHERE id=? AND user_type='user'");
    mysqli_stmt_bind_param($stmt,"i",$id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    header("Location: ADMIN.php?section=users"); exit();
}

// ── Notifications ─────────────────────────────────────────────────────────────
mysqli_query($db,"CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(150) NOT NULL, message TEXT NOT NULL, type ENUM('info','success','warning','emergency') DEFAULT 'info', is_read TINYINT(1) DEFAULT 0, expires_at DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES user_info(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($section==='notifications' && isset($_POST['send_notif'])) {
    $ntitle=trim($_POST['notif_title']??'');$nmsg=trim($_POST['notif_message']??'');
    $ntype=$_POST['notif_type']??'info';$ntarget=$_POST['notif_target']??'all';
    $nexpiry=!empty($_POST['notif_expiry'])?$_POST['notif_expiry']:null;$uid=intval($_POST['target_user_id']??0);
    if ($ntitle&&$nmsg) {
        if ($ntarget==='all') {
            $uq=mysqli_query($db,"SELECT id FROM user_info WHERE user_type='user'");
            while ($ur=mysqli_fetch_assoc($uq)) { $st=mysqli_prepare($db,"INSERT INTO notifications (user_id,title,message,type,expires_at) VALUES (?,?,?,?,?)"); mysqli_stmt_bind_param($st,'issss',$ur['id'],$ntitle,$nmsg,$ntype,$nexpiry); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
        } else { $st=mysqli_prepare($db,"INSERT INTO notifications (user_id,title,message,type,expires_at) VALUES (?,?,?,?,?)"); mysqli_stmt_bind_param($st,'issss',$uid,$ntitle,$nmsg,$ntype,$nexpiry); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
    }
    header("Location: ADMIN.php?section=notifications&sent=1"); exit();
}
if ($section==='notifications'&&isset($_GET['delete_notif'])) { $nid=intval($_GET['delete_notif']); $st=mysqli_prepare($db,"DELETE FROM notifications WHERE id=?"); mysqli_stmt_bind_param($st,'i',$nid); mysqli_stmt_execute($st); mysqli_stmt_close($st); header("Location: ADMIN.php?section=notifications"); exit(); }
if ($section==='notifications'&&isset($_GET['delete_all_notifs'])) { mysqli_query($db,"DELETE FROM notifications"); header("Location: ADMIN.php?section=notifications"); exit(); }
if ($section==='notifications'&&isset($_GET['mark_all_read'])) { mysqli_query($db,"UPDATE notifications SET is_read=1"); header("Location: ADMIN.php?section=notifications"); exit(); }

// ── Auto-create tables ─────────────────────────────────────────────────────────
mysqli_query($db,"CREATE TABLE IF NOT EXISTS payments (payment_id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, farmer_id INT NOT NULL, amount_cash DECIMAL(10,2) DEFAULT 0.00, kg_palay DECIMAL(10,2) DEFAULT 0.00, payment_method VARCHAR(20) DEFAULT 'cash', status VARCHAR(20) DEFAULT 'verified', paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE, FOREIGN KEY (farmer_id) REFERENCES user_info(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($db,"CREATE TABLE IF NOT EXISTS messages (message_id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, receiver_id INT NOT NULL, sender_type VARCHAR(10) DEFAULT 'user', message TEXT NOT NULL, is_read TINYINT(1) DEFAULT 0, sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Add cellphone column if missing
@mysqli_query($db,"ALTER TABLE user_info ADD COLUMN IF NOT EXISTS cellphone VARCHAR(20) DEFAULT NULL AFTER address");
// Add status column if missing
@mysqli_query($db,"ALTER TABLE user_info ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active' AFTER cellphone");

// ── Data queries ──────────────────────────────────────────────────────────────
$total_users     = mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM user_info WHERE user_type='user'"))[0]??0;
$total_equipment = mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM equipment"))[0]??0;
$pending_bookings= mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM bookings WHERE status='pending'"))[0]??0;
$total_revenue   = mysqli_fetch_row(mysqli_query($db,"SELECT COALESCE(SUM(amount_cash),0) FROM payments WHERE status='verified'"))[0]??0;
$palay_total     = mysqli_fetch_row(mysqli_query($db,"SELECT COALESCE(SUM(kg_palay),0) FROM payments WHERE status='verified'"))[0]??0;

$recent_bookings_q=mysqli_query($db,"SELECT b.booking_id,u.username,e.name AS equip_name,b.status,b.created_at FROM bookings b LEFT JOIN user_info u ON b.farmer_id=u.id LEFT JOIN equipment e ON b.equipment_id=e.equipment_id ORDER BY b.created_at DESC LIMIT 5");
$equipment_q=mysqli_query($db,"SELECT * FROM equipment ORDER BY created_at DESC");
$bookings_q=mysqli_query($db,"SELECT b.*,u.username,u.email,u.cellphone,e.name AS equip_name,e.category FROM bookings b LEFT JOIN user_info u ON b.farmer_id=u.id LEFT JOIN equipment e ON b.equipment_id=e.equipment_id ORDER BY b.created_at DESC");
$payments_q=mysqli_query($db,"SELECT p.*,u.username,e.name AS equip_name,b.start_date FROM payments p LEFT JOIN user_info u ON p.farmer_id=u.id LEFT JOIN bookings b ON p.booking_id=b.booking_id LEFT JOIN equipment e ON b.equipment_id=e.equipment_id ORDER BY p.paid_at DESC");
$users_q=mysqli_query($db,"SELECT * FROM user_info WHERE user_type='user' ORDER BY created_at DESC");
$pending_pay_q=mysqli_query($db,"SELECT b.booking_id,b.farmer_id,b.total_amount,b.payment_method,u.username,u.cellphone,e.name AS equip_name,b.start_date FROM bookings b LEFT JOIN user_info u ON b.farmer_id=u.id LEFT JOIN equipment e ON b.equipment_id=e.equipment_id WHERE b.status='approved' AND b.booking_id NOT IN (SELECT booking_id FROM payments)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>PILDEMCO Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --g1:#0d3d28;--g2:#1a5f3f;--g3:#2d8a5e;--g4:#4caf80;
    --g5:#c8e6d8;--g6:#e8f5ee;
    --k1:#6d5c2e;--k2:#b8963e;--k3:#f4a261;--k4:#fde8cc;
    --radius:12px;
    --shadow:0 4px 20px rgba(0,0,0,.08);
    --shadow-md:0 8px 32px rgba(0,0,0,.13);
    --header-h:158px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;background:#edf2ee;color:#1a2820;min-height:100vh;display:flex;flex-direction:column;}

/* ════════════════════════════════════════
   ENHANCED HEADER — wider, more visual
════════════════════════════════════════ */
.site-header{
    position:sticky;top:0;z-index:200;
    background:linear-gradient(180deg,var(--g1) 0%,#0f4a30 100%);
    box-shadow:0 4px 24px rgba(0,0,0,.3);
    border-bottom:3px solid var(--k3);
}
/* Top row */
.header-top{
    display:flex;align-items:center;gap:16px;
    padding:0 36px;height:90px;
    border-bottom:1px solid rgba(255,255,255,.1);
    max-width:100%;
}
.header-brand{display:flex;align-items:center;gap:16px;text-decoration:none;flex-shrink:0;}
.header-brand img{width:68px;height:68px;border-radius:50%;border:3px solid var(--k3);object-fit:cover;box-shadow:0 3px 14px rgba(0,0,0,.35);}
.brand-text .name{color:#fff;font-size:1.35rem;font-weight:900;line-height:1.1;font-family:'Nunito',sans-serif;letter-spacing:.02em;}
.brand-text .sub{color:var(--g5);font-size:.74rem;letter-spacing:.08em;font-weight:600;}
.header-divider{width:1.5px;height:36px;background:rgba(255,255,255,.14);flex-shrink:0;margin:0 4px;}
/* User info section */
.header-user-section{display:flex;align-items:center;gap:10px;}
.admin-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--k2),var(--k3));display:flex;align-items:center;justify-content:center;color:var(--g1);font-weight:900;font-size:.9rem;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.2);}
.admin-info .admin-name{color:#fff;font-size:.8rem;font-weight:700;}
.admin-info .admin-role{color:var(--k3);font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;font-weight:600;}
/* Date badge */
.header-date-badge{
    background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);
    border-radius:20px;padding:5px 14px;
    color:rgba(255,255,255,.7);font-size:.7rem;font-weight:600;
    display:flex;align-items:center;gap:6px;
}
/* Controls */
.tl-bar{display:flex;align-items:center;gap:8px;margin-left:auto;}
.theme-toggle{width:40px;height:21px;border-radius:11px;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);cursor:pointer;position:relative;flex-shrink:0;}
.theme-toggle::after{content:'';position:absolute;top:2px;left:2px;width:17px;height:17px;border-radius:50%;background:#fff;transition:transform .25s;}
[data-theme="dark"] .theme-toggle::after{transform:translateX(19px);}
.theme-icon{font-size:.88rem;color:rgba(255,255,255,.6);}
.lang-toggle{display:flex;align-items:center;background:rgba(255,255,255,.1);border-radius:18px;overflow:hidden;border:1px solid rgba(255,255,255,.2);}
.lang-btn{padding:4px 10px;font-size:.62rem;font-weight:700;cursor:pointer;border:none;background:transparent;font-family:'Poppins',sans-serif;color:rgba(255,255,255,.5);letter-spacing:.06em;transition:all .2s;}
.lang-btn.active{background:rgba(255,255,255,.22);color:#fff;border-radius:14px;}

/* Nav row */
.header-nav{
    display:flex;align-items:stretch;gap:0;
    padding:0 28px;height:62px;
    overflow-x:auto;background:rgba(0,0,0,.12);
}
.header-nav::-webkit-scrollbar{height:0;}
.nav-sep{width:1px;background:rgba(255,255,255,.1);margin:10px 6px;flex-shrink:0;}
.nav-section-label{
    padding:0 10px;color:rgba(255,255,255,.3);font-size:.55rem;
    font-weight:700;letter-spacing:.14em;text-transform:uppercase;
    flex-shrink:0;display:flex;align-items:center;
}
.nav-item{
    display:flex;align-items:center;gap:7px;
    padding:0 16px;
    color:rgba(255,255,255,.6);
    text-decoration:none;font-size:.79rem;font-weight:600;
    border-bottom:3px solid transparent;
    white-space:nowrap;
    transition:all .18s;flex-shrink:0;
    position:relative;
}
.nav-item:hover{background:rgba(255,255,255,.07);color:#fff;}
.nav-item.active{border-bottom-color:var(--k3);color:#fff;background:rgba(44,138,94,.18);}
.nav-item .icon{font-size:1.02rem;}
.nav-badge{background:var(--k3);color:var(--g1);font-size:.58rem;font-weight:800;padding:2px 7px;border-radius:10px;}
.logout-btn{display:flex;align-items:center;gap:7px;color:rgba(255,255,255,.5);text-decoration:none;font-size:.75rem;font-weight:600;padding:0 16px;border-bottom:3px solid transparent;transition:all .18s;margin-left:auto;flex-shrink:0;}
.logout-btn:hover{background:rgba(220,50,50,.15);color:#ff8a80;border-bottom-color:#ef4444;}

/* ════════════════ MAIN CONTENT ════════════════ */
.main-wrapper{flex:1;display:flex;flex-direction:column;}
.content{padding:28px 36px;flex:1;max-width:100%;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;padding-bottom:18px;border-bottom:2px solid #dde8df;}
.page-header h2{font-size:1.35rem;font-weight:800;color:var(--g1);font-family:'Nunito',sans-serif;}
.page-header p{font-size:.76rem;color:#888;margin-top:3px;}

/* ── STAT CARDS ── */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:28px;}
.stat-card{
    background:#fff;border-radius:var(--radius);padding:22px 24px;
    box-shadow:var(--shadow);border-left:4px solid var(--g3);
    display:flex;align-items:center;gap:16px;
    transition:transform .2s,box-shadow .2s;
    border:2px solid #e8f0ea;border-left:4px solid var(--g3);
}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);}
.stat-card.orange{border-left-color:var(--k3);border-color:#f0e8dc;border-left-color:var(--k3);}
.stat-card.gold{border-left-color:var(--k2);border-color:#f0ead8;border-left-color:var(--k2);}
.stat-card.dark{border-left-color:var(--g1);border-color:#dde6e0;border-left-color:var(--g1);}
.stat-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;}
.stat-icon.green{background:var(--g6);}
.stat-icon.orange{background:#fff3e8;}
.stat-icon.gold{background:#fdf4e0;}
.stat-icon.dark{background:#e8edf0;}
.stat-val{font-size:1.6rem;font-weight:800;color:var(--g1);line-height:1;font-family:'Nunito',sans-serif;}
.stat-label{font-size:.7rem;color:#888;margin-top:4px;font-weight:600;}

/* ── CARDS ── */
.card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:24px;border:2px solid #e5eee7;}
.card-header{padding:18px 24px;border-bottom:2px solid #eef4ef;display:flex;align-items:center;justify-content:space-between;background:#fafcfa;}
.card-header h3{font-size:.97rem;font-weight:800;color:var(--g1);font-family:'Nunito',sans-serif;}
.card-header p{font-size:.7rem;color:#aaa;margin-top:2px;}

/* ── TABLES ── */
table{width:100%;border-collapse:collapse;font-size:.78rem;}
thead th{background:#ddeee5 !important;color:#0d3d28 !important;font-size:.66rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;padding:13px 16px;text-align:left;border-bottom:2px solid #b0d4bf !important;}
thead th:first-child{border-radius:0;}
tbody td{padding:12px 16px;border-bottom:1.5px solid #edf2ee;vertical-align:middle;color:#1a2820;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:#f4faf5;}
.table-wrap{overflow-x:auto;}
/* Calendar has its own header styling — reset it */
#dashCalGrid thead th{background:transparent !important;padding:6px 2px !important;}

/* ── BADGES ── */
.badge{display:inline-block;padding:4px 11px;border-radius:20px;font-size:.63rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}
.badge-pending{background:#fff8e1;color:#f59e0b;border:1.5px solid #fde68a;}
.badge-approved{background:#e8f5e9;color:#16a34a;border:1.5px solid #86efac;}
.badge-rejected{background:#fee2e2;color:#dc2626;border:1.5px solid #fca5a5;}
.badge-completed{background:#e0f2fe;color:#0284c7;border:1.5px solid #7dd3fc;}
.badge-cancelled{background:#f3f4f6;color:#6b7280;border:1.5px solid #d1d5db;}
.badge-available{background:#e8f5e9;color:#16a34a;border:1.5px solid #86efac;}
.badge-maintenance{background:#fee2e2;color:#dc2626;border:1.5px solid #fca5a5;}
.badge-unavailable{background:#f3f4f6;color:#6b7280;border:1.5px solid #d1d5db;}
.badge-cash{background:#e0f2fe;color:#0284c7;border:1.5px solid #7dd3fc;}
.badge-palay{background:#fdf4e0;color:#b45309;border:1.5px solid #fcd34d;}
.badge-verified{background:#e8f5e9;color:#16a34a;border:1.5px solid #86efac;}

/* Phone badge */
.phone-chip{display:inline-flex;align-items:center;gap:4px;background:#e8f5ee;color:var(--g2);border:1px solid var(--g5);border-radius:12px;padding:3px 9px;font-size:.65rem;font-weight:700;}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 15px;border-radius:9px;font-family:'Poppins',sans-serif;font-size:.73rem;font-weight:700;cursor:pointer;text-decoration:none;border:none;transition:all .18s;}
.btn-green{background:var(--g3);color:#fff;}.btn-green:hover{background:var(--g2);}
.btn-red{background:#ef4444;color:#fff;}.btn-red:hover{background:#dc2626;}
.btn-orange{background:var(--k3);color:var(--g1);}.btn-orange:hover{background:#e8924f;}
.btn-blue{background:#3b82f6;color:#fff;}.btn-blue:hover{background:#2563eb;}
.btn-outline{background:transparent;color:var(--g3);border:2px solid var(--g3);}.btn-outline:hover{background:var(--g6);}
.btn-sm{padding:5px 11px;font-size:.68rem;}
.btn-gap{display:flex;gap:6px;flex-wrap:wrap;}

/* ── ALERTS ── */
.msg-success{background:#dcfce7;color:#15803d;border:1.5px solid #86efac;border-radius:10px;padding:12px 18px;font-size:.8rem;font-weight:700;margin-bottom:18px;}
.msg-error{background:#fee2e2;color:#b91c1c;border:1.5px solid #fca5a5;border-radius:10px;padding:12px 18px;font-size:.8rem;font-weight:700;margin-bottom:18px;}

/* ── PILL ROW ── */
.pill-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;}
.pill{padding:7px 16px;border-radius:20px;font-size:.73rem;font-weight:700;background:#fff;color:var(--g2);border:2px solid var(--g5);}
.pill.orange{background:#fff3e8;color:#c2622b;border-color:#f4d3b0;}
.pill.blue{background:#e0f2fe;color:#0284c7;border-color:#7dd3fc;}

/* ── MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.5);align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;border-radius:18px;padding:34px;width:560px;max-width:97vw;max-height:92vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,.22);animation:modalIn .25s ease;border:2px solid #e5eee7;}
@keyframes modalIn{from{transform:scale(.95);opacity:0}to{transform:scale(1);opacity:1}}
.modal h3{font-size:1.05rem;font-weight:800;color:var(--g1);margin-bottom:22px;font-family:'Nunito',sans-serif;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group.full{grid-column:span 2;}
.form-group label{font-size:.7rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.04em;}
.form-group input,.form-group select,.form-group textarea{padding:10px 13px;border:2px solid #e0e0e0;border-radius:9px;font-family:'Poppins',sans-serif;font-size:.8rem;background:#fafafa;outline:none;transition:border-color .2s,box-shadow .2s;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--g3);box-shadow:0 0 0 3px rgba(45,138,94,.12);}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;}

/* ── DASHBOARD GRID ── */
.dash-grid{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-top:20px;}
.empty{padding:36px;text-align:center;color:#aaa;font-size:.82rem;}
.empty .empty-icon{font-size:2.2rem;display:block;margin-bottom:8px;}

/* Notification title presets */
.notif-title-preset {
    padding: 4px 10px; border-radius: 14px; font-size: .62rem; font-weight: 700;
    border: 1.5px solid #c8e6d8; background: #f0faf4; color: var(--g2);
    cursor: pointer; font-family: 'Poppins', sans-serif; transition: all .15s;
}
.notif-title-preset:hover { background: var(--g2); color: #fff; border-color: var(--g2); }
.notif-type-btn { transition: all .15s; }
.notif-type-btn.active-type { box-shadow: 0 0 0 3px rgba(0,0,0,.15); transform: scale(1.06); }

@media(max-width:1100px){.stats-grid{grid-template-columns:1fr 1fr;}.dash-grid{grid-template-columns:1fr;}}

/* ════════════════════════════════════════
   MOBILE HAMBURGER MENU
════════════════════════════════════════ */
.hamburger-btn{display:none;flex-direction:column;justify-content:center;align-items:center;width:42px;height:42px;border-radius:10px;border:none;cursor:pointer;background:rgba(255,255,255,.12);gap:5px;flex-shrink:0;transition:background .2s;}
.hamburger-btn:hover{background:rgba(255,255,255,.22);}
.hamburger-btn span{display:block;width:22px;height:2.5px;background:#fff;border-radius:2px;transition:all .28s cubic-bezier(.4,0,.2,1);transform-origin:center;}
.hamburger-btn.open span:nth-child(1){transform:translateY(7.5px) rotate(45deg);}
.hamburger-btn.open span:nth-child(2){opacity:0;transform:scaleX(0);}
.hamburger-btn.open span:nth-child(3){transform:translateY(-7.5px) rotate(-45deg);}
.mobile-nav-overlay{display:none;position:fixed;inset:0;z-index:190;background:rgba(0,0,0,.5);backdrop-filter:blur(2px);}
.mobile-nav-overlay.open{display:block;}
.mobile-nav-drawer{position:fixed;top:0;left:-100%;width:82vw;max-width:300px;height:100vh;z-index:195;background:linear-gradient(180deg,#0a3020 0%,#0d3d28 100%);box-shadow:6px 0 32px rgba(0,0,0,.4);transition:left .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow-y:auto;}
.mobile-nav-drawer.open{left:0;}
.mobile-drawer-header{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:12px;flex-shrink:0;}
.mobile-drawer-header img{width:50px;height:50px;border-radius:50%;border:2px solid var(--k3);object-fit:cover;}
.mobile-drawer-brand .name{color:#fff;font-size:1rem;font-weight:900;font-family:'Nunito',sans-serif;}
.mobile-drawer-brand .sub{color:var(--g5);font-size:.62rem;font-weight:600;}
.mobile-drawer-user{padding:12px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.mobile-drawer-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--k2),var(--k3));display:flex;align-items:center;justify-content:center;color:var(--g1);font-weight:900;font-size:.85rem;flex-shrink:0;}
.mobile-drawer-user .uname{color:#fff;font-size:.82rem;font-weight:700;}
.mobile-drawer-user .urole{color:var(--k3);font-size:.58rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;}
.mobile-nav-label{padding:14px 20px 4px;color:rgba(255,255,255,.32);font-size:.54rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;}
.mobile-nav-link{display:flex;align-items:center;gap:12px;padding:13px 20px;color:rgba(255,255,255,.7);text-decoration:none;font-size:.86rem;font-weight:600;border-left:3px solid transparent;transition:all .18s;}
.mobile-nav-link:hover{background:rgba(255,255,255,.07);color:#fff;}
.mobile-nav-link.active{border-left-color:var(--k3);color:#fff;background:rgba(44,138,94,.2);}
.mobile-nav-link .mnav-icon{font-size:1.1rem;width:26px;text-align:center;flex-shrink:0;}
.mobile-nav-badge{background:var(--k3);color:var(--g1);font-size:.58rem;font-weight:800;padding:2px 7px;border-radius:10px;margin-left:auto;}
.mobile-nav-divider{height:1px;background:rgba(255,255,255,.08);margin:4px 0;}
.mobile-drawer-controls{padding:12px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.mobile-drawer-controls .theme-icon{color:rgba(255,255,255,.6);font-size:.9rem;}
.mobile-date-strip{padding:8px 20px;color:rgba(255,255,255,.45);font-size:.64rem;font-weight:600;}
.mobile-drawer-footer{margin-top:auto;padding:14px 20px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0;}
.mobile-logout-btn{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;background:rgba(220,50,50,.12);border:1px solid rgba(220,50,50,.25);color:#ff8a80;font-size:.84rem;font-weight:700;text-decoration:none;transition:all .2s;width:100%;justify-content:center;}
.mobile-logout-btn:hover{background:rgba(220,50,50,.25);}
@media(max-width:768px){
    /* Prevent iOS zoom */
    input,select,textarea{font-size:16px!important;}
    .hamburger-btn{display:flex;}
    .header-divider,.header-user-section,.header-date-badge,.tl-bar,.header-nav{display:none!important;}
    .header-top{height:60px;padding:0 12px;gap:9px;}
    .header-brand img{width:40px;height:40px;}
    .brand-text .name{font-size:.92rem;}
    .brand-text .sub{font-size:.55rem;}
    .content{padding:12px 10px;}
    .page-header{flex-direction:column;align-items:flex-start;gap:6px;margin-bottom:16px;}
    .page-header h2{font-size:1rem;}
    .page-header p{font-size:.68rem;}
    .stats-grid{grid-template-columns:1fr 1fr;gap:10px;}
    .dash-grid{grid-template-columns:1fr;}
    .stat-card{padding:13px 12px;gap:9px;border-radius:10px;}
    .stat-icon{width:36px;height:36px;font-size:1rem;}
    .stat-val{font-size:1.15rem;}
    .stat-label{font-size:.61rem;}
    .btn{font-size:.68rem;padding:7px 11px;}
    .card{border-radius:10px;margin-bottom:13px;}
    .card-header{padding:11px 13px;}
    .card-header h3{font-size:.83rem;}
    .card-header p{font-size:.63rem;}
    .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;}
    table{font-size:.71rem;min-width:520px;}
    thead th{padding:8px 9px;font-size:.59rem;}
    tbody td{padding:8px 9px;}
    .badge,.badge-pending,.badge-approved,.badge-rejected,.badge-completed,
    .badge-cancelled,.badge-cash,.badge-palay,.badge-available{font-size:.57rem;padding:2px 7px;}
    .modal{padding:18px 14px;border-radius:14px;max-height:92vh;}
    .modal h3{font-size:.88rem;}
    .fg label{font-size:.64rem;}
    .fg input,.fg select,.fg textarea{padding:9px 10px;}
    .btn-gap{flex-wrap:wrap;gap:5px;}
    .pill{font-size:.68rem;padding:4px 11px;}
    .msg-success,.msg-error{font-size:.75rem;padding:9px 12px;}
    /* Reports grid */
    div[style*="grid-template-columns:1fr 1fr"]{display:flex!important;flex-direction:column!important;}
    div[style*="grid-column:span 2"]{grid-column:span 1!important;}
}
@media(max-width:400px){
    .stats-grid{grid-template-columns:1fr;}
    .content{padding:10px 8px;}
    table{min-width:440px;}
    .header-top{height:56px;}
    .header-brand img{width:36px;height:36px;}
    .brand-text .name{font-size:.82rem;}
}

/* ════════════════════════════════════════
   LIGHT MODE — boosted contrast
════════════════════════════════════════ */
body { color: #111d15; }
tbody td { color: #1a2820; }
.stat-val { color: #0d3d28; }
.stat-label { color: #3e5a47; font-weight: 700; }
.card-header h3 { color: #0d3d28; }
.page-header h2 { color: #0d3d28; }
.page-header p { color: #3e5a47; font-weight: 600; }
.form-group label { color: #2d4a38 !important; font-weight: 700 !important; }
.form-group input, .form-group select, .form-group textarea { color: #1a2820 !important; }
.nav-section-label { color: rgba(255,255,255,.55) !important; font-weight: 800 !important; }
table { color: #1a2820; }
.empty { color: #5a7a65 !important; font-weight: 600; }
.modal h3 { color: #0d3d28 !important; }

/* ════════════════════════════════════════
   DARK MODE — Full text & background fix
   Maximum readability on dark background
════════════════════════════════════════ */
[data-theme="dark"] body {
    background: #091410;
    color: #ddeee4;
}

/* Main content area */
[data-theme="dark"] .content { background: #091410; }

/* Page header */
[data-theme="dark"] .page-header { border-bottom-color: #1c3226; }
[data-theme="dark"] .page-header h2 { color: #b8f0cc !important; }
[data-theme="dark"] .page-header p  { color: #7dc898 !important; }

/* Stat cards */
[data-theme="dark"] .stat-card { background: #112018; border-color: #1c3828; color: #ddeee4; }
[data-theme="dark"] .stat-card.orange { border-color: #3c2810; }
[data-theme="dark"] .stat-card.gold   { border-color: #382e10; }
[data-theme="dark"] .stat-card.dark   { border-color: #1c2e24; }
[data-theme="dark"] .stat-val   { color: #c2f0d0 !important; }
[data-theme="dark"] .stat-label { color: #82c498 !important; font-weight: 700; }
[data-theme="dark"] .stat-icon.green  { background: #183422; }
[data-theme="dark"] .stat-icon.orange { background: #2c1c08; }
[data-theme="dark"] .stat-icon.gold   { background: #28200e; }
[data-theme="dark"] .stat-icon.dark   { background: #18241e; }

/* Cards */
[data-theme="dark"] .card { background: #112018; border-color: #1c3828; }
[data-theme="dark"] .card-header { background: #0d1c14; border-bottom-color: #1c2e22; }
[data-theme="dark"] .card-header h3 { color: #b8f0cc !important; }
[data-theme="dark"] .card-header p  { color: #7dc898 !important; }

/* Tables */
[data-theme="dark"] table { color: #d8ece2; }
[data-theme="dark"] thead th { background: #1a3828 !important; color: #a0f0c0 !important; border-bottom: 2px solid #2a5c3e !important; }
[data-theme="dark"] tbody td { border-bottom-color: #1a2e22; color: #d0e8d8 !important; }
[data-theme="dark"] tbody tr:hover td { background: #162e20; }
[data-theme="dark"] .table-wrap { background: #112018; }

/* Badges — keep existing colors but ensure readability */
[data-theme="dark"] .badge-pending   { background: #2e2306; color: #fbbf24; border-color: #78540a; }
[data-theme="dark"] .badge-approved  { background: #0a2e14; color: #4ade80; border-color: #166534; }
[data-theme="dark"] .badge-rejected  { background: #2e0a0a; color: #f87171; border-color: #7f1d1d; }
[data-theme="dark"] .badge-completed { background: #0a1e36; color: #60a5fa; border-color: #1e3a5f; }
[data-theme="dark"] .badge-cancelled { background: #1e2226; color: #9ca3af; border-color: #374151; }
[data-theme="dark"] .badge-available { background: #0a2e14; color: #4ade80; border-color: #166534; }
[data-theme="dark"] .badge-maintenance{ background: #2e0a0a; color: #f87171; border-color: #7f1d1d; }
[data-theme="dark"] .badge-unavailable{ background: #1e2226; color: #9ca3af; border-color: #374151; }
[data-theme="dark"] .badge-cash      { background: #0a1e36; color: #60a5fa; border-color: #1e3a5f; }
[data-theme="dark"] .badge-palay     { background: #2a1c08; color: #fcd34d; border-color: #78450a; }
[data-theme="dark"] .badge-verified  { background: #0a2e14; color: #4ade80; border-color: #166534; }

/* Phone chip */
[data-theme="dark"] .phone-chip { background: #183422; color: #86efb8; border-color: #2a6045; }

/* Buttons */
[data-theme="dark"] .btn-outline { color: #6edf9a; border-color: #6edf9a; }
[data-theme="dark"] .btn-outline:hover { background: #1a3826; }

/* Alerts */
[data-theme="dark"] .msg-success { background: #0a2e14; color: #5ee890; border-color: #1a7040; }
[data-theme="dark"] .msg-error   { background: #2e0a0a; color: #ff8080; border-color: #7f1d1d; }

/* Pills */
[data-theme="dark"] .pill { background: #112018; color: #86efb8; border-color: #2a5c3e; font-weight: 700; }
[data-theme="dark"] .pill.orange { background: #28180a; color: #ffb07a; border-color: #8c3e18; }
[data-theme="dark"] .pill.blue   { background: #0a1a2e; color: #7ec8ff; border-color: #1e3a6e; }

/* Modal */
[data-theme="dark"] .modal { background: #112018; border-color: #2a5c3e; }
[data-theme="dark"] .modal h3 { color: #b8f0cc !important; }
[data-theme="dark"] .form-group label { color: #8ecfa6 !important; font-weight: 700 !important; }
[data-theme="dark"] .form-group input,
[data-theme="dark"] .form-group select,
[data-theme="dark"] .form-group textarea {
    background: #0c1c14;
    border-color: #2a5c3e;
    color: #d8ece2 !important;
}
[data-theme="dark"] .form-group input::placeholder,
[data-theme="dark"] .form-group textarea::placeholder { color: #4a7a58; }
[data-theme="dark"] .form-group input:focus,
[data-theme="dark"] .form-group select:focus,
[data-theme="dark"] .form-group textarea:focus { border-color: var(--g4); box-shadow: 0 0 0 3px rgba(76,175,128,.22); }

/* Empty state */
[data-theme="dark"] .empty { color: #5aaa72 !important; font-weight: 600; }

/* Section header borders */
[data-theme="dark"] .section-header { border-bottom-color: #1e3328; }

/* All general text in dark mode — maximum contrast */
[data-theme="dark"] h1,[data-theme="dark"] h2,[data-theme="dark"] h3,
[data-theme="dark"] h4,[data-theme="dark"] h5,[data-theme="dark"] h6 { color: #b8f0cc !important; }
[data-theme="dark"] p { color: #cce8d6 !important; }
[data-theme="dark"] span { color: #d0e8da; }
[data-theme="dark"] td { color: #d0e8d8 !important; }
[data-theme="dark"] th { color: #d0f0dc !important; }
[data-theme="dark"] label { color: #8ecfa6 !important; }
[data-theme="dark"] li { color: #cce8d6 !important; }

/* Override inline color styles */
[data-theme="dark"] [style*="color:#555"],
[data-theme="dark"] [style*="color: #555"] { color: #98d4b0 !important; }
[data-theme="dark"] [style*="color:#888"],
[data-theme="dark"] [style*="color: #888"] { color: #88c8a0 !important; }
[data-theme="dark"] [style*="color:#aaa"],
[data-theme="dark"] [style*="color: #aaa"] { color: #78bc94 !important; }
[data-theme="dark"] [style*="color:#ccc"],
[data-theme="dark"] [style*="color: #ccc"] { color: #5a9c72 !important; }
[data-theme="dark"] [style*="color:#bbb"],
[data-theme="dark"] [style*="color: #bbb"] { color: #6aac82 !important; }
[data-theme="dark"] [style*="color:#222"],
[data-theme="dark"] [style*="color: #222"] { color: #d8ece2 !important; }
[data-theme="dark"] [style*="color:#333"],
[data-theme="dark"] [style*="color: #333"] { color: #cce8d6 !important; }
[data-theme="dark"] [style*="color:#666"],
[data-theme="dark"] [style*="color: #666"] { color: #8ecfa6 !important; }
[data-theme="dark"] [style*="color:var(--g1)"],
[data-theme="dark"] [style*="color: var(--g1)"] { color: #b8f0cc !important; }
[data-theme="dark"] [style*="color:var(--g2)"],
[data-theme="dark"] [style*="color: var(--g2)"] { color: #86efb8 !important; }
[data-theme="dark"] [style*="color:#1e2e1e"],
[data-theme="dark"] [style*="color: #1e2e1e"] { color: #d8ece2 !important; }
[data-theme="dark"] [style*="background:#fafafa"],
[data-theme="dark"] [style*="background: #fafafa"] { background: #0c1c14 !important; }
[data-theme="dark"] [style*="background:#fff"],
[data-theme="dark"] [style*="background: #fff"] { background: #112018 !important; }
[data-theme="dark"] [style*="background:#e8f5ee"],
[data-theme="dark"] [style*="background: #e8f5ee"] { background: #183422 !important; }
[data-theme="dark"] [style*="background:#fff8e1"],
[data-theme="dark"] [style*="background: #fff8e1"] { background: #281e0a !important; }
[data-theme="dark"] [style*="background:#fafcfa"],
[data-theme="dark"] [style*="background: #fafcfa"] { background: #0d1c14 !important; }
[data-theme="dark"] [style*="background:#fee2e2"],
[data-theme="dark"] [style*="background: #fee2e2"] { background: #2a0a0a !important; color: #ffa0a0 !important; }
[data-theme="dark"] [style*="background:#e0f2fe"],
[data-theme="dark"] [style*="background: #e0f2fe"] { background: #0a1a2e !important; color: #7ec8ff !important; }
[data-theme="dark"] [style*="background:#fdf4e0"],
[data-theme="dark"] [style*="background: #fdf4e0"] { background: #281e08 !important; color: #ffd080 !important; }
[data-theme="dark"] [style*="background:#e0e7ff"],
[data-theme="dark"] [style*="background: #e0e7ff"] { background: #12183a !important; color: #a0b8ff !important; }
[data-theme="dark"] [style*="border-left:4px solid #ef4444"],
[data-theme="dark"] [style*="border-left: 4px solid #ef4444"] { border-left-color: #f87171 !important; }
[data-theme="dark"] [style*="border-left:4px solid var(--g3)"],
[data-theme="dark"] [style*="border-left: 4px solid var(--g3)"] { border-left-color: var(--g4) !important; }

/* Scrollbar for dark mode */
[data-theme="dark"] ::-webkit-scrollbar-track { background: #091410; }
[data-theme="dark"] ::-webkit-scrollbar-thumb { background: #2a5c3e; border-radius: 4px; }

/* Dashboard calendar dark mode */
[data-theme="dark"] #dashCalGrid thead th { color: #88d4a4 !important; background: transparent !important; }
[data-theme="dark"] #dashCalEventList { background: #112018; border-radius: 8px; padding: 10px; }
[data-theme="dark"] #dashCalEventDate { color: #b8f0cc !important; }
[data-theme="dark"] #dashCalBody td [style*="color:#333"] { color: #d0e8d8 !important; }
[data-theme="dark"] #dashCalBody td [style*="color:#991b1b"] { color: #ffa0a0 !important; }
[data-theme="dark"] #dashCalBody td [style*="color:#92400e"] { color: #ffcc80 !important; }

/* Calendar cell dark mode backgrounds */
[data-theme="dark"] #dashCalBody td[style*="background:#fff"] { background: #162c1e !important; border-color: #2a4838 !important; }
[data-theme="dark"] #dashCalBody td[style*="background:#fafafa"] { background: #0e1a14 !important; }

/* Notif preset buttons dark mode */
[data-theme="dark"] .notif-title-preset { background: #183422; border-color: #2a5c3e; color: #86efb8; }
[data-theme="dark"] .notif-title-preset:hover { background: var(--g2); color: #fff; }


@media(max-width:1100px){.stats-grid{grid-template-columns:1fr 1fr;}.dash-grid{grid-template-columns:1fr;}}
@media(max-width:700px){.stats-grid{grid-template-columns:1fr 1fr;}.content{padding:12px 10px;}.header-top{padding:0 12px;}.header-nav{padding:0 8px;}}
</style>
<?php include 'theme_lang.php'; ?>
</head>
<body>

<!-- ═══════════ ENHANCED HEADER ═══════════ -->
<header class="site-header">
    <!-- Top row: brand + user info + controls -->
    <div class="header-top">
        <!-- Hamburger (mobile only) -->
        <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleMobileNav()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <a href="?section=dashboard" class="header-brand">
            <img src="LOGO_OF_PILdemCO-removebg-preview.png" alt="PILDEMCO">
            <div class="brand-text">
                <div class="name">PILDEMCO</div>
                <div class="sub">San Agustin Chapter &bull; Admin Panel</div>
            </div>
        </a>
        <div class="header-divider"></div>
        <div class="header-user-section">
            <div class="admin-avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
            <div class="admin-info">
                <div class="admin-name"><?= $admin_name ?></div>
                <div class="admin-role">⚙️ Administrator</div>
            </div>
        </div>
        <div class="header-date-badge">📅 <?= date('D, M j Y') ?></div>
        <div class="tl-bar">
            <span class="theme-icon" id="themeIcon">🌙</span>
            <button class="theme-toggle" onclick="toggleTheme()" style="background:var(--g3)"></button>
            <div class="lang-toggle">
                <button class="lang-btn active" data-lang="en" onclick="setLang('en')">EN</button>
                <button class="lang-btn" data-lang="tl" onclick="setLang('tl')">TL</button>
            </div>
        </div>
    </div>
    <!-- Nav row -->
    <nav class="header-nav">
        <a href="?section=dashboard" class="nav-item <?= $section=='dashboard'?'active':'' ?>"><span class="icon">🏠</span> Dashboard</a>
        <div class="nav-sep"></div>
        <span class="nav-section-label">Manage</span>
        <a href="?section=equipment" class="nav-item <?= $section=='equipment'?'active':'' ?>"><span class="icon">🚜</span> Equipment</a>
        <a href="?section=bookings"  class="nav-item <?= $section=='bookings'?'active':'' ?>">
            <span class="icon">📅</span> Bookings
            <?php if($pending_bookings>0): ?><span class="nav-badge"><?= $pending_bookings ?></span><?php endif; ?>
        </a>
        <a href="?section=payments"  class="nav-item <?= $section=='payments'?'active':'' ?>"><span class="icon">💰</span> Payments</a>
        <a href="?section=users"     class="nav-item <?= $section=='users'?'active':'' ?>"><span class="icon">🧑‍🌾</span> Users</a>
        <div class="nav-sep"></div>
        <span class="nav-section-label">Reports</span>
        <a href="?section=reports"   class="nav-item <?= $section=='reports'?'active':'' ?>"><span class="icon">📊</span> Reports</a>
        <div class="nav-sep"></div>
        <?php
        $notif_tbl_chk    = mysqli_fetch_row(mysqli_query($db,"SHOW TABLES LIKE 'notifications'"));
        $notif_nav_count  = $notif_tbl_chk ? (mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM notifications WHERE is_read=0"))[0]??0) : 0;
        ?>
        <a href="?section=notifications" class="nav-item <?= $section=='notifications'?'active':'' ?>">
            <span class="icon">🔔</span> Notifications
            <?php if($notif_nav_count>0): ?><span class="nav-badge"><?= $notif_nav_count ?></span><?php endif; ?>
        </a>
        <a href="LOGOUT.php" class="logout-btn"><span>🚪</span> Logout</a>
    </nav>
</header>

<!-- ═══════════ MOBILE NAV DRAWER ═══════════ -->
<div class="mobile-nav-overlay" id="mobileNavOverlay" onclick="closeMobileNav()"></div>
<div class="mobile-nav-drawer" id="mobileNavDrawer">
    <!-- Drawer brand -->
    <div class="mobile-drawer-header">
        <img src="LOGO_OF_PILdemCO-removebg-preview.png" alt="PILDEMCO">
        <div class="mobile-drawer-brand">
            <div class="name">PILDEMCO</div>
            <div class="sub">San Agustin Chapter &bull; Admin Panel</div>
        </div>
    </div>
    <!-- Admin info -->
    <div class="mobile-drawer-user">
        <div class="mobile-drawer-avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
        <div>
            <div class="uname"><?= $admin_name ?></div>
            <div class="urole">⚙️ Administrator</div>
        </div>
    </div>
    <!-- Date + theme controls -->
    <div class="mobile-date-strip">📅 <?= date('D, M j Y') ?></div>
    <div class="mobile-drawer-controls">
        <span class="theme-icon" id="mobileThemeIcon">🌙</span>
        <button class="theme-toggle" onclick="toggleTheme()" style="background:var(--g3)"></button>
        <div class="lang-toggle" style="margin-left:auto;">
            <button class="lang-btn active" data-lang="en" onclick="setLang('en')">EN</button>
            <button class="lang-btn" data-lang="tl" onclick="setLang('tl')">TL</button>
        </div>
    </div>
    <!-- Navigation links -->
    <div class="mobile-nav-label">Main</div>
    <a href="?section=dashboard" class="mobile-nav-link <?= $section=='dashboard'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">🏠</span> Dashboard
    </a>
    <div class="mobile-nav-divider"></div>
    <div class="mobile-nav-label">Manage</div>
    <a href="?section=equipment" class="mobile-nav-link <?= $section=='equipment'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">🚜</span> Equipment
    </a>
    <a href="?section=bookings" class="mobile-nav-link <?= $section=='bookings'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">📅</span> Bookings
        <?php if($pending_bookings>0): ?><span class="mobile-nav-badge"><?= $pending_bookings ?></span><?php endif; ?>
    </a>
    <a href="?section=payments" class="mobile-nav-link <?= $section=='payments'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">💰</span> Payments
    </a>
    <a href="?section=users" class="mobile-nav-link <?= $section=='users'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">🧑‍🌾</span> Users
    </a>
    <div class="mobile-nav-divider"></div>
    <div class="mobile-nav-label">Reports</div>
    <a href="?section=reports" class="mobile-nav-link <?= $section=='reports'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">📊</span> Reports
    </a>
    <div class="mobile-nav-divider"></div>
    <a href="?section=notifications" class="mobile-nav-link <?= $section=='notifications'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">🔔</span> Notifications
        <?php if(isset($notif_nav_count) && $notif_nav_count>0): ?><span class="mobile-nav-badge"><?= $notif_nav_count ?></span><?php endif; ?>
    </a>
    <!-- Logout -->
    <div class="mobile-drawer-footer">
        <a href="LOGOUT.php" class="mobile-logout-btn">🚪 &nbsp;Logout</a>
    </div>
</div>

<!-- ═══════════ MAIN ═══════════ -->
<div class="main-wrapper">
<div class="content">

<!-- ══════ DASHBOARD ══════ -->
<?php if($section=='dashboard'): ?>
    <div class="page-header">
        <div><h2>🏠 Dashboard Overview</h2><p>Welcome back, <?= $admin_name ?>. Here's today's summary.</p></div>
    </div>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon green">🧑‍🌾</div><div><div class="stat-val"><?= $total_users ?></div><div class="stat-label">Registered Users</div></div></div>
        <div class="stat-card orange"><div class="stat-icon orange">🚜</div><div><div class="stat-val"><?= $total_equipment ?></div><div class="stat-label">Total Equipment</div></div></div>
        <div class="stat-card gold"><div class="stat-icon gold">📅</div><div><div class="stat-val"><?= $pending_bookings ?></div><div class="stat-label">Pending Bookings</div></div></div>
        <div class="stat-card dark"><div class="stat-icon dark">💵</div><div><div class="stat-val">₱<?= number_format($total_revenue,0) ?></div><div class="stat-label">Cash Revenue</div></div></div>
    </div>
    <div class="pill-row">
        <div class="pill">🌾 Palay Collected: <?= number_format($palay_total,1) ?> sack</div>
        <div class="pill orange">⏳ Pending: <?= $pending_bookings ?></div>
        <div class="pill blue">📍 San Jose, Occ. Mindoro</div>
    </div>

    <!-- ── MACHINE SCHEDULE CALENDAR ── -->
    <?php
    // Fetch bookings for calendar (pending + approved)
    $dash_cal_q = mysqli_query($db,
        "SELECT b.booking_id, b.status,
                COALESCE(b.use_date, b.start_date) AS book_date,
                b.use_time_start, b.use_time_end,
                e.name AS equip_name, u.username
         FROM bookings b
         LEFT JOIN equipment e ON b.equipment_id = e.equipment_id
         LEFT JOIN user_info u ON b.farmer_id = u.id
         WHERE b.status IN ('pending','approved')
         ORDER BY book_date ASC");
    $dash_cal_events = [];
    while ($dc = mysqli_fetch_assoc($dash_cal_q)) {
        $d = $dc['book_date'];
        if (!isset($dash_cal_events[$d])) $dash_cal_events[$d] = [];
        $dash_cal_events[$d][] = $dc;
    }
    ?>
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h3>📅 Machine Schedule Calendar</h3>
                <p>Upcoming bookings for all equipment</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <button class="btn btn-outline btn-sm" onclick="dashCalPrev()">‹</button>
                <button class="btn btn-outline btn-sm" onclick="dashCalToday()" style="font-size:.66rem;">Today</button>
                <button class="btn btn-outline btn-sm" onclick="dashCalNext()">›</button>
                <span id="dashCalTitle" style="font-size:.82rem;font-weight:800;color:var(--g1);min-width:140px;text-align:center;"></span>
            </div>
        </div>
        <div style="padding:16px 20px;">
            <!-- Legend -->
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;font-size:.68rem;font-weight:600;color:#666;">
                <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:3px;background:#fee2e2;border:1px solid #fca5a5;display:inline-block"></span>Booked/Approved</span>
                <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:3px;background:#fff8e1;border:1px solid #fde68a;display:inline-block"></span>Pending</span>
                <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:3px;background:#e8f5ee;border:1px solid #86efac;display:inline-block"></span>Free</span>
                <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:3px;background:var(--g3);display:inline-block"></span>Today</span>
            </div>
            <table style="width:100%;border-collapse:separate;border-spacing:3px;" id="dashCalGrid">
                <thead><tr>
                    <th style="font-size:.68rem;font-weight:800;color:#ef4444;text-align:center;padding:6px 2px;text-transform:uppercase;letter-spacing:.06em;">SUN</th>
                    <th style="font-size:.68rem;font-weight:800;color:var(--g2);text-align:center;padding:6px 2px;text-transform:uppercase;letter-spacing:.06em;">MON</th>
                    <th style="font-size:.68rem;font-weight:800;color:var(--g2);text-align:center;padding:6px 2px;text-transform:uppercase;letter-spacing:.06em;">TUE</th>
                    <th style="font-size:.68rem;font-weight:800;color:var(--g2);text-align:center;padding:6px 2px;text-transform:uppercase;letter-spacing:.06em;">WED</th>
                    <th style="font-size:.68rem;font-weight:800;color:var(--g2);text-align:center;padding:6px 2px;text-transform:uppercase;letter-spacing:.06em;">THU</th>
                    <th style="font-size:.68rem;font-weight:800;color:var(--g2);text-align:center;padding:6px 2px;text-transform:uppercase;letter-spacing:.06em;">FRI</th>
                    <th style="font-size:.68rem;font-weight:800;color:#3b82f6;text-align:center;padding:6px 2px;text-transform:uppercase;letter-spacing:.06em;">SAT</th>
                </tr></thead>
                <tbody id="dashCalBody"></tbody>
            </table>
            <div id="dashCalEventList" style="margin-top:14px;display:none;">
                <div style="font-size:.72rem;font-weight:700;color:var(--g1);margin-bottom:8px;" id="dashCalEventDate"></div>
                <div id="dashCalEventItems"></div>
            </div>
        </div>
    </div>

    <script>
    const DASH_CAL_EVENTS = <?= json_encode($dash_cal_events) ?>;
    let dashCalYear  = new Date().getFullYear();
    let dashCalMonth = new Date().getMonth();
    const dashToday  = new Date(); dashToday.setHours(0,0,0,0);
    const DASH_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function dashCalPrev()  { dashCalMonth--; if(dashCalMonth<0){dashCalMonth=11;dashCalYear--;} buildDashCal(); }
    function dashCalNext()  { dashCalMonth++; if(dashCalMonth>11){dashCalMonth=0;dashCalYear++;} buildDashCal(); }
    function dashCalToday() { dashCalYear=dashToday.getFullYear(); dashCalMonth=dashToday.getMonth(); buildDashCal(); }

    function buildDashCal() {
        document.getElementById('dashCalTitle').textContent = DASH_MONTHS[dashCalMonth] + ' ' + dashCalYear;
        const body = document.getElementById('dashCalBody');
        body.innerHTML = '';
        const firstDay    = new Date(dashCalYear, dashCalMonth, 1).getDay();
        const daysInMonth = new Date(dashCalYear, dashCalMonth+1, 0).getDate();
        const prevDays    = new Date(dashCalYear, dashCalMonth, 0).getDate();
        let day = 1, nextDay = 1;
        const total = Math.ceil((firstDay + daysInMonth)/7)*7;

        for (let i=0; i<total; i++) {
            if (i%7===0) body.insertRow();
            const row = body.lastElementChild;
            const cell = row.insertCell();
            cell.style.cssText = 'border-radius:8px;padding:4px 3px;text-align:center;vertical-align:top;min-height:48px;cursor:pointer;transition:all .15s;min-width:32px;';

            if (i < firstDay) {
                cell.innerHTML = `<span style="font-size:.72rem;color:#ccc;">${prevDays-firstDay+i+1}</span>`;
                cell.style.background='#fafafa'; cell.style.cursor='default';
            } else if (day > daysInMonth) {
                cell.innerHTML = `<span style="font-size:.72rem;color:#ccc;">${nextDay++}</span>`;
                cell.style.background='#fafafa'; cell.style.cursor='default';
            } else {
                const mm   = String(dashCalMonth+1).padStart(2,'0');
                const dd   = String(day).padStart(2,'0');
                const dstr = `${dashCalYear}-${mm}-${dd}`;
                const cellDate = new Date(dashCalYear, dashCalMonth, day);
                cellDate.setHours(0,0,0,0);
                const isToday   = cellDate.getTime() === dashToday.getTime();
                const events    = DASH_CAL_EVENTS[dstr] || [];
                const hasApproved = events.some(e=>e.status==='approved');
                const hasPending  = events.some(e=>e.status==='pending');

                let bg = '#fff', border = '1.5px solid #e5e7eb';
                if (isToday)        { bg = 'var(--g3)'; border = '2px solid var(--g2)'; }
                else if (hasApproved){ bg='#fee2e2'; border='1.5px solid #fca5a5'; }
                else if (hasPending) { bg='#fff8e1'; border='1.5px solid #fde68a'; }
                else                 { bg='#fff'; border='1.5px solid #e5e7eb'; }

                const dots = events.slice(0,3).map(e =>
                    `<span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:${e.status==='approved'?'#ef4444':'#f59e0b'};margin:0 1px;"></span>`
                ).join('');

                cell.innerHTML = `<span style="font-size:.76rem;font-weight:${isToday?'800':'600'};color:${isToday?'#fff':hasApproved?'#991b1b':hasPending?'#92400e':'#333'};display:block;line-height:1.4;">${day}</span><div style="display:flex;justify-content:center;gap:1px;margin-top:2px;">${dots}</div>`;
                cell.style.background = bg;
                cell.style.border = border;
                if (events.length) {
                    cell.onclick = () => showDashCalEvents(dstr, events);
                    cell.onmouseover = () => { if (!isToday) cell.style.filter='brightness(.95)'; cell.style.transform='scale(1.06)'; };
                    cell.onmouseout  = () => { cell.style.filter=''; cell.style.transform=''; };
                }
                day++;
            }
        }
    }

    function showDashCalEvents(dateStr, events) {
        const panel = document.getElementById('dashCalEventList');
        const title = document.getElementById('dashCalEventDate');
        const items = document.getElementById('dashCalEventItems');
        const d = new Date(dateStr+'T00:00:00');
        title.textContent = '📅 ' + d.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
        items.innerHTML = events.map(e => {
            const timeStr = e.use_time_start ? `⏰ ${e.use_time_start.substring(0,5)}${e.use_time_end?' – '+e.use_time_end.substring(0,5):''}` : '';
            const statusColor = e.status==='approved'?'#ef4444':'#f59e0b';
            const statusBg    = e.status==='approved'?'#fee2e2':'#fff8e1';
            return `<div style="background:${statusBg};border-left:3px solid ${statusColor};border-radius:8px;padding:9px 13px;margin-bottom:7px;display:flex;align-items:center;gap:10px;">
                <div style="flex:1;"><div style="font-size:.8rem;font-weight:700;color:#222;">🚜 ${e.equip_name||'Equipment'}</div>
                <div style="font-size:.7rem;color:#555;margin-top:2px;">👤 ${e.username||'Farmer'} ${timeStr?'&nbsp;·&nbsp;'+timeStr:''}</div></div>
                <span style="font-size:.62rem;font-weight:800;padding:3px 9px;border-radius:12px;background:${statusColor};color:#fff;text-transform:uppercase;">${e.status}</span>
                <a href="?section=bookings" style="font-size:.65rem;color:var(--g3);font-weight:700;text-decoration:none;">View →</a>
            </div>`;
        }).join('');
        panel.style.display = 'block';
        panel.scrollIntoView({behavior:'smooth',block:'nearest'});
    }

    document.addEventListener('DOMContentLoaded', buildDashCal);
    </script>
    <div class="dash-grid">
        <div class="card">
            <div class="card-header"><div><h3>Recent Booking Requests</h3><p>Latest 5 submissions</p></div><a href="?section=bookings" class="btn btn-outline btn-sm">View All</a></div>
            <div class="table-wrap"><table>
                <thead><tr><th>#</th><th>User</th><th>Equipment</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php if(mysqli_num_rows($recent_bookings_q)>0): while($r=mysqli_fetch_assoc($recent_bookings_q)): ?>
                <tr><td>#<?= $r['booking_id'] ?></td><td><?= htmlspecialchars($r['username']??'N/A') ?></td><td><?= htmlspecialchars($r['equip_name']??'N/A') ?></td><td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td><td><?= date('M j, Y',strtotime($r['created_at'])) ?></td></tr>
                <?php endwhile; else: ?><tr><td colspan="5" class="empty">No bookings yet.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <div class="card-header"><div><h3>Quick Actions</h3></div></div>
            <div style="padding:20px;display:flex;flex-direction:column;gap:10px;">
                <a href="?section=bookings" class="btn btn-green">📋 Review Pending Bookings</a>
                <button onclick="openModal('addEquipModal')" class="btn btn-orange">➕ Add New Equipment</button>
                <a href="?section=payments" class="btn btn-blue">💰 View Payments</a>
                <a href="?section=users"    class="btn btn-outline">🧑‍🌾 Manage Users</a>
                <a href="?section=reports"  class="btn btn-outline">📊 View Reports</a>
            </div>
        </div>
    </div>

<!-- ══════ EQUIPMENT ══════ -->
<?php elseif($section=='equipment'): ?>
    <div class="page-header">
        <div><h2>🚜 Equipment Management</h2><p>Add, edit, or remove farming machinery.</p></div>
        <button class="btn btn-green" onclick="openModal('addEquipModal')">+ Add Equipment</button>
    </div>
    <div class="card">
        <div class="card-header"><div><h3>Equipment Catalog</h3><p><?= mysqli_num_rows($equipment_q) ?> items</p></div></div>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Name</th><th>Category</th><th>Cash Rate</th><th>Palay Rate (sack)</th><th>Unit</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(mysqli_num_rows($equipment_q)>0): while($e=mysqli_fetch_assoc($equipment_q)): ?>
            <tr>
                <td><?= $e['equipment_id'] ?></td>
                <td><strong><?= htmlspecialchars($e['name']) ?></strong><br><span style="color:#aaa;font-size:.64rem"><?= htmlspecialchars(substr($e['description']??'',0,50)) ?>…</span></td>
                <td><?= htmlspecialchars($e['category']) ?></td>
                <td>₱<?= number_format($e['rate_cash'],2) ?></td>
                <td><?= number_format($e['rate_palay'],2) ?> sack</td>
                <td><?= $e['rate_unit'] ?></td>
                <td><span class="badge badge-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
                <td><div class="btn-gap">
                    <button class="btn btn-outline btn-sm" onclick="openEditEquip(<?= htmlspecialchars(json_encode($e),ENT_QUOTES) ?>)">✏️ Edit</button>
                    <a href="?section=equipment&delete_equipment=<?= $e['equipment_id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete?')">🗑Delete</a>
                </div></td>
            </tr>
            <?php endwhile; else: ?><tr><td colspan="8"><div class="empty"><span class="empty-icon">🚜</span>No equipment yet.</div></td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

<!-- ══════ BOOKINGS ══════ -->
<?php elseif($section=='bookings'): ?>
    <div class="page-header">
        <div><h2>📅 Booking Management</h2><p>Approve, reject, complete, or edit rental requests.</p></div>
    </div>
    <?php if(isset($_GET['edited'])): ?><div class="msg-success">✅ Booking updated successfully!</div><?php endif; ?>
    <?php
    $filter=$_GET['filter']??'all';
    $filter_sql=$filter!='all'?"WHERE b.status='".mysqli_real_escape_string($db,$filter)."'":'';
    $bq=mysqli_query($db,"SELECT b.*,u.username,u.email,u.cellphone,e.name AS equip_name,e.category FROM bookings b LEFT JOIN user_info u ON b.farmer_id=u.id LEFT JOIN equipment e ON b.equipment_id=e.equipment_id $filter_sql ORDER BY b.created_at DESC");
    ?>
    <div class="card">
        <div class="card-header">
            <div><h3>Booking Requests</h3></div>
            <div class="btn-gap">
                <a href="?section=bookings&filter=all"       class="btn btn-outline btn-sm">All</a>
                <a href="?section=bookings&filter=pending"   class="btn btn-orange btn-sm">Pending</a>
                <a href="?section=bookings&filter=approved"  class="btn btn-green btn-sm">Approved</a>
                <a href="?section=bookings&filter=completed" class="btn btn-blue btn-sm">Completed</a>
            </div>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>User & Contact</th><th>Equipment</th><th>Dates</th><th>Amount</th><th>Payment</th><th>Status</th><th>Remarks</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(mysqli_num_rows($bq)>0): while($b=mysqli_fetch_assoc($bq)): ?>
            <tr>
                <td>#<?= $b['booking_id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($b['username']??'N/A') ?></strong><br>
                    <span style="color:#aaa;font-size:.64rem"><?= htmlspecialchars($b['email']??'') ?></span><br>
                    <?php if(!empty($b['cellphone'])): ?><span class="phone-chip">📱 <?= htmlspecialchars($b['cellphone']) ?></span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($b['equip_name']??'N/A') ?><br><span style="color:#aaa;font-size:.64rem"><?= $b['category'] ?></span></td>
                <td><?= date('M j',strtotime($b['start_date'])) ?> – <?= date('M j, Y',strtotime($b['end_date'])) ?></td>
                <td>₱<?= number_format($b['total_amount'],2) ?></td>
                <td><span class="badge badge-<?= $b['payment_method'] ?>"><?= ucfirst($b['payment_method']) ?></span></td>
                <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                <td style="max-width:140px;font-size:.72rem;color:#555"><?= !empty($b['remarks'])?htmlspecialchars($b['remarks']):'<span style="color:#ccc">—</span>' ?></td>
                <td><div class="btn-gap">
                    <?php if($b['status']=='pending'): ?>
                        <a href="?section=bookings&approve_booking=<?= $b['booking_id'] ?>" class="btn btn-green btn-sm" onclick="return confirm('Approve?')">✓Approve</a>
                        <a href="?section=bookings&reject_booking=<?= $b['booking_id'] ?>"  class="btn btn-red btn-sm"   onclick="return confirm('Reject?')">✗REJEXT</a>
                    <?php elseif($b['status']=='approved'): ?>
                        <a href="?section=bookings&complete_booking=<?= $b['booking_id'] ?>" class="btn btn-blue btn-sm" onclick="return confirm('Mark complete?')">✔ Done</a>
                    <?php else: ?><span style="color:#ccc;font-size:.7rem">—</span><?php endif; ?>
                    <button class="btn btn-orange btn-sm" onclick="openEditBooking(<?= htmlspecialchars(json_encode(['id'=>$b['booking_id'],'start'=>$b['start_date'],'end'=>$b['end_date'],'remarks'=>$b['remarks']??'','status'=>$b['status']]),ENT_QUOTES) ?>)">✏️ Edit</button>
                </div></td>
            </tr>
            <?php endwhile; else: ?><tr><td colspan="9"><div class="empty"><span class="empty-icon">📅</span>No bookings found.</div></td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

<!-- ══════ PAYMENTS ══════ -->
<?php elseif($section=='payments'): ?>
    <div class="page-header"><div><h2>💰 Payment Records</h2><p>Record and verify cash or palay payments. Auto-records are created when a booking is marked complete.</p></div></div>
    <?php $pending_pay_rows=mysqli_num_rows($pending_pay_q); ?>
    <?php if($pending_pay_rows>0): ?>
    <div class="card" style="border-left:4px solid var(--k3)">
        <div class="card-header"><div><h3>⏳ Awaiting Payment</h3><p><?= $pending_pay_rows ?> approved booking(s) pending payment</p></div></div>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>User & Contact</th><th>Equipment</th><th>Amount Due</th><th>Method</th><th>Action</th></tr></thead>
            <tbody>
            <?php while($pp=mysqli_fetch_assoc($pending_pay_q)): ?>
            <tr>
                <td>#<?= $pp['booking_id'] ?></td>
                <td>
                    <?= htmlspecialchars($pp['username']) ?><br>
                    <?php if(!empty($pp['cellphone'])): ?><span class="phone-chip">📱 <?= htmlspecialchars($pp['cellphone']) ?></span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($pp['equip_name']) ?></td>
                <td>₱<?= number_format($pp['total_amount'],2) ?></td>
                <td><span class="badge badge-<?= $pp['payment_method'] ?>"><?= ucfirst($pp['payment_method']) ?></span></td>
                <td><button class="btn btn-green btn-sm" onclick="openPayModal(<?= $pp['booking_id'] ?>,<?= $pp['farmer_id'] ?>,'<?= $pp['payment_method'] ?>',<?= $pp['total_amount'] ?>)">💳 Record</button></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header">
            <div><h3>All Payment Records</h3></div>
            <div class="btn-gap">
                <div class="pill">💵 Cash: ₱<?= number_format($total_revenue,0) ?></div>
                <div class="pill orange">🌾 Palay: <?= number_format($palay_total,1) ?> sack</div>
            </div>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Booking</th><th>User</th><th>Equipment</th><th>Method</th><th>Cash Paid</th><th>Palay (sack)</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php if(mysqli_num_rows($payments_q)>0): while($p=mysqli_fetch_assoc($payments_q)): ?>
            <tr>
                <td><?= $p['payment_id'] ?></td><td>#<?= $p['booking_id'] ?></td>
                <td><?= htmlspecialchars($p['username']??'N/A') ?></td>
                <td><?= htmlspecialchars($p['equip_name']??'N/A') ?></td>
                <td><span class="badge badge-<?= $p['payment_method'] ?>"><?= ucfirst($p['payment_method']) ?></span></td>
                <td>₱<?= number_format($p['amount_cash'],2) ?></td>
                <td><?= number_format($p['kg_palay'],2) ?> sack</td>
                <td><span class="badge badge-<?= $p['status'] ?>" <?= $p['status']=='auto-recorded'?'style="background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;"':'' ?>><?= $p['status']=='auto-recorded'?'🤖 Auto-Recorded':ucfirst($p['status']) ?></span></td>
                <td><?= date('M j, Y',strtotime($p['paid_at'])) ?></td>
            </tr>
            <?php endwhile; else: ?><tr><td colspan="9"><div class="empty"><span class="empty-icon">💰</span>No payments yet.</div></td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

<!-- ══════ USERS ══════ -->
<?php elseif($section=='users'): ?>
    <div class="page-header"><div><h2>🧑‍🌾 User Accounts</h2><p>Registered farmers from San Jose, Occidental Mindoro.</p></div></div>
    <?php if(isset($_GET['suspended'])): ?><div class="msg-success">✅ User account suspended.</div><?php endif; ?>
    <?php if(isset($_GET['unsuspended'])): ?><div class="msg-success">✅ User account reactivated.</div><?php endif; ?>
    <div class="card">
        <div class="card-header"><div><h3>All Registered Users</h3><p><?= mysqli_num_rows($users_q) ?> accounts</p></div></div>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Cellphone</th><th>Barangay</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(mysqli_num_rows($users_q)>0): while($u=mysqli_fetch_assoc($users_q)): 
                $uStatus = $u['status'] ?? 'active';
            ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><strong><?= htmlspecialchars($u['username']) ?></strong><?php if($uStatus==='suspended'): ?> <span style="font-size:.6rem;color:#ef4444;font-weight:700;">⛔ Suspended</span><?php endif; ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?php if(!empty($u['cellphone'])): ?><span class="phone-chip">📱 <?= htmlspecialchars($u['cellphone']) ?></span><?php else: ?><span style="color:#ccc">—</span><?php endif; ?></td>
                <td><?= htmlspecialchars($u['address']??'—') ?></td>
                <td><?php if($uStatus==='suspended'): ?>
                    <span class="badge badge-rejected">⛔ Suspended</span>
                <?php else: ?>
                    <span class="badge badge-available">✓ Active</span>
                <?php endif; ?></td>
                <td><?= date('M j, Y',strtotime($u['created_at'])) ?></td>
                <td><div class="btn-gap">
                    <?php if($uStatus==='suspended'): ?>
                        <a href="?section=users&unsuspend_user=<?= $u['id'] ?>" class="btn btn-green btn-sm" onclick="return confirm('Reactivate this user?')">✓ Restore</a>
                    <?php else: ?>
                        <a href="?section=users&suspend_user=<?= $u['id'] ?>" class="btn btn-orange btn-sm" onclick="return confirm('Suspend this user? They will not be able to log in.')">⛔ Suspend</a>
                    <?php endif; ?>
                    <a href="?section=users&delete_user=<?= $u['id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Permanently delete user?')">🗑Delete</a>
                </div></td>
            </tr>
            <?php endwhile; else: ?><tr><td colspan="7"><div class="empty"><span class="empty-icon">🧑‍🌾</span>No users yet.</div></td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

<!-- ══════ REPORTS ══════ -->
<?php elseif($section=='reports'): ?>
    <div class="page-header"><div><h2>📊 Reports & Analytics</h2><p>Equipment usage, bookings, and payment summaries.</p></div></div>
    <?php
    $equip_usage=mysqli_query($db,"SELECT e.name,e.category,COUNT(b.booking_id) AS total_bookings,SUM(CASE WHEN b.status='completed' THEN 1 ELSE 0 END) AS completed,COALESCE(SUM(b.total_amount),0) AS total_earned FROM equipment e LEFT JOIN bookings b ON e.equipment_id=b.equipment_id GROUP BY e.equipment_id ORDER BY total_bookings DESC");
    $monthly=mysqli_query($db,"SELECT DATE_FORMAT(created_at,'%b %Y') AS month,COUNT(*) AS cnt FROM bookings GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY created_at DESC LIMIT 6");
    $pay_breakdown=mysqli_query($db,"SELECT payment_method,COUNT(*) AS cnt,SUM(amount_cash) AS total_cash,SUM(kg_palay) AS total_palay FROM payments WHERE status='verified' GROUP BY payment_method");
    $total_all=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM bookings"))[0]??0;
    $completed=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM bookings WHERE status='completed'"))[0]??0;
    $rejected=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM bookings WHERE status='rejected'"))[0]??0;
    $avail_equip=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM equipment WHERE status='available'"))[0]??0;
    ?>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon green">📋</div><div><div class="stat-val"><?= $total_all ?></div><div class="stat-label">Total Bookings</div></div></div>
        <div class="stat-card orange"><div class="stat-icon orange">✅</div><div><div class="stat-val"><?= $completed ?></div><div class="stat-label">Completed</div></div></div>
        <div class="stat-card gold"><div class="stat-icon gold">❌</div><div><div class="stat-val"><?= $rejected ?></div><div class="stat-label">Rejected</div></div></div>
        <div class="stat-card dark"><div class="stat-icon dark">🚜</div><div><div class="stat-val"><?= $avail_equip ?></div><div class="stat-label">Available Equipment</div></div></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div class="card">
            <div class="card-header"><div><h3>Equipment Usage</h3></div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Equipment</th><th>Category</th><th>Bookings</th><th>Completed</th><th>Revenue</th></tr></thead>
                <tbody><?php while($eu=mysqli_fetch_assoc($equip_usage)): ?><tr><td><?= htmlspecialchars($eu['name']) ?></td><td><?= $eu['category'] ?></td><td><?= $eu['total_bookings'] ?></td><td><?= $eu['completed'] ?></td><td>₱<?= number_format($eu['total_earned'],0) ?></td></tr><?php endwhile; ?></tbody>
            </table></div>
        </div>
        <div class="card">
            <div class="card-header"><div><h3>Payment Breakdown</h3></div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Method</th><th>Transactions</th><th>Cash</th><th>Palay (sack)</th></tr></thead>
                <tbody><?php while($pb=mysqli_fetch_assoc($pay_breakdown)): ?><tr><td><span class="badge badge-<?= $pb['payment_method'] ?>"><?= ucfirst($pb['payment_method']) ?></span></td><td><?= $pb['cnt'] ?></td><td>₱<?= number_format($pb['total_cash'],2) ?></td><td><?= number_format($pb['total_palay'],2) ?> sack</td></tr><?php endwhile; ?></tbody>
            </table></div>
        </div>
        <div class="card" style="grid-column:span 2">
            <div class="card-header"><div><h3>Monthly Bookings (Last 6 Months)</h3></div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Month</th><th>Bookings</th><th>Visual</th></tr></thead>
                <tbody><?php while($mb=mysqli_fetch_assoc($monthly)): $bar_pct=min(100,intval($mb['cnt'])*10); ?><tr><td><?= $mb['month'] ?></td><td><?= $mb['cnt'] ?></td><td><div style="background:#edf2ee;border-radius:6px;height:14px;width:200px;"><div style="background:var(--g3);height:100%;border-radius:6px;width:<?= $bar_pct ?>%"></div></div></td></tr><?php endwhile; ?></tbody>
            </table></div>
        </div>
    </div>

<!-- ══════ NOTIFICATIONS ══════ -->
<?php elseif($section=='notifications'):
    $nf=$_GET['nf']??'all';
    if($nf==='unread') $nf_where="WHERE n.is_read=0";
    elseif(in_array($nf,['info','warning','emergency','success'])) $nf_where="WHERE n.type='$nf'";
    else $nf_where="";
    $notifs_q=mysqli_query($db,"SELECT n.id AS notif_id,n.user_id,n.title,n.message,n.type,n.is_read,n.expires_at,n.created_at,u.username FROM notifications n LEFT JOIN user_info u ON n.user_id=u.id $nf_where ORDER BY n.created_at DESC");
    $nt_total=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM notifications"))[0]??0;
    $nt_unread=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM notifications WHERE is_read=0"))[0]??0;
    $nt_info=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM notifications WHERE type='info'"))[0]??0;
    $nt_warn=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM notifications WHERE type='warning'"))[0]??0;
    $nt_emerg=mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM notifications WHERE type='emergency'"))[0]??0;
    $users_notif_q=mysqli_query($db,"SELECT id,username FROM user_info WHERE user_type='user' ORDER BY username");
?>
    <div class="page-header" style="margin-bottom:18px;">
        <div><h2>🔔 Notifications</h2><p>Compose and send alerts to farmers.</p></div>
        <div class="btn-gap">
            <?php if($nt_unread>0): ?><a href="?section=notifications&mark_all_read=1" class="btn btn-outline btn-sm">✓ Mark All Read</a><?php endif; ?>
            <?php if($nt_total>0): ?><a href="?section=notifications&delete_all_notifs=1" class="btn btn-red btn-sm" onclick="return confirm('Delete ALL?')">🗑 Clear All</a><?php endif; ?>
        </div>
    </div>
    <?php if(isset($_GET['sent'])): ?><div class="msg-success">✅ Notification sent!</div><?php endif; ?>

    <div class="card" style="margin-bottom:22px;">
        <div class="card-header"><div><h3>📢 Compose Notification</h3></div></div>
        <div style="padding:24px;">
        <form method="POST" action="?section=notifications">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group full"><label>Notification Title *</label>
                    <input type="text" name="notif_title" id="notifTitleField" required maxlength="150"
                           placeholder="e.g. Equipment available this Saturday">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:7px;">
                        <span style="font-size:.62rem;color:#888;font-weight:600;align-self:center;">Quick Titles:</span>
                        <button type="button" class="notif-title-preset" onclick="setNotifTitle('📢 Equipment Available This Week')">📢 Available</button>
                        <button type="button" class="notif-title-preset" onclick="setNotifTitle('🚨 Emergency: Service Suspended')">🚨 Emergency</button>
                        <button type="button" class="notif-title-preset" onclick="setNotifTitle('✅ Booking Approved')">✅ Approved</button>
                        <button type="button" class="notif-title-preset" onclick="setNotifTitle('⚠️ Scheduled Maintenance Notice')">⚠️ Maintenance</button>
                        <button type="button" class="notif-title-preset" onclick="setNotifTitle('ℹ️ General Announcement')">ℹ️ Announcement</button>
                        <button type="button" class="notif-title-preset" onclick="setNotifTitle('💰 Payment Reminder')">💰 Payment</button>
                    </div>
                </div>
                <div class="form-group full"><label>Message *</label><textarea name="notif_message" rows="3" required maxlength="600" placeholder="Write your message here…" style="padding:10px 13px;border:2px solid #e0e0e0;border-radius:9px;font-family:'Poppins',sans-serif;font-size:.8rem;background:#fafafa;outline:none;width:100%;"></textarea></div>
                <div class="form-group">
                    <label>Type</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;" id="notifTypeBtns">
                        <button type="button" class="notif-type-btn active-type" data-type="info"      data-prefix="ℹ️" style="padding:7px 14px;border-radius:20px;font-size:.72rem;font-weight:700;border:2px solid #86efac;background:#e8f5ee;color:#1a5f3f;cursor:pointer;font-family:'Poppins',sans-serif;"      onclick="setNTypeBtn(this,'info')">ℹ️ Info</button>
                        <button type="button" class="notif-type-btn"              data-type="success"   data-prefix="✅" style="padding:7px 14px;border-radius:20px;font-size:.72rem;font-weight:700;border:2px solid #4ade80;background:#dcfce7;color:#15803d;cursor:pointer;font-family:'Poppins',sans-serif;"  onclick="setNTypeBtn(this,'success')">✅ Success</button>
                        <button type="button" class="notif-type-btn"              data-type="warning"   data-prefix="⚠️" style="padding:7px 14px;border-radius:20px;font-size:.72rem;font-weight:700;border:2px solid #fde68a;background:#fff8e1;color:#92400e;cursor:pointer;font-family:'Poppins',sans-serif;" onclick="setNTypeBtn(this,'warning')">⚠️ Warning</button>
                        <button type="button" class="notif-type-btn"              data-type="emergency" data-prefix="🚨" style="padding:7px 14px;border-radius:20px;font-size:.72rem;font-weight:700;border:2px solid #fca5a5;background:#fee2e2;color:#991b1b;cursor:pointer;font-family:'Poppins',sans-serif;" onclick="setNTypeBtn(this,'emergency')">🚨 Emergency</button>
                    </div>
                    <input type="hidden" name="notif_type" id="notifTypeVal" value="info">
                </div>
                <div class="form-group">
                    <label>Send To</label>
                    <select name="notif_target" id="notifTarget" onchange="toggleUserSel()" style="padding:10px 13px;border:2px solid #e0e0e0;border-radius:9px;font-family:'Poppins',sans-serif;font-size:.8rem;background:#fafafa;outline:none;">
                        <option value="all">📢 All Farmers</option>
                        <option value="specific">👤 Specific Farmer</option>
                    </select>
                </div>
                <div class="form-group" id="specificUserWrap" style="display:none;">
                    <label>Select Farmer</label>
                    <select name="target_user_id" style="padding:10px 13px;border:2px solid #e0e0e0;border-radius:9px;font-family:'Poppins',sans-serif;font-size:.8rem;background:#fafafa;outline:none;">
                        <option value="">-- Choose Farmer --</option>
                        <?php while($uf=mysqli_fetch_assoc($users_notif_q)): ?><option value="<?= $uf['id'] ?>"><?= htmlspecialchars($uf['username']) ?></option><?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group full" style="margin-top:6px;"><button type="submit" name="send_notif" class="btn btn-green" style="width:fit-content;padding:10px 28px;">📢 Send Notification</button></div>
            </div>
        </form>
        </div>
    </div>

    <!-- Notification list -->
    <?php $type_icons=['info'=>'ℹ️','success'=>'✅','warning'=>'⚠️','emergency'=>'🚨'];
    $notif_count=mysqli_num_rows($notifs_q); ?>
    <?php if($notif_count===0): ?>
        <div style="text-align:center;padding:60px;color:#aaa;"><div style="font-size:2.8rem;margin-bottom:12px">🔔</div><p>No notifications sent yet.</p></div>
    <?php else: while($n=mysqli_fetch_assoc($notifs_q)):
        $ntype=$n['type']??'info'; $n_icon=$type_icons[$ntype]??'🔔';
        $type_colors=['info'=>'#2d8a5e','success'=>'#16a34a','warning'=>'#f59e0b','emergency'=>'#ef4444'];
        $bc=$type_colors[$ntype]??'#2d8a5e';
    ?>
        <div style="background:#fff;border-radius:12px;padding:16px 20px;margin-bottom:10px;display:flex;align-items:flex-start;gap:14px;border-left:4px solid <?= $bc ?>;border:2px solid #eef4ef;border-left:4px solid <?= $bc ?>;box-shadow:var(--shadow);">
            <div style="font-size:1.5rem;flex-shrink:0;"><?= $n_icon ?></div>
            <div style="flex:1;">
                <div style="font-size:.88rem;font-weight:700;color:#1e2e1e;margin-bottom:4px;"><?= htmlspecialchars($n['title']) ?></div>
                <div style="font-size:.76rem;color:#555;line-height:1.6;"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                <div style="font-size:.65rem;color:#bbb;margin-top:6px;">👤 <?= htmlspecialchars($n['username']??'Unknown') ?> &bull; <?= date('M j, Y g:i A',strtotime($n['created_at'])) ?></div>
            </div>
            <a href="?section=notifications&delete_notif=<?= $n['notif_id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete?')">🗑</a>
        </div>
    <?php endwhile; endif; ?>

<?php endif; ?>
</div><!-- /content -->
</div><!-- /main-wrapper -->

<!-- MODAL: ADD EQUIPMENT -->
<div class="modal-overlay" id="addEquipModal">
    <div class="modal">
        <h3>➕ Add New Equipment</h3>
        <form method="POST" action="?section=equipment" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group"><label>Name *</label><input type="text" name="equip_name" required placeholder="e.g. 4WD Tractor"></div>
                <div class="form-group"><label>Category *</label><select name="equip_category" required><option value="">-- Select --</option><option>Land Preparation</option><option>Harvesting</option><option>Soil Preparation</option><option>Post Harvest</option><option>Other</option></select></div>
                <div class="form-group"><label>Cash Rate (₱) *</label><input type="number" name="rate_cash" step="0.01" required placeholder="1500.00"></div>
                <div class="form-group"><label>Palay Rate (sack/ha) — Sack Rate</label><input type="number" name="rate_palay" step="0.01" placeholder="8.00"></div>
                <div class="form-group"><label>Rate Unit</label><select name="rate_unit"><option value="per hectare" selected>Per Hectare</option><option value="per day">Per Day</option><option value="per hour">Per Hour</option></select></div>
                <div class="form-group"><label>Status</label><select name="equip_status"><option value="available">Available</option><option value="maintenance">Maintenance</option><option value="unavailable">Unavailable</option></select></div>
                <div class="form-group full"><label>Description</label><textarea name="equip_desc" rows="3" placeholder="Brief description…"></textarea></div>
                <div class="form-group full"><label>Image (optional)</label><input type="file" name="equip_image" accept="image/*"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addEquipModal')">Cancel</button>
                <button type="submit" name="add_equipment" class="btn btn-green">Add Equipment</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT EQUIPMENT -->
<div class="modal-overlay" id="editEquipModal">
    <div class="modal">
        <h3>✏️ Edit Equipment</h3>
        <form method="POST" action="?section=equipment">
            <input type="hidden" name="equip_id" id="edit_equip_id">
            <div class="form-grid">
                <div class="form-group"><label>Name *</label><input type="text" name="equip_name" id="edit_equip_name" required></div>
                <div class="form-group"><label>Category</label><select name="equip_category" id="edit_equip_category"><option>Land Preparation</option><option>Harvesting</option><option>Soil Preparation</option><option>Post Harvest</option><option>Other</option></select></div>
                <div class="form-group"><label>Cash Rate (₱)</label><input type="number" name="rate_cash" id="edit_rate_cash" step="0.01"></div>
                <div class="form-group"><label>Palay Rate (sack)</label><input type="number" name="rate_palay" id="edit_rate_palay" step="0.01"></div>
                <div class="form-group"><label>Rate Unit</label><select name="rate_unit" id="edit_rate_unit"><option value="per hectare">Per Hectare</option><option value="per day">Per Day</option><option value="per hour">Per Hour</option></select></div>
                <div class="form-group"><label>Status</label><select name="equip_status" id="edit_equip_status"><option value="available">Available</option><option value="maintenance">Maintenance</option><option value="unavailable">Unavailable</option></select></div>
                <div class="form-group full"><label>Description</label><textarea name="equip_desc" id="edit_equip_desc" rows="3"></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('editEquipModal')">Cancel</button>
                <button type="submit" name="edit_equipment" class="btn btn-green">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT BOOKING (Emergency Reschedule) -->
<div class="modal-overlay" id="editBookingModal">
    <div class="modal">
        <h3>✏️ Edit Booking</h3>
        <p style="font-size:.77rem;color:#888;margin-bottom:14px;">Use this to reschedule or update booking details in case of emergency.</p>
        <form method="POST" action="?section=bookings">
            <input type="hidden" name="booking_id" id="eb_booking_id">
            <div class="form-grid">
                <div class="form-group"><label>Start Date *</label><input type="date" name="new_start_date" id="eb_start_date" required></div>
                <div class="form-group"><label>End Date *</label><input type="date" name="new_end_date" id="eb_end_date" required></div>
                <div class="form-group"><label>Status</label>
                    <select name="new_status" id="eb_status">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-group full"><label>Remarks / Emergency Notes</label><textarea name="new_remarks" id="eb_remarks" rows="3" placeholder="e.g. Rescheduled due to weather emergency…" style="padding:10px 13px;border:2px solid #e0e0e0;border-radius:9px;font-family:'Poppins',sans-serif;font-size:.8rem;background:#fafafa;outline:none;width:100%;resize:vertical;"></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('editBookingModal')">Cancel</button>
                <button type="submit" name="edit_booking" class="btn btn-green">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: RECORD PAYMENT -->
<div class="modal-overlay" id="payModal">
    <div class="modal">
        <h3>💳 Record Payment</h3>
        <form method="POST" action="?section=payments">
            <input type="hidden" name="booking_id" id="pay_booking_id">
            <input type="hidden" name="farmer_id"  id="pay_farmer_id">
            <input type="hidden" id="pay_total_amount" value="0">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Payment Method</label>
                    <div style="display:flex;gap:10px;">
                        <div onclick="selectRecMethod('cash')" id="recCardCash" style="flex:1;cursor:pointer;border-radius:10px;border:2px solid var(--g3);background:#e8f5ee;padding:14px;transition:all .18s;">
                            <div style="font-size:1.2rem">💵</div><div style="font-size:.82rem;font-weight:700;color:var(--g1);margin-top:4px">Cash</div>
                            <div id="recCashAmountDisplay" style="display:none;margin-top:8px;padding:5px 8px;background:var(--g2);color:#fff;border-radius:7px;font-size:.76rem;font-weight:700;"></div>
                        </div>
                        <div onclick="selectRecMethod('palay')" id="recCardPalay" style="flex:1;cursor:pointer;border-radius:10px;border:2px solid #e0e0e0;background:#fafafa;padding:14px;transition:all .18s;">
                            <div style="font-size:1.2rem">🌾</div><div style="font-size:.82rem;font-weight:700;color:var(--g1);margin-top:4px">Palay</div>
                            <div id="recPalayAmountDisplay" style="display:none;margin-top:8px;padding:5px 8px;background:#b45309;color:#fff;border-radius:7px;font-size:.76rem;font-weight:700;"></div>
                        </div>
                    </div>
                    <select name="pay_method" id="pay_method" onchange="togglePayFields()" style="display:none"><option value="cash">cash</option><option value="palay">palay</option></select>
                </div>
                <div class="form-group" id="cashField"><label>Amount (₱)</label><input type="number" name="amount_cash" id="pay_amount" step="0.01" placeholder="0.00" style="font-weight:700;color:var(--g2);font-size:.9rem;"></div>
                <div class="form-group" id="palayField" style="display:none"><label>Palay (sack)</label><input type="number" name="kg_palay" id="pay_palay_sako" step="0.01" placeholder="0.00" style="font-weight:700;color:#b45309;font-size:.9rem;"></div>
            </div>
            <div id="paySummaryBox" style="margin-top:14px;padding:12px 16px;background:#e8f5ee;border-radius:10px;border-left:4px solid var(--g3);font-size:.78rem;color:var(--g2);font-weight:700;display:none;"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('payModal')">Cancel</button>
                <button type="submit" name="record_payment" class="btn btn-green">💾 Save Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditBooking(data) {
    document.getElementById('eb_booking_id').value = data.id;
    document.getElementById('eb_start_date').value  = data.start;
    document.getElementById('eb_end_date').value    = data.end;
    document.getElementById('eb_remarks').value     = data.remarks;
    document.getElementById('eb_status').value      = data.status;
    openModal('editBookingModal');
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => { o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }); });
function openEditEquip(data) {
    document.getElementById('edit_equip_id').value       = data.equipment_id;
    document.getElementById('edit_equip_name').value     = data.name;
    document.getElementById('edit_equip_category').value = data.category;
    document.getElementById('edit_rate_cash').value      = data.rate_cash;
    document.getElementById('edit_rate_palay').value     = data.rate_palay;
    document.getElementById('edit_rate_unit').value      = data.rate_unit;
    document.getElementById('edit_equip_status').value   = data.status;
    document.getElementById('edit_equip_desc').value     = data.description;
    openModal('editEquipModal');
}
function openPayModal(bookingId,farmerId,method,amount) {
    document.getElementById('pay_booking_id').value  = bookingId;
    document.getElementById('pay_farmer_id').value   = farmerId;
    document.getElementById('pay_total_amount').value = amount;
    document.getElementById('pay_method').value = method;
    selectRecMethod(method);
    autoFillPayAmounts(amount,method);
    openModal('payModal');
}
function selectRecMethod(method) {
    document.getElementById('pay_method').value = method;
    const cashCard=document.getElementById('recCardCash');const palayCard=document.getElementById('recCardPalay');
    if(method==='cash'){cashCard.style.border='2px solid var(--g3)';cashCard.style.background='#e8f5ee';palayCard.style.border='2px solid #e0e0e0';palayCard.style.background='#fafafa';}
    else{palayCard.style.border='2px solid #f59e0b';palayCard.style.background='#fff8e1';cashCard.style.border='2px solid #e0e0e0';cashCard.style.background='#fafafa';}
    togglePayFields();
}
function togglePayFields(){
    const m=document.getElementById('pay_method').value;
    document.getElementById('cashField').style.display =m==='cash'?'':'none';
    document.getElementById('palayField').style.display=m==='palay'?'':'none';
    updatePaySummary();
}
function autoFillPayAmounts(amt,method){
    const a=parseFloat(amt)||0;
    document.getElementById('pay_amount').value=a.toFixed(2);
    document.getElementById('pay_palay_sako').value=a.toFixed(2);
    document.getElementById('recCashAmountDisplay').style.display='block';
    document.getElementById('recPalayAmountDisplay').style.display='block';
    document.getElementById('recCashAmountDisplay').innerHTML=`₱${a.toLocaleString('en-PH',{minimumFractionDigits:2})}`;
    document.getElementById('recPalayAmountDisplay').innerHTML=`${a.toFixed(2)} sack`;
    updatePaySummary();
}
function updatePaySummary(){
    const m=document.getElementById('pay_method').value;
    const box=document.getElementById('paySummaryBox');
    const cash=parseFloat(document.getElementById('pay_amount').value)||0;
    const palay=parseFloat(document.getElementById('pay_palay_sako').value)||0;
    box.style.display='block';
    if(m==='cash'){box.style.borderLeftColor='var(--g3)';box.style.background='#e8f5ee';box.style.color='var(--g2)';box.innerHTML=`✅ Recording: <strong>₱${cash.toLocaleString('en-PH',{minimumFractionDigits:2})}</strong> cash payment`;}
    else{box.style.borderLeftColor='#f59e0b';box.style.background='#fff8e1';box.style.color='#92400e';box.innerHTML=`✅ Recording: <strong>${palay.toFixed(2)} sack</strong> of palay`;}
}
document.addEventListener('DOMContentLoaded',function(){
    const pa=document.getElementById('pay_amount');const pp=document.getElementById('pay_palay_sako');
    if(pa)pa.addEventListener('input',updatePaySummary);if(pp)pp.addEventListener('input',updatePaySummary);
});
function setNTypeBtn(btn, val) {
    document.getElementById('notifTypeVal').value = val;
    // Remove active-type from all
    document.querySelectorAll('.notif-type-btn').forEach(b => b.classList.remove('active-type'));
    btn.classList.add('active-type');
    // Auto-prefix title if field is empty or was a previous auto-prefix
    const titleField = document.getElementById('notifTitleField');
    const prefixes   = ['ℹ️ ','✅ ','⚠️ ','🚨 '];
    const prefix     = btn.dataset.prefix + ' ';
    const currentVal = titleField.value;
    const hadPrefix  = prefixes.some(p => currentVal.startsWith(p));
    if (!currentVal || hadPrefix) {
        titleField.value = prefix;
        titleField.focus();
        titleField.setSelectionRange(prefix.length, prefix.length);
    }
}
function setNType(btn, val) { setNTypeBtn(btn, val); } // backwards compat
function setNotifTitle(title) {
    const f = document.getElementById('notifTitleField');
    if (f) { f.value = title; f.focus(); }
}
function toggleUserSel(){const v=document.getElementById('notifTarget').value;document.getElementById('specificUserWrap').style.display=v==='specific'?'':'none';}

// ── Mobile Nav ──────────────────────────────────────────────────────────────
function toggleMobileNav(){
    const btn     = document.getElementById('hamburgerBtn');
    const drawer  = document.getElementById('mobileNavDrawer');
    const overlay = document.getElementById('mobileNavOverlay');
    const isOpen  = drawer.classList.contains('open');
    if(isOpen){ closeMobileNav(); } else {
        btn.classList.add('open');
        drawer.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow='hidden';
    }
}
function closeMobileNav(){
    document.getElementById('hamburgerBtn')?.classList.remove('open');
    document.getElementById('mobileNavDrawer')?.classList.remove('open');
    document.getElementById('mobileNavOverlay')?.classList.remove('open');
    document.body.style.overflow='';
}
// Sync mobile theme icon
document.addEventListener('DOMContentLoaded',function(){
    const saved = localStorage.getItem('pildemco_theme')||'light';
    const mIcon = document.getElementById('mobileThemeIcon');
    if(mIcon) mIcon.textContent = saved==='dark'?'☀️':'🌙';
});
// Override toggleTheme to also update mobile icon
(function(){
    const origToggle = window.toggleTheme;
    window.toggleTheme = function(){
        if(origToggle) origToggle();
        const cur  = document.documentElement.getAttribute('data-theme')||'light';
        const mIcon= document.getElementById('mobileThemeIcon');
        if(mIcon) mIcon.textContent = cur==='dark'?'🌙':'☀️';
    };
})();
// Close drawer on ESC
document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeMobileNav(); });
</script>

</body>
</html>