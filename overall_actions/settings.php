<?php
require_once __DIR__ . '/../dont_touch_kinda_stuff/bootstrap.php';
internhub_bootstrap(['db' => true, 'csrf' => true]);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$table = $_SESSION['table'] ?? null;

$user_name = '';
$user_email = '';
if ($user_role === 'student') {
    $stmt = $conn->prepare("SELECT name, email FROM students WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user['name'] ?? 'User';
    $user_email = $user['email'] ?? '';
} elseif ($user_role === 'supervisor') {
    $stmt = $conn->prepare("SELECT name, email FROM supervisors WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user['name'] ?? 'User';
    $user_email = $user['email'] ?? '';
} elseif ($user_role === 'coordinator') {
    $stmt = $conn->prepare("SELECT name, email FROM coordinators WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user['name'] ?? 'User';
    $user_email = $user['email'] ?? '';
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFToken::validateAndRegenerate('settings_csrf')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password && $new_password && $confirm_password) {
            if ($new_password !== $confirm_password) {
                $error = 'New passwords do not match';
            } elseif (strlen($new_password) < 8) {
                $error = 'Password must be at least 8 characters';
            } else {
                $stmt = $conn->prepare("SELECT password_hash FROM $table WHERE id = ?");
                $stmt->execute([$user_id]);
                $stored_hash = $stmt->fetchColumn();

                if (password_verify($current_password, $stored_hash)) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE $table SET password_hash = ? WHERE id = ?");
                    $update->execute([$new_hash, $user_id]);
                    $success = 'Password updated successfully';
                } else {
                    $error = 'Current password is incorrect';
                }
            }
        } else {
            $error = 'All fields are required';
        }
    }
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
<title>Settings - InternHub</title>
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
            <a href="<?= $dashboard_link ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-home"></i><span class="font-medium">Dashboard</span>
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
            <a href="messages.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-comments"></i><span class="font-medium">Messages</span>
            </a>
        </div>
        <div class="space-y-2 mt-auto">
            <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
                <i class="fas fa-cog"></i><span class="font-medium">Settings</span>
            </a>
            <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                <i class="fas fa-sign-out-alt"></i><span class="font-medium">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<main class="flex-1 flex flex-col overflow-hidden">
<header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800">Settings</h2>
            <p class="text-gray-600">Manage your account preferences</p>
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
    <div class="max-w-2xl">
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200 mb-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Account Information</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <p class="mt-1 text-gray-900"><?= htmlspecialchars($user_name) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <p class="mt-1 text-gray-900"><?= htmlspecialchars($user_email) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Role</label>
                    <p class="mt-1 text-gray-900"><?= ucfirst($user_role) ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Change Password</h3>

            <?php if ($success): ?>
                <div class="mb-4 p-3 bg-green-100 text-green-700 border border-green-300 rounded-lg">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-lg">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo CSRFToken::field('settings_csrf'); ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input type="password" name="current_password" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" name="confirm_password" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</main>
</div>
</body>
</html>
