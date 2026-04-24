<?php
/**
 * theme_lang.php
 * ─────────────────────────────────────────────────────────────
 * Shared dark-mode + Tagalog/English language toggle.
 * Include inside <head> AFTER the main <style> block.
 *
 * Usage: <?php include 'theme_lang.php'; ?>
 */
?>
<!-- ═══ DARK MODE CSS VARIABLES ═══ -->
<style>
/* ── Light (default) ── */
:root {
    --bg-body:      #f0f4f1;
    --bg-card:      #ffffff;
    --bg-sidebar:   #0d3d28;
    --bg-topbar:    #ffffff;
    --bg-input:     #fafafa;
    --bg-table-hd:  #e8f5ee;
    --bg-row-hover: #f7fbf8;
    --bg-hero:      linear-gradient(130deg,#1a5f3f 0%,#0d3d28 55%,#0a2f1e 100%);
    --text-base:    #222222;
    --text-muted:   #888888;
    --text-soft:    #aaaaaa;
    --border-base:  #e8eee9;
    --border-light: #f0f4f1;
    --shadow-sm:    0 4px 20px rgba(0,0,0,.08);
    --shadow-md:    0 8px 32px rgba(0,0,0,.12);
    --modal-bg:     #ffffff;
    --toggle-bg:    rgba(255,255,255,.12);
    --cal-other:    #fafafa;
    --cal-past:     #f9f9f9;
    --msg-pane-bg:  #f8fcf9;
}

/* ── Dark mode ── */
[data-theme="dark"] {
    --bg-body:      #0f1a14;
    --bg-card:      #1a2820;
    --bg-sidebar:   #0a1810;
    --bg-topbar:    #142018;
    --bg-input:     #1e2f25;
    --bg-table-hd:  #1e2f25;
    --bg-row-hover: #1c2b22;
    --bg-hero:      linear-gradient(130deg,#0f2518 0%,#0a1810 55%,#060f09 100%);
    --text-base:    #e2ede6;
    --text-muted:   #8aab94;
    --text-soft:    #6a8a72;
    --border-base:  #243828;
    --border-light: #1c2d22;
    --shadow-sm:    0 4px 20px rgba(0,0,0,.25);
    --shadow-md:    0 8px 32px rgba(0,0,0,.4);
    --modal-bg:     #1a2820;
    --toggle-bg:    rgba(0,0,0,.25);
    --cal-other:    #14201a;
    --cal-past:     #111a14;
    --msg-pane-bg:  #111c16;
}

/* ── Apply CSS vars to key elements ── */
body                { background: var(--bg-body) !important; color: var(--text-base) !important; }
.sidebar            { background: var(--bg-sidebar) !important; }
.topbar             { background: var(--bg-topbar) !important; border-color: var(--border-base) !important; }
.card,.modal,.msg-page { background: var(--bg-card) !important; }
.card-header,.card-head { border-color: var(--border-light) !important; }
.card-header h3,.card-head h3,
.page-header h2, .stat-val, .sv { color: var(--text-base) !important; }
.page-header p, .stat-label, .sl,
.topbar-date, .act-sub, .act-date { color: var(--text-muted) !important; }
table               { background: transparent !important; }
thead th            { background: var(--bg-table-hd) !important; }
tbody tr:hover td   { background: var(--bg-row-hover) !important; }
tbody td            { border-color: var(--border-light) !important; color: var(--text-base) !important; }
.stat-card          { background: var(--bg-card) !important; box-shadow: var(--shadow-sm) !important; }
.modal              { background: var(--modal-bg) !important; }
.modal h3           { color: var(--text-base) !important; }
.form-group label,.fg label { color: var(--text-muted) !important; }
.form-group input,.form-group select,.form-group textarea,
.fg input,.fg select,.fg textarea {
    background: var(--bg-input) !important;
    border-color: var(--border-base) !important;
    color: var(--text-base) !important;
}
#pageInput,#chatInput { background: var(--bg-input) !important; color: var(--text-base) !important; border-color: var(--border-base) !important; }
.msg-bub.theirs,.msg-bubble.theirs { background: var(--bg-card) !important; border-color: var(--border-base) !important; color: var(--text-base) !important; }
.msg-thread,.msg-pane,#chatMessages { background: var(--msg-pane-bg) !important; }
.msg-sidebar,.msg-main      { background: var(--bg-card) !important; }
.msg-sidebar-head,.msg-convo-item,
.msg-main-head, .msg-input-area { border-color: var(--border-light) !important; background: var(--bg-card) !important; }
.msg-convo-item:hover       { background: var(--bg-row-hover) !important; }
.msg-convo-item.active      { background: rgba(44,138,94,.18) !important; }
.quick-btn                  { background: var(--bg-card) !important; color: var(--text-base) !important; }
.q-label                    { color: var(--text-base) !important; }
.q-sub                      { color: var(--text-muted) !important; }
.hero-banner                { background: var(--bg-hero) !important; }
.equip-card                 { background: var(--bg-card) !important; }
.equip-name                 { color: var(--text-base) !important; }
.equip-desc                 { color: var(--text-muted) !important; }
.cal-grid tbody td.other-month { background: var(--cal-other) !important; }
.cal-grid tbody td.past-day    { background: var(--cal-past) !important; color: var(--text-soft) !important; }
.cal-grid tbody td.cal-free    { background: var(--bg-card) !important; border-color: var(--border-base) !important; }
.avail-cal-wrap, .avail-cal-wrap .avail-cal-header,
.rate-row, .convo-item      { border-color: var(--border-light) !important; }
.convo-item                 { background: var(--bg-card) !important; }
.chat-header-log,.topbar-user,.topbar-chip { filter: none; }
/* topbar user chip */
.topbar-user,.topbar-chip { background: rgba(44,138,94,.18) !important; color: #a5d6c0 !important; }
/* nav labels / soft text */
.nav-section-label,.nav-label { color: rgba(255,255,255,.3) !important; }
/* activity items */
.activity-item { border-color: var(--border-light) !important; }
.act-equip      { color: var(--text-base) !important; }
/* notifications */
.notif-item     { background: var(--bg-card) !important; border-color: var(--border-light) !important; }
</style>

<!-- ═══ THEME + LANGUAGE TOGGLE BUTTONS ═══ -->
<style>
.tl-bar {
    display: flex; align-items: center; gap: 8px;
}
/* Theme toggle */
.theme-toggle {
    width: 42px; height: 22px;
    border-radius: 11px;
    background: var(--g3);
    border: none; cursor: pointer;
    position: relative; flex-shrink: 0;
    transition: background .3s;
}
.theme-toggle::after {
    content: '';
    position: absolute; top: 2px; left: 2px;
    width: 18px; height: 18px;
    border-radius: 50%; background: #fff;
    transition: transform .25s;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
[data-theme="dark"] .theme-toggle { background: #2d5a3f; }
[data-theme="dark"] .theme-toggle::after { transform: translateX(20px); }
.theme-icon { font-size: .9rem; line-height: 1; cursor: pointer; }

/* Language toggle */
.lang-toggle {
    display: flex; align-items: center;
    background: var(--toggle-bg, rgba(255,255,255,.12));
    border-radius: 20px; overflow: hidden;
    border: 1px solid rgba(255,255,255,.15);
    flex-shrink: 0;
}
.lang-btn {
    padding: 4px 10px; font-size: .64rem; font-weight: 700;
    cursor: pointer; border: none; background: transparent;
    font-family: 'Poppins', sans-serif;
    color: rgba(255,255,255,.55); letter-spacing: .04em;
    transition: all .2s;
}
.lang-btn.active {
    background: rgba(255,255,255,.18); color: #fff;
    border-radius: 16px;
}

/* Override for topbar (light bg) */
.topbar .lang-toggle {
    background: var(--bg-table-hd);
    border-color: var(--border-base);
}
.topbar .lang-btn       { color: var(--text-muted); }
.topbar .lang-btn.active{ background: var(--g3); color: #fff; }
</style>

<!-- ═══ THEME + LANGUAGE JAVASCRIPT ═══ -->
<script>
// ── Theme ───────────────────────────────────────────────────────────────────
(function(){
    const saved = localStorage.getItem('pildemco_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
})();

function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'light';
    const next = cur === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('pildemco_theme', next);
    updateThemeIcon();
}
function updateThemeIcon() {
    const icon = document.getElementById('themeIcon');
    if (!icon) return;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    icon.textContent = isDark ? '☀️' : '🌙';
}
document.addEventListener('DOMContentLoaded', updateThemeIcon);

// ── Language ─────────────────────────────────────────────────────────────────
const LANG_STRINGS = {
    en: {
        // Nav
        nav_dashboard: 'Dashboard', nav_equipment: 'Equipment', nav_bookings: 'Bookings',
        nav_payments: 'Payments', nav_users: 'Users', nav_reports: 'Reports',
        nav_messages: 'Messages', nav_home: 'Home', nav_browse: 'Browse Equipment',
        nav_book: 'Book Equipment', nav_my_bookings: 'My Bookings',
        nav_my_payments: 'My Payments', nav_profile: 'My Profile',
        nav_notifications: 'Notifications', nav_logout: 'Logout',
        // General
        welcome_back: 'Welcome back',
        registered_users: 'Registered Users',
        total_equipment: 'Total Equipment',
        pending_bookings: 'Pending Bookings',
        cash_revenue: 'Cash Revenue',
        recent_bookings: 'Recent Booking Requests',
        latest_5: 'Latest 5 submissions',
        view_all: 'View All',
        quick_actions: 'Quick Actions',
        review_pending: 'Review Pending Bookings',
        add_equipment: 'Add New Equipment',
        view_payments: 'View Payments',
        manage_users: 'Manage Users',
        view_reports: 'View Reports',
        // Bookings
        approve: 'Approve', reject: 'Reject', complete: 'Complete',
        cancel: 'Cancel', status: 'Status', actions: 'Actions',
        user: 'User', equipment: 'Equipment', dates: 'Dates',
        amount: 'Amount', payment: 'Payment',
        // Status labels
        pending: 'Pending', approved: 'Approved', rejected: 'Rejected',
        completed: 'Completed', cancelled: 'Cancelled', available: 'Available',
        // Confirm dialogs
        confirm_approve: 'Approve this booking?',
        confirm_reject: 'Reject this booking?',
        confirm_complete: 'Mark as completed?',
        confirm_cancel: 'Cancel this booking?',
        confirm_delete: 'Delete this record?',
        // Forms
        save_changes: 'Save Changes', cancel_btn: 'Cancel',
        submit_booking: 'Submit Booking', record_payment: 'Save Payment',
        // Messages section
        messages_title: 'Messages', chat_farmers: 'Chat with registered farmers.',
        select_convo: 'Select a conversation to start messaging',
        no_convos: 'No conversations yet. Users will appear here when they message you.',
        no_msgs: 'No messages yet. Say hello! 👋',
        type_msg: 'Type a message…',
        // Equipment
        equip_name: 'Equipment Name', category: 'Category',
        cash_rate: 'Cash Rate (₱)', palay_rate: 'Palay Rate (kg)',
        rate_unit: 'Rate Unit', description: 'Description',
        equip_image: 'Equipment Image (optional)',
        book_now: 'Book Now',
        // Farmer portal
        browse_catalog: 'Browse Catalog', see_machinery: 'See all available machinery',
        track_bookings: 'Track your rental status', submit_request: 'Submit a new rental request',
        payment_summary: 'Payment Summary', total_cash_paid: 'CASH PAID',
        total_palay: 'PALAY PAID', view_transactions: 'View Transactions →',
        recent_activity: 'Recent Activity', your_last_3: 'Your last 3 bookings',
        // Availability calendar
        view_cal: '📅 View Availability Calendar', hide_cal: '📅 Hide Calendar',
        today_lbl: 'Today', available_lbl: '✅ Available',
        admin_panel: 'Admin Panel',
        farmer_portal: 'Farmer Portal',
        role_admin: 'Administrator',
        role_farmer: 'Farmer',
        san_agustin: 'San Agustin Chapter',
        // Booking form
        book_equipment_title: 'Book Equipment',
        select_equipment: '-- Select Equipment --',
        per_day: 'Per Day', per_hour: 'Per Hour', per_hectare: 'Per Hectare',
        // Date of use
        date_of_use: 'Date of Use *', time_start: 'Time Start', time_end: 'Time End',
        barangay: 'Barangay / Location', purok: 'Purok / Sitio',
        payment_method: 'Payment Method *', remarks: 'Remarks (optional)',
        special_instructions: 'Special instructions…',
        // Profile
        account_info: 'Account Information', booking_stats: 'Booking Statistics',
        active_account: 'Active Account',
        // Dashboard
        dashboard_overview: 'Dashboard Overview',
        welcome_summary: "Welcome back. Here's today's summary.",
        palay_collected: 'Palay Collected',
        // Reports
        reports_title: 'Reports & Analytics',
        equip_usage: 'Equipment Usage', monthly_bookings: 'Monthly Bookings (Last 6 Months)',
        payment_breakdown: 'Payment Breakdown',
    },
    tl: {
        // Nav
        nav_dashboard: 'Dashboard', nav_equipment: 'Kagamitan', nav_bookings: 'Mga Booking',
        nav_payments: 'Mga Bayad', nav_users: 'Mga Magsasaka', nav_reports: 'Ulat',
        nav_messages: 'Mga Mensahe', nav_home: 'Home', nav_browse: 'Tumingin ng Kagamitan',
        nav_book: 'Mag-Book ng Kagamitan', nav_my_bookings: 'Aking mga Booking',
        nav_my_payments: 'Aking mga Bayad', nav_profile: 'Aking Profile',
        nav_notifications: 'Mga Abiso', nav_logout: 'Mag-logout',
        // General
        welcome_back: 'Maligayang pagbabalik',
        registered_users: 'Nakarehistrong Gumagamit',
        total_equipment: 'Kabuuang Kagamitan',
        pending_bookings: 'Naghihintay na Booking',
        cash_revenue: 'Kita sa Pera',
        recent_bookings: 'Mga Pinakabagong Kahilingan',
        latest_5: 'Pinakabagong 5 na isinumite',
        view_all: 'Tingnan Lahat',
        quick_actions: 'Mabilis na Aksyon',
        review_pending: 'Suriin ang Naghihintay na Booking',
        add_equipment: 'Magdagdag ng Kagamitan',
        view_payments: 'Tingnan ang mga Bayad',
        manage_users: 'Pamahalaan ang mga Magsasaka',
        view_reports: 'Tingnan ang Ulat',
        // Bookings
        approve: 'Aprubahan', reject: 'Tanggihan', complete: 'Tapusin',
        cancel: 'Kanselahin', status: 'Katayuan', actions: 'Aksyon',
        user: 'Gumagamit', equipment: 'Kagamitan', dates: 'Petsa',
        amount: 'Halaga', payment: 'Paraan ng Bayad',
        // Status labels
        pending: 'Naghihintay', approved: 'Naaprubahan', rejected: 'Tinanggihan',
        completed: 'Tapos na', cancelled: 'Nakansela', available: 'Makukuha',
        // Confirm dialogs
        confirm_approve: 'Aprubahan ang booking na ito?',
        confirm_reject: 'Tanggihan ang booking na ito?',
        confirm_complete: 'Markahan bilang tapos na?',
        confirm_cancel: 'Kanselahin ang booking na ito?',
        confirm_delete: 'Burahin ang rekord na ito?',
        // Forms
        save_changes: 'I-save ang mga Pagbabago', cancel_btn: 'Kanselahin',
        submit_booking: 'Isumite ang Booking', record_payment: 'I-save ang Bayad',
        // Messages
        messages_title: 'Mga Mensahe', chat_farmers: 'Makipag-chat sa mga nakarehistrong magsasaka.',
        select_convo: 'Pumili ng usapan para magsimulang mag-mensahe',
        no_convos: 'Wala pang usapan. Makikita dito ang mga gumagamit kapag nagpadala sila ng mensahe.',
        no_msgs: 'Wala pang mensahe. Kumusta! 👋',
        type_msg: 'Mag-type ng mensahe…',
        // Equipment
        equip_name: 'Pangalan ng Kagamitan', category: 'Kategorya',
        cash_rate: 'Presyo sa Pera (₱)', palay_rate: 'Presyo sa Palay (kg)',
        rate_unit: 'Yunit ng Presyo', description: 'Paglalarawan',
        equip_image: 'Larawan ng Kagamitan (opsyonal)',
        book_now: 'Mag-Book Ngayon',
        // Farmer portal
        browse_catalog: 'Tumingin ng Katalogo', see_machinery: 'Tingnan ang lahat ng makinarya',
        track_bookings: 'Subaybayan ang katayuan ng iyong renta',
        submit_request: 'Magsumite ng bagong kahilingan',
        payment_summary: 'Buod ng Bayad', total_cash_paid: 'CASH NA BINAYAD',
        total_palay: 'PALAY NA IBINIGAY', view_transactions: 'Tingnan ang mga Transaksyon →',
        recent_activity: 'Mga Kamakailang Aktibidad', your_last_3: 'Ang iyong huling 3 na booking',
        // Availability calendar
        view_cal: '📅 Tingnan ang Kalendaryo', hide_cal: '📅 Itago ang Kalendaryo',
        today_lbl: 'Ngayon', available_lbl: '✅ Makukuha',
        admin_panel: 'Admin Panel',
        farmer_portal: 'Portal ng Magsasaka',
        role_admin: 'Tagapangasiwa',
        role_farmer: 'Magsasaka',
        san_agustin: 'Sangay ng San Agustin',
        // Booking form
        book_equipment_title: 'Mag-Book ng Kagamitan',
        select_equipment: '-- Pumili ng Kagamitan --',
        per_day: 'Bawat Araw', per_hour: 'Bawat Oras', per_hectare: 'Bawat Ektarya',
        // Booking form
        date_of_use: 'Petsa ng Paggamit *', time_start: 'Oras ng Simula', time_end: 'Oras ng Katapusan',
        barangay: 'Barangay / Lokasyon', purok: 'Purok / Sitio',
        payment_method: 'Paraan ng Bayad *', remarks: 'Mga Puna (opsyonal)',
        special_instructions: 'Mga espesyal na tagubilin…',
        // Profile
        account_info: 'Impormasyon ng Account', booking_stats: 'Estadistika ng Booking',
        active_account: 'Aktibong Account',
        // Dashboard
        dashboard_overview: 'Pangkalahatang Tanaw ng Dashboard',
        welcome_summary: "Maligayang pagbabalik. Narito ang buod ngayon.",
        palay_collected: 'Nakolektang Palay',
        // Reports
        reports_title: 'Ulat at Pagsusuri',
        equip_usage: 'Paggamit ng Kagamitan',
        monthly_bookings: 'Buwanang Booking (Nakaraang 6 na Buwan)',
        payment_breakdown: 'Breakdown ng Bayad',
    }
};

let currentLang = localStorage.getItem('pildemco_lang') || 'en';

function t(key) {
    return (LANG_STRINGS[currentLang] && LANG_STRINGS[currentLang][key])
        || (LANG_STRINGS['en'] && LANG_STRINGS['en'][key])
        || key;
}

function setLang(lang) {
    currentLang = lang;
    localStorage.setItem('pildemco_lang', lang);
    applyLang();
    updateLangButtons();
}

function applyLang() {
    // All elements with data-i18n="key"
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        el.textContent = t(key);
    });
    // Placeholders
    document.querySelectorAll('[data-i18n-ph]').forEach(el => {
        el.placeholder = t(el.getAttribute('data-i18n-ph'));
    });
}

function updateLangButtons() {
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.lang === currentLang);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    applyLang();
    updateLangButtons();
});
</script>
