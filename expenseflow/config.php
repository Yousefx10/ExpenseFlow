<?php
// config.php
$host = 'localhost';
$db   = 'expenseflow';   // change to your DB name
$user = 'root';          // DB user
$pass = '';              // DB password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database connection failed.');
}

// Tenant mode: 'shared' (all users see all data) or 'isolated' (each user sees own data).
// Default is 'shared'. If an app_settings table exists with a 'tenant_mode' setting,
// it will override this default.
$tenantMode = 'shared';

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'tenant_mode' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['setting_value'])) {
        $value = strtolower(trim($row['setting_value']));
        if ($value === 'shared' || $value === 'isolated') {
            $tenantMode = $value;
        }
    }
} catch (PDOException $e) {
    // If the app_settings table does not exist or any error occurs,
    // we simply fall back to the default 'shared' mode.
}

// Get company name if shared mode
$companyName = '';
if ($tenantMode === 'shared') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'company_name' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty(trim($row['setting_value']))) {
            $companyName = trim($row['setting_value']);
        }
    } catch (PDOException $e) {
        // ignore
    }
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
