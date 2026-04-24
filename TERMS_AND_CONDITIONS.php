<?php
// ── TERMS_AND_CONDITIONS.php ──────────────────────────────────────────────────
// Shown once after login (for regular users only).
// User must scroll to bottom and click "I Agree" to proceed to USER_PAGE.php.
// Agreement is stored in session so it's only shown once per login session.
// ─────────────────────────────────────────────────────────────────────────────
session_start();
include "db.php";

// Auth guard – only logged-in regular users
if (!isset($_SESSION['username']) || $_SESSION['user_type'] != "user") {
    header("Location: LOGIN_PAGE.php"); exit();
}

// If user already agreed this session, skip straight to user page
if (!empty($_SESSION['terms_agreed'])) {
    header("Location: USER_PAGE.php"); exit();
}

// Handle agreement POST
if (isset($_POST['agree_terms'])) {
    $_SESSION['terms_agreed'] = true;

    // Log the agreement timestamp in DB (optional – gracefully skipped if column missing)
    $uid = intval($_SESSION['user_id'] ?? 0);
    if ($uid) {
        @mysqli_query($db,
            "UPDATE user_info SET terms_agreed_at = NOW() WHERE id = $uid"
        );
    }
    header("Location: USER_PAGE.php"); exit();
}

// Handle decline POST
if (isset($_POST['decline_terms'])) {
    session_destroy();
    header("Location: LOGIN_PAGE.php?declined=1"); exit();
}

$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>PILDEMCO – Terms & Conditions</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
/* ── CSS Variables ─────────────────────────────────────────────────────────── */
:root {
    --g1: #0d3d28;
    --g2: #1a5f3f;
    --g3: #2d8a5e;
    --accent: #f4a261;
    --accent2: #e76f51;

    --bg-page: #f0f4f1;
    --card-bg: #ffffff;
    --card-border: rgba(45,138,94,.12);
    --text-base: #1a2e22;
    --text-muted: #6b7c72;
    --text-light: #9aab9e;
    --input-bg: #f8fbf9;
    --input-border: #d4e4d9;
    --shadow-card: 0 8px 40px rgba(13,61,40,.12), 0 2px 8px rgba(13,61,40,.06);
    --shadow-btn: 0 4px 20px rgba(45,138,94,.35);
    --divider: rgba(45,138,94,.1);
    --scroll-track: #e8f0eb;
    --scroll-thumb: #2d8a5e;
    --badge-bg: #e8f5ee;
    --badge-text: #1a5f3f;
}

[data-theme="dark"] {
    --bg-page: #0b1810;
    --card-bg: #111e16;
    --card-border: rgba(45,138,94,.2);
    --text-base: #ddeee4;
    --text-muted: #7aaa8a;
    --text-light: #4a7a58;
    --input-bg: #162218;
    --input-border: #234030;
    --shadow-card: 0 8px 40px rgba(0,0,0,.5);
    --shadow-btn: 0 4px 20px rgba(45,138,94,.4);
    --divider: rgba(45,138,94,.15);
    --scroll-track: #1a2a1e;
    --scroll-thumb: #2d8a5e;
    --badge-bg: rgba(45,138,94,.15);
    --badge-text: #5abe82;
}

/* ── Reset & Base ──────────────────────────────────────────────────────────── */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

html { scroll-behavior: smooth; }

body {
    font-family: 'Poppins', sans-serif;
    background: var(--bg-page);
    color: var(--text-base);
    min-height: 100vh;
    transition: background .3s, color .3s;
    position: relative;
    overflow-x: hidden;
}

