<?php
session_start();

if (file_exists(__DIR__ . '/../dont_touch_kinda_stuff/db.php')) {
    require_once __DIR__ . '/../dont_touch_kinda_stuff/db.php';
} elseif (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    die('Database connection file not found.');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: ../overall_actions/auth.php");
    exit;
}

$supervisor_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT s.name, c.name AS company FROM supervisors s JOIN companies c ON s.company_id = c.id WHERE s.id = ?");
$stmt->execute([$supervisor_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
$supervisor_name = $supervisor['name'] ?? 'Supervisor';
$company_name = $supervisor['company'] ?? '';

$stmt = $conn->prepare("SELECT internship_id FROM supervisor_internships WHERE supervisor_id = ?");
$stmt->execute([$supervisor_id]);
$internship_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$students = [];
if ($internship_ids) {
    $in_query = implode(',', array_fill(0, count($internship_ids), '?'));

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.name AS student_name,
            i.total_hours_required,
            COALESCE((SELECT SUM(h.duration_hours) FROM hours h WHERE h.student_id = s.id AND h.status = 'approved'), 0) AS approved_hours,
            COALESCE((SELECT SUM(h.duration_hours) FROM hours h WHERE h.student_id = s.id AND h.status = 'pending'), 0) AS pending_hours,
            (SELECT COUNT(*) FROM reports r WHERE r.student_id = s.id) AS reports_submitted
        FROM students s
        JOIN student_internships si ON s.id = si.student_id
        JOIN internships i ON si.internship_id = i.id
        WHERE si.internship_id IN ($in_query)
        ORDER BY s.name
    ");
    $stmt->execute($internship_ids);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Progress - InternHub</title>
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
            <a href="dashboard_supervisor.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-home"></i><span class="font-medium">Dashboard</span>
            </a>
            <a href="approve_hours.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-clock"></i><span class="font-medium">Approve Hours</span>
            </a>
            <a href="review_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-file-alt"></i><span class="font-medium">Review Reports</span>
            </a>
            <a href="student_progress.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
                <i class="fas fa-chart-line"></i><span class="font-medium">Student Progress</span>
            </a>
            <a href="../overall_actions/messages.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-comments"></i><span class="font-medium">Messages</span>
            </a>
        </div>
        <div class="space-y-2 mt-auto">
            <a href="../overall_actions/settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-cog"></i><span class="font-medium">Settings</span>
            </a>
            <a href="../overall_actions/logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-sign-out-alt"></i><span class="font-medium">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<main class="flex-1 flex flex-col overflow-hidden">
<header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800">Student Progress</h2>
            <p class="text-gray-600"><?= htmlspecialchars($company_name) ?></p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="bg-gray-200 border-2 border-dashed rounded-xl w-12 h-12 flex items-center justify-center">
                <i class="fas fa-user text-gray-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($supervisor_name) ?></p>
                <p class="text-sm text-gray-500">Supervisor</p>
            </div>
        </div>
    </div>
</header>

<div class="flex-1 overflow-y-auto p-6 space-y-6">
    <?php if (empty($students)): ?>
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <p class="text-gray-600 text-center py-8">No students assigned yet.</p>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Hours Progress Overview</h3>
            <div class="h-80">
                <canvas id="progressChart"></canvas>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Student Details</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Approved Hours</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pending Hours</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Required Hours</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reports</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($students as $stu): ?>
                            <?php
                            $progress = $stu['total_hours_required'] > 0
                                ? round(($stu['approved_hours'] / $stu['total_hours_required']) * 100, 1)
                                : 0;
                            $onTrack = $progress >= 50;
                            ?>
                            <tr class="<?= !$onTrack ? 'bg-red-50' : '' ?>">
                                <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($stu['student_name']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-800"><?= $stu['approved_hours'] ?> hrs</td>
                                <td class="px-4 py-3 text-sm text-yellow-600"><?= $stu['pending_hours'] ?> hrs</td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= $stu['total_hours_required'] ?> hrs</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?= min($progress, 100) ?>%"></div>
                                        </div>
                                        <span class="text-sm text-gray-700"><?= $progress ?>%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= $stu['reports_submitted'] ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $onTrack ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $onTrack ? 'On Track' : 'At Risk' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</main>
</div>

<script>
<?php if (!empty($students)): ?>
const labels = <?= json_encode(array_map(function($s) { return explode(' ', $s['student_name'])[0]; }, $students)) ?>;
const approved = <?= json_encode(array_column($students, 'approved_hours')) ?>;
const required = <?= json_encode(array_column($students, 'total_hours_required')) ?>;

const ctx = document.getElementById('progressChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Approved Hours',
                data: approved,
                backgroundColor: '#3b82f6',
                borderRadius: 4
            },
            {
                label: 'Required Hours',
                data: required,
                backgroundColor: '#e5e7eb',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true } }
    }
});
<?php endif; ?>
</script>
</body>
</html>
