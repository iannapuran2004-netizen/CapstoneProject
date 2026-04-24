<?php
// ── reset_passwords.php ───────────────────────────────────────────────────────
// Run ONCE after importing pildemco_database.sql to set real bcrypt hashes
// for all farmer accounts. Then DELETE this file immediately.
// ─────────────────────────────────────────────────────────────────────────────
include 'db.php';

$farmer_pw = 'Farmer@123';
$admin_pw  = 'Admin@1234';

$farmer_hash = password_hash($farmer_pw, PASSWORD_DEFAULT);
$admin_hash  = password_hash($admin_pw,  PASSWORD_DEFAULT);

mysqli_query($db, "UPDATE user_info SET password='$farmer_hash' WHERE user_type='user'");
mysqli_query($db, "UPDATE user_info SET password='$admin_hash'  WHERE user_type='admin'");

echo "<pre style='font-family:monospace;padding:20px;'>";
echo "✅ Passwords updated successfully!\n\n";
echo "ROLE     | EMAIL                    | PASSWORD\n";
echo "---------|--------------------------|------------\n";
echo "Admin    | admin\@pildemco.coop      | $admin_pw\n";
echo "Farmer   | juan\@example.com         | $farmer_pw\n";
echo "Farmer   | maria\@example.com        | $farmer_pw\n";
echo "Farmer   | pedro\@example.com        | $farmer_pw\n";
echo "Farmer   | ana\@example.com          | $farmer_pw\n";
echo "Farmer   | roberto\@example.com      | $farmer_pw\n";
echo "\n⚠️  DELETE this file now! (reset_passwords.php)\n";
echo "</pre>";
?>
