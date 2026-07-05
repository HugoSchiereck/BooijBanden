<?php
// gebruikers.php

session_start();
require_once 'db.php';

// Controleer of de gebruiker is ingelogd én de rol 'admin' heeft
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Geen admin? Terug naar het dashboard
    header("Location: index.php");
    exit;
}

$msg = "";
$error = "";

// --- Verwerk Verwijderen ---
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    
    // Voorkom dat de admin zichzelf verwijdert
    if ($del_id === $_SESSION['user_id']) {
        $error = "Je kunt niet je eigen account verwijderen!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$del_id])) {
            $msg = "Gebruiker succesvol verwijderd.";
        } else {
            $error = "Fout bij het verwijderen van de gebruiker.";
        }
    }
}

// --- Verwerk Nieuwe Gebruiker ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'] === 'admin' ? 'admin' : 'employee';
    
    if (empty($username) || empty($password)) {
        $error = "Vul zowel een gebruikersnaam als een wachtwoord in.";
    } else {
        // Controleer of de gebruikersnaam al bestaat
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetch()) {
            $error = "Deze gebruikersnaam bestaat al. Kies een andere.";
        } else {
            // Wachtwoord veilig versleutelen
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            if ($stmtInsert->execute([$username, $hashed_password, $role])) {
                $msg = "Nieuwe gebruiker '$username' succesvol aangemaakt.";
            } else {
                $error = "Fout bij het opslaan in de database.";
            }
        }
    }
}

// Haal alle gebruikers op
$stmtUsers = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
$users = $stmtUsers->fetchAll();

$pageTitle = "Gebruikersbeheer";
include 'header.php';
?>

<main class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-800">Gebruikersbeheer</h1>
        <p class="text-slate-500 mt-1">Beheer de toegang tot het Booij Banden systeem.</p>
    </div>

    <?php if ($msg): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded shadow-sm text-green-800 font-medium"><?php echo $msg; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded shadow-sm text-red-800 font-medium"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Linker Kolom: Nieuwe gebruiker toevoegen -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h2 class="font-bold text-lg text-slate-800">Nieuwe Account</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Gebruikersnaam</label>
                            <input required type="text" name="username" class="w-full border border-slate-300 rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Wachtwoord</label>
                            <input required type="password" name="password" class="w-full border border-slate-300 rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Rol / Rechten</label>
                            <select name="role" class="w-full border border-slate-300 rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="employee">Medewerker (Standaard)</option>
                                <option value="admin">Beheerder (Admin)</option>
                            </select>
                        </div>
                        <button type="submit" name="add_user" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-colors">
                            Gebruiker Aanmaken
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Rechter Kolom: Lijst met gebruikers -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h2 class="font-bold text-lg text-slate-800">Huidige Gebruikers</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Naam</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Rol</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Aangemaakt</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acties</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-bold">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($user['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">Admin</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800">Medewerker</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        <?php echo date('d-m-Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <a href="?delete=<?php echo $user['id']; ?>" onclick="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?');" class="text-red-600 hover:text-red-900">Verwijderen</a>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic">Huidig account</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>

</main>

<?php include 'footer.php'; ?>