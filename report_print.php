<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId       = $_SESSION['user_id'];
$userName     = $_SESSION['user_name'] ?? 'User';
$currencyCode = $_SESSION['currency'] ?? 'USD';

$symbolMap = [
    'USD' => '$',
    'SAR' => '﷼',
    'EGP' => '£',
    'INR' => '₹',
];
$currencyDisplayMode = $_SESSION['currency_display'] ?? 'symbol';
$currencySymbol = $symbolMap[$currencyCode] ?? '$';
$currencyPrefix = $currencyDisplayMode === 'symbol' ? $currencySymbol : $currencyCode . ' ';

$view = $_GET['view'] ?? 'cash';
$view = $view === 'finance' ? 'finance' : 'cash';

$defaultStart = date('Y-m-01');
$defaultEnd   = date('Y-m-t');
$todayDate    = date('Y-m-d');
$weekStartQuick = date('Y-m-d', strtotime('sunday last week +1 day'));
$weekEndQuick   = date('Y-m-d', strtotime('saturday this week'));
$yearStart = date('Y-01-01');
$yearEnd   = date('Y-12-31');

$start  = $_GET['start_date'] ?? $defaultStart;
$end    = $_GET['end_date']   ?? $defaultEnd;

$rows = [];
$summary = [
    'total_income'  => 0,
    'total_expense' => 0,
    'total_cost'    => 0,
];
$netValue = 0;

