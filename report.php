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

$currencyDisplayMode = $_SESSION['currency_display'] ?? 'symbol';
$currencySymbol = $symbolMap[$currencyCode] ?? '$';
$currencyPrefix = $currencyDisplayMode === 'symbol' ? $currencySymbol : $currencyCode . ' ';

$view = $_GET['view'] ?? 'cash';
$view = $view === 'finance' ? 'finance' : 'cash';

// Default filter: this month
$defaultStart = date('Y-m-01');
$defaultEnd   = date('Y-m-t');

// Quick reports ranges
$todayDate = date('Y-m-d');

$weekStartQuick = date('Y-m-d', strtotime('sunday last week +1 day'));
$weekEndQuick   = date('Y-m-d', strtotime('saturday this week'));


$monthStartQuick = $defaultStart;
$monthEndQuick   = $defaultEnd;

$yearStart = date('Y-01-01');
$yearEnd   = date('Y-12-31');







$start  = $_GET['start_date'] ?? $defaultStart;
$end    = $_GET['end_date']   ?? $defaultEnd;
$type   = $_GET['type']       ?? 'all';   // all, income, expense
$method = $_GET['method']     ?? 'all';   // all, cash, bank
$categoryFilter = $_GET['category_id'] ?? '';

if ($tenantMode === 'isolated') {
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = :uid ORDER BY name ASC");
    $stmt->execute([':uid' => $userId]);
} else {
    $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
    $stmt->execute();
}
$categoryOptions = $stmt->fetchAll();

$rows = [];
$totalIncome  = 0;
$totalExpense = 0;
$net = 0;

$financeRows = [];
$financeSummary = [
    'total_expense' => 0,
    'total_cost'    => 0,
    'total_income'  => 0,
];
$financeNet = 0;

if ($view === 'cash') {
    $sql = "SELECT t.*, c.name AS category_name, b.name AS bank_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN bank_accounts b ON t.bank_id = b.id
            WHERE 1=1";

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

    $sql .= " ORDER BY t.tx_date DESC, t.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

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
} else {
    $financeWhereList = "WHERE 1=1";
    $financeParams = [];

    if ($tenantMode === 'isolated') {
        $financeWhereList .= " AND fb.user_id = :uid";
        $financeParams[':uid'] = $userId;
    }

    if ($start !== '') {
        $financeWhereList .= " AND fb.tx_date >= :start";
        $financeParams[':start'] = $start;
    }

    if ($end !== '') {
        $financeWhereList .= " AND fb.tx_date <= :end";
        $financeParams[':end'] = $end;
    }

    $financeCategoryFilter = $_GET['category_id'] ?? '';
    if ($financeCategoryFilter !== '') {
        $financeWhereList .= " AND fb.category_id = :fcat";
        $financeParams[':fcat'] = (int)$financeCategoryFilter;
    }

    $financeSql = "
        SELECT fb.*, u.name AS user_name, c.name AS category_name, b.name AS bank_name
        FROM finance_book fb
        LEFT JOIN users u ON fb.user_id = u.id
        LEFT JOIN categories c ON fb.category_id = c.id
        LEFT JOIN bank_accounts b ON fb.bank_id = b.id
        $financeWhereList
        ORDER BY fb.tx_date DESC, fb.id DESC
    ";
    $stmtFinance = $pdo->prepare($financeSql);
    $stmtFinance->execute($financeParams);
    $financeRows = $stmtFinance->fetchAll();

    $financeSummarySql = "
        SELECT
            COALESCE(SUM(fb.cost), 0)    AS total_cost,
            COALESCE(SUM(fb.expense), 0) AS total_expense,
            COALESCE(SUM(fb.profit), 0)  AS total_income
        FROM finance_book fb
        " . $financeWhereList . " AND fb.is_deleted = 0
    ";
    $stmtSummary = $pdo->prepare($financeSummarySql);
    $stmtSummary->execute($financeParams);
    $financeSummary = $stmtSummary->fetch() ?: $financeSummary;
    $financeNet = $financeSummary['total_income'] - $financeSummary['total_expense'] - $financeSummary['total_cost'];
}

