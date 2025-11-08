<?php
// analysis.php
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
$currencyLabel = $currencyDisplayMode === 'symbol' ? $currencySymbol : $currencyCode;

$todayDate = date('Y-m-d');
$weekStartQuick = date('Y-m-d', strtotime('sunday last week +1 day'));
$weekEndQuick   = date('Y-m-d', strtotime('saturday this week'));
$monthStartQuick = date('Y-m-01');
$monthEndQuick   = date('Y-m-t');
$yearStart = date('Y-01-01');
$yearEnd   = date('Y-12-31');

$start  = $_GET['start_date'] ?? $monthStartQuick;
$end    = $_GET['end_date']   ?? $monthEndQuick;


$expenseSql = "SELECT t.category_id, c.name AS category_name, SUM(t.amount) AS total_amount
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.is_deleted = 0
          AND t.type = 'expense'
          AND t.tx_date BETWEEN :start AND :end";

$expenseParams = [
    ':start' => $start,
    ':end'   => $end,
];

if ($tenantMode === 'isolated') {
    $expenseSql .= " AND t.user_id = :uid";
    $expenseParams[':uid'] = $userId;
}

$expenseSql .= " GROUP BY t.category_id, c.name
          ORDER BY total_amount DESC";

$stmt = $pdo->prepare($expenseSql);
$stmt->execute($expenseParams);

$expenseRows = $stmt->fetchAll();

$expenseLabels = [];
$expenseValues = [];
foreach ($expenseRows as $r) {
    $expenseLabels[] = $r['category_name'] !== null && $r['category_name'] !== '' ? $r['category_name'] : 'Uncategorized';
    $expenseValues[] = (float) $r['total_amount'];
}

$totalExpense = array_sum($expenseValues);

$incomeSql = "SELECT t.category_id, c.name AS category_name, SUM(t.amount) AS total_amount
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.is_deleted = 0
          AND t.type = 'income'
          AND t.tx_date BETWEEN :start AND :end";

$incomeParams = [
    ':start' => $start,
    ':end'   => $end,
];

if ($tenantMode === 'isolated') {
    $incomeSql .= " AND t.user_id = :uid";
    $incomeParams[':uid'] = $userId;
}

$incomeSql .= " GROUP BY t.category_id, c.name
          ORDER BY total_amount DESC";

$stmtIncome = $pdo->prepare($incomeSql);
$stmtIncome->execute($incomeParams);

$incomeRows = $stmtIncome->fetchAll();

$incomeLabels = [];
$incomeValues = [];
foreach ($incomeRows as $r) {
    $incomeLabels[] = $r['category_name'] !== null && $r['category_name'] !== '' ? $r['category_name'] : 'Uncategorized';
    $incomeValues[] = (float) $r['total_amount'];
}

$financeTotals = [
    'cost'    => 0,
    'expense' => 0,
    'income'  => 0,
];

$financeSql = "
    SELECT
        COALESCE(SUM(fb.cost), 0)    AS total_cost,
        COALESCE(SUM(fb.expense), 0) AS total_expense,
        COALESCE(SUM(fb.profit), 0)  AS total_income
    FROM finance_book fb
    WHERE fb.tx_date BETWEEN :start AND :end
      AND fb.is_deleted = 0
";
$financeParams = [
    ':start' => $start,
    ':end'   => $end,
];

if ($tenantMode === 'isolated') {
    $financeSql .= " AND fb.user_id = :uid";
    $financeParams[':uid'] = $userId;
}

$stmtFinance = $pdo->prepare($financeSql);
$stmtFinance->execute($financeParams);
$financeRow = $stmtFinance->fetch();
if ($financeRow) {
    $financeTotals['cost']    = (float)$financeRow['total_cost'];
    $financeTotals['expense'] = (float)$financeRow['total_expense'];
    $financeTotals['income']  = (float)$financeRow['total_income'];
}

