<?php
require_once __DIR__ . '/../dont_touch_kinda_stuff/bootstrap.php';
internhub_bootstrap(['db' => true]);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$user_name = '';
if ($user_role === 'student') {
    $stmt = $conn->prepare("SELECT name FROM students WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_name = $stmt->fetchColumn() ?: 'User';
} elseif ($user_role === 'supervisor') {
    $stmt = $conn->prepare("SELECT name FROM supervisors WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_name = $stmt->fetchColumn() ?: 'User';
} elseif ($user_role === 'coordinator') {
    $stmt = $conn->prepare("SELECT name FROM coordinators WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_name = $stmt->fetchColumn() ?: 'User';
}

$dashboard_link = '../student_actions/dashboard.php';
if ($user_role === 'supervisor') {
    $dashboard_link = '../supervisor_actions/dashboard_supervisor.php';
} elseif ($user_role === 'coordinator') {
    $dashboard_link = '../coordinator_actions/dashboard_coordinator.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - InternHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <a href="<?= $dashboard_link ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-home"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <?php if ($user_role === 'student'): ?>
                        <a href="../student_actions/log_hours.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-clock"></i>
                            <span class="font-medium">Log Hours</span>
                        </a>
                        <a href="../student_actions/submit-reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-file-alt"></i>
                            <span class="font-medium">Submit Reports</span>
                        </a>
                    <?php elseif ($user_role === 'supervisor'): ?>
                        <a href="../supervisor_actions/approve_hours.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-clock"></i>
                            <span class="font-medium">Approve Hours</span>
                        </a>
                        <a href="../supervisor_actions/review_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-file-alt"></i>
                            <span class="font-medium">Review Reports</span>
                        </a>
                        <a href="../supervisor_actions/student_progress.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-chart-line"></i>
                            <span class="font-medium">Student Progress</span>
                        </a>
                    <?php elseif ($user_role === 'coordinator'): ?>
                        <a href="../coordinator_actions/review_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-file-alt"></i>
                            <span class="font-medium">Review Reports</span>
                        </a>
                        <a href="../coordinator_actions/student_progress.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-chart-line"></i>
                            <span class="font-medium">Student Progress</span>
                        </a>
                    <?php endif; ?>
                    <a href="messages.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
                        <i class="fas fa-comments"></i>
                        <span class="font-medium">Messages</span>
                    </a>
                </div>
                <div class="space-y-2 mt-auto">
                    <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-cog"></i>
                        <span class="font-medium">Settings</span>
                    </a>
                    <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
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
                        <h2 class="text-2xl font-semibold text-gray-800">Messages</h2>
                        <p class="text-gray-600">Messages feature coming soon</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="bg-gray-200 border-2 border-dashed rounded-xl w-12 h-12 flex items-center justify-center">
                            <i class="fas fa-user text-gray-500"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                            <p class="text-sm text-gray-500"><?= ucfirst($user_role) ?></p>
                        </div>
                    </div>
                </div>
            </header>
            <div class="flex-1 overflow-y-auto p-6">
                <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden p-8 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600 text-lg">No messages yet</p>
                    <p class="text-gray-500 text-sm mt-2">The messaging system will be available soon</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
