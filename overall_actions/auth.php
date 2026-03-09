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

// pegar os dados fornecidos no form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $roles = [
        'student' => 'students',
        'supervisor' => 'supervisors',
        'coordinator' => 'coordinators'
    ];

    // procurar o user com o email e password fornecidos
    $user_found = false;
    foreach ($roles as $role => $table) {
        $stmt = $conn->prepare("SELECT id, email, password_hash, first_login FROM $table WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
    $user_found = true;

    // guardar as informacoes do user na sessao
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $role;
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_login'] = $user['first_login'];
    $_SESSION['table'] = $table;

    // se for o primeiro login do utilizador, redirecionamos para mudar a password dada pelo HR
    if ($user['first_login'] == 1) {
        header("Location: change_password.php");
        exit;
    }

    // depois lemos o role guardado na sessao e redirecionamos para o dashboard correto
    if ($role === 'student') header("Location: ../student_actions/dashboard.php");
    elseif ($role === 'supervisor') header("Location: ../supervisor_actions/dashboard_supervisor.php");
    elseif ($role === 'coordinator') header("Location: ../coordinator_actions/dashboard_coordinator.php");
    exit;
}
    }
    if (!$user_found) {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InternHub — Login</title>
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
        <aside class="w-64 bg-blue-700 text-white hidden md:flex flex-col items-center justify-center p-8 text-center">
            <h1 class="text-3xl font-bold mb-4">InternHub</h1>
            <p class="text-blue-200">Track your internship hours, reports, and progress.</p>
        </aside>
        <main class="flex-1 flex items-center justify-center p-4 relative">
            <div class="absolute top-6 left-6">
                <a href="../index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Home
                </a>
            </div>
            <div class="w-full max-w-md">
                <div class="bg-white p-8 rounded-xl shadow-lg border border-gray-200">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-14 h-14 bg-blue-100 text-blue-600 flex items-center justify-center rounded-full mb-3">
                            <i class="fas fa-lock text-2xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800">Welcome back</h2>
                        <p class="text-gray-600">Sign in to your account</p>
                    </div>
                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input 
                                type="email" 
                                name="email"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                required
                            >
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>

                            <!-- replaced: wrap input and add toggle button -->
                            <div class="relative border border-gray-300 rounded-lg">
                                <input 
                                    id="authPassword"
                                    type="password" 
                                    name="password"
                                    class="w-full px-4 py-2 border-transparent rounded-lg focus:outline-none focus:ring-0"
                                    required
                                >
                                <button 
                                    type="button" 
                                    id="authTogglePassword" 
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                    aria-label="Show password"
                                ></button>
                                
                            </div>
                            <div class="mt-1 text-right text-xs">
                            <a href="forgot_password.php" class="text-gray-700 hover:underline font-medium">
                                Forgot Password?
                            </a>
                        </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition">
                            Sign In
                        </button>
                    </form>
                    <div class="mt-6 text-center">
                        <p class="text-gray-600 text-sm">
                            Don’t have an account? <span class="text-gray-800 font-medium">Ask your HR department.</span>
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pw = document.getElementById('authPassword');
    const toggle = document.getElementById('authTogglePassword');
    if (!pw || !toggle) return;

    const eyeSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"> <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /> <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /> </svg>`;
    const eyeOffSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"> <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /> </svg>`;

    // initialize to eye (password hidden)
    toggle.innerHTML = eyeSvg;
    toggle.setAttribute('aria-label', 'Show password');

    toggle.addEventListener('click', (e) => {
        e.preventDefault();
        const hidden = pw.getAttribute('type') === 'password';
        pw.setAttribute('type', hidden ? 'text' : 'password');
        if (hidden) {
            toggle.innerHTML = eyeOffSvg;
            toggle.setAttribute('aria-label', 'Hide password');
        } else {
            toggle.innerHTML = eyeSvg;
            toggle.setAttribute('aria-label', 'Show password');
        }
    });
});
</script>
