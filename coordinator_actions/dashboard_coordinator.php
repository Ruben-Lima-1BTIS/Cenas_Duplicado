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

// prevenir qualquer outro role de aceder a esta pagina
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: auth.php");
    exit;
}

$coordinator_id = $_SESSION['user_id'];

// pegar todas as turmas deste coordenador
$stmt = $conn->prepare("SELECT id, sigla AS class_name FROM classes WHERE coordinator_id = ?");
$stmt->execute([$coordinator_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ver qual turma foi selecionada
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : ($classes[0]['id'] ?? null);

// pegar o nome da turma selecionada para mostrar
$selected_class_name = null;
foreach ($classes as $c) {
    if ($c['id'] == $class_id) {
        $selected_class_name = $c['class_name'];
        break;
    }
}

// pegar a informacao do coordenador
$stmt = $conn->prepare("SELECT name AS coordinator_name FROM coordinators WHERE id = ?");
$stmt->execute([$coordinator_id]);
$coordinator = $stmt->fetch(PDO::FETCH_ASSOC);

// se o coordenador nao tiver nenhuma turma atribuida
if (!$class_id) {
    die("No classes assigned to this coordinator.");
}

// selecionar todos os alunos da turma selecionada
$stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
$stmt->execute([$class_id]);
$total_students = $stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.name AS student_name,
        comp.name AS company_name,
        i.total_hours_required,
        COALESCE((SELECT SUM(h.duration_hours) FROM hours h WHERE h.student_id = s.id),0) AS logged_hours,
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


// horas feiras esta semana por aluno
$stmt = $conn->prepare("
    SELECT 
        s.name AS student_name,
        comp.name AS company_name,
        COALESCE(SUM(h.duration_hours),0) AS weekly_hours
    FROM students s
    LEFT JOIN student_internships si ON s.id = si.student_id
    LEFT JOIN internships i ON si.internship_id = i.id
    LEFT JOIN companies comp ON i.company_id = comp.id
    LEFT JOIN hours h 
        ON s.id = h.student_id 
        AND h.internship_id = i.id
        AND YEARWEEK(h.date,1) = YEARWEEK(CURRENT_DATE,1)
    WHERE s.class_id = ?
    GROUP BY s.id, comp.name
");
$stmt->execute([$class_id]);
$weekly_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coordinator Dashboard - InternHub</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
tailwind.config = {
    theme: {
        extend: {
            colors: { primary: {500: '#2563eb', 700:'#1d4ed8'} }
        }
    }
}
</script>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">

<!-- menu lateral -->
<aside class="w-64 bg-blue-700 text-white flex flex-col">
    <div class="p-6 border-b border-blue-600">
        <h1 class="text-2xl font-bold">InternHub</h1>
    </div>
    <nav class="p-4 flex flex-col min-h-[calc(100vh-5rem)]">
        <div class="space-y-2 flex-1">
            <a href="dashboard_coordinator.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-700 border-l-4 border-blue-500">
                <i class="fas fa-home"></i><span class="font-medium">Dashboard</span>
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
<header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Coordinator Dashboard</h2>
        <form method="get" class="mt-1">
            <label for="class_id" class="text-gray-700 font-medium mr-2">Class:</label>
            <select name="class_id" id="class_id" onchange="this.form.submit()" class="border-gray-300 rounded">
                <?php foreach($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id']==$class_id?'selected':'' ?>>
                        <?= htmlspecialchars($c['class_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="flex items-center space-x-4">
        <div class="bg-gray-200 border-2 border-dashed rounded-xl w-12 h-12 flex items-center justify-center">
            <i class="fas fa-user text-gray-500"></i>
        </div>
        <div>
            <p class="font-medium text-gray-800"><?= htmlspecialchars($coordinator['coordinator_name']) ?></p>
            <p class="text-sm text-gray-500">Coordinator</p>
        </div>
    </div>
</header>

<div class="flex-1 overflow-y-auto p-6 space-y-6">

<!-- estatisticas dos alunos resumida -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
        <p class="text-sm text-gray-600">Total Students</p>
        <p class="text-2xl font-bold text-gray-800"><?= $total_students ?></p>
    </div>
    <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
        <p class="text-sm text-gray-600">On Track</p>
        <p class="text-2xl font-bold text-green-600">
            <?= round(count(array_filter($students, fn($s)=>$s['logged_hours']>=($s['total_hours_required']*0.7)))/max($total_students,1)*100,1) ?>%
        </p>
    </div>
    <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
        <p class="text-sm text-gray-600">Needs Attention</p>
        <p class="text-2xl font-bold text-red-600">
            <?= count(array_filter($students, fn($s)=>$s['logged_hours']<($s['total_hours_required']*0.7))) ?>
        </p>
    </div>
</div>

<!-- graficos chart.js -->
<div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
    <h3 class="text-xl font-semibold text-gray-800 mb-4 text-center">Hours Logged This Week</h3>
    <div class="h-80"><canvas id="studentHoursChart"></canvas></div>
</div>

<!-- tabela dos alunos -->
<div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Student Progress</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hours</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reports</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php foreach ($students as $s):
                $onTrack = $s['logged_hours'] >= ($s['total_hours_required']*0.7);
            ?>
                <tr class="<?= !$onTrack ? 'bg-red-50' : '' ?>">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($s['student_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($s['company_name'] ?? '') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-800"><?= (int)$s['logged_hours'] ?> / <?= (int)$s['total_hours_required'] ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= (int)$s['reports_submitted'] ?>/<?= (int)$s['reports_required'] ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?= $onTrack?'bg-green-100 text-green-800':'bg-red-100 text-red-800' ?>">
                            <?= $onTrack?'On Track':'At Risk' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="../overall_actions/messages.php?user_id=<?= (int)$s['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            <i class="fas fa-comment mr-1"></i> Message
                        </a>
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
const labels = <?= json_encode(array_column($weekly_hours,'student_name')) ?>;
const hours = <?= json_encode(array_column($weekly_hours,'weekly_hours')) ?>;

new Chart(document.getElementById("studentHoursChart"),{
    type:"bar",
    data:{
        labels: labels,
        datasets:[
            {label:"Hours This Week",data:hours,backgroundColor:"#3b82f6",borderRadius:4,borderSkipped:false},
            {label:"Weekly Target (35 hrs)",data:Array(labels.length).fill(35),backgroundColor:"#e5e7eb"}
        ]
    },
    options:{responsive:true,maintainAspectRatio:false}
});
</script>

</body>
</html>
