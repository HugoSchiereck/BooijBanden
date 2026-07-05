<?php
// header.php

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - Booij Banden' : 'Booij Banden'; ?></title>
    <!-- Tailwind CSS inladen via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Verberg scrollbars op mobiele swipe-elementen (zoals tabbladen) */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<!-- Op kleine schermen text-sm als basis, vanaf sm (tablets) text-base -->
<body class="bg-slate-100 min-h-screen flex flex-col text-sm sm:text-base">

<nav class="bg-white shadow-sm border-b border-slate-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-14 sm:h-16">
            
            <div class="flex items-center gap-6">
                <!-- Het Logo -->
                <a href="<?php echo $is_logged_in ? 'dashboard.php' : 'index.php'; ?>" class="flex-shrink-0 flex items-center">
                    <img src="https://booijbanden.nl/wp-content/uploads/2025/05/logo_v21.svg" alt="Booij Banden" class="h-8 sm:h-10 w-auto">
                </a>
                
                <!-- Desktop Menu Items (Verborgen op mobiel) -->
                <?php if ($is_logged_in): ?>
                <div class="hidden md:block">
                    <div class="flex items-baseline space-x-1">
                        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'; ?> px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5">
                            🏠 Dashboard
                        </a>
                        <a href="voorraad.php" class="<?php echo ($current_page == 'voorraad.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'; ?> px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5">
                            📦 Voorraad
                        </a>
                        <a href="werkplaats.php" class="<?php echo ($current_page == 'werkplaats.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'; ?> px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5">
                            💳 Kassa
                        </a>
                        <a href="klanten.php" class="<?php echo ($current_page == 'klanten.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'; ?> px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5">
                            👥 Klanten
                        </a>
                        <a href="picklijst.php" class="<?php echo ($current_page == 'picklijst.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'; ?> px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5">
                            📋 Picklijst
                        </a>
                        
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="gebruikers.php" class="<?php echo ($current_page == 'gebruikers.php') ? 'bg-slate-800 text-white font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'; ?> px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5 ml-2">
                                ⚙️ Beheer
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Rechterkant: Desktop Login/Logout & Mobiele Hamburger -->
            <div class="flex items-center gap-3">
                <?php if ($is_logged_in): ?>
                    <a href="scanner.php" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-bold transition-colors flex items-center gap-1.5 shadow-sm">
                        📷 <span class="hidden sm:inline">Scan</span>
                    </a>
                    
                    <!-- Desktop Uitloggen -->
                    <div class="hidden md:flex items-center gap-4">
                        <span class="text-slate-500 text-sm">
                            <strong class="text-slate-800"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Gebruiker'); ?></strong>
                        </span>
                        <a href="logout.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors">
                            Uitloggen
                        </a>
                    </div>
                    
                    <!-- Mobiele Hamburger Knop -->
                    <div class="md:hidden flex items-center">
                        <button onclick="toggleMobileMenu()" class="text-slate-500 hover:text-slate-700 hover:bg-slate-100 p-2 rounded-lg focus:outline-none">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs sm:text-sm font-bold transition-colors">
                        Login
                    </a>
                <?php endif; ?>
            </div>
            
        </div>
    </div>

    <!-- Mobiel Menu (Uitklapbaar) -->
    <?php if ($is_logged_in): ?>
    <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 bg-white">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">🏠 Dashboard</a>
            <a href="voorraad.php" class="<?php echo ($current_page == 'voorraad.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">📦 Voorraad & Zoeken</a>
            <a href="werkplaats.php" class="<?php echo ($current_page == 'werkplaats.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">💳 Kassa & Workflow</a>
            <a href="klanten.php" class="<?php echo ($current_page == 'klanten.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">👥 Klanten & Auto's</a>
            <a href="picklijst.php" class="<?php echo ($current_page == 'picklijst.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">📋 Magazijn Picklijst</a>
            <a href="magazijn.php" class="<?php echo ($current_page == 'magazijn.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">🗺️ Magazijn Blueprint</a>
            <a href="invoer.php" class="<?php echo ($current_page == 'invoer.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">➕ Nieuwe Invoer</a>
            <a href="combineren.php" class="<?php echo ($current_page == 'combineren.php') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">🔗 Sets Combineren</a>
            
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <div class="border-t border-slate-100 my-2"></div>
                <a href="gebruikers.php" class="<?php echo ($current_page == 'gebruikers.php') ? 'bg-slate-800 text-white font-bold' : 'text-slate-600'; ?> block px-3 py-2.5 rounded-lg text-base font-medium">⚙️ Gebruikersbeheer</a>
            <?php endif; ?>
            
            <div class="border-t border-slate-100 my-2"></div>
            <div class="px-3 py-2 text-slate-400 text-sm">Ingelogd als <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
            <a href="logout.php" class="block px-3 py-2.5 text-red-600 font-bold text-base">🚪 Uitloggen</a>
        </div>
    </div>
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        }
    </script>
    <?php endif; ?>
</nav>