if ($view === 'finance') {
    $clearFiltersUrl = 'report.php?view=finance';
    $exportUrl = sprintf(
        'export_csv.php?dataset=finance&start_date=%s&end_date=%s',
        urlencode($start),
        urlencode($end)
    );
    $printUrl = sprintf(
        'report_print.php?view=finance&start_date=%s&end_date=%s',
        urlencode($start),
        urlencode($end)
    );
    if ($financeCategoryFilter !== '') {
        $clearFiltersUrl .= '&category_id=' . urlencode($financeCategoryFilter);
        $exportUrl      .= '&category_id=' . urlencode($financeCategoryFilter);
        $printUrl       .= '&category_id=' . urlencode($financeCategoryFilter);
    }
} else {
    $clearFiltersUrl = 'report.php?view=cash';
    $exportUrl = sprintf(
        'export_csv.php?start_date=%s&end_date=%s&type=%s&method=%s',
        urlencode($start),
        urlencode($end),
        urlencode($type),
        urlencode($method)
    );
    $printUrl = sprintf(
        'report_print.php?view=cash&start_date=%s&end_date=%s&type=%s&method=%s',
        urlencode($start),
        urlencode($end),
        urlencode($type),
        urlencode($method)
    );
    if ($categoryFilter !== '') {
        $clearFiltersUrl .= '&category_id=' . urlencode($categoryFilter);
        $exportUrl      .= '&category_id=' . urlencode($categoryFilter);
        $printUrl       .= '&category_id=' . urlencode($categoryFilter);
    }
}
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
        <div class="logo">
    Expense<span>Flow</span>
    <?php if (!empty($companyName)): ?>
        <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
    <?php endif; ?>
</div>

