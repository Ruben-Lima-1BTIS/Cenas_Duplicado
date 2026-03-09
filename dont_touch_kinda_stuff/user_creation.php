<?php
session_start();


require_once __DIR__ . '/db.php';

$success = "";
$error = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];

    try {
        if ($type === 'class') {
            $course = trim($_POST['course'] ?? '');
            $sigla = trim($_POST['sigla'] ?? '');
            $year = isset($_POST['year']) && $_POST['year'] !== '' ? (int)$_POST['year'] : null;
            $coordinator_id = isset($_POST['coordinator_id']) && $_POST['coordinator_id'] !== '' ? (int)$_POST['coordinator_id'] : null;

            if ($course === '' || $sigla === '' || $year === null || $coordinator_id === null) {
                $error = "All class fields are required.";
            } else {
                $stmt = $conn->prepare("INSERT INTO classes (course, sigla, year, coordinator_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$course, $sigla, $year, $coordinator_id]);
                $success = "Class created successfully!";
            }
        }

        elseif ($type === 'company') {
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if ($name === '' || $address === '' || $email === '' || $phone === '') {
                $error = "All company fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid company email address.";
            } else {
                $stmt = $conn->prepare("INSERT INTO companies (name, address, email, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $address, $email, $phone]);
                $success = "Company created successfully!";
            }
        }

        elseif ($type === 'coordinator') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $class_id = isset($_POST['class_id']) && $_POST['class_id'] !== '' ? (int)$_POST['class_id'] : null;
            $plain_password = $_POST['password'] ?? '';

            if ($name === '' || $email === '' || $plain_password === '') {
                $error = "Name, email and password are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid coordinator email address.";
            } else {
                $password = password_hash($plain_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO coordinators (name, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $password]);
                $coord_id = $conn->lastInsertId();

                if ($class_id !== null) {
                    $upd = $conn->prepare("UPDATE classes SET coordinator_id = ? WHERE id = ?");
                    $upd->execute([$coord_id, $class_id]);
                    $success = "Coordinator created and linked to class successfully!";
                } else {
                    $success = "Coordinator created successfully!";
                }
            }
        }

        elseif ($type === 'internship') {
            $title = trim($_POST['title'] ?? '');
            $company_id = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null;
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $total_hours_required = isset($_POST['total_hours_required']) ? (int)$_POST['total_hours_required'] : null;
            $min_hours_day = isset($_POST['min_hours_day']) && $_POST['min_hours_day'] !== '' ? (float)$_POST['min_hours_day'] : null;
            $lunch_break_minutes = isset($_POST['lunch_break_minutes']) && $_POST['lunch_break_minutes'] !== '' ? (int)$_POST['lunch_break_minutes'] : null;

            if ($title === '' || $company_id === null || $start_date === '' || $end_date === '' || $total_hours_required === null || $min_hours_day === null || $lunch_break_minutes === null) {
                $error = "All internship fields are required.";
            } elseif (strtotime($start_date) === false || strtotime($end_date) === false) {
                $error = "Invalid internship dates.";
            } elseif (strtotime($start_date) > strtotime($end_date)) {
                $error = "Internship start date must be before or equal to end date.";
            } else {
                $stmt = $conn->prepare("INSERT INTO internships (company_id, title, start_date, end_date, total_hours_required, min_hours_day, lunch_break_minutes) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$company_id, $title, $start_date, $end_date, $total_hours_required, $min_hours_day, $lunch_break_minutes]);
                $success = "Internship created successfully!";
            }
        }

        elseif ($type === 'student') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $class_id = isset($_POST['class_id']) && $_POST['class_id'] !== '' ? (int)$_POST['class_id'] : null;
            $internship_id = isset($_POST['internship_id']) && $_POST['internship_id'] !== '' ? (int)$_POST['internship_id'] : null;
            $plain_password = $_POST['password'] ?? '';

            if ($name === '' || $email === '' || $plain_password === '' || $class_id === null || $internship_id === null) {
                $error = "All student fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid student email address.";
            } else {
                $password = password_hash($plain_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO students (name, email, password_hash, class_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password, $class_id]);
                $student_id = $conn->lastInsertId();

                $link = $conn->prepare("INSERT INTO student_internships (student_id, internship_id) VALUES (?, ?)");
                $link->execute([$student_id, $internship_id]);

                $success = "Student created and assigned successfully!";
            }
        }

        elseif ($type === 'supervisor') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $company_id = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null;
            $plain_password = $_POST['password'] ?? '';

            if ($name === '' || $email === '' || $plain_password === '' || $company_id === null) {
                $error = "All supervisor fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid supervisor email address.";
            } else {
                $password = password_hash($plain_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO supervisors (name, email, password_hash, company_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password, $company_id]);
                $success = "Supervisor created and assigned successfully!";
            }
        }

        elseif ($type === 'assign_supervisor_internship') {
            $supervisor_id = isset($_POST['supervisor_id']) && $_POST['supervisor_id'] !== '' ? (int)$_POST['supervisor_id'] : null;
            $internship_id = isset($_POST['internship_id']) && $_POST['internship_id'] !== '' ? (int)$_POST['internship_id'] : null;

            if ($supervisor_id === null || $internship_id === null) {
                $error = "Supervisor and internship are required.";
            } else {
                $check = $conn->prepare("SELECT id FROM supervisor_internships WHERE supervisor_id = ? AND internship_id = ?");
                $check->execute([$supervisor_id, $internship_id]);
                if ($check->rowCount() > 0) {
                    $error = "This supervisor is already assigned to this internship.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO supervisor_internships (supervisor_id, internship_id) VALUES (?, ?)");
                    $stmt->execute([$supervisor_id, $internship_id]);
                    $success = "Supervisor assigned to internship successfully!";
                }
            }
        }

        elseif ($type === 'assign_student_internship') {
            $student_id = isset($_POST['student_id']) && $_POST['student_id'] !== '' ? (int)$_POST['student_id'] : null;
            $internship_id = isset($_POST['internship_id']) && $_POST['internship_id'] !== '' ? (int)$_POST['internship_id'] : null;

            if ($student_id === null || $internship_id === null) {
                $error = "Student and internship are required.";
            } else {
                $check = $conn->prepare("SELECT id FROM student_internships WHERE student_id = ?");
                $check->execute([$student_id]);
                if ($check->rowCount() > 0) {
                    $error = "This student is already assigned to an internship.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO student_internships (student_id, internship_id) VALUES (?, ?)");
                    $stmt->execute([$student_id, $internship_id]);
                    $success = "Student assigned to internship successfully!";
                }
            }
        }

        else {
            $error = "Unknown entity type.";
        }

    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$companies = $conn->query("SELECT id, name FROM companies")->fetchAll(PDO::FETCH_ASSOC);
