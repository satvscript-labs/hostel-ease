<?php
header('Content-Type: text/plain');

$host = $_GET['host'] ?? '127.0.0.1';
$user = $_GET['user'] ?? 'root';
$pass = $_GET['pass'] ?? '';
$db = $_GET['db'] ?? 'hsms';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "Fixing users without +91 prefix...\n";

    // Update users table
    $stmt = $pdo->prepare("
        UPDATE users
        SET mobile = CONCAT('+91', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '+91', ''), '91', ''), '-', ''), ' ', ''), -10))
        WHERE mobile NOT LIKE '+91%' AND mobile IS NOT NULL AND mobile != ''
    ");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "✓ Updated {$count} user(s) in users table\n";

    // Show updated users
    echo "\n=== Updated Users ===\n";
    $stmt = $pdo->query("SELECT id, name, mobile, is_active FROM users WHERE id = 11");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        echo "ID {$u['id']}: {$u['name']} | Mobile: {$u['mobile']} | Active: " . ($u['is_active'] ? 'YES' : 'NO') . "\n";
    }

    echo "\nDone!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
