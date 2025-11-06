<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit('Not authorized');
}

$userId = $_SESSION['user_id'];

$currencyCode = $_SESSION['currency'] ?? 'USD';
$symbolMap = [
    'USD' => '$',
    'SAR' => '﷼',
    'EGP' => '£',
    'INR' => '₹',
];
$cur = $symbolMap[$currencyCode] ?? '$';





$start  = $_GET['start_date'] ?? '';
$end    = $_GET['end_date']   ?? '';
$type   = $_GET['type']       ?? 'all';
$method = $_GET['method']     ?? 'all';

$sql = "SELECT 
            t.tx_date,
            t.type,
            t.payment_method,
            t.amount,
            t.description,
            t.created_by,
            c.name AS category_name
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.is_deleted = 0";

$params = [];

if ($tenantMode === 'isolated') {
    $sql .= " AND t.user_id = :uid";
    $params[':uid'] = $userId;
}

if ($start !== '') {
    $sql .= " AND t.tx_date >= :start";
    $params[':start'] = $start;
}

if ($end !== '') {
    $sql .= " AND t.tx_date <= :end";
    $params[':end'] = $end;
}

if ($type !== 'all') {
    $sql .= " AND t.type = :type";
    $params[':type'] = $type;
}

if ($method !== 'all') {
    $sql .= " AND t.payment_method = :pm";
    $params[':pm'] = $method;
}

$sql .= " ORDER BY t.tx_date ASC, t.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = "transactions_" . date('Y-m-d_H-i-s') . ".csv";



header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' .$filename);

$output = fopen('php://output', 'w');
fputcsv($output, ['Date','Type','Payment Method','Amount (' .$cur.')','Description','Created By','Category']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['tx_date'],
        $row['type'],
        $row['payment_method'],
        $row['amount'],
        $row['description'],
        $row['created_by'],
        $row['category_name'],
    ]);
}

fclose($output);
exit;
