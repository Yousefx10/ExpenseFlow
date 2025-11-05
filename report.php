<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$currencyCode = $_SESSION['currency'] ?? 'USD';

$symbolMap = [
    'USD' => '$',
    'SAR' => '﷼',
    'EGP' => '£',
    'INR' => '₹',
];

$cur = $symbolMap[$currencyCode] ?? '$';

// Default filter: this month
$defaultStart = date('Y-m-01');
$defaultEnd   = date('Y-m-t');

// Quick reports ranges
$todayDate = date('Y-m-d');

$weekStartQuick = date('Y-m-d', strtotime('monday this week'));
$weekEndQuick   = date('Y-m-d', strtotime('sunday this week'));


$monthStartQuick = $defaultStart;
$monthEndQuick   = $defaultEnd;

$yearStart = date('Y-01-01');
$yearEnd   = date('Y-12-31');






$start  = $_GET['start_date'] ?? $defaultStart;
$end    = $_GET['end_date']   ?? $defaultEnd;
$type   = $_GET['type']       ?? 'all';   // all, income, expense
$method = $_GET['method']     ?? 'all';   // all, cash, bank

$sql = "SELECT t.*, c.name AS category_name
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = :uid";

$params = [':uid' => $userId];

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


$sql .= " ORDER BY t.tx_date DESC, t.id DESC";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();


$totalIncome  = 0;
$totalExpense = 0;

foreach ($rows as $r) {
    if ((int)$r['is_deleted'] === 1) {
        continue;
    }
    if ($r['type'] === 'income') {
        $totalIncome += $r['amount'];
    } else {
        $totalExpense += $r['amount'];
    }
}

$net = $totalIncome - $totalExpense;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report History - ExpenseFlow</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="logo">Expense<span>Flow</span></div>
<ul class="nav-links">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="report.php">Report History</a></li>
    <li><a href="analysis.php" class="<?= basename($_SERVER['PHP_SELF'])==='analysis.php'?'active':'' ?>">Analysis</a></li>
    <li><a href="settings.php">Settings</a></li>
</ul>

        <div class="user">
            <div><?= htmlspecialchars($userName) ?></div>
            <div class="page-subtitle"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
            <div style="margin-top:8px;">
                <a href="logout.php" class="badge">Logout</a>
            </div>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
                        <button class="topbar-menu-btn" type="button" onclick="toggleSidebar()">
            ☰
            </button>
            <div>
                <div class="page-title">Report History</div>
                <div class="page-subtitle">
                    <?= count($rows) ?> transactions found
                </div>
            </div>
            <div>
                <a class="btn btn-secondary" href="report.php">Clear Filters</a>
                <a class="btn btn-primary"
                   href="export_csv.php?start_date=<?= urlencode($start) ?>&end_date=<?= urlencode($end) ?>&type=<?= urlencode($type) ?>&method=<?= urlencode($method) ?>">
                    Export CSV
                </a>
            </div>
        </div>

        <section class="card">
            <div class="section-title">Filters</div>
            <form method="get">
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start) ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end) ?>">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="all"    <?= $type==='all'?'selected':'' ?>>All Types</option>
                            <option value="income" <?= $type==='income'?'selected':'' ?>>Income</option>
                            <option value="expense"<?= $type==='expense'?'selected':'' ?>>Expense</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="method">
                            <option value="all"  <?= $method==='all'?'selected':'' ?>>All Methods</option>
                            <option value="cash" <?= $method==='cash'?'selected':'' ?>>Cash</option>
                            <option value="bank" <?= $method==='bank'?'selected':'' ?>>Bank</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:12px;">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                </div>
            </form>
        </section>

        <section class="card">
    <div class="section-title">Quick Reports</div>
    <div class="quick-reports-row">
        <a class="btn btn-secondary"
           href="export_csv.php?start_date=<?= urlencode($todayDate) ?>&end_date=<?= urlencode($todayDate) ?>&type=all&method=all">
            Today (CSV)
        </a>

        <a class="btn btn-secondary"
           href="export_csv.php?start_date=<?= urlencode($weekStartQuick) ?>&end_date=<?= urlencode($weekEndQuick) ?>&type=all&method=all">
            Current Week (CSV)
        </a>

        <a class="btn btn-secondary"
           href="export_csv.php?start_date=<?= urlencode($monthStartQuick) ?>&end_date=<?= urlencode($monthEndQuick) ?>&type=all&method=all">
            Current Month (CSV)
        </a>

        <a class="btn btn-secondary"
           href="export_csv.php?start_date=<?= urlencode($yearStart) ?>&end_date=<?= urlencode($yearEnd) ?>&type=all&method=all">
            Current Year (CSV)
        </a>
    </div>
</section>


        <section class="card">
            <div class="report-summary">
                <div class="card">
                    <div class="summary-card-title">Total Income</div>
                    <div class="summary-card-value summary-income">
                        <?= $cur . number_format($totalIncome, 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Expenses</div>
                    <div class="summary-card-value summary-expense">
                        <?= $cur . number_format($totalExpense, 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Net Balance</div>
                    <div class="summary-card-value <?= $net >= 0 ? 'summary-income' : 'summary-expense' ?>">
                        <?= $cur . number_format($net, 2) ?>
                    </div>
                </div>
            </div>

            <div class="tx-list" style="margin-top:16px;">
                <?php if (!$rows): ?>
                    <div class="page-subtitle">No transactions for this filter.</div>
                <?php else: ?>
                    <?php foreach ($rows as $tx): 
                        $isDeleted = (int)$tx['is_deleted'] === 1;
                    ?>
                        <div class="tx-item <?= $isDeleted ? 'deleted' : '' ?>">
                            <div class="tx-main">
                                
                            <?php
$descRaw = $tx['description'] ?: 'No description';
$limit   = 120;
$descShort = (strlen($descRaw) > $limit)
    ? substr($descRaw, 0, $limit) . '…'
    : $descRaw;
?>
<strong class="tx-desc-short"
        data-full-description="<?= htmlspecialchars($descRaw, ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($descShort) ?>
</strong>




                                    <div class="tx-meta">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($tx['tx_date']))) ?>
                                        • <?= ucfirst($tx['payment_method']) ?>
                                        • <?= ucfirst($tx['type']) ?>
                                        <?php if (!empty($tx['category_name'])): ?>
                                            • <?= htmlspecialchars($tx['category_name']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($tx['created_by'])): ?>
                                            • by <?= htmlspecialchars($tx['created_by']) ?>
                                        <?php endif; ?>
                                    </div>


                                <?php if ($isDeleted): ?>
                                    <div class="tx-deleted-label">Deleted</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="tx-amount 
                                    <?= $isDeleted ? 'deleted' : ($tx['type'] === 'income' ? 'income' : 'expense') ?>">
                                    <?= $tx['type'] === 'income' ? '+' : '-' ?>
                                    <?= $cur . number_format($tx['amount'], 2) ?>
                                </span>
                                <span class="tx-actions">
                                    <?php if (!$isDeleted): ?>
                                        <a href="delete_transaction.php?id=<?= $tx['id'] ?>"
                                           onclick="return confirm('Delete this transaction?');">
                                            Delete
                                        </a>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
