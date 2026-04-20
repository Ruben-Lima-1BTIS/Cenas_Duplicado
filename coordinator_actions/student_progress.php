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

// Prevent access by other roles
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: ../overall_actions/auth.php");
    exit;
}

$coordinator_id = $_SESSION['user_id'];

// Get all classes for this coordinator
$stmt = $conn->prepare("SELECT id, sigla AS class_name FROM classes WHERE coordinator_id = ?");
$stmt->execute([$coordinator_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// See which class was selected
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : ($classes[0]['id'] ?? null);

// Get the selected class name to display
$selected_class_name = null;
foreach ($classes as $c) {
    if ($c['id'] == $class_id) {
        $selected_class_name = $c['class_name'];
        break;
    }
}

// Get coordinator info
$stmt = $conn->prepare("SELECT name AS coordinator_name FROM coordinators WHERE id = ?");
$stmt->execute([$coordinator_id]);
$coordinator = $stmt->fetch(PDO::FETCH_ASSOC);

// If coordinator has no classes assigned
if (!$class_id) {
    die("No classes assigned to this coordinator.");
}

// Get all students from the selected class with their progress
$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.name AS student_name,
        comp.name AS company_name,
        i.total_hours_required,
        COALESCE((SELECT SUM(h.duration_hours) FROM hours h WHERE h.student_id = s.id AND h.status = 'approved'), 0) AS approved_hours,
        COALESCE((SELECT SUM(h.duration_hours) FROM hours h WHERE h.student_id = s.id AND h.status = 'pending'), 0) AS pending_hours,
        (SELECT COUNT(*) FROM reports r WHERE r.student_id = s.id) AS reports_submitted,
        CEILING(TIMESTAMPDIFF(MONTH, i.start_date, i.end_date)) AS reports_required
    FROM students s
    LEFT JOIN student_internships si ON s.id = si.student_id
    LEFT JOIN internships i ON si.internship_id = i.id
    LEFT JOIN companies comp ON i.company_id = comp.id
    WHERE s.class_id = ?
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get weekly hours for each student (last 4 weeks)
$weekly_data = [];
foreach ($students as $student) {
    $stmt = $conn->prepare("
        SELECT 
            YEARWEEK(h.date, 1) AS week,
            SUM(h.duration_hours) AS hours
        FROM hours h
        WHERE h.student_id = ? AND h.status = 'approved'
        GROUP BY YEARWEEK(h.date, 1)
        ORDER BY week DESC
        LIMIT 4
    ");
    $stmt->execute([$student['id']]);
    $weekly_data[$student['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent hour logs for each student
$recent_logs = [];
foreach ($students as $student) {
    $stmt = $conn->prepare("
        SELECT date, duration_hours, status
        FROM hours
        WHERE student_id = ?
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$student['id']]);
    $recent_logs[$student['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get reports for each student
$student_reports = [];
foreach ($students as $student) {
    $stmt = $conn->prepare("
        SELECT title, status, created_at
        FROM reports
        WHERE student_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$student['id']]);
    $student_reports[$student['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progress | InternHub</title>
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
                    <a href="dashboard_coordinator.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-home"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <a href="review_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-file-alt"></i>
                        <span class="font-medium">Review Reports</span>
                    </a>
                    <a href="student_progress.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
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
                        <h2 class="text-2xl font-semibold text-gray-800">Student Progress</h2>
                        <p class="text-gray-600"><?= htmlspecialchars($selected_class_name ?? 'Class') ?> — <?= htmlspecialchars($coordinator['coordinator_name'] ?? 'Coordinator') ?></p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="bg-gray-200 border-2 border-dashed rounded-xl w-12 h-12 flex items-center justify-center">
                            <i class="fas fa-user text-gray-500"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($coordinator['coordinator_name'] ?? 'Coordinator') ?></p>
                            <p class="text-sm text-gray-500">Coordinator</p>
                        </div>
                    </div>
                </div>
            </header>
            <div class="flex-1 overflow-y-auto p-6">
                <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">All Students — <?= htmlspecialchars($selected_class_name ?? 'Class') ?></h3>
                    <div id="studentsList" class="space-y-3">
                        <?php foreach ($students as $index => $student): ?>
                            <?php
                            $total_logged = $student['approved_hours'] + $student['pending_hours'];
                            $progress_percent = $student['total_hours_required'] > 0 ? round(($student['approved_hours'] / $student['total_hours_required']) * 100) : 0;
                            $status = $progress_percent >= 80 ? 'On Track' : ($progress_percent >= 50 ? 'Caution' : 'At Risk');
                            $status_class = $progress_percent >= 80 ? 'bg-green-100 text-green-800' : ($progress_percent >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                            $student_id = 'student-' . $student['id'];
                            ?>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="flex justify-between items-center p-4 bg-gray-50 cursor-pointer hover:bg-gray-100" onclick="toggleStudent('<?= $student_id ?>')">
                                    <div class="flex items-center space-x-4">
                                        <i id="icon-<?= $student_id ?>" class="fas fa-chevron-right text-gray-500"></i>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($student['student_name']) ?></span>
                                    </div>
                                    <div class="flex items-center space-x-4 text-sm text-gray-600">
                                        <span><?= htmlspecialchars($student['company_name'] ?? 'No Company') ?></span>
                                        <span><?= $total_logged ?> / <?= $student['total_hours_required'] ?> hrs</span>
                                        <span class="px-2 py-0.5 <?= $status_class ?> rounded-full text-xs"><?= $status ?></span>
                                    </div>
                                </div>
                                <div id="detail-<?= $student_id ?>" class="hidden p-4 border-t border-gray-100">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                                        <div>
                                            <h4 class="font-medium text-gray-800 mb-2">Weekly Hours</h4>
                                            <canvas id="chart-<?= $student_id ?>" height="150"></canvas>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 mb-2">Recent Hour Logs</h4>
                                            <ul class="text-sm space-y-1">
                                                <?php foreach ($recent_logs[$student['id']] as $log): ?>
                                                    <li class="flex justify-between">
                                                        <span><?= htmlspecialchars($log['date']) ?></span>
                                                        <span><?= htmlspecialchars($log['duration_hours']) ?> hrs — 
                                                            <span class="capitalize <?= $log['status'] === 'approved' ? 'text-green-600' : ($log['status'] === 'pending' ? 'text-yellow-600' : 'text-red-600') ?>">
                                                                <?= htmlspecialchars($log['status']) ?>
                                                            </span>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800 mb-2">Reports</h4>
                                        <ul class="text-sm space-y-1">
                                            <?php foreach ($student_reports[$student['id']] as $report): ?>
                                                <li>
                                                    <?= htmlspecialchars($report['title']) ?> — 
                                                    <span class="capitalize <?= $report['status'] === 'approved' ? 'text-green-600' : ($report['status'] === 'pending' ? 'text-yellow-600' : 'text-red-600') ?>">
                                                        <?= htmlspecialchars($report['status']) ?>
                                                    </span>
                                                    <span class="text-gray-500">(<?= date('Y-m-d', strtotime($report['created_at'])) ?>)</span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="mt-3 flex justify-end">
                                        <button class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded flex items-center">
                                            <i class="fas fa-comment mr-1"></i> Message Student
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const weeklyData = <?php echo json_encode($weekly_data); ?>;

        function toggleStudent(id) {
            const detail = document.getElementById('detail-' + id);
            const icon = document.getElementById('icon-' + id);
            
            if (detail.classList.contains('hidden')) {
                // Close all other details
                document.querySelectorAll('[id^="detail-student"]').forEach(el => {
                    el.classList.add('hidden');
                    const elId = el.id.replace('detail-', '');
                    document.getElementById('icon-' + elId).className = 'fas fa-chevron-right text-gray-500';
                });
                
                // Open this one
                detail.classList.remove('hidden');
                icon.className = 'fas fa-chevron-down text-gray-700';
                
                // Initialize chart only once
                if (!window['chart_' + id]) {
                    const studentId = id.replace('student-', '');
                    const data = weeklyData[studentId] || [];
                    const labels = [];
                    const hours = [];
                    
                    // Create labels for last 4 weeks
                    for (let i = 3; i >= 0; i--) {
                        const week = new Date();
                        week.setDate(week.getDate() - (i * 7));
                        const weekNum = Math.ceil((week - new Date(week.getFullYear(), 0, 1)) / (7 * 24 * 60 * 60 * 1000));
                        labels.push('Week ' + weekNum);
                    }
                    
                    // Fill hours data (reverse to match labels)
                    for (let i = 3; i >= 0; i--) {
                        const weekData = data.find(d => parseInt(d.week) === (new Date().getFullYear() * 100 + Math.ceil((new Date() - new Date(new Date().getFullYear(), 0, 1)) / (7 * 24 * 60 * 60 * 1000)) - i));
                        hours.push(weekData ? parseFloat(weekData.hours) : 0);
                    }
                    
                    const ctx = document.getElementById('chart-' + id).getContext('2d');
                    window['chart_' + id] = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Hours',
                                data: hours,
                                backgroundColor: '#3b82f6'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            } else {
                // Close this one
                detail.classList.add('hidden');
                icon.className = 'fas fa-chevron-right text-gray-500';
            }
        }
    </script>
</body>
</html>