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
    if (!CSRFToken::validate('review_reports_supervisor_csrf')) {
        echo json_encode(['success' => false, 'error' => 'Invalid request token']);
        exit;
    }

    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
    $action = $_POST['action'];

    if ($report_id && in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : null;

        try {
            // Ownership check: verify the report's student is in one of this supervisor's internships
            $ownership = $conn->prepare("
                SELECT COUNT(*) FROM reports r
                JOIN student_internships si ON si.student_id = r.student_id
                JOIN supervisor_internships svi ON svi.internship_id = si.internship_id
                WHERE r.id = ? AND svi.supervisor_id = ?
            ");
            $ownership->execute([$report_id, $supervisor_id]);
            if ((int)$ownership->fetchColumn() === 0) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE reports SET status = ?, feedback = ? WHERE id = ?");
            $stmt->execute([$status, $feedback, $report_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Review reports error: " . $e->getMessage());
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

$reports = [];
if ($internship_ids) {
    $in_query = implode(',', array_fill(0, count($internship_ids), '?'));

    $stmt = $conn->prepare("
        SELECT r.id, r.title, r.file_path, r.status, r.feedback, r.created_at,
               s.name AS student_name, s.id AS student_id
        FROM reports r
        JOIN students s ON r.student_id = s.id
        WHERE r.student_id IN (
            SELECT si.student_id FROM student_internships si WHERE si.internship_id IN ($in_query)
        )
        ORDER BY r.created_at DESC
    ");
    $stmt->execute($internship_ids);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Reports - InternHub</title>
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
            <a href="approve_hours.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-clock"></i><span class="font-medium">Approve Hours</span>
            </a>
            <a href="review_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
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
            <h2 class="text-2xl font-semibold text-gray-800">Review Student Reports</h2>
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

<div class="flex-1 overflow-y-auto p-6">
    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Student Reports</h3>

        <?php if (empty($reports)): ?>
            <p class="text-gray-600 text-center py-8">No reports submitted yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($r['student_name']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($r['title']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                                <td class="px-4 py-3">
                                    <?php
                                    $status = strtolower($r['status']);
                                    $badge = [
                                        "pending" => "bg-yellow-100 text-yellow-800",
                                        "approved" => "bg-green-100 text-green-800",
                                        "rejected" => "bg-red-100 text-red-800"
                                    ][$status];
                                    ?>
                                    <span class="px-2 py-1 text-xs rounded-full <?= $badge ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2">
                                        <?php if ($r['file_path']): ?>
                                            <a href="../student_actions/<?= htmlspecialchars($r['file_path']) ?>"
                                               target="_blank"
                                               class="text-blue-600 hover:text-blue-800 text-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($status === 'pending'): ?>
                                            <button onclick="approveReport(<?= $r['id'] ?>)"
                                                    class="text-green-600 hover:text-green-800 text-sm">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button onclick="openRejectModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['student_name']) ?>')"
                                                    class="text-red-600 hover:text-red-800 text-sm">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($r['feedback']): ?>
                                            <button onclick="showFeedback('<?= htmlspecialchars($r['feedback'], ENT_QUOTES) ?>')"
                                                    class="text-gray-600 hover:text-gray-800 text-sm">
                                                <i class="fas fa-comment"></i> Feedback
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</main>
</div>

<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96 shadow-lg">
        <h3 class="text-lg font-semibold mb-3">Reject Report</h3>
        <p class="text-sm text-gray-600 mb-3">Provide feedback for <span id="studentName" class="font-medium"></span>:</p>
        <textarea id="rejectFeedback" class="w-full border border-gray-300 rounded-md p-2 mb-4 text-sm" rows="4" placeholder="Enter your feedback..."></textarea>
        <div class="flex justify-end space-x-3">
            <button onclick="closeRejectModal()" class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded">Cancel</button>
            <button onclick="submitReject()" class="px-3 py-1 text-sm bg-red-600 hover:bg-red-700 text-white rounded">Submit</button>
        </div>
    </div>
</div>

<div id="feedbackModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96 shadow-lg">
        <h3 class="text-lg font-semibold mb-3">Feedback</h3>
        <p id="feedbackContent" class="text-sm text-gray-700 mb-4"></p>
        <div class="flex justify-end">
            <button onclick="closeFeedbackModal()" class="px-3 py-1 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded">Close</button>
        </div>
    </div>
</div>

<script>
let currentReportId = null;
const csrfToken = <?php echo json_encode(CSRFToken::generate('review_reports_supervisor_csrf')); ?>;

function approveReport(reportId) {
    if (!confirm('Are you sure you want to approve this report?')) return;

    const formData = new FormData();
    formData.append('report_id', reportId);
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

function openRejectModal(reportId, studentName) {
    currentReportId = reportId;
    document.getElementById('studentName').textContent = studentName;
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectFeedback').value = '';
    currentReportId = null;
}

function submitReject() {
    const feedback = document.getElementById('rejectFeedback').value.trim();
    if (!feedback) {
        alert('Please provide feedback');
        return;
    }

    const formData = new FormData();
    formData.append('report_id', currentReportId);
    formData.append('action', 'reject');
    formData.append('feedback', feedback);
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

function showFeedback(feedback) {
    document.getElementById('feedbackContent').textContent = feedback;
    document.getElementById('feedbackModal').classList.remove('hidden');
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').classList.add('hidden');
}
</script>
</body>
</html>
