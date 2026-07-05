<?php
// login.php

session_start();
require_once 'db.php';

// Als de gebruiker al is ingelogd, sturen we ze direct door naar het dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Vul alstublieft zowel uw gebruikersnaam als wachtwoord in.';
    } else {
        // Gebruiker opzoeken in de database
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Controleren of de gebruiker bestaat en of de password hash overeenkomt
        if ($user && password_verify($password, $user['password'])) {
            // Inloggen succesvol! We slaan de gegevens op in de sessie.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Doorsturen naar het dashboard
            header("Location: index.php");
            exit;
        } else {
            $error = 'Ongeldige gebruikersnaam of wachtwoord.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - Booij Banden</title>
    <!-- Tailwind CSS voor een strakke, moderne interface -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 flex items-center justify-center h-screen antialiased">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md border border-slate-200">
        
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Booij Banden</h1>
            <p class="text-slate-500 mt-2 text-sm">Log in op het voorraadbeheersysteem</p>
        </div>
        
        <?php if ($error): ?>
            <!-- Foutmelding weergave -->
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r" role="alert">
                <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-5">
            <div>
                <label class="block text-slate-700 text-sm font-semibold mb-2" for="username">Gebruikersnaam</label>
                <input class="appearance-none border border-slate-300 rounded-lg w-full py-3 px-4 text-slate-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow" id="username" name="username" type="text" placeholder="Bijv. admin" required autofocus>
            </div>
            
            <div>
                <label class="block text-slate-700 text-sm font-semibold mb-2" for="password">Wachtwoord</label>
                <input class="appearance-none border border-slate-300 rounded-lg w-full py-3 px-4 text-slate-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow" id="password" name="password" type="password" placeholder="********" required>
            </div>
            
            <div class="pt-2">
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 shadow-md" type="submit">
                    Inloggen
                </button>
            </div>
        </form>
        
    </div>
</body>
</html>