$financeHasData = ($financeTotals['expense'] + $financeTotals['cost'] + $financeTotals['income']) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analysis - ExpenseFlow</title>
    <link rel="stylesheet" href="assets/style.css">
    <!-- Chart.js  -->
    <script src="assets/chart.js"></script>
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
            <li><a href="report.php">Report History</a></li>
            <li><a href="analysis.php" class="active">Analysis</a></li>
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
                <div class="page-title">Analysis</div>
                <div class="page-subtitle">
                    Reporting period: <?= htmlspecialchars($start) ?> → <?= htmlspecialchars($end) ?>
                </div>
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
                   href="analysis.php?start_date=<?= urlencode($todayDate) ?>&end_date=<?= urlencode($todayDate) ?>">
                    Today
                </a>
                <a class="btn btn-secondary"
                   href="analysis.php?start_date=<?= urlencode($weekStartQuick) ?>&end_date=<?= urlencode($weekEndQuick) ?>">
                    This Week
                </a>
                <a class="btn btn-secondary"
                   href="analysis.php?start_date=<?= urlencode($monthStartQuick) ?>&end_date=<?= urlencode($monthEndQuick) ?>">
                    This Month
                </a>
                <a class="btn btn-secondary"
                   href="analysis.php?start_date=<?= urlencode($yearStart) ?>&end_date=<?= urlencode($yearEnd) ?>">
                    This Year
                </a>
            </div>
        </section>

        <section class="card">
            <div class="section-title">Visualization Mode</div>
            <div class="toggle-group">
                <button type="button" class="toggle-btn active" data-panel="cash-panel">Money Flow</button>
                <button type="button" class="toggle-btn" data-panel="finance-panel">Finance Book</button>
            </div>
        </section>

        <section class="card" id="cash-panel">
            <div class="section-title">Cash Flow Charts</div>
            <div class="toggle-group" style="margin-bottom:12px;">
                <button type="button" class="toggle-btn active" data-chart="expenses">Expenses</button>
                <button type="button" class="toggle-btn" data-chart="income">Income</button>
            </div>

            <div data-chart-view="expenses">
                <?php if (empty($expenseLabels)): ?>
                    <div class="page-subtitle" style="margin-top:8px;">No expense data for this period.</div>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="expenseChart"></canvas>
                    </div>
                    <div class="legend-list">
                        <?php foreach ($expenseLabels as $i => $lbl): ?>
                            <div class="legend-item">
                                <span class="legend-swatch" id="expense-swatch-<?= $i ?>"></span>
                                <span><?= htmlspecialchars($lbl) ?></span>
                            <span style="margin-left:auto;font-weight:600;">
                                <?= $currencyPrefix . number_format($expenseValues[$i], 2) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div data-chart-view="income" style="display:none;">
                <?php if (empty($incomeLabels)): ?>
                    <div class="page-subtitle" style="margin-top:8px;">No income data for this period.</div>
                <?php else: ?>
                    <div class="chart-wrap chart-wrap-wide">
                        <canvas id="incomeChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" id="finance-panel" style="display:none;">
            <div class="section-title">Finance Book Breakdown</div>
            <?php if (!$financeHasData): ?>
                <div class="page-subtitle" style="margin-top:8px;">No finance entries for this period.</div>
            <?php else: ?>
                <div class="chart-wrap">
                    <canvas id="financeBreakdown"></canvas>
                </div>
                <div class="legend-list">
                    <?php
                    $financeLabels = ['Cost', 'Expense', 'Income'];
                    $financeValuesList = [
                        $financeTotals['cost'],
                        $financeTotals['expense'],
                        $financeTotals['income'],
                    ];
                    $financeColors = ['#0ea5e9', '#dc2626', '#16a34a'];
                    foreach ($financeLabels as $idx => $lbl):
                    ?>
                    <div class="legend-item">
                        <span class="legend-swatch" style="background: <?= $financeColors[$idx] ?>"></span>
                        <span><?= $lbl ?></span>
                        <span style="margin-left:auto;font-weight:600;">
                            <?= $currencyPrefix . number_format($financeValuesList[$idx], 2) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<script>
