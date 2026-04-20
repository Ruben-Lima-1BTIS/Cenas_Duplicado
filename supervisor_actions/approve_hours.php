<?php
session_start();
require_once __DIR__ . '/../dont_touch_kinda_stuff/CSRFToken.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!CSRFToken::validate('approve_hours_csrf')) {
        echo json_encode(['success' => false, 'error' => 'Invalid request token']);
        exit;
    }

    $hour_id = isset($_POST['hour_id']) ? intval($_POST['hour_id']) : 0;
    $action = $_POST['action'];

    if ($hour_id && in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;

        try {
            // Ownership check: verify the hour belongs to one of this supervisor's internships
            $ownership = $conn->prepare("
                SELECT COUNT(*) FROM hours h
                JOIN supervisor_internships si ON si.internship_id = h.internship_id
                WHERE h.id = ? AND si.supervisor_id = ?
            ");
            $ownership->execute([$hour_id, $supervisor_id]);
            if ((int)$ownership->fetchColumn() === 0) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE hours SET status = ?, supervisor_reviewed_by = ?, supervisor_comment = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $supervisor_id, $comment, $hour_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Approve hours error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
    exit;
}

$stmt = $conn->prepare("SELECT internship_id FROM supervisor_internships WHERE supervisor_id = ?");
$stmt->execute([$supervisor_id]);
$internship_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$pending_hours_by_week = [];
$approved_hours = [];
$rejected_hours = [];

if ($internship_ids) {
    $in_query = implode(',', array_fill(0, count($internship_ids), '?'));

    // Group pending hours by week
    $stmt = $conn->prepare("
        SELECT h.id, h.student_id, h.duration_hours, h.date, h.start_time, h.end_time, h.status, h.supervisor_comment,
               s.name AS student_name, c.name AS company_name,
               YEARWEEK(h.date, 1) AS week_num,
               DATE_SUB(h.date, INTERVAL WEEKDAY(h.date) DAY) AS week_start,
               DATE_ADD(DATE_SUB(h.date, INTERVAL WEEKDAY(h.date) DAY), INTERVAL 6 DAY) AS week_end
        FROM hours h
        JOIN students s ON h.student_id = s.id
        JOIN internships i ON h.internship_id = i.id
        JOIN companies c ON i.company_id = c.id
        WHERE h.internship_id IN ($in_query) AND h.status = 'pending'
        ORDER BY h.date DESC
    ");
    $stmt->execute($internship_ids);
    $all_pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by week
    foreach ($all_pending as $hour) {
        $week_key = $hour['week_start'] . ' to ' . $hour['week_end'];
        if (!isset($pending_hours_by_week[$week_key])) {
            $pending_hours_by_week[$week_key] = [];
        }
        $pending_hours_by_week[$week_key][] = $hour;
    }

    $stmt = $conn->prepare("
        SELECT h.id, h.student_id, h.duration_hours, h.date, h.start_time, h.end_time, h.status, h.supervisor_comment,
               s.name AS student_name, c.name AS company_name
        FROM hours h
        JOIN students s ON h.student_id = s.id
        JOIN internships i ON h.internship_id = i.id
        JOIN companies c ON i.company_id = c.id
        WHERE h.internship_id IN ($in_query) AND h.status = 'approved'
        ORDER BY h.date DESC
        LIMIT 20
    ");
    $stmt->execute($internship_ids);
    $approved_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT h.id, h.student_id, h.duration_hours, h.date, h.start_time, h.end_time, h.status, h.supervisor_comment,
               s.name AS student_name, c.name AS company_name
        FROM hours h
        JOIN students s ON h.student_id = s.id
        JOIN internships i ON h.internship_id = i.id
        JOIN companies c ON i.company_id = c.id
        WHERE h.internship_id IN ($in_query) AND h.status = 'rejected'
        ORDER BY h.date DESC
        LIMIT 20
    ");
    $stmt->execute($internship_ids);
    $rejected_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Hours - InternHub</title>
<script src="https://cdn.tailwindcss.com"></script>
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
            <a href="approve_hours.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
                <i class="fas fa-clock"></i><span class="font-medium">Approve Hours</span>
            </a>
            <a href="review_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-file-alt"></i><span class="font-medium">Review Reports</span>
            </a>
            <a href="student_progress.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
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
            <h2 class="text-2xl font-semibold text-gray-800">Approve Student Hours</h2>
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
    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Pending Hours for Approval</h3>
        <?php if (empty($pending_hours_by_week)): ?>
            <p class="text-gray-600 text-center py-8">No pending hours to approve.</p>
        <?php else: ?>
            <?php foreach ($pending_hours_by_week as $week_range => $hours): ?>
                <div class="mb-6">
                    <h4 class="text-lg font-medium text-gray-700 mb-3">Week of <?= htmlspecialchars($week_range) ?></h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Start Time</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">End Time</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hours</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($hours as $row): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($row['student_name']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($row['start_time']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($row['end_time']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-800"><?= htmlspecialchars($row['duration_hours']) ?> hrs</td>
                                    <td class="px-4 py-3 flex space-x-2">
                                        <button onclick="approveHour(<?= $row['id'] ?>)"
                                                class="bg-green-500 hover:bg-green-600 text-white text-xs px-3 py-1 rounded">
                                            Approve
                                        </button>
                                        <button onclick="openRejectModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['student_name']) ?>')"
                                                class="bg-red-500 hover:bg-red-600 text-white text-xs px-3 py-1 rounded">
                                            Reject
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Recently Approved Hours</h3>
        <?php if (empty($approved_hours)): ?>
            <p class="text-gray-600 text-center py-8">No approved hours yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hours</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($approved_hours as $row): ?>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($row['student_name']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= date('M j, Y', strtotime($row['date'])) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-800"><?= htmlspecialchars($row['duration_hours']) ?> hrs</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Approved</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($rejected_hours)): ?>
    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Recently Rejected Hours</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hours</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Comment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($rejected_hours as $row): ?>
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($row['student_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= date('M j, Y', strtotime($row['date'])) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-800"><?= htmlspecialchars($row['duration_hours']) ?> hrs</td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($row['supervisor_comment'] ?? 'No comment') ?></td>
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

<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96 shadow-lg">
        <h3 class="text-lg font-semibold mb-3">Reject Hours</h3>
        <p class="text-sm text-gray-600 mb-3">Provide a reason for rejecting <span id="studentName" class="font-medium"></span>'s submission:</p>
        <textarea id="rejectReason" class="w-full border border-gray-300 rounded-md p-2 mb-4 text-sm" rows="3" placeholder="Enter rejection reason..."></textarea>
        <div class="flex justify-end space-x-3">
            <button onclick="closeRejectModal()" class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded">Cancel</button>
            <button onclick="submitReject()" class="px-3 py-1 text-sm bg-red-600 hover:bg-red-700 text-white rounded">Submit</button>
        </div>
    </div>
</div>

<script>
let currentHourId = null;
const csrfToken = <?php echo json_encode(CSRFToken::generate('approve_hours_csrf')); ?>;

function approveHour(hourId) {
    const formData = new FormData();
    formData.append('hour_id', hourId);
    formData.append('action', 'approve');
    formData.append('csrf_token', csrfToken);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    });
}

function openRejectModal(hourId, studentName) {
    currentHourId = hourId;
    document.getElementById('studentName').textContent = studentName;
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectReason').value = '';
    currentHourId = null;
}

function submitReject() {
    const comment = document.getElementById('rejectReason').value.trim();
    if (!comment) {
        alert('Please provide a reason for rejection');
        return;
    }

    const formData = new FormData();
    formData.append('hour_id', currentHourId);
    formData.append('action', 'reject');
    formData.append('comment', comment);
    formData.append('csrf_token', csrfToken);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    });
}
</script>
</body>
</html>