/* ── Animated background blobs ─────────────────────────────────────────────── */
.bg-blobs {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
}
.blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .25;
    animation: blobDrift 18s ease-in-out infinite alternate;
}
[data-theme="dark"] .blob { opacity: .12; }
.blob-1 { width: 500px; height: 500px; background: #2d8a5e; top: -150px; right: -100px; animation-delay: 0s; }
.blob-2 { width: 350px; height: 350px; background: #f4a261; bottom: -80px; left: -80px; animation-delay: -6s; }
.blob-3 { width: 280px; height: 280px; background: #0d3d28; top: 40%; left: 30%; animation-delay: -12s; }
@keyframes blobDrift {
    0%   { transform: translate(0,0) scale(1); }
    100% { transform: translate(30px,20px) scale(1.08); }
}

/* ── Top controls ──────────────────────────────────────────────────────────── */
.top-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 56px;
    background: rgba(255,255,255,.85);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--divider);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    z-index: 1000;
    transition: background .3s, border-color .3s;
}
[data-theme="dark"] .top-bar {
    background: rgba(11,24,16,.88);
}
.top-bar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}
.top-bar-brand img {
    height: 36px;
    width: auto;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,.12));
}
.top-bar-brand span {
    font-family: 'Playfair Display', serif;
    font-size: .95rem;
    font-weight: 700;
    color: var(--g2);
    letter-spacing: .02em;
}
.top-bar-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}
.theme-toggle {
    width: 42px; height: 22px;
    border-radius: 11px;
    background: var(--g3);
    border: none;
    cursor: pointer;
    position: relative;
    transition: background .3s;
}
.theme-toggle::after {
    content: '';
    position: absolute;
    top: 2px; left: 2px;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: #fff;
    transition: transform .25s;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
[data-theme="dark"] .theme-toggle::after { transform: translateX(20px); }
.theme-icon { font-size: .9rem; }
.user-pill {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px 5px 5px;
    background: var(--badge-bg);
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 600;
    color: var(--badge-text);
    border: 1px solid var(--input-border);
}
.user-pill-avatar {
    width: 26px; height: 26px;
    border-radius: 50%;
    background: var(--g3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .65rem;
    font-weight: 700;
    color: #fff;
}

/* ── Page layout ───────────────────────────────────────────────────────────── */
.page-wrapper {
    position: relative;
    z-index: 10;
    min-height: 100vh;
    padding: 80px 16px 40px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

.terms-card {
    width: 100%;
    max-width: 820px;
    background: var(--card-bg);
    border-radius: 24px;
    border: 1px solid var(--card-border);
    box-shadow: var(--shadow-card);
    overflow: hidden;
    animation: cardIn .5s cubic-bezier(.22,.61,.36,1) both;
}
@keyframes cardIn {
    from { opacity: 0; transform: translateY(28px) scale(.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ── Card header ───────────────────────────────────────────────────────────── */
.card-header {
    background: linear-gradient(135deg, var(--g1) 0%, var(--g2) 55%, #3da06c 100%);
    padding: 32px 36px 28px;
    position: relative;
    overflow: hidden;
}
.card-header::after {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: rgba(255,255,255,.05);
    top: -100px; right: -60px;
}
.card-header::before {
    content: '';
    position: absolute;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(244,162,97,.1);
    bottom: -80px; left: -40px;
}
.header-inner {
    position: relative;
    z-index: 2;
}
.header-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(244,162,97,.2);
    border: 1px solid rgba(244,162,97,.35);
    color: #f4c07a;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: .65rem;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 12px;
}
.card-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.4rem, 3vw, 2rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 8px;
}
.card-header p {
    font-size: .78rem;
    color: rgba(255,255,255,.7);
    line-height: 1.6;
}
.effective-date {
    margin-top: 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .68rem;
    color: rgba(255,255,255,.55);
    border-top: 1px solid rgba(255,255,255,.1);
    padding-top: 12px;
}

/* ── Progress bar (scroll indicator) ──────────────────────────────────────── */
.scroll-progress-wrap {
    height: 4px;
    background: var(--scroll-track);
    position: sticky;
    top: 0;
    z-index: 100;
}
.scroll-progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--g3), var(--accent));
    border-radius: 0 2px 2px 0;
    transition: width .1s linear;
}

/* ── Scroll instruction ────────────────────────────────────────────────────── */
.scroll-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--badge-bg);
    border-bottom: 1px solid var(--divider);
    font-size: .7rem;
    color: var(--badge-text);
    font-weight: 600;
    transition: opacity .4s;
}
.scroll-hint.hidden { opacity: 0; pointer-events: none; }
.scroll-hint-icon {
    animation: bounce 1.4s ease-in-out infinite;
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(4px); }
}