$classes = $conn->query("SELECT id, course, sigla, year FROM classes")->fetchAll(PDO::FETCH_ASSOC);
$internships = $conn->query("SELECT id, title FROM internships")->fetchAll(PDO::FETCH_ASSOC);
$coordinators = $conn->query("SELECT id, name FROM coordinators")->fetchAll(PDO::FETCH_ASSOC);
$supervisors = $conn->query("SELECT id, name FROM supervisors")->fetchAll(PDO::FETCH_ASSOC);
$students = $conn->query("SELECT id, name FROM students")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InternHub — HR User Creation</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6 font-sans">
    <h1 class="text-2xl font-bold mb-6">HR — Create & Assign Entities</h1>

    <?php if($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-4 rounded mb-4 shadow">
        <h2 class="font-semibold mb-2">Create Class</h2>
        <input type="hidden" name="type" value="class">
        <input type="text" name="course" placeholder="Course Name" class="border p-2 rounded w-full mb-2" required>
        <input type="text" name="sigla" placeholder="Sigla (e.g. 3-BTIS)" class="border p-2 rounded w-full mb-2" required>
    <input type="number" name="year" placeholder="Year" class="border p-2 rounded w-full mb-2" required>

    <select name="coordinator_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Assign Coordinator (optional)</option>
            <?php foreach($coordinators as $coord): ?>
                <option value="<?= $coord['id'] ?>"><?= htmlspecialchars($coord['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create Class</button>
    </form>

    <form method="POST" class="bg-white p-4 rounded mb-4 shadow">
        <h2 class="font-semibold mb-2">Create Company</h2>
        <input type="hidden" name="type" value="company">
        <input type="text" name="name" placeholder="Company Name" class="border p-2 rounded w-full mb-2" required>
    <input type="text" name="address" placeholder="Address" class="border p-2 rounded w-full mb-2" required>
    <input type="email" name="email" placeholder="Email" class="border p-2 rounded w-full mb-2" required>
    <input type="text" name="phone" placeholder="Phone" class="border p-2 rounded w-full mb-2" required>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create Company</button>
    </form>

    <form method="POST" class="bg-white p-4 rounded mb-4 shadow">
        <h2 class="font-semibold mb-2">Create Coordinator</h2>
        <input type="hidden" name="type" value="coordinator">
        <input type="text" name="name" placeholder="Full Name" class="border p-2 rounded w-full mb-2" required>
        <input type="email" name="email" placeholder="Email" class="border p-2 rounded w-full mb-2" required>
        <input type="password" name="password" placeholder="Password" class="border p-2 rounded w-full mb-2" required>

        <select name="class_id" class="border p-2 rounded w-full mb-2">
            <option value="">Assign to Class (optional)</option>
            <?php foreach($classes as $c): ?>
                <?php $label = $c['course'] . ' (' . $c['sigla'] . ')'; ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create Coordinator</button>
    </form>

    <form method="POST" class="bg-white p-4 rounded mb-4 shadow">
        <h2 class="font-semibold mb-2">Create Internship</h2>
        <input type="hidden" name="type" value="internship">
    <input type="text" name="title" placeholder="Internship Title" class="border p-2 rounded w-full mb-2" required>
        <select name="company_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Select Company</option>
            <?php foreach($companies as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="start_date" class="border p-2 rounded w-full mb-2" required>
        <input type="date" name="end_date" class="border p-2 rounded w-full mb-2" required>
        <input type="number" name="total_hours_required" placeholder="Total Hours Required" class="border p-2 rounded w-full mb-2" required>
    <input type="number" step="0.5" name="min_hours_day" placeholder="Min Hours/Day" class="border p-2 rounded w-full mb-2" required>
    <input type="number" name="lunch_break_minutes" placeholder="Lunch Break (minutes)" class="border p-2 rounded w-full mb-2" required>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create Internship</button>
    </form>

    <form method="POST" class="bg-white p-4 rounded mb-4 shadow">
        <h2 class="font-semibold mb-2">Create Student</h2>
        <input type="hidden" name="type" value="student">
        <input type="text" name="name" placeholder="Full Name" class="border p-2 rounded w-full mb-2" required>
        <input type="email" name="email" placeholder="Email" class="border p-2 rounded w-full mb-2" required>
        <input type="password" name="password" placeholder="Password" class="border p-2 rounded w-full mb-2" required>

        <select name="class_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Select Class</option>
            <?php foreach($classes as $c): ?>
                <?php $label = $c['course'] . ' (' . $c['sigla'] . ')'; ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="internship_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Select Internship</option>
            <?php foreach($internships as $i): ?>
                <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['title']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create Student</button>
    </form>

    <form method="POST" class="bg-white p-4 rounded mb-4 shadow">
        <h2 class="font-semibold mb-2">Create Supervisor</h2>
        <input type="hidden" name="type" value="supervisor">
        <input type="text" name="name" placeholder="Full Name" class="border p-2 rounded w-full mb-2" required>
        <input type="email" name="email" placeholder="Email" class="border p-2 rounded w-full mb-2" required>
        <input type="password" name="password" placeholder="Password" class="border p-2 rounded w-full mb-2" required>

        <select name="company_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Assign to Company</option>
            <?php foreach($companies as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create Supervisor</button>
    </form>

    <form method="POST" class="bg-white p-4 rounded mb-4 shadow">
        <h2 class="font-semibold mb-2">Assign Supervisor to Internship</h2>
        <input type="hidden" name="type" value="assign_supervisor_internship">

        <select name="supervisor_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Select Supervisor</option>
            <?php foreach($supervisors as $sup): ?>
                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="internship_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Select Internship</option>
            <?php foreach($internships as $int): ?>
                <option value="<?= $int['id'] ?>"><?= htmlspecialchars($int['title']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Assign Supervisor</button>
    </form>

    <form method="POST" class="bg-white p-4 rounded mb-4 shadow">
        <h2 class="font-semibold mb-2">Assign Student to Internship</h2>
        <input type="hidden" name="type" value="assign_student_internship">

        <select name="student_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Select Student</option>
            <?php foreach($students as $stu): ?>
                <option value="<?= $stu['id'] ?>"><?= htmlspecialchars($stu['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="internship_id" class="border p-2 rounded w-full mb-2" required>
            <option value="">Select Internship</option>
            <?php foreach($internships as $int): ?>
                <option value="<?= $int['id'] ?>"><?= htmlspecialchars($int['title']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Assign Student</button>
    </form>
</body>
</html>