const ANALYSIS_DATA = {
    currency: <?= json_encode($currencyLabel) ?>,
    expense: {
        labels: <?= json_encode($expenseLabels) ?>,
        values: <?= json_encode($expenseValues) ?>
    },
    income: {
        labels: <?= json_encode($incomeLabels) ?>,
        values: <?= json_encode($incomeValues) ?>
    },
    finance: {
        values: <?= json_encode([
            $financeTotals['cost'],
            $financeTotals['expense'],
            $financeTotals['income']
        ]) ?>,
        hasData: <?= $financeHasData ? 'true' : 'false' ?>
    }
};
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const panelButtons = document.querySelectorAll('.toggle-group button[data-panel]');
    const panels = {
        'cash-panel': document.getElementById('cash-panel'),
        'finance-panel': document.getElementById('finance-panel')
    };

    panelButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            panelButtons.forEach(b => b.classList.toggle('active', b === btn));
            Object.keys(panels).forEach(id => {
                if (panels[id]) {
                    panels[id].style.display = (id === btn.dataset.panel) ? '' : 'none';
                }
            });
        });
    });

    const chartButtons = document.querySelectorAll('#cash-panel .toggle-group button[data-chart]');
    const chartViews = document.querySelectorAll('#cash-panel [data-chart-view]');
    chartButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            chartButtons.forEach(b => b.classList.toggle('active', b === btn));
            chartViews.forEach(view => {
                view.style.display = view.getAttribute('data-chart-view') === btn.dataset.chart ? '' : 'none';
            });
        });
    });

    const expenseCanvas = document.getElementById('expenseChart');
    const incomeCanvas = document.getElementById('incomeChart');
    const financeCanvas = document.getElementById('financeBreakdown');

    let expenseChartInstance = null;
    let incomeChartInstance = null;
    let financeChartInstance = null;

    const colorFromIndex = (i) => `hsl(${(i * 43) % 360} 70% 55%)`;

    if (expenseCanvas && ANALYSIS_DATA.expense.labels.length) {
        const colors = ANALYSIS_DATA.expense.labels.map((_, i) => colorFromIndex(i));
        expenseChartInstance = new Chart(expenseCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ANALYSIS_DATA.expense.labels,
                datasets: [{
                    data: ANALYSIS_DATA.expense.values,
                    backgroundColor: colors,
                    borderColor: colors.map((c) => c.replace('55%', '35%')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const val = context.parsed;
                                return `${context.label}: ${ANALYSIS_DATA.currency}${Number(val).toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                })}`;
                            }
                        }
                    }
                },
                cutout: '55%'
            }
        });

        ANALYSIS_DATA.expense.labels.forEach((_, i) => {
            const swatch = document.getElementById(`expense-swatch-${i}`);
            if (swatch) swatch.style.background = colors[i];
        });
    }

    if (incomeCanvas && ANALYSIS_DATA.income.labels.length) {
        incomeChartInstance = new Chart(incomeCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ANALYSIS_DATA.income.labels,
                datasets: [{
                    label: 'Income',
                    data: ANALYSIS_DATA.income.values,
                    backgroundColor: '#16a34a',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => `${ANALYSIS_DATA.currency}${Number(value).toLocaleString()}`
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    if (financeCanvas && ANALYSIS_DATA.finance.hasData) {
        const financeColors = ['#0ea5e9', '#dc2626', '#16a34a'];
        financeChartInstance = new Chart(financeCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Cost', 'Expense', 'Income'],
                datasets: [{
                    data: ANALYSIS_DATA.finance.values,
                    backgroundColor: financeColors,
                    borderColor: financeColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.label}: ${ANALYSIS_DATA.currency}${Number(context.parsed).toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            })}`
                        }
                    }
                },
                cutout: '55%'
            }
        });
    }
});
</script>
<script src="assets/app.js"></script>
</body>
</html>
