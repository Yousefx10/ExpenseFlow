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

$cur = $symbolMap[$currencyCode] ?? '$';

$todayDate = date('Y-m-d');
$weekStartQuick = date('Y-m-d', strtotime('monday this week'));
$weekEndQuick   = date('Y-m-d', strtotime('sunday this week'));
$monthStartQuick = date('Y-m-01');
$monthEndQuick   = date('Y-m-t');
$yearStart = date('Y-01-01');
$yearEnd   = date('Y-12-31');

$start  = $_GET['start_date'] ?? $monthStartQuick;
$end    = $_GET['end_date']   ?? $monthEndQuick;

$sql = "SELECT t.category_id, c.name AS category_name, SUM(t.amount) AS total_amount
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = :uid
          AND t.is_deleted = 0
          AND t.type = 'expense'
          AND t.tx_date BETWEEN :start AND :end
        GROUP BY t.category_id, c.name
        ORDER BY total_amount DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':uid'   => $userId,
    ':start' => $start,
    ':end'   => $end,
]);
$rows = $stmt->fetchAll();

$labels = [];
$values = [];
foreach ($rows as $r) {
    $labels[] = $r['category_name'] !== null && $r['category_name'] !== '' ? $r['category_name'] : 'Uncategorized';
    $values[] = (float) $r['total_amount'];
}

$totalExpense = array_sum($values);
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
        <div class="logo">Expense<span>Flow</span></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Dashboard</a></li>
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
                    Expenses by Category — <?= $cur . number_format($totalExpense, 2) ?>
                    (<?= htmlspecialchars($start) ?> → <?= htmlspecialchars($end) ?>)
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
            <div class="section-title">Expenses by Category</div>

            <?php if (empty($labels)): ?>
                <div class="page-subtitle" style="margin-top:8px;">No expense data for this period.</div>
            <?php else: ?>
                <div class="chart-wrap">
                    <canvas id="byCategory"></canvas>
                </div>

                <div class="legend-list">
                    <?php foreach ($labels as $i => $lbl): ?>
                        <div class="legend-item">
                            <span class="legend-swatch" id="swatch-<?= $i ?>"></span>
                            <span><?= htmlspecialchars($lbl) ?></span>
                            <span style="margin-left:auto;font-weight:600;">
                                <?= $cur . number_format($values[$i], 2) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <script>
                    const labels = <?= json_encode($labels) ?>;
                    const values = <?= json_encode($values) ?>;

                    const colors = labels.map((_, i) => `hsl(${(i * 43) % 360} 70% 55%)`);
                    const borderColors = labels.map((_, i) => `hsl(${(i * 43) % 360} 70% 35%)`);

                    labels.forEach((_, i) => {
                        const el = document.getElementById('swatch-' + i);
                        if (el) el.style.background = colors[i];
                    });

                    const ctx = document.getElementById('byCategory').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Expenses',
                                data: values,
                                backgroundColor: colors,
                                borderColor: borderColors,
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
                                            return `${context.label}: <?= $cur ?>${Number(val).toLocaleString(
                                                undefined,
                                                { minimumFractionDigits: 2, maximumFractionDigits: 2 }
                                            )}`;
                                        }
                                    }
                                }
                            },
                            cutout: '55%'
                        }
                    });
                </script>
            <?php endif; ?>
        </section>
    </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>

