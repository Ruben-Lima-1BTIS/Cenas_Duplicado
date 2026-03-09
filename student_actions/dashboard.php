<?php
session_start();

// Fix: Use correct path to db.php (now inside dont_touch_kinda_stuff)
if (file_exists(__DIR__ . '/../dont_touch_kinda_stuff/db.php')) {
    require_once __DIR__ . '/../dont_touch_kinda_stuff/db.php';
} elseif (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    die('Database connection file not found.');
}

$student_id = $_SESSION['user_id'];

// ---- TOTAL HOURS REQUIRED FROM INTERNSHIP ----
$stmt = $conn->prepare("
    SELECT i.total_hours_required
    FROM internships i
    JOIN student_internships si ON si.internship_id = i.id
    WHERE si.student_id = ?
    LIMIT 1
");
$stmt->execute([$student_id]);
$total_required = (float) ($stmt->fetchColumn() ?? 0);

// ---- TOTAL HOURS LOGGED ----
$stmt = $conn->prepare("SELECT SUM(duration_hours) AS total FROM hours WHERE student_id = ?");
$stmt->execute([$student_id]);
$total_hours = (float) ($stmt->fetchColumn() ?? 0);

// ---- HOURS LEFT ----
$hours_left = max($total_required - $total_hours, 0);


// ---- SUBMITTED REPORTS ----
$stmt = $conn->prepare("SELECT COUNT(*) FROM reports WHERE student_id = ?");
$stmt->execute([$student_id]);
$report_count = (int) ($stmt->fetchColumn() ?? 0);

// ---- PIE CHART DATA ----
$stmt = $conn->prepare("SELECT SUM(duration_hours) FROM hours WHERE student_id = ? AND status = 'approved'");
$stmt->execute([$student_id]);
$approved_hours = (float) ($stmt->fetchColumn() ?? 0);

$stmt = $conn->prepare("SELECT SUM(duration_hours) FROM hours WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$student_id]);
$pending_hours = (float) ($stmt->fetchColumn() ?? 0);

$stmt = $conn->prepare("SELECT SUM(duration_hours) FROM hours WHERE student_id = ? AND status = 'rejected'");
$stmt->execute([$student_id]);
$rejected_hours = (float) ($stmt->fetchColumn() ?? 0);

$missing_hours = max($total_required - $approved_hours, 0);

// ---- HOURS THIS WEEK ----
$weekData = [];
$days = ['Mon','Tue','Wed','Thu','Fri'];

foreach ($days as $index => $day) {
    $stmt = $conn->prepare("
        SELECT SUM(duration_hours) FROM hours 
        WHERE student_id = ? 
        AND WEEK(date) = WEEK(CURDATE())
        AND DAYOFWEEK(date) = ?
    ");
    $stmt->execute([$student_id, $index + 2]); // Mon=2
    $weekData[$day] = (float) ($stmt->fetchColumn() ?? 0);
}

// fetch student's display name (fallback to session email or generic)
$stmt = $conn->prepare("SELECT name FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$user_name = $stmt->fetchColumn();
if ($user_name === false || $user_name === null || $user_name === '') {
    $user_name = $_SESSION['email'] ?? 'Student';
}

// Helper for relative URLs (works for most setups)
function relUrl($path) {
    $base = dirname($_SERVER['SCRIPT_NAME']);
    return $base . $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InternHub Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">

<aside class="w-64 bg-blue-700 text-white flex flex-col">
    <div class="p-6 border-b border-blue-600">
        <h1 class="text-2xl font-bold">InternHub</h1>
    </div>
    <nav class="p-4 flex flex-col min-h-[calc(100vh-5rem)]">
        <div class="space-y-2 flex-1">
            <a href="<?= relUrl('/dashboard.php') ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
                <i class="fas fa-home"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="<?= relUrl('/log_hours.php') ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-clock"></i>
                <span class="font-medium">Log Hours</span>
            </a>
            <a href="<?= relUrl('/submit-reports.php') ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-file-alt"></i>
                <span class="font-medium">Submit Reports</span>
            </a>
            <a href="<?= dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/overall_actions/messages.php' ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-comments"></i>
                <span class="font-medium">Messages</span>
            </a>
        </div>
        <div class="space-y-2 mt-auto">
            <a href="<?= dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/overall_actions/settings.php' ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-cog"></i>
                <span class="font-medium">Settings</span>
            </a>
            <a href="<?= dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/overall_actions/logout.php' ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-sign-out-alt"></i>
                <span class="font-medium">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<!-- MAIN -->
<main class="flex-1 flex flex-col overflow-hidden">
<header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800">Hello, <?= htmlspecialchars($user_name) ?></h2>
            <p class="text-gray-600">Welcome back to your dashboard</p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="bg-gray-200 border-2 border-dashed rounded-xl w-12 h-12 flex items-center justify-center">
                <i class="fas fa-user text-gray-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                <p class="text-sm text-gray-500">Intern</p>
            </div>
        </div>
    </div>
</header>

<div class="flex-1 overflow-y-auto p-6 space-y-6">
    <!-- TOP CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-blue-600 text-white p-6 rounded-xl shadow-lg hover:bg-blue-700 transition-colors duration-200 cursor-pointer">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Hours Left to Do</h3>
                    <p class="text-3xl font-bold mt-2"><?= $hours_left ?> hours</p>
                </div>
                <i class="fas fa-clock text-4xl opacity-80"></i>
            </div>
        </div>
        <div class="bg-blue-600 text-white p-6 rounded-xl shadow-lg hover:bg-blue-700 transition-colors duration-200 cursor-pointer">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Submitted Reports</h3>
                    <p class="text-3xl font-bold mt-2"><?= $report_count ?> reports</p>
                </div>
                <i class="fas fa-file-alt text-4xl opacity-80"></i>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-4 text-center">Hour Status Overview</h3>
            <div class="h-80" style="height:340px;display:flex;align-items:center;justify-content:center;">
                <canvas id="pieChart" style="max-height:320px"></canvas>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-4 text-center">Hours Made This Week</h3>
            <div class="h-80">
                <canvas id="barChart"></canvas>
            </div>
        </div>
    </div>
</div>
</main>
</div>

<script>
const pieCtx = document.getElementById('pieChart').getContext('2d');
const pieChart = new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: ['Hours Missing', 'Hours to Approve', 'Hours Approved', 'Hours Rejected'],
        datasets: [{
            data: [
                <?= $missing_hours ?>,
                <?= $pending_hours ?>,
                <?= $approved_hours ?>,
                <?= $rejected_hours ?>
            ],
            backgroundColor: ['#ef4444', '#f97316', '#10b981', '#3b82f6'],
            borderWidth: 0
        }]
    }
});

const barCtx = document.getElementById('barChart').getContext('2d');
const barChart = new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: ['Mon','Tue','Wed','Thu','Fri'],
        datasets: [
            {
                label: 'Hours Logged',
                data: [
                    <?= $weekData['Mon'] ?>,
                    <?= $weekData['Tue'] ?>,
                    <?= $weekData['Wed'] ?>,
                    <?= $weekData['Thu'] ?>,
                    <?= $weekData['Fri'] ?>
                ],
                backgroundColor: '#3b82f6',
                borderRadius: 4,
                borderSkipped: false
            },
            {
                label: 'Target Hours',
                data: [6,6,6,6,6],
                backgroundColor: '#94a3b8',
                borderRadius: 4,
                borderSkipped: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true, ticks: { callback: function(value){ return value + ' hrs'; } } }
        }
    }
});
</script>
</body>
</html>
