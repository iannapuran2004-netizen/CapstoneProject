<?php
session_start();
include "db.php";

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['username']) || $_SESSION['user_type'] != "user") {
    header("Location: LOGIN_PAGE.php");
    exit();
}

// ── Terms & Conditions guard ──────────────────────────────────────────────────
// Force users to agree to T&C before accessing the dashboard each login session
if (empty($_SESSION['terms_agreed'])) {
    header("Location: TERMS_AND_CONDITIONS.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$section  = $_GET['section'] ?? 'home';

// ── FIX: get farmer_id from session (set by LOGIN_PAGE at login time) ─────────
// user_info.id is the farmer_id used in bookings/payments tables
$farmer_id      = intval($_SESSION['user_id'] ?? 0);
$farmer_name    = $username;
$farmer_address = htmlspecialchars($_SESSION['address'] ?? 'San Jose, Occ. Mindoro');
$farmer_email   = htmlspecialchars($_SESSION['email']   ?? '');

// ── Chat widget vars ──────────────────────────────────────────────────────────
$my_id   = $farmer_id;
$my_type = 'user';

// ════════════════════════════════════════════════════════════════════════════════
//  POST: SUBMIT BOOKING
// ════════════════════════════════════════════════════════════════════════════════
$booking_msg   = '';
$booking_error = '';

if (isset($_POST['submit_booking'])) {
    $equip_id    = intval($_POST['equipment_id']);
    $use_date    = $_POST['use_date']         ?? '';
    $time_start  = $_POST['use_time_start']   ?? '';
    $time_end    = $_POST['use_time_end']      ?? '';
    $pay_method  = $_POST['payment_method'];
    $remarks     = trim($_POST['remarks']      ?? '');
    $book_brgy   = trim($_POST['book_barangay']?? $farmer_address);
    $book_purok  = trim($_POST['book_purok']   ?? '');
    $book_lat    = trim($_POST['book_lat']     ?? '');
    $book_lng    = trim($_POST['book_lng']     ?? '');
    $crop_type   = trim($_POST['crop_type']    ?? '');
    $land_area   = floatval($_POST['land_area'] ?? 0);

    if (empty($use_date)) {
        $booking_error = 'Please select the date of use.';
    } elseif (strtotime($use_date) < strtotime(date('Y-m-d'))) {
        $booking_error = 'Date of use cannot be in the past.';
    } elseif ($equip_id == 0) {
        $booking_error = 'Please select an equipment.';
    } else {
        // Check date conflict
        $chk = mysqli_prepare($db,
            "SELECT COUNT(*) FROM bookings
             WHERE equipment_id = ?
               AND status IN ('pending','approved')
               AND start_date = ?");
        mysqli_stmt_bind_param($chk, "is", $equip_id, $use_date);
        mysqli_stmt_execute($chk);
        $conflict = mysqli_stmt_get_result($chk)->fetch_row()[0] ?? 0;
        mysqli_stmt_close($chk);

        if ($conflict > 0) {
            $booking_error = 'That equipment is already booked on that date. Please choose another date.';
        } else {
            // Get equipment rate
            $eq = mysqli_prepare($db,
                "SELECT rate_cash, rate_palay, rate_unit FROM equipment WHERE equipment_id=?");
            mysqli_stmt_bind_param($eq, "i", $equip_id);
            mysqli_stmt_execute($eq);
            $eq_row = mysqli_stmt_get_result($eq)->fetch_assoc();
            mysqli_stmt_close($eq);

            $rate = $pay_method == 'cash'
                        ? floatval($eq_row['rate_cash']  ?? 0)
                        : floatval($eq_row['rate_palay'] ?? 0);
            $unit = $eq_row['rate_unit'] ?? 'per day';

            // Crop type multiplier for dynamic pricing
            $crop_multipliers = ['palay'=>1.0,'mais'=>1.05,'gulay'=>1.1,'niyog'=>0.95,'prutas'=>1.0,'iba'=>1.0];
            $crop_mult = $crop_multipliers[$crop_type] ?? 1.0;
            $area = $land_area > 0 ? $land_area : 1;

            // Calculate total based on rate unit + land area + crop type
            if ($unit === 'per hectare') {
                $total = $rate * $area * $crop_mult;
            } elseif ($unit === 'per hour' && $time_start && $time_end) {
                $hours = max(0.5, (strtotime($time_end) - strtotime($time_start)) / 3600);
                $total = $rate * $hours * $crop_mult;
            } else {
                $total = $rate * $crop_mult; // flat per day rate
            }

            $ins = mysqli_prepare($db,
                "INSERT INTO bookings
                    (farmer_id, equipment_id, start_date, end_date, total_hours, total_amount, payment_method, status, remarks)
                 VALUES (?, ?, ?, ?, 1, ?, ?, 'pending', ?)");
            mysqli_stmt_bind_param($ins, "iissdss",
                $farmer_id, $equip_id, $use_date, $use_date, $total, $pay_method, $remarks);

            if (mysqli_stmt_execute($ins)) {
                $bid = mysqli_insert_id($db);
                // Save time + location info (v2/v3 migration columns)
                $esc_date   = mysqli_real_escape_string($db, $use_date);
                $esc_ts     = $time_start ? "'".mysqli_real_escape_string($db,$time_start)."'" : "NULL";
                $esc_te     = $time_end   ? "'".mysqli_real_escape_string($db,$time_end)."'"   : "NULL";
                $esc_brgy   = mysqli_real_escape_string($db, $book_brgy);
                $esc_purok  = mysqli_real_escape_string($db, $book_purok);
                $esc_lat    = is_numeric($book_lat) ? floatval($book_lat) : 'NULL';
                $esc_lng    = is_numeric($book_lng) ? floatval($book_lng) : 'NULL';
                mysqli_query($db,
                    "UPDATE bookings SET
                        use_date        = '$esc_date',
                        use_time_start  = $esc_ts,
                        use_time_end    = $esc_te,
                        land_barangay   = '$esc_brgy',
                        land_purok      = '$esc_purok',
                        land_lat        = $esc_lat,
                        land_lng        = $esc_lng
                     WHERE booking_id   = $bid");
                // Append crop/area info to remarks
                if ($crop_type || $land_area > 0) {
                    $crop_note = "[Crop: " . ($crop_type ?: '—') . ", Area: " . number_format($land_area, 2) . " ha]";
                    $full_remarks = $remarks ? $remarks . ' ' . $crop_note : $crop_note;
                    $upd_rmk = mysqli_prepare($db, "UPDATE bookings SET remarks=? WHERE booking_id=?");
                    mysqli_stmt_bind_param($upd_rmk, "si", $full_remarks, $bid);
                    mysqli_stmt_execute($upd_rmk);
                    mysqli_stmt_close($upd_rmk);
                }
                $booking_msg = 'Booking submitted! Waiting for admin approval.';
                $section = 'bookings';
            } else {
                $booking_error = 'Failed to submit booking: ' . mysqli_stmt_error($ins);
            }
            mysqli_stmt_close($ins);
        }
    }
}

// ── CANCEL BOOKING ────────────────────────────────────────────────────────────
if (isset($_GET['cancel_booking'])) {
    $bid  = intval($_GET['cancel_booking']);
    $stmt = mysqli_prepare($db,
        "UPDATE bookings SET status='cancelled'
         WHERE booking_id=? AND farmer_id=? AND status='pending'");
    mysqli_stmt_bind_param($stmt, "ii", $bid, $farmer_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: USER_PAGE.php?section=bookings"); exit();
}

// ── Notifications: Mark all as read ─────────────────────────────────────────
if ($section === 'notifications' && isset($_GET['mark_all'])) {
    mysqli_query($db, "UPDATE notifications SET is_read=1 WHERE user_id=$my_id");
    header("Location: USER_PAGE.php?section=notifications"); exit();
}


// ════════════════════════════════════════════════════════════════════════════════
//  AUTO-CREATE TABLES IF MISSING
// ════════════════════════════════════════════════════════════════════════════════
mysqli_query($db, "CREATE TABLE IF NOT EXISTS payments (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    booking_id      INT NOT NULL,
    farmer_id       INT NOT NULL,
    amount_cash     DECIMAL(10,2) DEFAULT 0.00,
    kg_palay        DECIMAL(10,2) DEFAULT 0.00,
    payment_method  VARCHAR(20) DEFAULT 'cash',
    status          VARCHAR(20) DEFAULT 'verified',
    paid_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id)  REFERENCES user_info(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add extra booking columns if they don't exist (migration)
@mysqli_query($db, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS use_date DATE DEFAULT NULL");
@mysqli_query($db, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS use_time_start TIME DEFAULT NULL");
@mysqli_query($db, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS use_time_end TIME DEFAULT NULL");
@mysqli_query($db, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS land_barangay VARCHAR(200) DEFAULT NULL");
@mysqli_query($db, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS land_purok VARCHAR(200) DEFAULT NULL");
@mysqli_query($db, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS land_lat DECIMAL(10,7) DEFAULT NULL");
@mysqli_query($db, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS land_lng DECIMAL(10,7) DEFAULT NULL");

mysqli_query($db, "CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    title       VARCHAR(150) NOT NULL,
    message     TEXT NOT NULL,
    type        ENUM('info','success','warning','emergency') DEFAULT 'info',
    is_read     TINYINT(1) DEFAULT 0,
    expires_at  DATETIME DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_info(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ════════════════════════════════════════════════════════════════════════════════
//  DATA QUERIES — all use $farmer_id = user_info.id
// ════════════════════════════════════════════════════════════════════════════════
$my_total     = mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(*) FROM bookings WHERE farmer_id=$farmer_id"))[0] ?? 0;
$my_pending   = mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(*) FROM bookings WHERE farmer_id=$farmer_id AND status='pending'"))[0] ?? 0;
$my_approved  = mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(*) FROM bookings WHERE farmer_id=$farmer_id AND status='approved'"))[0] ?? 0;
$my_completed = mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(*) FROM bookings WHERE farmer_id=$farmer_id AND status='completed'"))[0] ?? 0;

$my_cash_paid  = mysqli_fetch_row(mysqli_query($db, "SELECT COALESCE(SUM(amount_cash),0) FROM payments WHERE farmer_id=$farmer_id AND status='verified'"))[0] ?? 0;
$my_palay_paid = mysqli_fetch_row(mysqli_query($db, "SELECT COALESCE(SUM(kg_palay),0) FROM payments WHERE farmer_id=$farmer_id AND status='verified'"))[0] ?? 0;

$my_payments_q = mysqli_query($db,
    "SELECT p.*, e.name AS equip_name, b.start_date, b.end_date
     FROM payments p
     LEFT JOIN bookings b   ON p.booking_id  = b.booking_id
     LEFT JOIN equipment e  ON b.equipment_id = e.equipment_id
     WHERE p.farmer_id = $farmer_id
     ORDER BY p.paid_at DESC");

$recent_q = mysqli_query($db,
    "SELECT b.booking_id, b.status, b.created_at, e.name AS equip_name, b.start_date
     FROM bookings b
     LEFT JOIN equipment e ON b.equipment_id = e.equipment_id
     WHERE b.farmer_id = $farmer_id
     ORDER BY b.created_at DESC LIMIT 3");

// ── Calendar: fetch ALL bookings (pending/approved) per equipment per date ──
// Used by JS to show availability colors
$cal_q = mysqli_query($db,
    "SELECT equipment_id,
            COALESCE(use_date, start_date) AS book_date,
            use_time_start, use_time_end,
            status
     FROM bookings
     WHERE status IN ('pending','approved')
     ORDER BY book_date");

// Build: { equipment_id: { 'YYYY-MM-DD': [{ status, time_start, time_end }] } }
$cal_booked = [];
while ($cb = mysqli_fetch_assoc($cal_q)) {
    $eid  = $cb['equipment_id'];
    $date = $cb['book_date'];
    if (!isset($cal_booked[$eid]))       $cal_booked[$eid] = [];
    if (!isset($cal_booked[$eid][$date])) $cal_booked[$eid][$date] = [];
    $cal_booked[$eid][$date][] = [
        'status' => $cb['status'],
        'ts'     => $cb['use_time_start'],
        'te'     => $cb['use_time_end'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>PILDEMCO – Farmer Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
:root {
    --g1:#0d3d28; --g2:#1a5f3f; --g3:#2d8a5e; --g4:#4caf80;
    --g5:#c8e6d8; --g6:#e8f5ee;
    --k1:#6d5c2e; --k2:#b8963e; --k3:#f4a261; --k4:#fde8cc;
    --topbar-h:158px;
    --radius:12px;
    --shadow:0 4px 20px rgba(0,0,0,.08);
    --shadow-md:0 8px 32px rgba(0,0,0,.13);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;background:#f0f4f1;color:#1e2e1e;min-height:100vh;display:flex;flex-direction:column;}

/* ── HEADER NAV (replaces sidebar) ── */
.site-header{
    position:sticky;top:0;z-index:200;
    background:linear-gradient(180deg,var(--g1) 0%,#0f4a30 100%);
    box-shadow:0 4px 24px rgba(0,0,0,.3);
    border-bottom:3px solid var(--k3);
}
.header-top{
    display:flex;align-items:center;gap:16px;
    padding:0 36px;height:90px;
    border-bottom:1px solid rgba(255,255,255,.08);
}
.header-brand{display:flex;align-items:center;gap:14px;text-decoration:none;}
.header-brand img{width:68px;height:68px;border-radius:50%;border:3px solid var(--k3);object-fit:cover;box-shadow:0 3px 14px rgba(0,0,0,.35);}
.brand-text .name{color:#fff;font-size:1.3rem;font-weight:900;line-height:1.1;font-family:'Poppins',sans-serif;letter-spacing:.01em;}
.brand-text .sub{color:var(--g5);font-size:.72rem;letter-spacing:.06em;font-weight:600;}
.header-user-pill{display:flex;align-items:center;gap:8px;}
.user-avatar-sm{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--g3),var(--g4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.88rem;flex-shrink:0;}
.user-info-sm .uname{color:#fff;font-size:.82rem;font-weight:700;}
.user-info-sm .urole{color:var(--k3);font-size:.65rem;letter-spacing:.08em;text-transform:uppercase;}
.header-date-sm{font-size:.72rem;color:rgba(255,255,255,.55);margin-left:16px;font-weight:500;}
.header-nav{
    display:flex;align-items:center;gap:2px;
    padding:0 28px;height:58px;
    overflow-x:auto;
}
.header-nav::-webkit-scrollbar{height:0;}
.nav-sep{width:1px;height:20px;background:rgba(255,255,255,.12);flex-shrink:0;margin:0 4px;}
.nav-item{display:flex;align-items:center;gap:7px;padding:8px 12px;color:rgba(255,255,255,.65);text-decoration:none;font-size:.77rem;font-weight:500;border-radius:8px;border-bottom:2px solid transparent;white-space:nowrap;transition:all .18s;flex-shrink:0;}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff;}
.nav-item.active{background:rgba(44,138,94,.25);border-bottom-color:var(--k3);color:#fff;}
.nav-item .ico{font-size:.95rem;}
.nav-badge{background:var(--k3);color:var(--g1);font-size:.58rem;font-weight:700;padding:2px 6px;border-radius:10px;}
.logout-btn{display:flex;align-items:center;gap:7px;color:rgba(255,255,255,.55);text-decoration:none;font-size:.74rem;font-weight:500;padding:8px 12px;border-radius:8px;transition:all .18s;margin-left:auto;flex-shrink:0;}
.logout-btn:hover{background:rgba(220,50,50,.15);color:#ff8a80;}

/* MAIN */
.main-wrap{flex:1;display:flex;flex-direction:column;min-height:calc(100vh - var(--topbar-h));}
.topbar{display:none;}
.topbar-title{font-size:1rem;font-weight:700;color:var(--g1);flex:1;}
.topbar-title span{color:var(--g3);}
.topbar-date{font-size:.72rem;color:#999;}
.topbar-chip{display:flex;align-items:center;gap:6px;background:var(--g6);color:var(--g2);font-size:.72rem;font-weight:600;padding:5px 13px;border-radius:20px;}
.content{padding:26px 28px;flex:1;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
.page-header h2{font-size:1.25rem;font-weight:700;color:var(--g1);}
.page-header p{font-size:.76rem;color:#888;margin-top:2px;}

/* STAT CARDS */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:#fff;border-radius:var(--radius);padding:20px 22px;box-shadow:var(--shadow);border-left:4px solid var(--g3);display:flex;align-items:center;gap:14px;transition:transform .2s,box-shadow .2s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);}
.stat-card.orange{border-left-color:var(--k3);}
.stat-card.gold{border-left-color:var(--k2);}
.stat-card.blue{border-left-color:#3b82f6;}
.stat-ico{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.ico-g{background:var(--g6);}
.ico-o{background:#fff3e8;}
.ico-b{background:#e0f2fe;}
.ico-k{background:#fdf4e0;}
.sv{font-size:1.4rem;font-weight:700;color:var(--g1);line-height:1;}
.sl{font-size:.68rem;color:#999;margin-top:3px;font-weight:500;}

/* CARDS / TABLES */
.card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:22px;}
.card-head{padding:16px 22px;border-bottom:1px solid #edf2ee;display:flex;align-items:center;justify-content:space-between;}
.card-head h3{font-size:.9rem;font-weight:700;color:var(--g1);}
.card-head p{font-size:.68rem;color:#aaa;margin-top:1px;}
table{width:100%;border-collapse:collapse;font-size:.78rem;}
thead th{background:var(--g6);color:var(--g2);font-size:.66rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:11px 16px;text-align:left;}
tbody td{padding:11px 16px;border-bottom:1px solid #f0f4f1;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:#f8fcf9;}
.table-wrap{overflow-x:auto;}

/* BADGES */
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.62rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}
.b-pending{background:#fff8e1;color:#f59e0b;border:1px solid #fde68a;}
.b-approved{background:#e8f5e9;color:#16a34a;border:1px solid #86efac;}
.b-rejected{background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;}
.b-completed{background:#e0f2fe;color:#0284c7;border:1px solid #7dd3fc;}
.b-cancelled{background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;}
.b-cash{background:#e0f2fe;color:#0284c7;border:1px solid #7dd3fc;}
.b-palay{background:#fdf4e0;color:#b45309;border:1px solid #fcd34d;}
.b-verified{background:#e8f5e9;color:#16a34a;border:1px solid #86efac;}
.b-available{background:#e8f5e9;color:#16a34a;border:1px solid #86efac;}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:8px;font-family:'Poppins',sans-serif;font-size:.74rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all .18s;}
.btn-green{background:var(--g3);color:#fff;} .btn-green:hover{background:var(--g2);}
.btn-orange{background:var(--k3);color:var(--g1);} .btn-orange:hover{background:#e8924f;}
.btn-red{background:#ef4444;color:#fff;} .btn-red:hover{background:#dc2626;}
.btn-outline{background:transparent;color:var(--g3);border:1.5px solid var(--g3);}
.btn-outline:hover{background:var(--g6);}
.btn-sm{padding:5px 10px;font-size:.68rem;}
.btn-full{width:100%;justify-content:center;}

/* ALERTS */
.msg-success{background:#dcfce7;color:#15803d;border:1px solid #86efac;border-radius:9px;padding:11px 16px;font-size:.8rem;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.msg-error{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:9px;padding:11px 16px;font-size:.8rem;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:8px;}

/* HERO BANNER */
.hero-banner{background:linear-gradient(130deg,var(--g2) 0%,var(--g1) 55%,#0a2f1e 100%);border-radius:var(--radius);padding:28px 32px;display:flex;align-items:center;gap:24px;margin-bottom:22px;position:relative;overflow:hidden;}
.hero-banner::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(244,162,97,.08);}
.hero-logo{width:68px;height:68px;border-radius:50%;border:3px solid var(--k3);object-fit:cover;flex-shrink:0;}
.hero-text .ht{font-size:1.2rem;font-weight:700;color:#fff;margin-bottom:4px;}
.hero-text .hs{font-size:.78rem;color:#a5d6c0;line-height:1.55;max-width:420px;}
.hero-action{margin-left:auto;flex-shrink:0;z-index:1;}

/* QUICK ACTIONS */
.quick-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px;}
.quick-btn{background:#fff;border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--g1);border:2px solid transparent;transition:all .2s;cursor:pointer;font-family:'Poppins',sans-serif;width:100%;}
.quick-btn:hover{border-color:var(--g4);transform:translateY(-2px);box-shadow:var(--shadow-md);}
.q-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.q-label{font-size:.82rem;font-weight:600;color:var(--g1);text-align:left;}
.q-sub{font-size:.66rem;color:#aaa;margin-top:1px;}

/* ACTIVITY FEED */
.activity-item{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid #f0f4f1;font-size:.78rem;}
.activity-item:last-child{border-bottom:none;}
.act-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.dot-pending{background:#f59e0b;}
.dot-approved{background:#16a34a;}
.dot-rejected{background:#ef4444;}
.dot-completed{background:#0284c7;}
.dot-cancelled{background:#9ca3af;}
.act-main{flex:1;}
.act-equip{font-weight:600;color:#222;}
.act-sub{color:#aaa;font-size:.68rem;margin-top:1px;}
.act-date{color:#ccc;font-size:.65rem;}

/* EQUIPMENT GRID */
.equip-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;}
.equip-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;border:2px solid transparent;transition:all .22s;}
.equip-card:hover{border-color:var(--g4);transform:translateY(-3px);box-shadow:var(--shadow-md);}
.equip-thumb{height:150px;background:linear-gradient(135deg,var(--g6),var(--k4));display:flex;align-items:center;justify-content:center;font-size:3.5rem;position:relative;}
.equip-thumb img{width:100%;height:100%;object-fit:cover;position:absolute;}
.avail-tag{position:absolute;top:10px;right:10px;font-size:.6rem;font-weight:700;padding:3px 9px;border-radius:12px;z-index:2;}
.avail-green{background:#e8f5e9;color:#16a34a;border:1px solid #86efac;}
.avail-other{background:#fff8e1;color:#f59e0b;border:1px solid #fde68a;}
.equip-body{padding:16px 18px 18px;}
.equip-cat{font-size:.62rem;font-weight:700;color:var(--g3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;}
.equip-name{font-size:.95rem;font-weight:700;color:var(--g1);margin-bottom:6px;}
.equip-desc{font-size:.72rem;color:#888;line-height:1.5;margin-bottom:12px;min-height:32px;}
.rate-row{display:flex;justify-content:space-between;padding:10px 0;border-top:1px dashed var(--g5);border-bottom:1px dashed var(--g5);margin-bottom:12px;}
.rate-item .rl{font-size:.6rem;color:#aaa;font-weight:600;}
.rate-item .rv{font-size:.84rem;font-weight:700;color:var(--g1);margin-top:1px;}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.45);align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;border-radius:16px;padding:30px;width:500px;max-width:96vw;max-height:92vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,.2);animation:mIn .22s ease;}
@keyframes mIn{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}
.modal h3{font-size:.98rem;font-weight:700;color:var(--g1);margin-bottom:18px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px;}
.fg{display:flex;flex-direction:column;gap:5px;}
.fg.full{grid-column:span 2;}
.fg label{font-size:.7rem;font-weight:600;color:#555;}
.fg input,.fg select,.fg textarea{padding:10px 12px;border:1.5px solid #ddd;border-radius:8px;font-family:'Poppins',sans-serif;font-size:.8rem;background:#fafafa;outline:none;transition:border-color .2s,box-shadow .2s;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--g3);box-shadow:0 0 0 3px rgba(45,138,94,.1);}
.modal-actions{display:flex;gap:9px;justify-content:flex-end;margin-top:20px;}
.rate-preview{background:var(--g6);border-radius:8px;padding:12px 14px;font-size:.76rem;color:var(--g2);font-weight:600;margin-top:4px;display:none;}
.rate-preview span{color:var(--g1);font-size:.9rem;font-weight:700;}

/* EMPTY STATE */
.empty-state{padding:36px;text-align:center;color:#bbb;}
.empty-state .ei{font-size:2.5rem;display:block;margin-bottom:8px;}
.empty-state p{font-size:.82rem;}

@media(max-width:900px){.stats-row{grid-template-columns:1fr 1fr}.quick-row{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.stats-row{grid-template-columns:1fr 1fr}.quick-row{grid-template-columns:1fr 1fr}.content{padding:12px 10px}.header-top{padding:0 12px}.header-nav{padding:0 8px}}

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
.mobile-drawer-brand .name{color:#fff;font-size:1rem;font-weight:900;font-family:'Poppins',sans-serif;}
.mobile-drawer-brand .sub{color:var(--g5);font-size:.62rem;font-weight:600;}
.mobile-drawer-user{padding:12px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.mobile-drawer-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g3),var(--g4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:.85rem;flex-shrink:0;}
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
    .hamburger-btn{display:flex;}
    .header-user-pill,.header-date-sm,.tl-bar,.header-nav{display:none!important;}
    .header-top{height:64px;padding:0 14px;gap:10px;}
    .header-brand img{width:44px;height:44px;}
    .brand-text .name{font-size:1rem;}
    .brand-text .sub{font-size:.58rem;}
    .content{padding:14px 12px;}
    .page-header{flex-direction:column;align-items:flex-start;gap:8px;}
    .page-header h2{font-size:1.1rem;}
    .stats-row{grid-template-columns:1fr 1fr;}
    .quick-row{grid-template-columns:1fr 1fr;}
}
@media(max-width:400px){.stats-row{grid-template-columns:1fr;}.quick-row{grid-template-columns:1fr;}}

/* ═══ COMPREHENSIVE MOBILE STYLES ═══ */
@media(max-width:768px){
    /* Prevent iOS zoom on inputs */
    input,select,textarea{font-size:16px!important;}

    /* Content */
    .content{padding:12px 10px;}
    .page-header{flex-direction:column;align-items:flex-start;gap:6px;margin-bottom:16px;}
    .page-header h2{font-size:1rem;}
    .page-header p{font-size:.68rem;}

    /* Stats */
    .stats-row{grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
    .stat-card{padding:13px 12px;gap:9px;border-radius:10px;}
    .stat-ico{width:34px;height:34px;font-size:1rem;border-radius:8px;}
    .sv{font-size:1.15rem;}
    .sl{font-size:.61rem;}

    /* Quick buttons */
    .quick-row{grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
    .quick-btn{padding:12px 11px;gap:8px;border-radius:10px;}
    .q-ico{width:32px;height:32px;font-size:.95rem;border-radius:8px;}
    .q-label{font-size:.75rem;}
    .q-sub{font-size:.61rem;}

    /* Hero banner */
    .hero-banner{padding:16px 14px;gap:12px;margin-bottom:14px;border-radius:12px;}
    .hero-logo{width:44px;height:44px;border:2px solid var(--k3);}
    .hero-text .ht{font-size:.95rem;}
    .hero-text .hs{font-size:.7rem;line-height:1.5;}
    .hero-action{display:none;}

    /* Cards */
    .card{border-radius:10px;margin-bottom:13px;}
    .card-head{padding:11px 13px;}
    .card-head h3{font-size:.82rem;}
    .card-head p{font-size:.63rem;}

    /* Tables */
    .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;}
    table{font-size:.72rem;min-width:480px;}
    thead th{padding:8px 9px;font-size:.59rem;}
    tbody td{padding:8px 9px;}
    .badge{font-size:.57rem;padding:2px 7px;}

    /* Equipment grid */
    .equip-grid{grid-template-columns:1fr 1fr;gap:11px;}
    .equip-thumb{height:105px;font-size:2.8rem;}
    .equip-body{padding:10px 11px 12px;}
    .equip-name{font-size:.82rem;}
    .equip-desc{font-size:.67rem;min-height:26px;margin-bottom:9px;}
    .equip-cat{font-size:.57rem;}
    .rate-row{padding:6px 0;margin-bottom:8px;}
    .rate-item .rv{font-size:.76rem;}
    .rate-item .rl{font-size:.55rem;}
    .avail-tag{font-size:.57rem;padding:2px 7px;}

    /* Modal */
    .modal{padding:18px 14px;border-radius:14px;max-height:92vh;}
    .modal h3{font-size:.88rem;margin-bottom:13px;}
    .form-grid{grid-template-columns:1fr;gap:9px;}
    .fg.full{grid-column:span 1;}
    .fg label{font-size:.65rem;}
    .fg input,.fg select,.fg textarea{padding:9px 10px;border-radius:7px;}
    .modal-actions{gap:7px;margin-top:14px;}
    .rate-preview{font-size:.73rem;padding:10px 12px;}

    /* Buttons */
    .btn-sm{font-size:.63rem;padding:5px 9px;}
    .btn-green,.btn-orange,.btn-red,.btn-outline{font-size:.73rem;padding:7px 12px;}

    /* Activity feed */
    .activity-item{gap:8px;padding:9px 0;}
    .act-equip{font-size:.75rem;}
    .act-sub{font-size:.63rem;}
    .act-date{font-size:.59rem;}

    /* Calendar */
    .avail-cal-wrap{padding:13px 11px;}
    .avail-cal-header{gap:8px;}
    .avail-cal-title{font-size:.88rem;}
    .cal-nav-btn{width:26px;height:26px;font-size:.78rem;}
    .cal-grid tbody td{min-height:34px;padding:3px 1px;font-size:.69rem;}
    .cal-day-num{font-size:.69rem;}
    .cal-dot{width:5px;height:5px;}
    .equip-filter-btn{font-size:.62rem;padding:4px 8px;}
    .cal-legend{gap:10px;font-size:.64rem;}
    .cal-legend-swatch{width:12px;height:12px;}

    /* Alerts */
    .msg-success,.msg-error{font-size:.75rem;padding:9px 12px;border-radius:8px;}

    /* Payment method cards */
    .pmc-inner{padding:10px 11px;}
    .pmc-icon{font-size:1.15rem;}
    .pmc-label{font-size:.75rem;}
    .pmc-sub{font-size:.59rem;}
    .pmc-amount{font-size:.65rem;padding:4px 7px;}

    /* Notification banner */
    .notif-banner{padding:13px 14px;border-radius:10px;}

    /* Two-column grids → one column */
    div[style*="grid-template-columns:2fr 1fr"]{display:flex!important;flex-direction:column!important;}
    div[style*="grid-template-columns: 2fr 1fr"]{display:flex!important;flex-direction:column!important;}
}

@media(max-width:480px){
    .stats-row{grid-template-columns:1fr 1fr;}
    .quick-row{grid-template-columns:1fr 1fr;}
    .equip-grid{grid-template-columns:1fr 1fr;}
    table{min-width:440px;}
}
@media(max-width:380px){
    .stats-row{grid-template-columns:1fr;}
    .quick-row{grid-template-columns:1fr;}
    .equip-grid{grid-template-columns:1fr;}
    .content{padding:10px 8px;}
    .stat-card{padding:12px 11px;}
    .sv{font-size:1.1rem;}
    .header-brand img{width:36px;height:36px;}
    .brand-text .name{font-size:.85rem;}
}

/* ═══════════════════════════════════════════
   AVAILABILITY CALENDAR
═══════════════════════════════════════════ */
.avail-cal-wrap{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-bottom:18px;}
.avail-cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;}
.avail-cal-title{font-size:1.15rem;font-weight:800;color:var(--g1);letter-spacing:.02em;text-transform:uppercase;}
.avail-cal-nav{display:flex;align-items:center;gap:8px;}
.cal-nav-btn{width:32px;height:32px;border-radius:50%;border:1.5px solid #dde3df;background:#fff;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;transition:all .15s;color:var(--g2);}
.cal-nav-btn:hover{background:var(--g2);color:#fff;border-color:var(--g2);}
.avail-cal-equip{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.equip-filter-btn{padding:5px 13px;border-radius:16px;font-size:.7rem;font-weight:700;border:1.5px solid #dde3df;background:#fff;cursor:pointer;transition:all .15s;font-family:'Poppins',sans-serif;color:#666;}
.equip-filter-btn.active{background:var(--g2);color:#fff;border-color:var(--g2);}
.equip-filter-btn:hover:not(.active){border-color:var(--g3);color:var(--g3);}

/* Grid */
.cal-grid{width:100%;border-collapse:separate;border-spacing:3px;}
.cal-grid thead th{
    font-size:.7rem;font-weight:800;color:var(--g2);
    text-align:center;padding:6px 2px;text-transform:uppercase;
    letter-spacing:.06em;
}
.cal-grid thead th:first-child{color:#ef4444;} /* SUN */
.cal-grid thead th:last-child{color:#3b82f6;}  /* SAT */
.cal-grid tbody td{
    width:calc(100%/7);aspect-ratio:1/1;
    text-align:center;vertical-align:top;
    border-radius:8px;padding:4px 2px;
    cursor:pointer;transition:all .18s;
    font-size:.78rem;
    min-height:44px;
    position:relative;
}
.cal-grid tbody td.other-month{color:#ccc;background:#fafafa;cursor:default;}
.cal-grid tbody td.other-month:hover{background:#fafafa;}
.cal-grid tbody td.today{font-weight:800;box-shadow:inset 0 0 0 2px var(--g3);}
.cal-grid tbody td.past-day{color:#bbb;cursor:not-allowed;background:#f9f9f9;}

/* Availability states */
.cal-grid tbody td.cal-free{background:#fff;border:1.5px solid #e5e7eb;}
.cal-grid tbody td.cal-free:hover{background:#e8f5ee;border-color:var(--g4);transform:scale(1.05);}
.cal-grid tbody td.cal-pending{background:#fff8e1;border:1.5px solid #fde68a;}
.cal-grid tbody td.cal-pending:hover{background:#fef3c7;transform:scale(1.05);}
.cal-grid tbody td.cal-booked{background:#fee2e2;border:1.5px solid #fca5a5;cursor:not-allowed;}
.cal-grid tbody td.cal-selected{background:var(--g3)!important;border-color:var(--g2)!important;color:#fff!important;transform:scale(1.08);box-shadow:0 4px 12px rgba(45,138,94,.4);}

.cal-day-num{font-size:.8rem;font-weight:700;display:block;line-height:1;}
.cal-day-dot{display:flex;justify-content:center;gap:2px;margin-top:3px;flex-wrap:wrap;}
.cal-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.cal-dot.booked{background:#ef4444;}
.cal-dot.pending{background:#f59e0b;}
.cal-time-tag{font-size:.54rem;color:inherit;margin-top:2px;line-height:1.2;opacity:.8;}

/* Legend */
.cal-legend{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:14px;font-size:.68rem;font-weight:600;color:#555;}
.cal-legend-item{display:flex;align-items:center;gap:5px;}
.cal-legend-swatch{width:14px;height:14px;border-radius:4px;flex-shrink:0;}
.cal-legend-swatch.free{background:#fff;border:1.5px solid #e5e7eb;}
.cal-legend-swatch.pending{background:#fff8e1;border:1.5px solid #fde68a;}
.cal-legend-swatch.booked{background:#fee2e2;border:1.5px solid #fca5a5;}
.cal-legend-swatch.selected{background:var(--g3);border-color:var(--g2);}

/* Tooltip */
.cal-tooltip{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);
    background:#1e2e1e;color:#fff;font-size:.62rem;padding:5px 9px;border-radius:7px;
    white-space:nowrap;z-index:999;pointer-events:none;opacity:0;transition:opacity .15s;
    box-shadow:0 4px 12px rgba(0,0,0,.2);}
.cal-tooltip::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);
    border:4px solid transparent;border-top-color:#1e2e1e;}
.cal-grid tbody td:hover .cal-tooltip{opacity:1;}

/* ── PAYMENT METHOD CARDS ─────────────────────────────── */
.pay-method-card {
    flex: 1; min-width: 140px;
    cursor: pointer;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    background: #fafafa;
    transition: all .2s;
    overflow: hidden;
}
.pay-method-card:hover { border-color: var(--g3); background: #f0faf4; }
.pay-method-card.selected {
    border-color: var(--g3);
    background: linear-gradient(135deg, #e8f5ee, #f0faf4);
    box-shadow: 0 0 0 3px rgba(45,138,94,.12);
}
.pmc-inner { padding: 14px 16px; display: flex; flex-direction: column; gap: 3px; }
.pmc-icon  { font-size: 1.4rem; }
.pmc-label { font-size: .82rem; font-weight: 700; color: var(--g1); }
.pmc-sub   { font-size: .65rem; color: #888; }
.pmc-amount {
    margin-top: 6px; padding: 5px 9px;
    background: var(--g1); color: #fff;
    border-radius: 8px; font-size: .72rem; font-weight: 700;
    line-height: 1.4;
}
.pay-method-card.selected .pmc-amount { background: var(--g2); }
/* ═══════════════════════════════════════════════
   DARK MODE — full visibility for ALL elements
═══════════════════════════════════════════════ */
[data-theme="dark"] {
    --g5:#7ab89a;
    --g6:#1a2e22;
}
[data-theme="dark"] body {
    background: #0f1a14;
    color: #d4e8da;
}

/* Cards */
[data-theme="dark"] .card,
[data-theme="dark"] .stat-card,
[data-theme="dark"] .quick-btn,
[data-theme="dark"] .equip-card,
[data-theme="dark"] .avail-cal-wrap,
[data-theme="dark"] .modal {
    background: #192a1f;
    border-color: #2a3e30;
    color: #d4e8da;
}
[data-theme="dark"] .card-head { border-bottom-color: #2a3e30; }
[data-theme="dark"] .card-head h3,
[data-theme="dark"] .page-header h2,
[data-theme="dark"] .sv,
[data-theme="dark"] .equip-name,
[data-theme="dark"] .q-label,
[data-theme="dark"] .modal h3,
[data-theme="dark"] .avail-cal-title,
[data-theme="dark"] .rate-item .rv,
[data-theme="dark"] .act-equip {
    color: #e8f5ee;
}
[data-theme="dark"] .card-head p,
[data-theme="dark"] .page-header p,
[data-theme="dark"] .sl,
[data-theme="dark"] .q-sub,
[data-theme="dark"] .equip-desc,
[data-theme="dark"] .act-sub,
[data-theme="dark"] .act-date,
[data-theme="dark"] .rate-item .rl {
    color: #7aaa8e;
}

/* Tables */
[data-theme="dark"] table { color: #d4e8da; }
[data-theme="dark"] thead th {
    background: #1e3328;
    color: #7fc99b;
}
[data-theme="dark"] tbody td {
    border-bottom-color: #1e3328;
    color: #cce0d4;
}
[data-theme="dark"] tbody tr:hover td { background: #1e3328; }

/* Stat cards */
[data-theme="dark"] .stat-card { background: #192a1f; }
[data-theme="dark"] .stat-card.orange { background: #231a10; }
[data-theme="dark"] .stat-card.gold   { background: #201e10; }
[data-theme="dark"] .stat-card.blue   { background: #101e2a; }
[data-theme="dark"] .ico-g { background: #1e3328; }
[data-theme="dark"] .ico-o { background: #2e1f0c; }
[data-theme="dark"] .ico-b { background: #0e1f2e; }
[data-theme="dark"] .ico-k { background: #2a220a; }

/* Buttons */
[data-theme="dark"] .btn-outline {
    color: #4caf80;
    border-color: #4caf80;
    background: transparent;
}
[data-theme="dark"] .btn-outline:hover { background: #1e3328; }

/* Forms inside modals */
[data-theme="dark"] .modal { background: #192a1f; }
[data-theme="dark"] .fg label { color: #9ec4ae; }
[data-theme="dark"] .fg input,
[data-theme="dark"] .fg select,
[data-theme="dark"] .fg textarea {
    background: #0f1a14;
    border-color: #2d4a38;
    color: #d4e8da;
}
[data-theme="dark"] .fg input::placeholder,
[data-theme="dark"] .fg textarea::placeholder { color: #4a6a55; }
[data-theme="dark"] .fg input:focus,
[data-theme="dark"] .fg select:focus,
[data-theme="dark"] .fg textarea:focus {
    border-color: var(--g4);
    box-shadow: 0 0 0 3px rgba(76,175,128,.15);
}

/* Rate preview box */
[data-theme="dark"] .rate-preview {
    background: #1e3328;
    color: #a5d6c0;
}
[data-theme="dark"] .rate-preview span { color: #4caf80; }

/* Badges — keep them readable in dark */
[data-theme="dark"] .b-pending   { background: #2e2200; color: #fbbf24; border-color: #92400e; }
[data-theme="dark"] .b-approved  { background: #0f2e18; color: #4ade80; border-color: #166534; }
[data-theme="dark"] .b-rejected  { background: #2e0f0f; color: #f87171; border-color: #7f1d1d; }
[data-theme="dark"] .b-completed { background: #0d1f2e; color: #60a5fa; border-color: #1e3a5f; }
[data-theme="dark"] .b-cancelled { background: #1e1e1e; color: #9ca3af; border-color: #374151; }
[data-theme="dark"] .b-cash      { background: #0d1f2e; color: #60a5fa; border-color: #1e3a5f; }
[data-theme="dark"] .b-palay     { background: #2a1e08; color: #fbbf24; border-color: #78350f; }
[data-theme="dark"] .b-verified  { background: #0f2e18; color: #4ade80; border-color: #166534; }
[data-theme="dark"] .b-available { background: #0f2e18; color: #4ade80; border-color: #166534; }

/* Activity feed */
[data-theme="dark"] .activity-item { border-bottom-color: #1e3328; }

/* Equipment cards */
[data-theme="dark"] .equip-card { background: #192a1f; border-color: #2a3e30; }
[data-theme="dark"] .equip-card:hover { border-color: var(--g4); }
[data-theme="dark"] .equip-thumb { background: linear-gradient(135deg, #1e3328, #2a1e08); }
[data-theme="dark"] .rate-row { border-top-color: #2a3e30; border-bottom-color: #2a3e30; }
[data-theme="dark"] .equip-cat { color: var(--g4); }

/* Quick action buttons */
[data-theme="dark"] .quick-btn { background: #192a1f; color: #e8f5ee; }
[data-theme="dark"] .quick-btn:hover { border-color: var(--g4); background: #1e3328; }
[data-theme="dark"] .q-ico { background: #1e3328; }

/* Alerts */
[data-theme="dark"] .msg-success { background: #0f2e18; color: #4ade80; border-color: #166534; }
[data-theme="dark"] .msg-error   { background: #2e0f0f; color: #f87171; border-color: #7f1d1d; }

/* Calendar */
[data-theme="dark"] .avail-cal-wrap { background: #192a1f; }
[data-theme="dark"] .avail-cal-title { color: #a5d6c0; }
[data-theme="dark"] .cal-grid thead th { color: #7fc99b; }
[data-theme="dark"] .cal-grid thead th:first-child { color: #f87171; }
[data-theme="dark"] .cal-grid thead th:last-child  { color: #60a5fa; }
[data-theme="dark"] .cal-grid tbody td.other-month { background: #111d16; color: #3a5040; }
[data-theme="dark"] .cal-grid tbody td.cal-free    { background: #192a1f; border-color: #2a3e30; color: #d4e8da; }
[data-theme="dark"] .cal-grid tbody td.cal-free:hover { background: #1e3328; border-color: var(--g4); }
[data-theme="dark"] .cal-grid tbody td.cal-pending { background: #2a1e08; border-color: #78350f; color: #fbbf24; }
[data-theme="dark"] .cal-grid tbody td.cal-booked  { background: #2e0f0f; border-color: #7f1d1d; color: #f87171; }
[data-theme="dark"] .cal-grid tbody td.past-day    { background: #111d16; color: #3a5040; }
[data-theme="dark"] .cal-grid tbody td.today       { box-shadow: inset 0 0 0 2px var(--g4); }
[data-theme="dark"] .cal-nav-btn { background: #192a1f; border-color: #2a3e30; color: #a5d6c0; }
[data-theme="dark"] .cal-nav-btn:hover { background: var(--g2); color: #fff; }
[data-theme="dark"] .equip-filter-btn { background: #192a1f; border-color: #2a3e30; color: #9ec4ae; }
[data-theme="dark"] .equip-filter-btn.active { background: var(--g2); color: #fff; }
[data-theme="dark"] .cal-legend { color: #9ec4ae; }
[data-theme="dark"] .cal-legend-swatch.free { background: #192a1f; border-color: #2a3e30; }

/* Notification / pill cards */
[data-theme="dark"] .pill-row .pill,
[data-theme="dark"] .pill { background: #1e3328; color: #a5d6c0; }

/* Table-wrap scrollbar */
[data-theme="dark"] ::-webkit-scrollbar-track { background: #111d16; }
[data-theme="dark"] ::-webkit-scrollbar-thumb { background: #2a3e30; }

/* Dividers */
[data-theme="dark"] hr { border-color: #2a3e30; }

/* Empty states */
[data-theme="dark"] .empty-state { color: #4a6a55; }

[data-theme="dark"] .pay-method-card { background: #1e2f25; border-color: #2a3e30; }
[data-theme="dark"] .pay-method-card:hover { background: #243828; border-color: var(--g3); }
[data-theme="dark"] .pay-method-card.selected { background: #1a3828; border-color: var(--g3); }
[data-theme="dark"] .pmc-label { color: #e2ede6; }
[data-theme="dark"] .pmc-sub   { color: #7aaa8e; }

/* Modal overlay backdrop */
[data-theme="dark"] .modal-overlay { background: rgba(0,0,0,.65); }

/* Mobile drawer dark mode */
[data-theme="dark"] .mobile-nav-drawer { background: linear-gradient(180deg,#061610 0%,#0a2018 100%); }
[data-theme="dark"] .mobile-drawer-header { border-bottom-color: rgba(255,255,255,.07); }
[data-theme="dark"] .mobile-drawer-user { border-bottom-color: rgba(255,255,255,.06); }
[data-theme="dark"] .mobile-drawer-controls { border-bottom-color: rgba(255,255,255,.06); }
[data-theme="dark"] .mobile-nav-divider { background: rgba(255,255,255,.06); }
[data-theme="dark"] .mobile-drawer-footer { border-top-color: rgba(255,255,255,.06); }
</style>
<?php include 'theme_lang.php'; ?>
</head>
<body>

<!-- HEADER NAV -->
<header class="site-header">
    <div class="header-top">
        <!-- Hamburger (mobile only) -->
        <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleMobileNav()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <a href="?section=home" class="header-brand">
            <img src="LOGO_OF_PILdemCO-removebg-preview.png" alt="PILDEMCO">
            <div class="brand-text">
                <div class="name">PILDEMCO</div>
                <div class="sub">Farmer Portal</div>
            </div>
        </a>
        <div style="width:1px;height:26px;background:rgba(255,255,255,.12);margin:0 10px;"></div>
        <div class="header-user-pill">
            <div class="user-avatar-sm"><?= strtoupper(substr($farmer_name,0,1)) ?></div>
            <div class="user-info-sm">
                <div class="uname"><?= $farmer_name ?></div>
                <div class="urole">🌾 Farmer</div>
            </div>
        </div>
        <div class="header-date-sm"><?= date('l, F j Y') ?></div>
        <div class="tl-bar" style="margin-left:auto;">
            <span class="theme-icon" id="themeIcon" style="color:rgba(255,255,255,.6)">🌙</span>
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light mode" style="background:var(--g3)"></button>
            <div class="lang-toggle">
                <button class="lang-btn active" data-lang="en" onclick="setLang('en')">EN</button>
                <button class="lang-btn" data-lang="tl" onclick="setLang('tl')">TL</button>
            </div>
        </div>
    </div>
    <nav class="header-nav">
        <a href="?section=home" class="nav-item <?= $section=='home'?'active':'' ?>"><span class="ico">🏠</span> Home</a>
        <div class="nav-sep"></div>
        <a href="?section=browse" class="nav-item <?= $section=='browse'?'active':'' ?>"><span class="ico">🚜</span> Browse Equipment</a>
        <a href="#" class="nav-item" onclick="openModal('bookModal'); return false;"><span class="ico">📋</span> Book Equipment</a>
        <div class="nav-sep"></div>
        <a href="?section=bookings" class="nav-item <?= $section=='bookings'?'active':'' ?>">
            <span class="ico">📅</span> My Bookings
            <?php if($my_pending>0): ?><span class="nav-badge"><?= $my_pending ?></span><?php endif; ?>
        </a>
        <a href="?section=payments" class="nav-item <?= $section=='payments'?'active':'' ?>"><span class="ico">💰</span> My Payments</a>
        <a href="?section=profile"  class="nav-item <?= $section=='profile' ?'active':'' ?>"><span class="ico">👤</span> My Profile</a>
        <div class="nav-sep"></div>
        <a href="?section=notifications" class="nav-item <?= $section=='notifications'?'active':'' ?>">
            <span class="ico">🔔</span> Notifications
            <?php
            $user_unread_notif = mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM notifications WHERE user_id=$my_id AND is_read=0"))[0] ?? 0;
            if($user_unread_notif > 0): ?><span class="nav-badge"><?= $user_unread_notif ?></span><?php endif; ?>
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
            <div class="sub">Farmer Portal</div>
        </div>
    </div>
    <!-- User info -->
    <div class="mobile-drawer-user">
        <div class="mobile-drawer-avatar"><?= strtoupper(substr($farmer_name,0,1)) ?></div>
        <div>
            <div class="uname"><?= $farmer_name ?></div>
            <div class="urole">🌾 Farmer</div>
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
    <a href="?section=home" class="mobile-nav-link <?= $section=='home'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">🏠</span> Home
    </a>
    <div class="mobile-nav-divider"></div>
    <div class="mobile-nav-label">Equipment</div>
    <a href="?section=browse" class="mobile-nav-link <?= $section=='browse'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">🚜</span> Browse Equipment
    </a>
    <a href="#" class="mobile-nav-link" onclick="closeMobileNav(); openModal('bookModal'); return false;">
        <span class="mnav-icon">📋</span> Book Equipment
    </a>
    <div class="mobile-nav-divider"></div>
    <div class="mobile-nav-label">My Account</div>
    <a href="?section=bookings" class="mobile-nav-link <?= $section=='bookings'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">📅</span> My Bookings
        <?php if($my_pending>0): ?><span class="mobile-nav-badge"><?= $my_pending ?></span><?php endif; ?>
    </a>
    <a href="?section=payments" class="mobile-nav-link <?= $section=='payments'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">💰</span> My Payments
    </a>
    <a href="?section=profile" class="mobile-nav-link <?= $section=='profile'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">👤</span> My Profile
    </a>
    <div class="mobile-nav-divider"></div>
    <a href="?section=notifications" class="mobile-nav-link <?= $section=='notifications'?'active':'' ?>" onclick="closeMobileNav()">
        <span class="mnav-icon">🔔</span> Notifications
        <?php
        $mob_unread = mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM notifications WHERE user_id=$my_id AND is_read=0"))[0] ?? 0;
        if($mob_unread > 0): ?><span class="mobile-nav-badge"><?= $mob_unread ?></span><?php endif; ?>
    </a>
    <!-- Logout -->
    <div class="mobile-drawer-footer">
        <a href="LOGOUT.php" class="mobile-logout-btn">🚪 &nbsp;Logout</a>
    </div>
</div>
<div class="main-wrap">
    <div class="topbar" style="display:none"></div>

    <div class="content">

    <?php if (!empty($booking_msg)):   ?><div class="msg-success">✅ <?= htmlspecialchars($booking_msg) ?></div><?php endif; ?>
    <?php if (!empty($booking_error)): ?><div class="msg-error">⚠ <?= htmlspecialchars($booking_error) ?></div><?php endif; ?>

    <!-- ═══════════ HOME ═══════════ -->
    <?php if ($section == 'home'): ?>

        <div class="hero-banner">
            <img class="hero-logo" src="LOGO_OF_PILdemCO-removebg-preview.png" alt="PILDEMCO">
            <div class="hero-text">
                <div class="ht">Welcome back, <?= $farmer_name ?>! 👋</div>
                <div class="hs">Browse available farm equipment, submit rental requests, and track your bookings. San Jose, Occ. Mindoro.</div>
            </div>
            <div class="hero-action">
                <button class="btn btn-orange" onclick="openModal('bookModal')">+ Book Equipment</button>
            </div>
        </div>

        <?php
        // Show latest unread notification as alert banner
        $latest_notif = mysqli_fetch_assoc(mysqli_query($db,
            "SELECT * FROM notifications WHERE user_id=$my_id AND is_read=0
             ORDER BY FIELD(type,'emergency','warning','info','success'), created_at DESC LIMIT 1"));
        if ($latest_notif):
            $nt = $latest_notif['type'];
            $ni = ['emergency'=>'🚨','warning'=>'⚠️','info'=>'ℹ️','success'=>'✅'][$nt] ?? '🔔';
            $nb = ['emergency'=>'#fee2e2','warning'=>'#fff8e1','info'=>'#e8f5ee','success'=>'#dcfce7'][$nt] ?? '#e8f5ee';
            $nc = ['emergency'=>'#991b1b','warning'=>'#92400e','info'=>'#1a5f3f','success'=>'#14532d'][$nt] ?? '#1a5f3f';
            $nbd= ['emergency'=>'#ef4444','warning'=>'#f59e0b','info'=>'#2d8a5e','success'=>'#16a34a'][$nt] ?? '#2d8a5e';
        ?>
        <div style="background:<?= $nb ?>;border:1.5px solid <?= $nbd ?>;border-radius:12px;padding:14px 18px;
                    display:flex;align-items:center;gap:14px;margin-bottom:18px;">
            <div style="font-size:1.6rem;flex-shrink:0"><?= $ni ?></div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:700;color:<?= $nc ?>;font-size:.86rem">
                    <?= htmlspecialchars($latest_notif['title']) ?>
                </div>
                <div style="font-size:.74rem;color:<?= $nc ?>;opacity:.85;margin-top:2px;line-height:1.5">
                    <?= htmlspecialchars(substr($latest_notif['message'], 0, 120)) ?><?= strlen($latest_notif['message']) > 120 ? '…' : '' ?>
                </div>
                <div style="font-size:.62rem;color:<?= $nc ?>;opacity:.6;margin-top:4px">
                    📅 <?= date('F j, Y · g:i A', strtotime($latest_notif['created_at'])) ?>
                    <?php if ($user_unread_notif > 1): ?>
                     · <strong><?= $user_unread_notif ?> unread notifications</strong>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
                <a href="?section=notifications" class="btn btn-sm"
                   style="background:<?= $nbd ?>;color:#fff;border:none">View All</a>
                <a href="?section=notifications&mark_all=1" class="btn btn-sm btn-outline"
                   style="font-size:.62rem;border-color:<?= $nbd ?>;color:<?= $nc ?>">Dismiss</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-ico ico-g">📋</div><div><div class="sv"><?= $my_total ?></div><div class="sl">Total Bookings</div></div></div>
            <div class="stat-card orange"><div class="stat-ico ico-o">⏳</div><div><div class="sv"><?= $my_pending ?></div><div class="sl">Pending</div></div></div>
            <div class="stat-card blue"><div class="stat-ico ico-b">✅</div><div><div class="sv"><?= $my_approved ?></div><div class="sl">Approved</div></div></div>
            <div class="stat-card gold"><div class="stat-ico ico-k">🏁</div><div><div class="sv"><?= $my_completed ?></div><div class="sl">Completed</div></div></div>
        </div>

        <div class="quick-row">
            <button class="quick-btn" onclick="openModal('bookModal')">
                <div class="q-ico ico-g">🚜</div>
                <div><div class="q-label"><span data-i18n="nav_book">Book Equipment</span></div><div class="q-sub"><span data-i18n="submit_request">Submit a new rental request</span></div></div>
            </button>
            <a href="?section=browse" class="quick-btn">
                <div class="q-ico ico-o">🔍</div>
                <div><div class="q-label"><span data-i18n="browse_catalog">Browse Catalog</span></div><div class="q-sub"><span data-i18n="see_machinery">See all available machinery</span></div></div>
            </a>
            <a href="?section=bookings" class="quick-btn">
                <div class="q-ico ico-b">📅</div>
                <div><div class="q-label">My Bookings</div><div class="q-sub"><span data-i18n="track_bookings">Track your rental status</span></div></div>
            </a>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;">
            <div class="card">
                <div class="card-head">
                    <div><h3>Recent Activity</h3><p>Your last 3 bookings</p></div>
                    <a href="?section=bookings" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div style="padding:0 22px;">
                <?php if (mysqli_num_rows($recent_q) > 0):
                    while ($r = mysqli_fetch_assoc($recent_q)): ?>
                    <div class="activity-item">
                        <div class="act-dot dot-<?= $r['status'] ?>"></div>
                        <div class="act-main">
                            <div class="act-equip"><?= htmlspecialchars($r['equip_name'] ?? 'Equipment') ?></div>
                            <div class="act-sub">Date of Use: <?= date('M j, Y', strtotime($r['start_date'])) ?></div>
                        </div>
                        <span class="badge b-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                        <div class="act-date"><?= date('M j', strtotime($r['created_at'])) ?></div>
                    </div>
                <?php endwhile; else: ?>
                    <div class="empty-state" style="padding:24px 0">
                        <span class="ei">📋</span>
                        <p>No bookings yet. <a href="#" onclick="openModal('bookModal')">Book equipment</a> to get started!</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><div><h3>Payment Summary</h3></div></div>
                <div style="padding:20px 22px;display:flex;flex-direction:column;gap:14px;">
                    <div style="background:var(--g6);border-radius:10px;padding:16px;">
                        <div style="font-size:.64rem;color:#888;font-weight:600;margin-bottom:4px;">💵 CASH PAID</div>
                        <div style="font-size:1.35rem;font-weight:700;color:var(--g1);">₱<?= number_format($my_cash_paid,2) ?></div>
                    </div>
                    <div style="background:#fdf4e0;border-radius:10px;padding:16px;">
                        <div style="font-size:.64rem;color:#888;font-weight:600;margin-bottom:4px;">🌾 PALAY IBINIGAY (sako)</div>
                        <div style="font-size:1.35rem;font-weight:700;color:var(--k1);"><?= number_format($my_palay_paid,1) ?> sack</div>
                    </div>
                    <a href="?section=payments" class="btn btn-outline btn-full btn-sm">View Transactions →</a>
                </div>
            </div>
        </div>

    <!-- ═══════════ BROWSE ═══════════ -->
    <?php elseif ($section == 'browse'): ?>

        <div class="page-header">
            <div><h2>🚜 Browse Equipment</h2><p>Available farming machinery for rent.</p></div>
            <button class="btn btn-green" onclick="openModal('bookModal')">+ Book Equipment</button>
        </div>

        <?php $equip_all = mysqli_query($db, "SELECT * FROM equipment ORDER BY status ASC, name ASC"); ?>
        <?php if (mysqli_num_rows($equip_all) == 0): ?>
            <div class="card"><div class="empty-state"><span class="ei">🚜</span><p>No equipment added yet.</p></div></div>
        <?php else: ?>
        <div class="equip-grid">
        <?php while ($e = mysqli_fetch_assoc($equip_all)):
            $icons = ['Land Preparation'=>'🚜','Harvesting'=>'⚙️','Soil Preparation'=>'🌱','Post Harvest'=>'🌾','Other'=>'🔧'];
            $ico = $icons[$e['category']] ?? '🔧';
            $available = $e['status'] == 'available';
        ?>
            <div class="equip-card">
                <div class="equip-thumb">
                    <?php if (!empty($e['image_path']) && file_exists($e['image_path'])): ?>
                        <img src="<?= htmlspecialchars($e['image_path']) ?>" alt="">
                    <?php else: ?>
                        <span style="position:relative;z-index:1"><?= $ico ?></span>
                    <?php endif; ?>
                    <span class="avail-tag <?= $available?'avail-green':'avail-other' ?>">
                        <?= $available ? '✓ Available' : '⏳ '.ucfirst($e['status']) ?>
                    </span>
                </div>
                <div class="equip-body">
                    <div class="equip-cat"><?= htmlspecialchars($e['category']) ?></div>
                    <div class="equip-name"><?= htmlspecialchars($e['name']) ?></div>
                    <div class="equip-desc"><?= htmlspecialchars(substr($e['description'],0,80)) ?><?= strlen($e['description'])>80?'…':'' ?></div>
                    <div style="font-size:.7rem;color:var(--g3);font-weight:600;margin-bottom:10px;text-align:center;background:var(--g6);border-radius:7px;padding:6px 10px;">
                        📋 <?= ucfirst($e['rate_unit']) ?> &nbsp;·&nbsp; See rates when booking
                    </div>
                    <?php if ($available): ?>
                        <button class="btn btn-green btn-full"
                            onclick="openBookWithEquip(<?= $e['equipment_id'] ?>,'<?= addslashes($e['name']) ?>',<?= $e['rate_cash'] ?>,<?= $e['rate_palay'] ?>,'<?= $e['rate_unit'] ?>')">
                            📋 Book Now
                        </button>
                    <?php else: ?>
                        <button class="btn btn-full" style="background:#eee;color:#aaa;cursor:not-allowed;" disabled>
                            Currently <?= ucfirst($e['status']) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
        <?php endif; ?>

    <!-- ═══════════ MY BOOKINGS ═══════════ -->
    <?php elseif ($section == 'bookings'): ?>

        <div class="page-header">
            <div><h2>📅 My Bookings</h2><p>Track all your equipment rental requests.</p></div>
            <button class="btn btn-green" onclick="openModal('bookModal')">+ New Booking</button>
        </div>

        <?php $f = $_GET['f'] ?? 'all';
        $filters = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','completed'=>'Completed','rejected'=>'Rejected','cancelled'=>'Cancelled'];
        ?>
        <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;">
            <?php foreach ($filters as $fk => $fl):
                $cls = $f == $fk ? 'btn-green' : 'btn-outline'; ?>
            <a href="?section=bookings&f=<?= $fk ?>" class="btn <?= $cls ?> btn-sm"><?= $fl ?></a>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-head"><div><h3>Booking Requests</h3></div></div>
            <div class="table-wrap">
                <?php
                $f_sql = $f != 'all' ? "AND b.status='".mysqli_real_escape_string($db,$f)."'" : '';
                $bq = mysqli_query($db,
                    "SELECT b.*, e.name AS equip_name, e.category,
                            b.land_barangay, b.land_purok, b.land_lat, b.land_lng
                     FROM bookings b
                     LEFT JOIN equipment e ON b.equipment_id = e.equipment_id
                     WHERE b.farmer_id = $farmer_id $f_sql
                     ORDER BY b.created_at DESC");
                ?>
                <table>
                    <thead><tr><th>#</th><th>Equipment</th><th>Date & Time</th><th>Location ng Lupa</th><th>Amount</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if (mysqli_num_rows($bq) > 0):
                        while ($b = mysqli_fetch_assoc($bq)): ?>
                        <tr>
                            <td>#<?= $b['booking_id'] ?></td>
                            <td><strong><?= htmlspecialchars($b['equip_name'] ?? 'N/A') ?></strong><br>
                                <span style="color:#aaa;font-size:.65rem"><?= htmlspecialchars($b['category'] ?? '') ?></span></td>
                            <td style="font-size:.76rem;white-space:nowrap">
                                📅 <?= date('M j, Y', strtotime($b['start_date'])) ?>
                                <?php if (!empty($b['use_time_start'])): ?>
                                <br>⏰ <?= date('h:i A', strtotime($b['use_time_start'])) ?>
                                <?php if (!empty($b['use_time_end'])): ?>
                                 – <?= date('h:i A', strtotime($b['use_time_end'])) ?>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.72rem;min-width:130px">
                                <?php if (!empty($b['land_barangay'])): ?>
                                <div style="color:#333;font-weight:500">🏘 <?= htmlspecialchars($b['land_barangay']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($b['land_purok'])): ?>
                                <div style="color:#2d8a5e;font-weight:700;margin-top:2px">📍 <?= htmlspecialchars($b['land_purok']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($b['land_lat']) && !empty($b['land_lng'])): ?>
                                <a href="https://maps.google.com/?q=<?= $b['land_lat'] ?>,<?= $b['land_lng'] ?>"
                                   target="_blank"
                                   style="font-size:.65rem;color:#2d8a5e;font-weight:600;display:inline-flex;align-items:center;gap:3px;margin-top:3px;text-decoration:none">
                                    📌 View on Map
                                </a>
                                <?php endif; ?>
                                <?php if (empty($b['land_barangay']) && empty($b['land_purok'])): ?>
                                <span style="color:#ccc">—</span>
                                <?php endif; ?>
                            </td>
                            <td>₱<?= number_format($b['total_amount'],2) ?></td>
                            <td><span class="badge b-<?= $b['payment_method'] ?>"><?= ucfirst($b['payment_method']) ?></span></td>
                            <td><span class="badge b-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                            <td>
                            <?php if ($b['status'] == 'pending'): ?>
                                <a href="?section=bookings&cancel_booking=<?= $b['booking_id'] ?>"
                                   class="btn btn-red btn-sm"
                                   onclick="return confirm('Cancel this booking?')">✕ Cancel</a>
                            <?php elseif ($b['status'] == 'approved'): ?>
                                <span style="font-size:.68rem;color:#16a34a;font-weight:600">✓ Approved</span>
                            <?php elseif ($b['status'] == 'completed'): ?>
                                <span style="font-size:.68rem;color:#0284c7;font-weight:600">✔ Done</span>
                            <?php else: ?>
                                <span style="color:#ccc;font-size:.68rem">—</span>
                            <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8">
                            <div class="empty-state">
                                <span class="ei">📅</span>
                                <p>No <?= $f!='all'?$f:'' ?> bookings. <a href="#" onclick="openModal('bookModal')">Book now →</a></p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ═══════════ MY PAYMENTS ═══════════ -->
    <?php elseif ($section == 'payments'): ?>

        <div class="page-header">
            <div><h2>💰 My Payment Records</h2><p>Your verified cash and palay payment history.</p></div>
        </div>

        <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
            <div style="background:#fff;border-radius:10px;padding:14px 20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:10px;">
                <span style="font-size:1.4rem">💵</span>
                <div>
                    <div style="font-size:.62rem;color:#aaa;font-weight:600">TOTAL CASH PAID</div>
                    <div style="font-size:1.1rem;font-weight:700;color:var(--g1)">₱<?= number_format($my_cash_paid,2) ?></div>
                </div>
            </div>
            <div style="background:#fff;border-radius:10px;padding:14px 20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:10px;">
                <span style="font-size:1.4rem">🌾</span>
                <div>
                    <div style="font-size:.62rem;color:#aaa;font-weight:600">TOTAL PALAY GIVEN (sako)</div>
                    <div style="font-size:1.1rem;font-weight:700;color:var(--k1)"><?= number_format($my_palay_paid,2) ?> sack</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><div><h3>Payment History</h3><p>Verified payments only</p></div></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Booking</th><th>Equipment</th><th>Service Date</th><th>Method</th><th>Cash Paid</th><th>Palay (sako)</th><th>Status</th><th>Date Paid</th></tr></thead>
                    <tbody>
                    <?php if (mysqli_num_rows($my_payments_q) > 0):
                        while ($p = mysqli_fetch_assoc($my_payments_q)): ?>
                        <tr>
                            <td><?= $p['payment_id'] ?></td>
                            <td>#<?= $p['booking_id'] ?></td>
                            <td><?= htmlspecialchars($p['equip_name'] ?? '—') ?></td>
                            <td><?= $p['start_date'] ? date('M j, Y', strtotime($p['start_date'])) : '—' ?></td>
                            <td><span class="badge b-<?= $p['payment_method'] ?>"><?= ucfirst($p['payment_method']) ?></span></td>
                            <td>₱<?= number_format($p['amount_cash'],2) ?></td>
                            <td><?= number_format($p['kg_palay'],2) ?> sack</td>
                            <td><span class="badge b-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td><?= date('M j, Y', strtotime($p['paid_at'])) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="9">
                            <div class="empty-state"><span class="ei">💳</span><p>No payment records yet.</p></div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ═══════════ PROFILE ═══════════ -->
    <?php elseif ($section == 'profile'): ?>

        <div class="page-header">
            <div><h2>👤 My Profile</h2><p>Your registered account details.</p></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start;">
            <div class="card" style="text-align:center;">
                <div style="padding:32px 24px;">
                    <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--g3),var(--g4));display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;font-weight:700;margin:0 auto 16px;">
                        <?= strtoupper(substr($farmer_name,0,1)) ?>
                    </div>
                    <div style="font-size:1rem;font-weight:700;color:var(--g1);"><?= $farmer_name ?></div>
                    <div style="font-size:.72rem;color:#aaa;margin-top:3px;">🌾 Farmer · San Jose, Occ. Mindoro</div>
                    <div style="margin-top:14px;"><span class="badge b-approved" style="font-size:.7rem;">Active Account</span></div>
                    <div style="margin-top:20px;display:grid;grid-template-columns:1fr 1fr;gap:10px;text-align:center;">
                        <div style="background:var(--g6);border-radius:8px;padding:12px;">
                            <div style="font-size:1.1rem;font-weight:700;color:var(--g1)"><?= $my_total ?></div>
                            <div style="font-size:.62rem;color:#888">Bookings</div><br><br><br><br>
                        </div>
                        <div style="background:#fdf4e0;border-radius:8px;padding:12px;">
                            <div style="font-size:1.1rem;font-weight:700;color:var(--k1)"><?= $my_completed ?></div>
                            <div style="font-size:.62rem;color:#888">Completed</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:16px;">
                <div class="card">
                    <div class="card-head"><div><h3>Account Information</h3></div></div>
                    <div style="padding:20px 24px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <?php
                        $info_rows = [
                            'Username'     => $farmer_name,
                            'Email'        => $farmer_email,
                            'Barangay'     => $farmer_address,
                            'User ID'      => '#' . $farmer_id,
                            'Account Type' => 'Farmer / User',
                        ];
                        foreach ($info_rows as $label => $val): ?>
                        <div>
                            <div style="font-size:.64rem;color:#aaa;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px"><?= $label ?></div>
                            <div style="font-size:.84rem;font-weight:600;color:#333"><?= htmlspecialchars($val) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><div><h3>Booking Statistics</h3></div></div>
                    <div style="padding:16px 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;text-align:center;">
                        <?php foreach ([
                            ['Total',     $my_total,     'var(--g1)'],
                            ['Pending',   $my_pending,   '#f59e0b'],
                            ['Approved',  $my_approved,  '#16a34a'],
                            ['Completed', $my_completed, '#0284c7'],
                        ] as $si): ?>
                        <div style="background:#f8faf8;border-radius:10px;padding:14px 8px;">
                            <div style="font-size:1.3rem;font-weight:700;color:<?= $si[2] ?>"><?= $si[1] ?></div>
                            <div style="font-size:.62rem;color:#aaa;margin-top:2px"><?= $si[0] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- ═══════════ MESSAGES ═══════════ -->
    <?php elseif ($section == 'notifications'):
        // (mark_all handler moved to top-of-file to prevent headers-already-sent error)
        // Fetch all notifications for this user
        $notifs_q = mysqli_query($db,
            "SELECT * FROM notifications
             WHERE user_id = $my_id
             ORDER BY created_at DESC");
        $notif_icons = [
            'emergency' => '🚨',
            'warning'   => '⚠️',
            'info'      => 'ℹ️',
            'success'   => '✅',
        ];
        $notif_colors = [
            'emergency' => ['bg'=>'#fee2e2','border'=>'#ef4444','text'=>'#991b1b'],
            'warning'   => ['bg'=>'#fff8e1','border'=>'#f59e0b','text'=>'#92400e'],
            'info'      => ['bg'=>'#e8f5ee','border'=>'#2d8a5e','text'=>'#1a5f3f'],
            'success'   => ['bg'=>'#dcfce7','border'=>'#16a34a','text'=>'#14532d'],
        ];
    ?>
    <style>
    .notif-page-wrap{max-width:700px;margin:0 auto;}
    .notif-card{
        border-radius:12px;padding:16px 18px;margin-bottom:12px;
        display:flex;align-items:flex-start;gap:14px;
        border-left:4px solid;position:relative;
        transition:transform .18s,box-shadow .18s;
    }
    .notif-card:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,.08);}
    .notif-card.unread::after{
        content:'';position:absolute;top:14px;right:14px;
        width:9px;height:9px;border-radius:50%;background:#ef4444;
        box-shadow:0 0 0 2px #fff;
    }
    .notif-icon{font-size:1.5rem;flex-shrink:0;margin-top:1px;}
    .notif-body{flex:1;min-width:0;}
    .notif-title{font-size:.88rem;font-weight:700;margin-bottom:3px;}
    .notif-msg{font-size:.76rem;line-height:1.6;opacity:.85;}
    .notif-meta{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:.65rem;opacity:.6;}
    .notif-type-badge{padding:2px 8px;border-radius:10px;font-size:.6rem;font-weight:800;
        text-transform:uppercase;letter-spacing:.06em;background:rgba(0,0,0,.08);}
    .notif-empty{text-align:center;padding:60px 20px;color:#aaa;}
    .notif-empty .ne-icon{font-size:3rem;display:block;margin-bottom:12px;}
    .notif-empty p{font-size:.82rem;line-height:1.6;}
    </style>

    <div class="page-header">
        <div>
            <h2>🔔 Notifications</h2>
            <p>Messages and alerts from PILDEMCO Admin</p>
        </div>
        <?php if ($user_unread_notif > 0): ?>
        <a href="?section=notifications&mark_all=1" class="btn btn-outline btn-sm">
            ✓ Mark All as Read
        </a>
        <?php endif; ?>
    </div>

    <div class="notif-page-wrap">
    <?php if (mysqli_num_rows($notifs_q) > 0):
        while ($n = mysqli_fetch_assoc($notifs_q)):
            $type  = $n['type'] ?? 'info';
            $icon  = $notif_icons[$type]  ?? '🔔';
            $color = $notif_colors[$type] ?? $notif_colors['info'];
            $unread_cls = !$n['is_read'] ? 'unread' : '';
    ?>
        <div class="notif-card <?= $unread_cls ?>"
             style="background:<?= $color['bg'] ?>;border-color:<?= $color['border'] ?>;color:<?= $color['text'] ?>">
            <div class="notif-icon"><?= $icon ?></div>
            <div class="notif-body">
                <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                <div class="notif-msg"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                <div class="notif-meta">
                    <span class="notif-type-badge"><?= strtoupper($type) ?></span>
                    <span>📅 <?= date('F j, Y · g:i A', strtotime($n['created_at'])) ?></span>
                    <?php if (!empty($n) && !$n['is_read']): ?>
                    <span style="color:#ef4444;font-weight:700">● New</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endwhile; else: ?>
        <div class="notif-empty">
            <span class="ne-icon">🔔</span>
            <p>Wala pang notification.<br>
            Kapag nagpadala ng mensahe ang admin (tulad ng emergency alerts o updates), makikita mo ito dito.</p>
        </div>
    <?php endif; ?>
    </div>

    <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════
     AVAILABILITY CALENDAR WIDGET
═══════════════════════════════════════ -->
<div class="avail-cal-wrap" id="availCalWrap" style="display:none">

    <!-- Header row: title + nav + equipment filter -->
    <div class="avail-cal-header">
        <div>
            <div class="avail-cal-title" id="calMonthTitle">SEPTEMBER 2026</div>
            <div style="font-size:.67rem;color:#aaa;margin-top:2px">
                I-click ang araw para piliin ang petsa ng booking
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <div class="avail-cal-nav">
                <button class="cal-nav-btn" onclick="calPrev()" title="Previous month">‹</button>
                <button class="cal-nav-btn" onclick="calToday()" title="Today" style="width:auto;border-radius:8px;padding:0 10px;font-size:.68rem;font-weight:700">Today</button>
                <button class="cal-nav-btn" onclick="calNext()" title="Next month">›</button>
            </div>
        </div>
    </div>

    <!-- Equipment filter pills -->
    <div class="avail-cal-equip" id="equipFilterRow" style="margin-bottom:14px">
        <span style="font-size:.67rem;color:#888;font-weight:600">Filter:</span>
        <button class="equip-filter-btn active" data-eid="all" onclick="setEquipFilter('all',this)">All Equipment</button>
        <?php
        $eq_list = mysqli_query($db, "SELECT equipment_id, name FROM equipment WHERE status='available' ORDER BY name");
        while ($eql = mysqli_fetch_assoc($eq_list)): ?>
        <button class="equip-filter-btn" data-eid="<?= $eql['equipment_id'] ?>"
            onclick="setEquipFilter('<?= $eql['equipment_id'] ?>',this)">
            <?= htmlspecialchars($eql['name']) ?>
        </button>
        <?php endwhile; ?>
    </div>

    <!-- Calendar grid -->
    <table class="cal-grid" id="calGrid">
        <thead>
            <tr>
                <th>SUN</th><th>MON</th><th>TUE</th>
                <th>WED</th><th>THU</th><th>FRI</th><th>SAT</th>
            </tr>
        </thead>
        <tbody id="calBody"></tbody>
    </table>

    <!-- Legend -->
    <div class="cal-legend">
        <div class="cal-legend-item"><div class="cal-legend-swatch free"></div> Available</div>
        <div class="cal-legend-item"><div class="cal-legend-swatch pending"></div> May Pending</div>
        <div class="cal-legend-item"><div class="cal-legend-swatch booked"></div> Booked / Hindi available</div>
        <div class="cal-legend-item"><div class="cal-legend-swatch selected"></div> Pinili mo</div>
    </div>
</div>

<!-- MODAL: BOOK EQUIPMENT -->
<div class="modal-overlay" id="bookModal">
    <div class="modal" style="width:560px;max-width:98vw">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <h3 style="margin:0;">📋 Book Farm Equipment</h3>
            <button type="button" onclick="closeModal('bookModal')"
                style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:#aaa;line-height:1;padding:2px 6px;border-radius:6px;transition:all .15s;"
                onmouseover="this.style.background='#fee2e2';this.style.color='#ef4444'"
                onmouseout="this.style.background='none';this.style.color='#aaa'"
                title="Close">✕</button>
        </div>
        <form method="POST" action="?section=bookings">
            <div class="form-grid">

                <!-- EQUIPMENT -->
                <div class="fg full">
                    <label>🚜 Select Equipment *</label>
                    <select name="equipment_id" id="bookEquipSelect" onchange="updateRatePreview(); calRefresh();" required>
                        <option value="">-- Choose Equipment --</option>
                        <?php
                        $modal_equip = mysqli_query($db, "SELECT * FROM equipment WHERE status='available' ORDER BY name");
                        while ($me = mysqli_fetch_assoc($modal_equip)): ?>
                        <option value="<?= $me['equipment_id'] ?>"
                                data-cash="<?= $me['rate_cash'] ?>"
                                data-palay="<?= $me['rate_palay'] ?>"
                                data-unit="<?= htmlspecialchars($me['rate_unit']) ?>">
                            <?= htmlspecialchars($me['name']) ?> (<?= $me['category'] ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- DATE -->
                <div class="fg full">
                    <label style="display:flex;align-items:center;justify-content:space-between">
                        <span>📅 Date of Use *</span>
                        <button type="button" id="calToggleBtn"
                            onclick="toggleAvailCal()"
                            style="padding:4px 11px;background:#e8f5ee;border:1.5px solid #2d8a5e;border-radius:8px;font-size:.66rem;font-weight:700;color:#2d8a5e;cursor:pointer;font-family:'Poppins',sans-serif;display:flex;align-items:center;gap:4px">
                            📅 View Availability Calendar
                        </button>
                    </label>
                    <input type="date" name="use_date" id="bookUseDate"
                           min="<?= date('Y-m-d') ?>" required onchange="calcTotal()">
                    <small style="color:#888;font-size:.67rem;margin-top:2px;display:block">
                        Piliin ang exact na araw ng paggamit ng makina.
                    </small>
                </div>

                <!-- TIME -->
                <div class="fg">
                    <label>⏰ Start Time</label>
                    <input type="time" name="use_time_start" id="bookTimeStart" onchange="calcTotal()">
                </div>
                <div class="fg">
                    <label>⏰ End Time</label>
                    <input type="time" name="use_time_end" id="bookTimeEnd" onchange="calcTotal()">
                </div>
                <div class="fg full" style="margin-top:-4px">
                    <div style="font-size:.65rem;color:#888;margin-bottom:5px">⚡ Quick time slots:</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        <?php
                        $slots=[["06:00","08:00"],["07:00","12:00"],["08:00","12:00"],
                                ["12:00","17:00"],["13:00","17:00"],["06:00","17:00"]];
                        foreach($slots as $s): ?>
                        <button type="button" class="time-slot-pill"
                            onclick="setTime('<?= $s[0] ?>','<?= $s[1] ?>')"
                            data-s="<?= $s[0] ?>" data-e="<?= $s[1] ?>">
                            <?= $s[0] ?> – <?= $s[1] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- LAND AREA FIELDS -->
                <div class="fg full" style="margin-top:6px;">
                    <div style="font-size:.64rem;font-weight:800;color:#b45309;text-transform:uppercase;letter-spacing:.08em;padding-bottom:6px;border-bottom:1.5px dashed #fde8cc;display:flex;align-items:center;gap:6px;">
                        🌾 Land & Crop Details
                        <span style="font-weight:400;color:#aaa;text-transform:none;letter-spacing:0;font-size:.62rem;">— Para sa tamang pagkalkula ng bayad</span>
                    </div>
                </div>
                <div class="fg">
                    <label>🌱 Crop Type *</label>
                    <select name="crop_type" id="bookCropType" onchange="calcTotal()" required>
                        <option value="">-- Pumili ng Pananim --</option>
                        <option value="palay">🌾 Palay (Rice)</option>
                        <option value="mais">🌽 Mais (Corn)</option>
                        <option value="gulay">🥬 Gulay (Vegetables)</option>
                        <option value="niyog">🥥 Niyog (Coconut)</option>
                        <option value="prutas">🍍 Prutas (Fruits)</option>
                        <option value="iba">🌿 Iba pa (Others)</option>
                    </select>
                </div>
                <div class="fg">
                    <label>📐 Land Area (hectares) *</label>
                    <input type="number" name="land_area" id="bookLandArea"
                           step="0.01" min="0.1" max="500"
                           placeholder="hal. 1.5"
                           onchange="calcTotal()" oninput="calcTotal()">
                    <small style="color:#888;font-size:.62rem;margin-top:2px;display:block;">
                        Sukat ng lupang gagamitin ng makina (sa ektarya)
                    </small>
                </div>

                <!-- LOCATION HEADER -->
                <div class="fg full" style="margin-top:6px">
                    <div style="font-size:.64rem;font-weight:800;color:#2d8a5e;text-transform:uppercase;letter-spacing:.08em;padding-bottom:6px;border-bottom:1.5px dashed #c8e6d8;display:flex;align-items:center;gap:6px">
                        📍 Location ng Lupa
                        <span style="font-weight:400;color:#aaa;text-transform:none;letter-spacing:0;font-size:.62rem">— Saan ipapabook ang makina?</span>
                    </div>
                </div>

                <!-- BARANGAY -->
                <div class="fg">
                    <label>🏘 Barangay *</label>
                    <input type="text" name="book_barangay" id="bookBarangay"
                           value="<?= $farmer_address ?>"
                           placeholder="hal. Brgy. Magsaysay, San Jose" required>
                </div>

                <!-- PUROK -->
                <div class="fg">
                    <label>📍 Purok / Sitio</label>
                    <input type="text" name="book_purok" id="bookPurok"
                           value="<?= htmlspecialchars($_SESSION['purok'] ?? '') ?>"
                           placeholder="hal. Purok 3, Sitio Mabini">
                    <small style="color:#888;font-size:.62rem;margin-top:2px;display:block">
                        Purok o Sitio ng lupa sa loob ng Barangay
                    </small>
                </div>

                <!-- MAP PICKER -->
                <div class="fg full">
                    <label style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                        <span>🗺 Pin Exact Location <small style="color:#aaa;font-weight:400">(optional)</small></span>
                        <button type="button" onclick="toggleBookMap()" id="mapToggleBtn"
                            style="padding:4px 11px;background:#e8f5ee;border:1.5px solid #2d8a5e;border-radius:8px;font-size:.66rem;font-weight:700;color:#2d8a5e;cursor:pointer;font-family:'Poppins',sans-serif">
                            🗺 Show Map
                        </button>
                    </label>
                    <div id="bookMapWrap" style="display:none;border-radius:10px;overflow:hidden;border:2px solid #b6ddc8;">
                        <div id="bookMap" style="height:220px;width:100%"></div>
                    </div>
                    <div id="bookCoordDisplay" style="display:none;margin-top:6px;padding:6px 10px;background:#e8f5ee;border-radius:7px;font-size:.68rem;color:#1a5f3f;font-weight:600">
                        📌 Pinned: <span id="bookCoordText"></span>
                        <button type="button" onclick="clearBookPin()"
                            style="margin-left:8px;background:none;border:none;color:#ef4444;cursor:pointer;font-size:.68rem;font-weight:700">✕ Remove</button>
                    </div>
                    <input type="hidden" name="book_lat" id="bookLat">
                    <input type="hidden" name="book_lng" id="bookLng">
                </div>

                <!-- PAYMENT -->
                <div class="fg full">
                    <label>💰 Payment Method *</label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <label class="pay-method-card" id="payCardCash" onclick="selectPayMethod('cash')">
                            <input type="radio" name="payment_method" value="cash" id="payRadioCash" required style="display:none">
                            <div class="pmc-inner">
                                <div class="pmc-icon">💵</div>
                                <div class="pmc-label">Cash</div>
                                <div class="pmc-sub">Bayad bago gamitin</div>
                                <div class="pmc-amount" id="cashAmountPreview" style="display:none"></div>
                            </div>
                        </label>
                        <label class="pay-method-card" id="payCardPalay" onclick="selectPayMethod('palay')">
                            <input type="radio" name="payment_method" value="palay" id="payRadioPalay" style="display:none">
                            <div class="pmc-inner">
                                <div class="pmc-icon">🌾</div>
                                <div class="pmc-label">Per Sack of Palay</div>
                                <div class="pmc-sub">Bayad pagkatapos ng ani</div>
                                <div class="pmc-amount" id="palayAmountPreview" style="display:none"></div>
                            </div>
                        </label>
                    </div>
                    <!-- hidden select for form submission -->
                    <select name="payment_method" id="bookPayMethod" onchange="calcTotal()" required style="display:none">
                        <option value="cash">Cash</option>
                        <option value="palay">Per Sack of Palay</option>
                    </select>
                </div>
                <div class="fg full">
                    <div class="rate-preview" id="ratePreview" style="display:none">Loading…</div>
                </div>

                <!-- REMARKS -->
                <div class="fg full">
                    <label>📝 Remarks (optional)</label>
                    <textarea name="remarks" rows="2" placeholder="Special instructions…"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('bookModal')">Cancel</button>
                <button type="submit" name="submit_booking" class="btn btn-green">📅 Submit Booking</button>
            </div>
        </form>
    </div>
</div>

<style>
.time-slot-pill{
    padding:5px 11px;background:#e8f5ee;border:1.5px solid #b6ddc8;
    border-radius:14px;font-size:.68rem;font-weight:600;color:#2d8a5e;
    cursor:pointer;transition:all .15s;font-family:'Poppins',sans-serif;
}
.time-slot-pill:hover,.time-slot-pill.active{
    background:#2d8a5e;border-color:#2d8a5e;color:#fff;
}
#bookMap .leaflet-container{ font-family:'Poppins',sans-serif; }
</style>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

// ── BOOK MAP (Leaflet) ───────────────────────────────────────────────────────
let bookMap = null, bookMarker = null, bookMapVisible = false;

function toggleBookMap() {
    const wrap = document.getElementById('bookMapWrap');
    const btn  = document.getElementById('mapToggleBtn');
    bookMapVisible = !bookMapVisible;
    wrap.style.display = bookMapVisible ? 'block' : 'none';
    btn.textContent    = bookMapVisible ? '🗺 Hide Map' : '🗺 Show Map';

    if (bookMapVisible && !bookMap) {
        // Center on San Jose, Occ. Mindoro by default
        bookMap = L.map('bookMap').setView([12.3525, 121.0772], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(bookMap);

        bookMap.on('click', function(e) {
            const lat = e.latlng.lat.toFixed(7);
            const lng = e.latlng.lng.toFixed(7);
            placeBookPin(lat, lng);
        });
    }
    if (bookMapVisible && bookMap) {
        setTimeout(() => bookMap.invalidateSize(), 100);
    }
}

function placeBookPin(lat, lng) {
    if (bookMarker) bookMap.removeLayer(bookMarker);
    const brgy  = document.getElementById('bookBarangay').value || 'Location';
    const purok = document.getElementById('bookPurok').value;
    const label = purok ? `📍 ${purok}, ${brgy}` : `🏘 ${brgy}`;
    bookMarker = L.marker([lat, lng]).addTo(bookMap)
        .bindPopup(`<b>${label}</b><br><small>${lat}, ${lng}</small>`)
        .openPopup();
    document.getElementById('bookLat').value = lat;
    document.getElementById('bookLng').value = lng;
    const display = document.getElementById('bookCoordDisplay');
    document.getElementById('bookCoordText').textContent = `${lat}, ${lng} — ${label}`;
    display.style.display = 'block';
}

function clearBookPin() {
    if (bookMarker && bookMap) bookMap.removeLayer(bookMarker);
    bookMarker = null;
    document.getElementById('bookLat').value = '';
    document.getElementById('bookLng').value = '';
    document.getElementById('bookCoordDisplay').style.display = 'none';
}

// Re-label marker when barangay or purok text changes
['bookBarangay','bookPurok'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => {
        if (bookMarker) {
            const lat   = document.getElementById('bookLat').value;
            const lng   = document.getElementById('bookLng').value;
            const brgy  = document.getElementById('bookBarangay').value || 'Location';
            const purok = document.getElementById('bookPurok').value;
            const label = purok ? `📍 ${purok}, ${brgy}` : `🏘 ${brgy}`;
            bookMarker.setPopupContent(`<b>${label}</b><br><small>${lat}, ${lng}</small>`);
            document.getElementById('bookCoordText').textContent = `${lat}, ${lng} — ${label}`;
        }
    });
});

// Reset map when modal closes
document.getElementById('bookModal').addEventListener('click', function(e) {
    if (e.target === this) {
        // modal closed — map stays so user doesn't lose pin
    }
});

function openBookWithEquip(id, name, cash, palay, unit) {
    const sel = document.getElementById('bookEquipSelect');
    sel.value = id;
    openModal('bookModal');
    updateRatePreview();
}

function updateRatePreview() {
    const sel  = document.getElementById('bookEquipSelect');
    const opt  = sel.options[sel.selectedIndex];
    const prev = document.getElementById('ratePreview');
    if (!opt || !opt.value) { prev.style.display='none'; return; }
    prev.style.display = 'block';
    calcTotal();
}

// ── Quick time slot setter ──────────────────────────────────────────────────
function setTime(start, end) {
    document.getElementById('bookTimeStart').value = start;
    document.getElementById('bookTimeEnd').value   = end;
    // Highlight active pill
    document.querySelectorAll('.time-slot-pill').forEach(p => {
        p.classList.toggle('active', p.dataset.s === start && p.dataset.e === end);
    });
    calcTotal();
}

// ── Select payment method card ──────────────────────────────────────────────
function selectPayMethod(method) {
    document.getElementById('bookPayMethod').value = method;
    document.getElementById('payRadioCash').checked  = (method === 'cash');
    document.getElementById('payRadioPalay').checked = (method === 'palay');
    document.getElementById('payCardCash').classList.toggle('selected',  method === 'cash');
    document.getElementById('payCardPalay').classList.toggle('selected', method === 'palay');
    calcTotal();
}

// ── Calculate estimated total ───────────────────────────────────────────────
function calcTotal() {
    const sel    = document.getElementById('bookEquipSelect');
    const opt    = sel.options[sel.selectedIndex];
    const date   = document.getElementById('bookUseDate').value;
    const tStart = document.getElementById('bookTimeStart').value;
    const tEnd   = document.getElementById('bookTimeEnd').value;
    const method = document.getElementById('bookPayMethod').value;
    const prev   = document.getElementById('ratePreview');
    if (!opt || !opt.value) return;

    const cash  = parseFloat(opt.dataset.cash  || 0);
    const palay = parseFloat(opt.dataset.palay || 0);
    const unit  = opt.dataset.unit || 'per day';

    // ── Get land area & crop type for dynamic pricing ─────────────────────────
    const landAreaEl = document.getElementById('bookLandArea');
    const cropTypeEl = document.getElementById('bookCropType');
    const landArea   = parseFloat(landAreaEl ? landAreaEl.value : 0) || 1;
    const cropType   = cropTypeEl ? cropTypeEl.value : '';

    // Crop multipliers: palay=1.0 (base), mais=1.05, gulay=1.1, others=1.0
    const cropMultipliers = { palay: 1.0, mais: 1.05, gulay: 1.1, niyog: 0.95, prutas: 1.0, iba: 1.0 };
    const cropMult = cropMultipliers[cropType] || 1.0;

    // ── Compute totals for BOTH methods ──────────────────────────────────────
    let totalCash  = 0;
    let totalPalay = 0;
    let hrs = 1;
    let landLabel = '';

    if (unit === 'per hectare') {
        totalCash  = cash  * landArea * cropMult;
        totalPalay = palay * landArea * cropMult;
        landLabel  = ` × ${landArea.toFixed(2)} ha`;
        if (cropMult !== 1.0) landLabel += ` × ${cropMult.toFixed(2)} (${cropType})`;
    } else if (unit === 'per hour' && tStart && tEnd) {
        const [sh, sm] = tStart.split(':').map(Number);
        const [eh, em] = tEnd.split(':').map(Number);
        hrs = Math.max(0.5, ((eh * 60 + em) - (sh * 60 + sm)) / 60);
        totalCash  = cash  * hrs * cropMult;
        totalPalay = palay * hrs * cropMult;
    } else {
        totalCash  = cash  * cropMult;
        totalPalay = palay * cropMult;
    }

    // ── Update payment card previews ─────────────────────────────────────────
    const cashPrev  = document.getElementById('cashAmountPreview');
    const palayPrev = document.getElementById('palayAmountPreview');

    if (opt.value) {
        cashPrev.style.display  = 'block';
        palayPrev.style.display = 'block';
        cashPrev.innerHTML  = `💵 Babayarang: <strong>₱${totalCash.toLocaleString('en-PH',{minimumFractionDigits:2})}</strong>`;
        palayPrev.innerHTML = `🌾 Babayarang: <strong>${totalPalay.toFixed(2)} sack</strong>`;
    }

    // ── Rate preview bar ─────────────────────────────────────────────────────
    const rate    = method === 'cash' ? cash : palay;
    let totalStr  = '';
    const fmtCash  = `₱${totalCash.toLocaleString('en-PH',{minimumFractionDigits:2})}`;
    const fmtPalay = `${totalPalay.toFixed(2)} sack palay`;

    if (unit === 'per hectare') {
        totalStr = method === 'cash'
            ? ` &nbsp;|&nbsp; ${landArea.toFixed(2)} ha${landLabel ? '' : ''} &nbsp;|&nbsp; Babayarang: <strong>${fmtCash}</strong>`
            : ` &nbsp;|&nbsp; ${landArea.toFixed(2)} ha &nbsp;|&nbsp; Babayarang: <strong>${fmtPalay}</strong>`;
    } else if (unit === 'per hour' && tStart && tEnd) {
        totalStr = method === 'cash'
            ? ` &nbsp;|&nbsp; ${hrs.toFixed(1)} hrs &nbsp;|&nbsp; Babayarang: <strong>${fmtCash}</strong>`
            : ` &nbsp;|&nbsp; ${hrs.toFixed(1)} hrs &nbsp;|&nbsp; Babayarang: <strong>${fmtPalay}</strong>`;
    } else {
        totalStr = method === 'cash'
            ? ` &nbsp;|&nbsp; 1 araw &nbsp;|&nbsp; Babayarang: <strong>${fmtCash}</strong>`
            : ` &nbsp;|&nbsp; 1 araw &nbsp;|&nbsp; Babayarang: <strong>${fmtPalay}</strong>`;
    }

    prev.style.display = 'block';
    const rateDisp = method === 'cash'
        ? '₱' + rate.toFixed(2) + ' ' + unit
        : rate.toFixed(2) + ' sako ' + unit;
    const dateDisp = date   ? ` &nbsp;|&nbsp; 📅 ${date}` : '';
    const timeDisp = (tStart && tEnd) ? ` &nbsp;|&nbsp; ⏰ ${tStart}–${tEnd}` : '';
    const cropDisp = cropType ? ` &nbsp;|&nbsp; 🌱 ${cropType}` : '';
    prev.innerHTML = 'Rate: <strong>' + rateDisp + '</strong>' + dateDisp + timeDisp + cropDisp + totalStr;
}

<?php if (!empty($booking_error)): ?>
openModal('bookModal');
<?php endif; ?>

// ═══════════════════════════════════════════════════════
//  AVAILABILITY CALENDAR — Full JS
// ═══════════════════════════════════════════════════════

// All booked/pending dates from PHP — { equipId: { 'YYYY-MM-DD': [{status,ts,te}] } }
const CAL_BOOKED = <?= json_encode($cal_booked) ?>;

let calYear    = new Date().getFullYear();
let calMonth   = new Date().getMonth(); // 0-based
let calSelDate = ''; // currently selected date (YYYY-MM-DD)
let calEquipId = 'all'; // currently filtered equipment
let calVisible = false;

const MONTHS = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE',
                'JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
const today  = new Date();
today.setHours(0,0,0,0);

// ── Toggle calendar visibility ──────────────────────────────────────────────
function toggleAvailCal() {
    calVisible = !calVisible;
    const wrap = document.getElementById('availCalWrap');
    const btn  = document.getElementById('calToggleBtn');
    wrap.style.display = calVisible ? 'block' : 'none';
    btn.innerHTML = calVisible
        ? '📅 Hide Calendar'
        : '📅 View Availability Calendar';
    if (calVisible) buildCalendar();
    // Scroll calendar into view smoothly
    if (calVisible) setTimeout(() => wrap.scrollIntoView({behavior:'smooth',block:'nearest'}), 50);
}

// ── Sync equipment filter with booking modal select ──────────────────────────
function calRefresh() {
    const sel = document.getElementById('bookEquipSelect');
    if (sel && sel.value) {
        calEquipId = sel.value;
        // Update filter pill UI
        document.querySelectorAll('.equip-filter-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.eid == calEquipId);
        });
    }
    if (calVisible) buildCalendar();
}

function setEquipFilter(eid, btn) {
    calEquipId = eid;
    document.querySelectorAll('.equip-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    // Sync booking modal select
    const sel = document.getElementById('bookEquipSelect');
    if (sel && eid !== 'all') sel.value = eid;
    buildCalendar();
}

// ── Navigation ───────────────────────────────────────────────────────────────
function calPrev()  { calMonth--; if (calMonth < 0)  { calMonth = 11; calYear--; } buildCalendar(); }
function calNext()  { calMonth++; if (calMonth > 11) { calMonth = 0;  calYear++; } buildCalendar(); }
function calToday() { calYear = today.getFullYear(); calMonth = today.getMonth(); buildCalendar(); }

// ── Get status for a date + equipment ────────────────────────────────────────
// Returns: 'booked' | 'pending' | 'free'
function getDayStatus(dateStr, equipId) {
    let hasBooked  = false;
    let hasPending = false;

    if (equipId === 'all') {
        // Check all equipment
        for (const eid in CAL_BOOKED) {
            const slots = CAL_BOOKED[eid][dateStr] || [];
            for (const s of slots) {
                if (s.status === 'approved') hasBooked  = true;
                if (s.status === 'pending')  hasPending = true;
            }
        }
    } else {
        const slots = (CAL_BOOKED[equipId] && CAL_BOOKED[equipId][dateStr]) || [];
        for (const s of slots) {
            if (s.status === 'approved') hasBooked  = true;
            if (s.status === 'pending')  hasPending = true;
        }
    }
    if (hasBooked)  return 'booked';
    if (hasPending) return 'pending';
    return 'free';
}

// ── Get tooltip text for a date ───────────────────────────────────────────────
function getDayTooltip(dateStr, equipId) {
    const lines = [];
    const check = (eid) => {
        const slots = (CAL_BOOKED[eid] && CAL_BOOKED[eid][dateStr]) || [];
        for (const s of slots) {
            const t = s.ts ? s.ts.substring(0,5) + (s.te ? ' – '+s.te.substring(0,5) : '') : '';
            const label = s.status === 'approved' ? '🔴 Booked' : '🟡 Pending';
            lines.push(label + (t ? ' ' + t : ''));
        }
    };
    if (equipId === 'all') {
        for (const eid in CAL_BOOKED) check(eid);
    } else {
        check(equipId);
    }
    return lines.length ? lines.join('\n') : '✅ Available';
}

// ── Build the calendar grid ───────────────────────────────────────────────────
function buildCalendar() {
    // Update header title
    document.getElementById('calMonthTitle').textContent =
        MONTHS[calMonth] + ' ' + calYear;

    const body    = document.getElementById('calBody');
    body.innerHTML = '';

    // First day of month & total days
    const firstDay   = new Date(calYear, calMonth, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
    const prevDays   = new Date(calYear, calMonth, 0).getDate(); // days in prev month

    let dayCount = 1;
    let nextDay  = 1;
    const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;

    for (let i = 0; i < totalCells; i++) {
        if (i % 7 === 0) body.insertRow();
        const row  = body.lastElementChild;
        const cell = row.insertCell();

        if (i < firstDay) {
            // Previous month days
            const d = prevDays - firstDay + i + 1;
            cell.className = 'other-month';
            cell.innerHTML = `<span class="cal-day-num">${d}</span>`;
        } else if (dayCount > daysInMonth) {
            // Next month days
            cell.className = 'other-month';
            cell.innerHTML = `<span class="cal-day-num">${nextDay++}</span>`;
        } else {
            // Current month
            const mm   = String(calMonth + 1).padStart(2, '0');
            const dd   = String(dayCount).padStart(2, '0');
            const dstr = `${calYear}-${mm}-${dd}`;
            const cellDate = new Date(calYear, calMonth, dayCount);
            cellDate.setHours(0,0,0,0);
            const isPast    = cellDate < today;
            const isToday   = cellDate.getTime() === today.getTime();
            const isSelected = dstr === calSelDate;
            const status    = getDayStatus(dstr, calEquipId);
            const tooltip   = getDayTooltip(dstr, calEquipId);

            // Build class list
            let cls = [];
            if (isPast)       cls.push('past-day');
            else if (isSelected) cls.push('cal-selected');
            else if (status === 'booked')  cls.push('cal-booked');
            else if (status === 'pending') cls.push('cal-pending');
            else cls.push('cal-free');
            if (isToday) cls.push('today');
            cell.className = cls.join(' ');

            // Get slots for dots
            let dots = '';
            if (!isPast && !isSelected) {
                const slotsForDots = [];
                if (calEquipId === 'all') {
                    for (const eid in CAL_BOOKED) {
                        (CAL_BOOKED[eid][dstr] || []).forEach(s => slotsForDots.push(s));
                    }
                } else {
                    (CAL_BOOKED[calEquipId] && (CAL_BOOKED[calEquipId][dstr] || [])).forEach(s => slotsForDots.push(s));
                }
                const dotHtml = slotsForDots.slice(0,4).map(s =>
                    `<span class="cal-dot ${s.status==='approved'?'booked':'pending'}"></span>`
                ).join('');
                if (dotHtml) dots = `<div class="cal-day-dot">${dotHtml}</div>`;
            }

            // Time range display for single-equip view
            let timeTag = '';
            if (calEquipId !== 'all' && status !== 'free' && !isSelected) {
                const sl = (CAL_BOOKED[calEquipId] && CAL_BOOKED[calEquipId][dstr]) || [];
                if (sl.length && sl[0].ts) {
                    const t = sl[0].ts.substring(0,5) + (sl[0].te ? '–'+sl[0].te.substring(0,5) : '');
                    timeTag = `<div class="cal-time-tag">${t}</div>`;
                }
            }

            // Tooltip span
            const tipHtml = `<div class="cal-tooltip">${tooltip.replace(/\n/g,'<br>')}</div>`;

            cell.innerHTML = `<span class="cal-day-num">${dayCount}</span>${dots}${timeTag}${tipHtml}`;

            // Click handler — only for free/pending days in future
            if (!isPast && status !== 'booked') {
                cell.onclick = () => selectCalDay(dstr, cell);
            }
            dayCount++;
        }
    }
}

// ── Select a day from calendar ────────────────────────────────────────────────
function selectCalDay(dateStr, cell) {
    // Deselect previous
    document.querySelectorAll('.cal-grid tbody td.cal-selected').forEach(td => {
        td.classList.remove('cal-selected');
        // Re-apply original status color
        const dstr2 = td.dataset.dstr;
        // Rebuild that cell by refreshing whole calendar
    });

    calSelDate = dateStr;
    buildCalendar(); // re-render to apply selected style

    // Sync to the date input field in booking modal
    const dateInput = document.getElementById('bookUseDate');
    if (dateInput) {
        dateInput.value = dateStr;
        // Trigger calcTotal if exists
        if (typeof calcTotal === 'function') calcTotal();
    }

    // Visual feedback — flash message
    const wrap = document.getElementById('availCalWrap');
    const flash = document.createElement('div');
    flash.style.cssText = 'margin-top:10px;padding:8px 14px;background:#e8f5ee;border-radius:8px;font-size:.74rem;color:#1a5f3f;font-weight:600;text-align:center;';
    flash.innerHTML = `✅ Napili: <strong>${dateStr}</strong> — date field updated!`;
    // Remove any previous flash
    const prev = wrap.querySelector('.cal-flash');
    if (prev) prev.remove();
    flash.className = 'cal-flash';
    wrap.appendChild(flash);
    setTimeout(() => { if (flash.parentNode) flash.remove(); }, 2500);
}

// ── Auto-sync: when date input changes, highlight that day on calendar ────────
document.addEventListener('DOMContentLoaded', () => {
    const dateInput = document.getElementById('bookUseDate');
    if (dateInput) {
        dateInput.addEventListener('change', () => {
            calSelDate = dateInput.value;
            if (calSelDate && calVisible) {
                // Navigate to that month if needed
                const [y, m] = calSelDate.split('-').map(Number);
                calYear  = y;
                calMonth = m - 1;
                buildCalendar();
            }
        });
    }
    // Auto-sync equipment from select to calendar
    const equipSel = document.getElementById('bookEquipSelect');
    if (equipSel) {
        equipSel.addEventListener('change', () => {
            if (equipSel.value) {
                calEquipId = equipSel.value;
                document.querySelectorAll('.equip-filter-btn').forEach(b => {
                    b.classList.toggle('active', b.dataset.eid == calEquipId);
                });
                if (calVisible) buildCalendar();
            }
        });
    }
});// ── Mobile Nav ──────────────────────────────────────────────────────────────
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
// Sync mobile theme icon with desktop
document.addEventListener('DOMContentLoaded', function(){
    const mobileIcon = document.getElementById('mobileThemeIcon');
    const theme = localStorage.getItem('theme') || 'light';
    if (mobileIcon) mobileIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
});
</script>

</body>
</html>