<ul class="nav-links">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="financebook.php">Finance Book</a></li>
    <li><a href="report.php" class="active">Report History</a></li>
    <li><a href="analysis.php">Analysis</a></li>
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
                <?php if ($view === 'cash'): ?>
                    <div class="page-subtitle">
                        <?= count($rows) ?> transactions found
                    </div>
                <?php else: ?>
                    <div class="page-subtitle">
                        <?= count($financeRows) ?> finance entries found
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <a class="btn btn-secondary" href="<?= htmlspecialchars($clearFiltersUrl) ?>">Clear Filters</a>
                <a class="btn btn-secondary" href="<?= htmlspecialchars($printUrl) ?>" target="_blank" rel="noopener">Print</a>
                <a class="btn btn-primary" href="<?= htmlspecialchars($exportUrl) ?>">Export CSV</a>
            </div>
        </div>
        <div class="report-tabs">
            <a class="report-tab <?= $view === 'cash' ? 'active' : '' ?>" href="report.php?view=cash">
                Cash Flow Report
            </a>
            <a class="report-tab <?= $view === 'finance' ? 'active' : '' ?>" href="report.php?view=finance">
                Finance Report
            </a>
        </div>

        <?php if ($view === 'cash'): ?>
        <section class="card">
            <div class="section-title">Filters</div>
            <form method="get">
                <input type="hidden" name="view" value="cash">
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
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="" <?= $categoryFilter===''?'selected':'' ?>>All Categories</option>
                            <?php foreach ($categoryOptions as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryFilter===(string)$cat['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
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
                        <?= $currencyPrefix . number_format($totalIncome, 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Expenses</div>
                    <div class="summary-card-value summary-expense">
                        <?= $currencyPrefix . number_format($totalExpense, 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Net Balance</div>
                    <div class="summary-card-value <?= $net >= 0 ? 'summary-income' : 'summary-expense' ?>">
                        <?= $currencyPrefix . number_format($net, 2) ?>
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
                                        <?php if ($tx['payment_method'] === 'bank'): ?>
                                            • Bank: <?= htmlspecialchars($tx['bank_name'] ?? 'N/A') ?>
                                        <?php endif; ?>
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
                                    <?= $currencyPrefix . number_format($tx['amount'], 2) ?>
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
        <?php else: ?>
        <section class="card">
            <div class="section-title">Filters</div>
            <form method="get">
                <input type="hidden" name="view" value="finance">
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
                        <label>Category</label>
                        <select name="category_id">
                            <option value="" <?= ($financeCategoryFilter ?? '')===''?'selected':'' ?>>All Categories</option>
                            <?php foreach ($categoryOptions as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($financeCategoryFilter ?? '')===(string)$cat['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:12px;">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="section-title">Quick Ranges</div>
            <div class="quick-reports-row">
                <a class="btn btn-secondary"
                   href="report.php?view=finance&start_date=<?= urlencode($todayDate) ?>&end_date=<?= urlencode($todayDate) ?>">
                    Today
                </a>
                <a class="btn btn-secondary"
                   href="report.php?view=finance&start_date=<?= urlencode($weekStartQuick) ?>&end_date=<?= urlencode($weekEndQuick) ?>">
                    This Week
                </a>
                <a class="btn btn-secondary"
                   href="report.php?view=finance&start_date=<?= urlencode($monthStartQuick) ?>&end_date=<?= urlencode($monthEndQuick) ?>">
                    This Month
                </a>
                <a class="btn btn-secondary"
                   href="report.php?view=finance&start_date=<?= urlencode($yearStart) ?>&end_date=<?= urlencode($yearEnd) ?>">
                    This Year
                </a>
            </div>
        </section>

        <section class="card">
            <div class="report-summary">
                <div class="card">
                    <div class="summary-card-title">Total Cost</div>
                    <div class="summary-card-value summary-balance">
                        <?= $currencyPrefix . number_format($financeSummary['total_cost'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Expense</div>
                    <div class="summary-card-value summary-expense">
                        <?= $currencyPrefix . number_format($financeSummary['total_expense'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Total Income</div>
                    <div class="summary-card-value summary-income">
                        <?= $currencyPrefix . number_format($financeSummary['total_income'], 2) ?>
                    </div>
                </div>
                <div class="card">
                    <div class="summary-card-title">Net Profit</div>
                    <div class="summary-card-value <?= $financeNet >= 0 ? 'summary-income' : 'summary-expense' ?>">
                        <?= $currencyPrefix . number_format($financeNet, 2) ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="section-title">Finance Entries</div>
            <?php if (!$financeRows): ?>
                <div class="page-subtitle" style="margin-top:8px;">No finance entries for this filter.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Cost</th>
                                <th>Expense</th>
                                <th>Income</th>
                                <th>Method</th>
                                <th>Net</th>
                                <th>Category</th>
                                <th>Bank</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($financeRows as $entry):
                                $netRow = ($entry['profit'] ?? 0) - ($entry['expense'] ?? 0) - ($entry['cost'] ?? 0);
                                $title = $entry['title'] ?: 'Untitled';
                                $isDeletedFinance = (int)($entry['is_deleted'] ?? 0) === 1;
                            ?>
                        <tr class="finance-row <?= $isDeletedFinance ? 'finance-row-deleted' : '' ?>"
                                 data-finance-entry='<?= htmlspecialchars(json_encode([
                                     'date'        => date('M j, Y', strtotime($entry['tx_date'])),
                                     'raw_date'    => $entry['tx_date'],
                                     'title'       => $title,
                                     'cost'        => (float)$entry['cost'],
                                     'expense'     => (float)$entry['expense'],
                                     'income'      => (float)$entry['profit'],
                                     'net'         => $netRow,
                                     'description' => $entry['description'] ?: 'No description provided.',
                                     'method'      => ucfirst($entry['payment_method'] ?? 'cash'),
                                     'category'    => $entry['category_name'] ?? 'No category',
                                     'bank'        => $entry['bank_name'] ?? 'No bank',
                                    'creator'     => $entry['user_name'] ?? 'Unknown',
                                    'is_deleted'  => $isDeletedFinance ? 1 : 0,
                                    'delete_url'  => $isDeletedFinance ? null : "delete_finance_entry.php?id=" . $entry['id'],
                                ]), ENT_QUOTES, 'UTF-8') ?>'
                                data-currency="<?= htmlspecialchars($currencySymbol) ?>">
                                <td><?= htmlspecialchars(date('M j, Y', strtotime($entry['tx_date']))) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($title) ?></div>
                                    <?php if ($isDeletedFinance): ?>
                                        <div class="tx-deleted-label" style="display:inline-block;margin-top:4px;">Deleted</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $currencyPrefix . number_format($entry['cost'], 2) ?></td>
                                <td><?= $currencyPrefix . number_format($entry['expense'], 2) ?></td>
                                <td><?= $currencyPrefix . number_format($entry['profit'], 2) ?></td>
                                <td><?= htmlspecialchars(ucfirst($entry['payment_method'] ?? 'cash')) ?></td>
                                <td class="<?= $netRow >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $currencyPrefix . number_format($netRow, 2) ?>
                                </td>
                                <td><?= htmlspecialchars($entry['category_name'] ?? 'No category') ?></td>
                                <td><?= htmlspecialchars($entry['bank_name'] ?? 'No bank') ?></td>
                                <td><?= htmlspecialchars($entry['user_name'] ?? 'Unknown') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if ($view === 'finance'): ?>
        <div class="modal-backdrop" id="financeModalBackdrop"></div>
        <div class="modal" id="financeModal" role="dialog" aria-modal="true" aria-labelledby="financeModalTitle">
            <div class="modal-card">
                <div class="modal-header">
                    <div>
                        <div class="modal-title" id="financeModalTitle">Entry Details</div>
                        <div class="modal-subtitle" id="financeModalDate"></div>
                    </div>
                    <button class="modal-close" type="button" id="financeModalClose">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="modal-field">
                        <span class="field-label">Title</span>
                        <span class="field-value" id="financeModalFieldTitle"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Cost</span>
                        <span class="field-value" id="financeModalFieldCost"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Expense</span>
                        <span class="field-value" id="financeModalFieldExpense"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Income</span>
                        <span class="field-value" id="financeModalFieldIncome"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Payment Method</span>
                        <span class="field-value" id="financeModalFieldMethod"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Net</span>
                        <span class="field-value" id="financeModalFieldNet"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Category</span>
                        <span class="field-value" id="financeModalFieldCategory"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Bank</span>
                        <span class="field-value" id="financeModalFieldBank"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Created By</span>
                        <span class="field-value" id="financeModalFieldCreator"></span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">Description</span>
                        <span class="field-value" id="financeModalFieldDescription"></span>
                    </div>
                    <div class="modal-field">
                        <button type="button" class="btn btn-danger" id="financeModalDelete">Delete Entry</button>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" type="button" id="financeModalCloseFooter">Close</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
