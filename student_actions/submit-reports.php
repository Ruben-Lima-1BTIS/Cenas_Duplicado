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

// require logged in student
$student_id = $_SESSION['user_id'] ?? null;
if (!$student_id) {
    header('Location: auth.php');
    exit;
}

// load student's internship (prefer active, fallback to latest)
$internship = null;
try {
    $stmt = $conn->prepare("
        SELECT i.id, i.start_date, i.end_date, i.total_hours_required
        FROM student_internships si
        JOIN internships i ON si.internship_id = i.id
        WHERE si.student_id = ? AND i.status = 'active'
        ORDER BY si.assigned_at DESC
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $internship = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$internship) {
        $stmt = $conn->prepare("
            SELECT i.id, i.start_date, i.end_date, i.total_hours_required
            FROM student_internships si
            JOIN internships i ON si.internship_id = i.id
            WHERE si.student_id = ?
            ORDER BY si.assigned_at DESC
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $internship = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $internship = null;
}

// build weeks array: week number => human readable label
$weeks = [];
if ($internship) {
    $start = new DateTime($internship['start_date']);
    $end = new DateTime($internship['end_date']);
    // normalize start to internship start (no shift)
    $interval = new DateInterval('P7D');
    $periodStart = clone $start;
    $weekNum = 1;
    while ($periodStart <= $end) {
        $periodEnd = (clone $periodStart)->add(new DateInterval('P6D'));
        if ($periodEnd > $end) $periodEnd = clone $end;
        $label = sprintf("Week %d (%s — %s)", $weekNum, $periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d'));
        $weeks[$weekNum] = $label;
        $weekNum++;
        $periodStart->add($interval);
    }
}

// prepare messages and handle POST upload
$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedWeek = isset($_POST['week']) ? (int)$_POST['week'] : 0;
    if (!$selectedWeek || !array_key_exists($selectedWeek, $weeks)) {
        $errors[] = 'Please select a valid week.';
    }

    if (!isset($_FILES['reportFile']) || $_FILES['reportFile']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a valid file.';
    } else {
        $file = $_FILES['reportFile'];
        $maxBytes = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxBytes) $errors[] = 'File exceeds 10MB limit.';
        // basic extension check
        $allowed = ['pdf','doc','docx','txt'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) $errors[] = 'Invalid file type.';
    }

    if (empty($errors)) {
        // create uploads folder
        $destDir = __DIR__ . '/uploads/reports/' . $student_id;
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        // unique filename
        $basename = pathinfo($file['name'], PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $basename);
        $uniqueName = $safeBase . '_' . time() . '.' . $ext;
        $destPath = $destDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $errors[] = 'Failed to move uploaded file.';
        } else {
            // insert into existing reports table using its actual columns:
            // id, student_id, internship_id, title, content, submitted_at, status, supervisor_reviewed_by, supervisor_comment
            try {
                $internship_id = $internship['id'] ?? null;
                $originalName = pathinfo($file['name'], PATHINFO_BASENAME);
                $title = "Week {$selectedWeek} - {$originalName}";
                $relativePath = 'uploads/reports/' . $student_id . '/' . $uniqueName;

                // Fix: Use the correct column name for timestamp (replace 'submitted_at' with 'created_at' or your actual column)
                $stmt = $conn->prepare("
                    INSERT INTO reports (student_id, title, file_path, created_at, status)
                    VALUES (?, ?, ?, NOW(), 'pending')
                ");
                $stmt->execute([$student_id, $title, $relativePath]);

                $success = 'Report submitted successfully.';
            } catch (Exception $e) {
                $errors[] = 'Database error while saving report record: ' . $e->getMessage();
                $success = 'File uploaded successfully (but DB save failed).';
            }
        }
    }
}

// fetch recent submissions (best-effort)
$recentReports = [];
try {
    $stmt = $conn->prepare("SELECT id, title, file_path, status, created_at FROM reports WHERE student_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$student_id]);
    $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentReports = [];
}

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
    <title>Submit Reports - InternHub</title>
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
<body>
    <div class="flex min-h-screen">
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
                    <a href="<?= relUrl('/log_hours.php') ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-clock"></i>
                        <span class="font-medium">Log Hours</span>
                    </a>
                    <a href="<?= relUrl('/submit-reports.php') ?>" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
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
        <main class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
               </a>     <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Submit Weekly Report</h2>
                        <p class="text-gray-600">Reflect on your progress and share updates</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="bg-gray-200 border-2 border-dashed rounded-xl w-12 h-12 flex items-center justify-center">
                            <i class="fas fa-user text-gray-500"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Ruben Lima</p>
                            <p class="text-sm text-gray-500">Intern</p>
                        </div>
                    </div>
                </div>
            </header>
<div class="bg-white p-6 rounded-xl shadow-md border border-gray-200 max-w-2xl">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Submit Weekly Report</h3>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form id="reportForm" method="POST" enctype="multipart/form-data">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Week</label>
                <select id="weekSelect" name="week" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    <option value="">Select week</option>
                    <?php if (!empty($weeks)): ?>
                        <?php foreach ($weeks as $num => $label): ?>
                            <option value="<?= $num ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No internship assigned</option>
                    <?php endif; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Upload Report File</label>
                <div id="dropZone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-400 transition cursor-pointer">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="reportFile" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-800">
                                <span>Upload a file</span>
                                <input id="reportFile" name="reportFile" type="file" class="sr-only" accept=".pdf,.doc,.docx,.txt" required>
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">PDF, DOC, DOCX, or TXT (Max 10MB)</p>
                    </div>
                </div>
                <p id="fileName" class="mt-2 text-sm text-gray-600 truncate"></p>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition">
                <i class="fas fa-paper-plane mr-2"></i> Submit Report
            </button>
        </div>
    </form>
</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 <?= $success ? '' : 'hidden' ?> flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 max-w-sm w-full mx-4 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check text-green-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Report Submitted!</h3>
        <p class="text-gray-600 mb-4">Your report has been sent to your supervisor for review.</p>
        <button id="closeModal" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg">
            Close
        </button>
    </div>
</div>

                <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Submissions</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Week</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (!empty($recentReports)): ?>
                                    <?php foreach ($recentReports as $r): ?>
                                        <?php
                                            // try to extract week from title if present ("Week N - ...")
                                            $weekDisplay = '-';
                                            if (!empty($r['title']) && preg_match('/Week\s*(\d+)/i', $r['title'], $m)) {
                                                $weekDisplay = 'Week ' . $m[1];
                                            } elseif (!empty($r['title'])) {
                                                $weekDisplay = htmlspecialchars($r['title']);
                                            }
                                            $submittedAt = !empty($r['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))) : '-';
                                            $status = strtolower($r['status'] ?? 'pending');

                                            // changed: build a download link with suggested filename and fallback class
                                            $fileLink = '';
                                            if (!empty($r['file_path'])) {
                                                $path = htmlspecialchars($r['file_path']);
                                                $basename = htmlspecialchars(basename($r['file_path']));
                                                $fileLink = '<a href="' . $path . '" class="text-blue-600 hover:underline download-link" download="' . $basename . '" data-filename="' . $basename . '">Download</a>';
                                            }
                                        ?>

                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-800"><?= $weekDisplay ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-800"><?= $submittedAt ?> <?= $fileLink ? '<span class="ml-2">' . $fileLink . '</span>' : '' ?></td>
                                            <td class="px-4 py-3">
                                                <?php if ($status === 'approved'): ?>
                                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Approved</span>
                                                <?php elseif ($status === 'rejected'): ?>
                                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Rejected</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-800" colspan="3">No submissions yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('reportFile').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            document.getElementById('fileName').textContent = fileName;
        });

        document.getElementById('reportForm').addEventListener('submit', function(e) {
            // client-side continue to allow server submit; keep existing behavior minimal
        });

        document.getElementById('closeModal').addEventListener('click', function() {
            document.getElementById('successModal').classList.add('hidden');
        });

        // if server reported success, show modal (already shown by server via class toggle)
        // ...existing code...

        // Fallback: force-download links with JS if browser ignores `download` attribute
        document.addEventListener('click', function (e) {
            const el = e.target.closest && e.target.closest('.download-link') || (e.target.classList && e.target.classList.contains('download-link') ? e.target : null);
            if (!el) return;
            e.preventDefault();

            const url = el.href;
            const filename = el.dataset.filename || 'report';

            // Try fetch -> blob -> download
            fetch(url, { credentials: 'same-origin' })
                .then(resp => {
                    if (!resp.ok) throw new Error('Network response was not ok');
                    return resp.blob();
                })
                .then(blob => {
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = blobUrl;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(blobUrl);
                })
                .catch(() => {
                    // last-resort: navigate to the file URL (may open in new tab)
                    window.location.href = url;
                });
        });
    </script>
</body>
</html>