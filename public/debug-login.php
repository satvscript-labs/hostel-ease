<?php
header('Content-Type: text/plain');

// Get credentials from query params
$host = $_GET['host'] ?? '127.0.0.1';
$user = $_GET['user'] ?? 'root';
$pass = $_GET['pass'] ?? '';
$db = $_GET['db'] ?? 'hsms';

try {
    echo "Connecting to {$host}:{$db} as {$user}...\n";
    $pdo = new PDO("mysql:host={$host};dbname={$db}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✓ Connected\n";

    echo "\n=== Migration Status ===\n";
    $stmt = $pdo->query("SELECT migration FROM migrations WHERE migration LIKE '%normalize_phone%' LIMIT 1");
    $mig = $stmt->fetch();
    echo $mig ? "✓ Migration RECORDED: " . $mig['migration'] . "\n" : "✗ Migration NOT in migrations table\n";

    echo "\n=== Users (Admin Accounts) ===\n";
    $stmt = $pdo->query("SELECT id, name, mobile, is_active FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($users)) {
        echo "NO USERS FOUND\n";
    } else {
        foreach ($users as $u) {
            echo "ID {$u['id']}: {$u['name']} | Mobile: {$u['mobile']} | Active: " . ($u['is_active'] ? 'YES' : 'NO') . "\n";
        }
    }

    echo "\n=== Checking for +91 prefix in users ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total, COUNT(CASE WHEN mobile LIKE '+91%' THEN 1 END) as with_prefix FROM users");
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total users: {$counts['total']}, With +91: {$counts['with_prefix']}\n";

    echo "\nDone. (File does NOT auto-delete)\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nUsage: ?host=127.0.0.1&user=u174003801_hsms&pass=6:l*s^@P&db=u174003801_hsms\n";
}
