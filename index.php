<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InternHub</title>
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
<body class="bg-gray-50 font-sans flex flex-col min-h-screen">
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <div class="bg-blue-600 text-white font-bold text-xl px-3 py-1 rounded">IH</div>
                        <span class="ml-2 text-xl font-bold text-gray-900">InternHub</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="overall_actions/auth.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition">
                        Login
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl lg:text-6xl">
            InternHub — Track Your Internship the Smart Way
        </h1>
        <p class="mt-6 max-w-2xl mx-auto text-xl text-gray-600">
            InternHub simplifies internships. Log hours, submit reports, and track progress — all in one platform for students, supervisors, and coordinators.
        </p>
        <div class="mt-10">
            <a href="overall_actions/auth.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium text-lg transition shadow-md">
                Login
            </a>
        </div>
    </div>
    <div class="bg-white py-16 flex-1">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900">Features for Every Role</h2>
                <p class="mt-4 text-lg text-gray-600">Everything you need to track and manage internships efficiently.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="border border-gray-200 rounded-xl p-6 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                        <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Students</h3>
                    <ul class="mt-3 space-y-2 text-gray-600">
                        <li>Log internship hours easily</li>
                        <li>Submit weekly reports</li>
                        <li>Track progress in real-time</li>
                    </ul>
                </div>
                <div class="border border-gray-200 rounded-xl p-6 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                        <i class="fas fa-user-tie text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Supervisors</h3>
                    <ul class="mt-3 space-y-2 text-gray-600">
                        <li>Approve hours and reports</li>
                        <li>Provide feedback</li>
                        <li>Manage multiple interns</li>
                        <li>Export data easily</li>
                    </ul>
                </div>
                <div class="border border-gray-200 rounded-xl p-6 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                        <i class="fas fa-chalkboard-teacher text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Coordinators</h3>
                    <ul class="mt-3 space-y-2 text-gray-600">
                        <li>Monitor all student progress</li>
                        <li>Identify students needing support</li>
                        <li>View analytics and stats</li>
                        <li>Ensure internship requirements are met</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <footer class="bg-gray-900 text-white py-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-gray-400">&copy; 2025 InternHub — ECL – Escola de Comércio de Lisboa</p>
            <p class="mt-2 text-sm text-gray-500">Final Evaluation Project — Ruben Alexandre Nobre Lima</p>
        </div>
    </footer>
</body>
</html>
