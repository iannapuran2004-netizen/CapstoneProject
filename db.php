<?php
// ── Database Connection ───────────────────────────────────────────────────────
$db = mysqli_connect("localhost", "root", "", "Pildemco_Database");

if (!$db) {
    die("<div style='font-family:sans-serif;padding:40px;text-align:center;color:#dc2626;'>
        <h2>⚠ Database Connection Failed</h2>
        <p>" . mysqli_connect_error() . "</p>
        <p style='color:#888;font-size:.85rem;margin-top:8px'>Make sure MySQL is running and the database <strong>Pildemco_Database</strong> exists.</p>
    </div>");
}

mysqli_set_charset($db, "utf8mb4");
?>