/* ── Content body ──────────────────────────────────────────────────────────── */
.card-body {
    height: 460px;
    overflow-y: auto;
    padding: 32px 36px;
    scrollbar-width: thin;
    scrollbar-color: var(--scroll-thumb) var(--scroll-track);
}
.card-body::-webkit-scrollbar { width: 6px; }
.card-body::-webkit-scrollbar-track { background: var(--scroll-track); }
.card-body::-webkit-scrollbar-thumb { background: var(--scroll-thumb); border-radius: 3px; }

/* ── Terms content typography ──────────────────────────────────────────────── */
.terms-section {
    margin-bottom: 28px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--divider);
}
.terms-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}
.section-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 12px;
}
.section-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--badge-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    margin-top: 1px;
}
.section-title {
    font-size: .88rem;
    font-weight: 700;
    color: var(--g2);
    letter-spacing: .01em;
    line-height: 1.4;
}
[data-theme="dark"] .section-title { color: #5abe82; }
.section-num {
    display: block;
    font-size: .6rem;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--text-light);
    margin-bottom: 2px;
}
.terms-text {
    font-size: .78rem;
    color: var(--text-muted);
    line-height: 1.8;
}
.terms-text strong {
    color: var(--text-base);
    font-weight: 600;
}
.terms-list {
    margin: 10px 0 10px 20px;
    list-style: none;
}
.terms-list li {
    position: relative;
    font-size: .77rem;
    color: var(--text-muted);
    line-height: 1.7;
    padding-left: 16px;
    margin-bottom: 5px;
}
.terms-list li::before {
    content: '▸';
    position: absolute;
    left: 0;
    color: var(--g3);
    font-size: .65rem;
    top: 4px;
}
.highlight-box {
    background: var(--badge-bg);
    border-left: 3px solid var(--g3);
    border-radius: 0 10px 10px 0;
    padding: 12px 16px;
    margin: 12px 0;
    font-size: .76rem;
    color: var(--badge-text);
    line-height: 1.7;
}
[data-theme="dark"] .highlight-box { background: rgba(45,138,94,.08); }
.warning-box {
    background: rgba(244,162,97,.1);
    border-left: 3px solid var(--accent);
    border-radius: 0 10px 10px 0;
    padding: 12px 16px;
    margin: 12px 0;
    font-size: .76rem;
    color: #c26a20;
    line-height: 1.7;
}
[data-theme="dark"] .warning-box { background: rgba(244,162,97,.07); color: #e8a055; }

/* ── Card footer / action area ─────────────────────────────────────────────── */
.card-footer {
    padding: 24px 36px;
    background: var(--input-bg);
    border-top: 1px solid var(--divider);
}
.footer-checkbox-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
    opacity: .5;
    transition: opacity .4s;
    pointer-events: none;
}
.footer-checkbox-row.enabled {
    opacity: 1;
    pointer-events: all;
}
.footer-checkbox-row input[type="checkbox"] {
    width: 18px;
    height: 18px;
    border-radius: 5px;
    accent-color: var(--g3);
    cursor: pointer;
    flex-shrink: 0;
    margin-top: 1px;
}
.footer-checkbox-row label {
    font-size: .77rem;
    color: var(--text-muted);
    line-height: 1.6;
    cursor: pointer;
}
.footer-checkbox-row label strong {
    color: var(--text-base);
}
.scroll-notice {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 8px 14px;
    background: rgba(244,162,97,.1);
    border-radius: 8px;
    font-size: .68rem;
    color: #b05e15;
    margin-bottom: 16px;
    font-weight: 600;
    transition: opacity .4s;
}
[data-theme="dark"] .scroll-notice { color: #e8a055; background: rgba(244,162,97,.08); }
.scroll-notice.hidden { display: none; }

.btn-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.btn-agree {
    flex: 1;
    min-width: 160px;
    padding: 13px 20px;
    border-radius: 50px;
    border: none;
    font-family: 'Poppins', sans-serif;
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    cursor: pointer;
    background: linear-gradient(135deg, var(--g3), var(--g1));
    color: #fff;
    box-shadow: var(--shadow-btn);
    transition: transform .2s, box-shadow .2s, opacity .3s;
    position: relative;
    overflow: hidden;
}
.btn-agree::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,.15);
    opacity: 0;
    transition: opacity .2s;
}
.btn-agree:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(45,138,94,.45); }
.btn-agree:not(:disabled):hover::after { opacity: 1; }
.btn-agree:disabled {
    opacity: .45;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
.btn-decline {
    padding: 13px 20px;
    border-radius: 50px;
    border: 1px solid var(--input-border);
    font-family: 'Poppins', sans-serif;
    font-size: .78rem;
    font-weight: 600;
    letter-spacing: .04em;
    cursor: pointer;
    background: transparent;
    color: var(--text-muted);
    transition: all .2s;
}
.btn-decline:hover {
    background: rgba(239,68,68,.08);
    border-color: rgba(239,68,68,.3);
    color: #dc2626;
}

/* ── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
    .page-wrapper { padding: 62px 8px 24px; }
    .top-bar { padding: 0 12px; height: 50px; }
    .top-bar-brand img { height: 28px; }
    .top-bar-brand span { font-size: .74rem; }
    .terms-card { border-radius: 12px; }
    .card-header { padding: 18px 15px 16px; }
    .card-header h1 { font-size: 1.1rem; }
    .card-header p { font-size: .72rem; }
    .effective-date { font-size: .63rem; padding-top: 10px; margin-top: 10px; }
    .header-badge { font-size: .6rem; padding: 3px 10px; }
    .card-body { padding: 14px 13px; height: 300px; }
    .card-body::-webkit-scrollbar { width: 4px; }
    .card-footer { padding: 13px 13px; }
    .section-header { gap: 9px; margin-bottom: 9px; }
    .section-icon { width: 30px; height: 30px; font-size: .85rem; }
    .section-title { font-size: .79rem; }
    .section-num { font-size: .58rem; }
    .terms-text { font-size: .73rem; line-height: 1.7; }
    .terms-list li { font-size: .71rem; }
    .highlight-box { font-size: .72rem; padding: 10px 12px; }
    .warning-box { font-size: .72rem; padding: 10px 12px; }
    .scroll-hint { font-size: .65rem; padding: 7px 12px; }
    .scroll-notice { font-size: .64rem; padding: 7px 11px; border-radius: 7px; }
    .footer-checkbox-row { gap: 10px; margin-bottom: 14px; }
    .footer-checkbox-row input[type="checkbox"] { width: 20px; height: 20px; }
    .footer-checkbox-row label { font-size: .72rem; line-height: 1.5; }
    .btn-agree { font-size: .74rem; padding: 12px 14px; min-width: 120px; }
    .btn-decline { font-size: .71rem; padding: 12px 12px; }
    .btn-row { gap: 8px; }
    .user-pill { display: none; }
    /* Larger touch targets for checkboxes */
    input[type="checkbox"] { min-width: 20px; min-height: 20px; }
}

