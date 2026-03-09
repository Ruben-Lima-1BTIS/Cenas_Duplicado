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

// ---------------------------
// SECURITY CHECK
// ---------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: auth.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch student name for header display
$stmt = $conn->prepare("SELECT name FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$user_name = $stmt->fetchColumn() ?: $_SESSION['email'] ?? 'Intern';

// Fetch the active internship for this student
$stmt = $conn->prepare("
    SELECT si.internship_id, i.title 
    FROM student_internships si
    JOIN internships i ON si.internship_id = i.id
    WHERE si.student_id = ? AND i.status = 'active'
    LIMIT 1
");
$stmt->execute([$student_id]);
$internship = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$internship) {
    die("No active internship assigned. Contact your coordinator.");
}
$internship_id = $internship['internship_id'];

// ---------------------------
// HANDLE FORM SUBMISSION
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $date = $_POST['date'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;

    if ($date && $start_time && $end_time) {
        try {
            // Prevent duplicate log for the same student + internship + date
            $chk = $conn->prepare("SELECT COUNT(*) FROM hours WHERE student_id = ? AND internship_id = ? AND date = ?");
            $chk->execute([$student_id, $internship_id, $date]);
            $exists = (int) $chk->fetchColumn();

            if ($exists > 0) {
                echo json_encode(["success" => false, "error" => "You have already logged hours for this date."]);
                exit;
            }

            $stmt = $conn->prepare("
                INSERT INTO hours (student_id, internship_id, date, start_time, end_time)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $internship_id, $date, $start_time, $end_time]);
            echo json_encode(["success" => true]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(["success" => false, "error" => "Missing data"]);
    exit;
}

// ---------------------------
// FETCH RECENT ENTRIES
// ---------------------------
$stmt = $conn->prepare("
    SELECT date, start_time, end_time, duration_hours, status
    FROM hours
    WHERE student_id = ? AND internship_id = ?
    ORDER BY date DESC
    LIMIT 20
");
$stmt->execute([$student_id, $internship_id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
<title>Log Hours - InternHub</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-blue-700 text-white flex flex-col">
        <div class="p-6 border-b border-blue-600">
            <h1 class="text-2xl font-bold">InternHub</h1>
        </div>
        <nav class="p-4 flex flex-col min-h-[calc(100vh-5rem)]">
            <div class="space-y-2 flex-1">
                <a href="<?= relUrl('/dashboard.php') ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                    <i class="fas fa-home"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                <a href="<?= relUrl('/log_hours.php') ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
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

        <!-- HEADER -->
        <header class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800">Log Your Internship Hours</h2>
                <p class="text-gray-600"><?= htmlspecialchars($internship['title']) ?></p>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-gray-200 rounded-xl flex items-center justify-center border-2 border-dashed">
                    <i class="fas fa-user text-gray-500"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                    <p class="text-sm text-gray-500">Intern</p>
                </div>
            </div>
        </header>

        <!-- CONTENT -->
        <div class="flex-1 overflow-y-auto p-6 space-y-8">

            <!-- FORM -->
            <div class="bg-white p-6 rounded-xl shadow-md border max-w-2xl">
                <h3 class="text-xl font-semibold mb-4 text-gray-800">Add New Hours</h3>
                <form id="hoursForm" method="POST">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Date</label>
                            <input type="date" name="date" required class="input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Start Time</label>
                            <input type="time" name="start_time" required class="input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">End Time</label>
                            <input type="time" name="end_time" required class="input">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg">
                            Log Hours
                        </button>
                    </div>
                </form>
            </div>

            <!-- RECENT ENTRIES -->
            <div class="bg-white p-6 rounded-xl shadow-md border">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Entries</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="th">Date</th>
                                <th class="th">Start Time</th>
                                <th class="th">End Time</th>
                                <th class="th">Hours</th>
                                <th class="th">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($entries as $row): ?>
                                <?php
                                $status = strtolower($row['status']);
                                $badge = [
                                    "pending" => "bg-yellow-100 text-yellow-800",
                                    "approved" => "bg-green-100 text-green-800",
                                    "rejected" => "bg-red-100 text-red-800"
                                ][$status];
                                ?>
                                <tr>
                                    <td class="td"><?= htmlspecialchars($row['date']) ?></td>
                                    <td class="td"><?= htmlspecialchars($row['start_time']) ?></td>
                                    <td class="td"><?= htmlspecialchars($row['end_time']) ?></td>
                                    <td class="td"><?= htmlspecialchars($row['duration_hours']) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full <?= $badge ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
document.getElementById("hoursForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    let res = await fetch("", { method: "POST", body: formData });
    let json = await res.json();
    if (json.success) location.reload();
    else alert("Error logging hours: " + (json.error ?? ""));
});
</script>

<style>
.input { @apply w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500; }
.th { @apply px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase; }
.td { @apply px-4 py-3 text-sm text-gray-800; }
</style>
</body>
</html>
