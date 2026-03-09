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

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: auth.php");
    exit;
}

$supervisor_id = $_SESSION['user_id'];

// Fetch supervisor info
$stmt = $conn->prepare("SELECT s.name, c.name AS company FROM supervisors s JOIN companies c ON s.company_id = c.id WHERE s.id = ?");
$stmt->execute([$supervisor_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
$supervisor_name = $supervisor['name'] ?? 'Supervisor';
$company_name = $supervisor['company'] ?? '';

// Fetch internships supervised by this supervisor
$stmt = $conn->prepare("SELECT internship_id FROM supervisor_internships WHERE supervisor_id = ?");
$stmt->execute([$supervisor_id]);
$internship_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare for IN clause
$in_query = '';
$params = [];
if ($internship_ids) {
    $in_query = implode(',', array_fill(0, count($internship_ids), '?'));
    $params = $internship_ids;
} else {
    $in_query = 'NULL';
}

// Pending hour approvals
$pending_hours = [];
$pending_count = 0;
if ($internship_ids) {
    $stmt = $conn->prepare("
        SELECT h.id, h.student_id, h.duration_hours, h.date, s.name AS student_name, c.name AS company_name
        FROM hours h
        JOIN students s ON h.student_id = s.id
        JOIN internships i ON h.internship_id = i.id
        JOIN companies c ON i.company_id = c.id
        WHERE h.internship_id IN ($in_query) AND h.status = 'pending'
        ORDER BY h.date DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $pending_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pending_count = count($pending_hours);
}

// Reports awaiting feedback (dummy: count pending reports for students in these internships)
$reports = [];
$reports_count = 0;
if ($internship_ids) {
    $stmt = $conn->prepare("
        SELECT r.id, r.student_id, r.title, r.created_at, s.name AS student_name
        FROM reports r
        JOIN students s ON r.student_id = s.id
        WHERE r.status = 'pending' AND r.student_id IN (
            SELECT si.student_id FROM student_internships si WHERE si.internship_id IN ($in_query)
        )
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $reports_count = count($reports);
}

// Students on track and flagged students (dummy: you can implement your own logic)
$students_on_track = 0;
$flagged_students = 0;
if ($internship_ids) {
    $stmt = $conn->prepare("
        SELECT s.id, s.name,
            (SELECT SUM(h2.duration_hours) FROM hours h2 WHERE h2.student_id = s.id AND h2.status = 'approved') AS approved_hours,
            i.total_hours_required
        FROM students s
        JOIN student_internships si ON s.id = si.student_id
        JOIN internships i ON si.internship_id = i.id
        WHERE si.internship_id IN ($in_query)
    ");
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_students = count($students);
    foreach ($students as $stu) {
        if ($stu['approved_hours'] >= 0.8 * $stu['total_hours_required']) {
            $students_on_track++;
        }
        if ($stu['approved_hours'] < 0.5 * $stu['total_hours_required']) {
            $flagged_students++;
        }
    }
    $on_track_percent = $total_students > 0 ? round(($students_on_track / $total_students) * 100) : 0;
} else {
    $on_track_percent = 0;
}

// For chart: get students and their hours
$chart_labels = [];
$chart_logged = [];
$chart_targets = [];
if ($internship_ids) {
    foreach ($students as $stu) {
        $chart_labels[] = explode(' ', $stu['name'])[0]; // First name
        $chart_logged[] = (float)($stu['approved_hours'] ?? 0);
        $chart_targets[] = (float)($stu['total_hours_required'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard - InternHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            500: '#2563eb',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
    <aside class="w-64 bg-blue-700 text-white flex flex-col">
        <div class="p-6 border-b border-blue-600">
            <h1 class="text-2xl font-bold">InternHub</h1>
        </div>
        <nav class="p-4 flex flex-col min-h-[calc(100vh-5rem)]">
            <div class="space-y-2 flex-1">
                <a href="dashboard_supervisor.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
                    <i class="fas fa-home"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                <a href="approve_hours.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                    <i class="fas fa-clock"></i>
                    <span class="font-medium">Approve Hours</span>
                </a>
                <a href="review_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                    <i class="fas fa-file-alt"></i>
                    <span class="font-medium">Review Reports</span>
                </a>
                <a href="student_progress.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                    <i class="fas fa-chart-line"></i>
                    <span class="font-medium">Student Progress</span>
                </a>
                <a href="../overall_actions/messages.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                    <i class="fas fa-comments"></i>
                    <span class="font-medium">Messages</span>
                </a>
            </div>
            <div class="space-y-2 mt-auto">
                <a href="../overall_actions/settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                    <i class="fas fa-cog"></i>
                    <span class="font-medium">Settings</span>
                </a>
                <a href="../overall_actions/logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="font-medium">Logout</span>
                </a>
            </div>
        </nav>
    </aside>
    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">Supervisor Dashboard</h2>
                    <p class="text-gray-600">Company: <?= htmlspecialchars($company_name) ?> — Supervisor</p>
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <p class="text-sm text-gray-600">Pending Hour Approvals</p>
                    <p class="text-2xl font-bold text-yellow-600"><?= $pending_count ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <p class="text-sm text-gray-600">Reports Awaiting Feedback</p>
                    <p class="text-2xl font-bold text-blue-600"><?= $reports_count ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <p class="text-sm text-gray-600">Students On Track</p>
                    <p class="text-2xl font-bold text-green-600"><?= $on_track_percent ?>%</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <p class="text-sm text-gray-600">Flagged Students</p>
                    <p class="text-2xl font-bold text-red-600"><?= $flagged_students ?></p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 text-center">Average Hours Logged</h3>
                <div class="h-80">
                    <canvas id="hoursOverviewChart"></canvas>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Pending Hours for Approval</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hours Logged</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date Submitted</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($pending_hours as $row): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($row['student_name']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($row['company_name']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-800"><?= htmlspecialchars($row['duration_hours']) ?> hrs</td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                <td class="px-4 py-3 flex space-x-2">
                                    <button class="bg-green-500 hover:bg-green-600 text-white text-xs px-3 py-1 rounded">Approve</button>
                                    <button onclick="openRejectModal('<?= htmlspecialchars($row['student_name']) ?>')" class="bg-red-500 hover:bg-red-600 text-white text-xs px-3 py-1 rounded">Reject</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Reports Pending Feedback</h3>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($reports as $report): ?>
                    <li class="py-3 flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($report['student_name']) ?></p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($report['title']) ?></p>
                        </div>
                        <div class="flex space-x-3">
                            <button class="text-blue-600 hover:underline text-sm">View Report</button>
                            <button class="text-green-600 hover:underline text-sm">Provide Feedback</button>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </main>
</div>
<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96 shadow-lg">
        <h3 class="text-lg font-semibold mb-3">Reject Hours</h3>
        <p class="text-sm text-gray-600 mb-3">Provide a reason for rejecting <span id="studentName" class="font-medium"></span>’s submission:</p>
        <textarea id="rejectReason" class="w-full border border-gray-300 rounded-md p-2 mb-4 text-sm" rows="3" placeholder="Enter rejection reason..."></textarea>
        <div class="flex justify-end space-x-3">
            <button onclick="closeRejectModal()" class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded">Cancel</button>
            <button class="px-3 py-1 text-sm bg-red-600 hover:bg-red-700 text-white rounded">Submit</button>
        </div>
    </div>
</div>

<script>
    function openRejectModal(student) {
        document.getElementById('rejectModal').classList.remove('hidden');
        document.getElementById('studentName').textContent = student;
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
    const ctx = document.getElementById('hoursOverviewChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Logged Hours',
                data: <?= json_encode($chart_logged) ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 4,
            }, {
                label: 'Target Hours',
                data: <?= json_encode($chart_targets) ?>,
                backgroundColor: '#e5e7eb'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
</body>
</html>