@media (min-width: 601px) and (max-width: 900px) {
    .terms-card { max-width: 680px; }
    .card-body { height: 420px; }
}
</style>
</head>
<body>

<!-- Background blobs -->
<div class="bg-blobs" aria-hidden="true">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
</div>

<!-- Top bar -->
<header class="top-bar" role="banner">
    <div class="top-bar-brand">
        <img src="LOGO_OF_PILdemCO-removebg-preview.png" alt="PILDEMCO Logo">
        <span>PILDEMCO</span>
    </div>
    <div class="top-bar-controls">
        <div class="user-pill">
            <div class="user-pill-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
            <?= $username ?>
        </div>
        <span class="theme-icon" id="themeIcon">🌙</span>
        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light mode" aria-label="Toggle theme"></button>
    </div>
</header>

<!-- Main content -->
<main class="page-wrapper" role="main">
    <div class="terms-card">

        <!-- Header -->
        <div class="card-header">
            <div class="header-inner">
                <div class="header-badge">
                    📋 &nbsp;Legal Agreement Required
                </div>
                <h1>Terms &amp; Conditions</h1>
                <p>Welcome, <strong style="color:#fff"><?= $username ?></strong>! Please read and accept our terms before using the PILDEMCO Equipment Booking System.</p>
                <div class="effective-date">
                    📅 &nbsp;Effective Date: January 1, 2025 &nbsp;·&nbsp; San Agustin Chapter
                </div>
            </div>
        </div>

        <!-- Scroll progress bar -->
        <div class="scroll-progress-wrap" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="Reading progress">
            <div class="scroll-progress-bar" id="scrollBar"></div>
        </div>

        <!-- Scroll hint -->
        <div class="scroll-hint" id="scrollHint" role="note">
            <span class="scroll-hint-icon">👇</span>
            Please scroll down to read all terms before agreeing
        </div>

        <!-- Terms content -->
        <div class="card-body" id="termsBody" role="document" aria-label="Terms and Conditions content">

            <!-- Section 1 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">📖</div>
                    <div>
                        <span class="section-num">Section 1</span>
                        <div class="section-title">Acceptance of Terms</div>
                    </div>
                </div>
                <p class="terms-text">
                    By accessing and using the <strong>PILDEMCO Equipment Booking and Management System</strong> (hereinafter "the System"), you confirm that you have read, understood, and agreed to be bound by these Terms and Conditions, as well as all applicable laws and regulations.
                </p>
                <div class="highlight-box">
                    If you do not agree to these terms, you must not use this system. Continued use after notification of any changes to these Terms shall constitute acceptance of the revised Terms.
                </div>
            </div>

            <!-- Section 2 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">🌾</div>
                    <div>
                        <span class="section-num">Section 2</span>
                        <div class="section-title">About PILDEMCO</div>
                    </div>
                </div>
                <p class="terms-text">
                    The <strong>Pilipino Demokratiko Multi-Purpose Cooperative (PILDEMCO)</strong>, San Agustin Chapter, is a registered cooperative serving the farming community. This system is provided exclusively to registered member-farmers for the purpose of:
                </p>
                <ul class="terms-list">
                    <li>Booking and scheduling of cooperative-owned agricultural equipment</li>
                    <li>Managing payment transactions (cash or palay)</li>
                    <li>Communicating with cooperative administrators</li>
                    <li>Accessing booking history and payment records</li>
                </ul>
            </div>

            <!-- Section 3 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">🔐</div>
                    <div>
                        <span class="section-num">Section 3</span>
                        <div class="section-title">User Account & Security</div>
                    </div>
                </div>
                <p class="terms-text">
                    As a registered user of this system, you agree to the following obligations regarding your account:
                </p>
                <ul class="terms-list">
                    <li>You are solely responsible for maintaining the confidentiality of your account credentials (email and password).</li>
                    <li>You must not share your account with any other person.</li>
                    <li>You must immediately notify the PILDEMCO administrator of any unauthorized access to your account.</li>
                    <li>You must provide accurate, current, and complete information during registration and keep it updated.</li>
                    <li>You must not create multiple accounts or register under false information.</li>
                </ul>
                <div class="warning-box">
                    ⚠ &nbsp;PILDEMCO reserves the right to suspend or terminate accounts found to be in violation of these terms without prior notice.
                </div>
            </div>

            <!-- Section 4 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">🚜</div>
                    <div>
                        <span class="section-num">Section 4</span>
                        <div class="section-title">Equipment Booking Rules</div>
                    </div>
                </div>
                <p class="terms-text">
                    When booking agricultural equipment through this system, you agree to:
                </p>
                <ul class="terms-list">
                    <li>Submit bookings only for legitimate agricultural use within your registered land area.</li>
                    <li>Honor confirmed bookings — failure to utilize booked equipment without prior cancellation may result in penalties.</li>
                    <li>Cancel bookings at least <strong>24 hours before</strong> the scheduled use date if you are unable to proceed.</li>
                    <li>Treat all cooperative equipment with care and return it in the same condition it was borrowed.</li>
                    <li>Report any equipment damage or malfunction immediately to the cooperative administrator.</li>
                    <li>Adhere to the designated time slots for equipment use.</li>
                </ul>
                <div class="highlight-box">
                    Double-booking or booking on behalf of another person without authorization is strictly prohibited.
                </div>
            </div>

            <!-- Section 5 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">💰</div>
                    <div>
                        <span class="section-num">Section 5</span>
                        <div class="section-title">Payment Terms</div>
                    </div>
                </div>
                <p class="terms-text">
                    All payment obligations arising from equipment bookings must be settled in accordance with the following:
                </p>
                <ul class="terms-list">
                    <li>Payments may be made in <strong>cash (Philippine Peso)</strong> or <strong>palay (unhusked rice)</strong> as agreed during booking.</li>
                    <li>Payment rates are set by the cooperative and are subject to change without prior notice.</li>
                    <li>All payments must be settled within the timeframe specified by the cooperative administrator.</li>
                    <li>Non-payment or delayed payment may result in suspension of booking privileges.</li>
                    <li>Receipts and payment records are maintained in the system and serve as official documentation.</li>
                </ul>
                <div class="warning-box">
                    ⚠ &nbsp;Disputes regarding payment amounts must be raised with the cooperative administrator within <strong>7 days</strong> of the transaction date.
                </div>
            </div>

            <!-- Section 6 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">🔒</div>
                    <div>
                        <span class="section-num">Section 6</span>
                        <div class="section-title">Privacy & Data Use</div>
                    </div>
                </div>
                <p class="terms-text">
                    PILDEMCO is committed to protecting your personal information. By using this system, you consent to:
                </p>
                <ul class="terms-list">
                    <li>Collection of your name, email address, barangay/address, and booking history for system operations.</li>
                    <li>Use of your data to process bookings, payments, and send system notifications.</li>
                    <li>Storage of your data on secured local servers managed by the cooperative.</li>
                    <li>Access to your data by authorized cooperative administrators only.</li>
                </ul>
                <div class="highlight-box">
                    Your personal data will not be shared with third parties without your explicit consent, except as required by law or cooperative regulations.
                </div>
            </div>

            <!-- Section 7 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">🤝</div>
                    <div>
                        <span class="section-num">Section 7</span>
                        <div class="section-title">User Conduct & Responsibilities</div>
                    </div>
                </div>
                <p class="terms-text">
                    All users of this system must conduct themselves professionally and in good faith. You agree <strong>NOT</strong> to:
                </p>
                <ul class="terms-list">
                    <li>Attempt to gain unauthorized access to system data or other users' accounts.</li>
                    <li>Interfere with or disrupt the system's normal operation.</li>
                    <li>Use the messaging feature for harassment, spam, or inappropriate communications.</li>
                    <li>Falsify booking information, payment records, or personal details.</li>
                    <li>Use the system for any unlawful purpose.</li>
                </ul>
            </div>

            <!-- Section 8 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">⚖️</div>
                    <div>
                        <span class="section-num">Section 8</span>
                        <div class="section-title">Limitation of Liability</div>
                    </div>
                </div>
                <p class="terms-text">
                    PILDEMCO shall not be held liable for:
                </p>
                <ul class="terms-list">
                    <li>System downtime due to maintenance, technical issues, or circumstances beyond the cooperative's control.</li>
                    <li>Loss of data due to force majeure events, including natural disasters or power outages.</li>
                    <li>Equipment unavailability due to repairs, emergencies, or prior commitments.</li>
                    <li>Any indirect, incidental, or consequential damages arising from system use.</li>
                </ul>
            </div>

            <!-- Section 9 -->
            <div class="terms-section">
                <div class="section-header">
                    <div class="section-icon">📝</div>
                    <div>
                        <span class="section-num">Section 9</span>
                        <div class="section-title">Amendments to Terms</div>
                    </div>
                </div>
                <p class="terms-text">
                    PILDEMCO reserves the right to modify these Terms and Conditions at any time. Users will be notified of significant changes through the system's notification feature. Continued use of the system after such changes constitutes your acceptance of the new terms.
                </p>
            </div>

            <!-- Section 10 -->
            <div class="terms-section" style="border-bottom:none; margin-bottom:0;">
                <div class="section-header">
                    <div class="section-icon">📞</div>
                    <div>
                        <span class="section-num">Section 10</span>
                        <div class="section-title">Contact & Governing Law</div>
                    </div>
                </div>
                <p class="terms-text">
                    For questions or concerns regarding these Terms, please contact the PILDEMCO administrator through the in-system messaging feature. These Terms and Conditions are governed by the laws of the Republic of the Philippines and applicable cooperative regulations under <strong>Republic Act No. 9520</strong> (Philippine Cooperative Code of 2008).
                </p>
                <div class="highlight-box" style="margin-top:16px; text-align:center;">
                    🌾 &nbsp;<strong>Thank you for being a valued member of PILDEMCO San Agustin Chapter.</strong>&nbsp; 🌾<br>
                    <span style="font-size:.72rem;">Your cooperation helps our farming community thrive.</span>
                </div>
            </div>

        </div><!-- /card-body -->

        <!-- Footer / action area -->
        <div class="card-footer">

            <!-- Notice shown until user scrolls to bottom -->
            <div class="scroll-notice" id="scrollNotice">
                📖 &nbsp;Please scroll through the entire Terms &amp; Conditions above to enable the agreement checkbox.
            </div>

            <!-- Checkbox row (disabled until fully scrolled) -->
            <form method="POST" action="" id="termsForm">
                <div class="footer-checkbox-row" id="checkboxRow">
                    <input type="checkbox" id="agreeCheck" name="agree_check" onchange="onCheckChange()">
                    <label for="agreeCheck">
                        I, <strong><?= $username ?></strong>, have read and fully understood the Terms &amp; Conditions of the PILDEMCO Equipment Booking System, and I voluntarily agree to comply with all the rules and policies stated above.
                    </label>
                </div>

                <div class="btn-row">
                    <button type="submit" name="agree_terms" class="btn-agree" id="btnAgree" disabled>
                        ✅ &nbsp;I Agree – Continue to Dashboard
                    </button>
                    <button type="submit" name="decline_terms" class="btn-decline"
                            onclick="return confirm('Are you sure you want to decline? You will be logged out.')">
                        Decline &amp; Log Out
                    </button>
                </div>
            </form>

        </div><!-- /card-footer -->

    </div><!-- /terms-card -->