if ($view === 'cash') {
    $type   = $_GET['type']   ?? 'all';
    $method = $_GET['method'] ?? 'all';
    $categoryFilter = $_GET['category_id'] ?? '';

    $sql = "SELECT t.*, c.name AS category_name, b.name AS bank_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN bank_accounts b ON t.bank_id = b.id
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

    if ($categoryFilter !== '') {
        $sql .= " AND t.category_id = :cat";
        $params[':cat'] = (int)$categoryFilter;
    }

    $sql .= " ORDER BY t.tx_date ASC, t.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        if ((int)$row['is_deleted'] === 1) {
            continue;
        }
        if ($row['type'] === 'income') {
            $summary['total_income'] += $row['amount'];
        } else {
            $summary['total_expense'] += $row['amount'];
        }
    }

    $netValue = $summary['total_income'] - $summary['total_expense'];
    $rangeLabel = sprintf('Cash Flow · %s → %s', htmlspecialchars($start), htmlspecialchars($end));
    $tableTitle = 'Cash Transactions';
} else {
    $financeWhere = "WHERE 1=1";
    $financeParams = [];
    $categoryFilter = $_GET['category_id'] ?? '';

    if ($tenantMode === 'isolated') {
        $financeWhere .= " AND fb.user_id = :uid";
        $financeParams[':uid'] = $userId;
    }

    if ($start !== '') {
        $financeWhere .= " AND fb.tx_date >= :start";
        $financeParams[':start'] = $start;
    }

    if ($end !== '') {
        $financeWhere .= " AND fb.tx_date <= :end";
        $financeParams[':end'] = $end;
    }

    if ($categoryFilter !== '') {
        $financeWhere .= " AND fb.category_id = :cat";
        $financeParams[':cat'] = (int)$categoryFilter;
    }

    $financeSql = "
        SELECT fb.*, u.name AS user_name, c.name AS category_name, b.name AS bank_name
        FROM finance_book fb
        LEFT JOIN users u ON fb.user_id = u.id
        LEFT JOIN categories c ON fb.category_id = c.id
        LEFT JOIN bank_accounts b ON fb.bank_id = b.id
        $financeWhere
        ORDER BY fb.tx_date ASC, fb.id ASC
    ";

    $stmt = $pdo->prepare($financeSql);
    $stmt->execute($financeParams);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        if ((int)$row['is_deleted'] === 1) {
            continue;
        }
        $summary['total_cost']    += $row['cost'];
        $summary['total_expense'] += $row['expense'];
        $summary['total_income']  += $row['profit'];
    }

    $netValue = $summary['total_income'] - $summary['total_expense'] - $summary['total_cost'];
    $rangeLabel = sprintf('Finance Book · %s → %s', htmlspecialchars($start), htmlspecialchars($end));
    $tableTitle = 'Finance Entries';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Printable Report - ExpenseFlow</title>
    <style>
        :root {
            color-scheme: light;
        }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 32px;
            padding-bottom: 10%;
            background: #ffffff;
            color: #0f172a;
        }
        h1 {
            font-size: 22px;
            margin-bottom: 4px;
        }
        .meta {
            font-size: 13px;
            color: #475569;
            margin-bottom: 16px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
        }
        .summary-card-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .summary-card-value {
            font-size: 18px;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 13px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }
        .desc-row td {
            background: #fdf2f8;
            color: #7c2d12;
            font-style: italic;
        }
        .print-deleted {
            opacity: 0.6;
        }

        .print-deleted td {
            text-decoration: line-through;
            color: #94a3b8;
        }
        .no-print {
            margin-bottom: 16px;
        }
        .no-print button {
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid #0ea5e9;
            background: #0ea5e9;
            color: #ffffff;
            cursor: pointer;
        }
        @media print {
            .no-print { display: none; }
            body { padding-bottom: 10%; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
    <h1><?= htmlspecialchars($tableTitle) ?></h1>
    <div class="meta">
        <?= $rangeLabel ?> · Generated by <?= htmlspecialchars($userName) ?> on <?= date('M j, Y H:i') ?>
    </div>

    <div class="summary-grid">
        <?php if ($view === 'cash'): ?>
            <div class="summary-card">
                <div class="summary-card-title">Total Income</div>
                <div class="summary-card-value"><?= $currencyPrefix . number_format($summary['total_income'], 2) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Expense</div>
                <div class="summary-card-value"><?= $currencyPrefix . number_format($summary['total_expense'], 2) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Net Balance</div>
                <div class="summary-card-value"><?= $currencyPrefix . number_format($netValue, 2) ?></div>
            </div>
        <?php else: ?>
            <div class="summary-card">
                <div class="summary-card-title">Total Cost</div>
                <div class="summary-card-value"><?= $currencyPrefix . number_format($summary['total_cost'], 2) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Expense</div>
                <div class="summary-card-value"><?= $currencyPrefix . number_format($summary['total_expense'], 2) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Income</div>
                <div class="summary-card-value"><?= $currencyPrefix . number_format($summary['total_income'], 2) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Net Profit</div>
                <div class="summary-card-value"><?= $currencyPrefix . number_format($netValue, 2) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
        <div class="meta">No records for this filter.</div>
    <?php else: ?>
        <table>
            <thead>
                <?php if ($view === 'cash'): ?>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Payment</th>
                        <th>Bank</th>
                        <th>Amount</th>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Cost</th>
                        <th>Expense</th>
                        <th>Income</th>
                        <th>Method</th>
                        <th>Bank</th>
                        <th>Net</th>
                    </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php if ($view === 'cash'):
                        $amountSign = $row['type'] === 'income' ? '+' : '-';
                        $amountDisplay = $amountSign . $currencyPrefix . number_format($row['amount'], 2);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($row['tx_date']))) ?></td>
                            <td><?= htmlspecialchars(ucfirst($row['type'])) ?></td>
                            <td><?= htmlspecialchars(ucfirst($row['payment_method'])) ?></td>
                            <td><?= htmlspecialchars($row['bank_name'] ?? '—') ?></td>
                            <td><?= $amountDisplay ?></td>
                        </tr>
                        <tr class="desc-row">
                            <td colspan="5">
                                <div>
                                    <strong>Category:</strong> <?= htmlspecialchars($row['category_name'] ?? 'No category') ?>
                                    &nbsp;•&nbsp;
                                    <strong>Created By:</strong> <?= htmlspecialchars($row['created_by'] ?: 'Unknown') ?>
                                </div>
                                <div>
                                    <strong>Description:</strong>
                                    <?= htmlspecialchars($row['description'] ?: 'No description provided.') ?>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $netRow = ($row['profit'] ?? 0) - ($row['expense'] ?? 0) - ($row['cost'] ?? 0);
                        ?>
                        <tr class="<?= isset($row['is_deleted']) && (int)$row['is_deleted'] === 1 ? 'print-deleted' : '' ?>">
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($row['tx_date']))) ?></td>
                            <td><?= htmlspecialchars($row['title'] ?: 'Untitled') ?></td>
                            <td><?= $currencyPrefix . number_format($row['cost'], 2) ?></td>
                            <td><?= $currencyPrefix . number_format($row['expense'], 2) ?></td>
                            <td><?= $currencyPrefix . number_format($row['profit'], 2) ?></td>
                            <td><?= htmlspecialchars(ucfirst($row['payment_method'] ?? 'cash')) ?></td>
                            <td><?= htmlspecialchars($row['bank_name'] ?? '—') ?></td>
                            <td><?= $currencyPrefix . number_format($netRow, 2) ?></td>
                        </tr>
                        <tr class="desc-row <?= isset($row['is_deleted']) && (int)$row['is_deleted'] === 1 ? 'print-deleted' : '' ?>">
                            <td colspan="8">
                                <div>
                                    <strong>Category:</strong> <?= htmlspecialchars($row['category_name'] ?? 'No category') ?>
                                    &nbsp;•&nbsp;
                                    <strong>Created By:</strong> <?= htmlspecialchars($row['user_name'] ?? 'Unknown') ?>
                                </div>
                                <div>
                                    <strong>Description:</strong>
                                    <?= htmlspecialchars($row['description'] ?: 'No description provided.') ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <script>
        window.addEventListener('load', function () {
            if (window.matchMedia) {
                window.print();
            }
        });
    </script>
</body>
</html>