</main>

<script>
/* ── Theme ─────────────────────────────────────────────────────────────────── */
(function () {
    const saved = localStorage.getItem('pildemco_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.textContent = saved === 'dark' ? '☀️' : '🌙';
})();

function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'light';
    const next = cur === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('pildemco_theme', next);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.textContent = next === 'dark' ? '☀️' : '🌙';
}

/* ── Scroll-to-bottom logic ────────────────────────────────────────────────── */
const body        = document.getElementById('termsBody');
const scrollBar   = document.getElementById('scrollBar');
const scrollHint  = document.getElementById('scrollHint');
const scrollNotice= document.getElementById('scrollNotice');
const checkboxRow = document.getElementById('checkboxRow');
const btnAgree    = document.getElementById('btnAgree');
let   scrolledToBottom = false;

body.addEventListener('scroll', function () {
    // Update progress bar
    const scrolled  = body.scrollTop;
    const maxScroll = body.scrollHeight - body.clientHeight;
    const pct       = maxScroll > 0 ? Math.min(100, Math.round((scrolled / maxScroll) * 100)) : 100;
    scrollBar.style.width = pct + '%';
    scrollBar.closest('[role="progressbar"]').setAttribute('aria-valuenow', pct);

    // Hide scroll hint after minimal scroll
    if (scrolled > 60) scrollHint.classList.add('hidden');

    // Enable checkbox when user has scrolled ≥ 92% of content
    if (!scrolledToBottom && pct >= 92) {
        scrolledToBottom = true;
        checkboxRow.classList.add('enabled');
        scrollNotice.classList.add('hidden');
    }
});

function onCheckChange() {
    const chk = document.getElementById('agreeCheck');
    btnAgree.disabled = !chk.checked;
}

/* ── Prevent accidental form submit via Enter key on decline ──────────────── */
document.getElementById('termsForm').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        const target = e.target;
        // Only allow submit via the agree button
        if (target.name !== 'agree_terms') e.preventDefault();
    }
});
</script>
</body>
</html>
