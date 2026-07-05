<?php
// magazijn.php

session_start();
require_once 'db.php';

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Bepaal de huidige stelling (rack). Standaard is dit 1.
$racks = range(1, 12);
$current_rack = isset($_GET['rack']) && in_array((int)$_GET['rack'], $racks) ? (int)$_GET['rack'] : 1;

// Haal de bezetting op voor de huidige stelling
// We tellen alleen banden met de status 'voorraad' of 'gereserveerd' mee.
$stmt = $pdo->prepare("
    SELECT l.col, l.level, COUNT(t.id) as tire_count 
    FROM locations l 
    LEFT JOIN tires t ON t.location_id = l.id AND t.status IN ('voorraad', 'gereserveerd')
    WHERE l.rack = ? 
    GROUP BY l.col, l.level
");
$stmt->execute([$current_rack]);
$locations = $stmt->fetchAll();

// Zet de data om naar een makkelijk uitleesbare matrix: $gridData['A'][10] = 4;
$gridData = [];
foreach ($locations as $loc) {
    $gridData[$loc['col']][$loc['level']] = (int)$loc['tire_count'];
}

$cols = ['A','B','C','D','E','F','G','H'];
$levels = range(10, 1); // Van boven (laag 10) naar beneden (laag 1)

// Functie voor de kleurcodering op basis van het aantal banden in een vak
function getBinStyle($count) {
    if ($count === 0) return ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-700'];
    if ($count <= 4) return ['bg' => 'bg-blue-50', 'border' => 'border-blue-300', 'text' => 'text-blue-800'];
    if ($count <= 7) return ['bg' => 'bg-amber-50', 'border' => 'border-amber-400', 'text' => 'text-amber-800'];
    return ['bg' => 'bg-red-50', 'border' => 'border-red-400', 'text' => 'text-red-800'];
}

$pageTitle = "Magazijn Overzicht";
include 'header.php';
?>

<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Magazijn Locaties</h1>
            <p class="text-slate-500 mt-1">Bekijk in één oogopslag welke vakken nog beschikbaar zijn.</p>
        </div>
        
        <!-- Legenda -->
        <div class="flex gap-3 text-xs font-bold uppercase tracking-wider bg-white p-3 rounded-lg shadow-sm border border-slate-200">
            <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-emerald-400"></span> Leeg</div>
            <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-blue-400"></span> 1-4</div>
            <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-amber-400"></span> 5-7</div>
            <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-400"></span> 8+ (Vol)</div>
        </div>
    </div>

    <!-- Stellingen Selectie -->
    <div class="mb-6">
        <div class="text-sm font-bold text-slate-500 mb-2 uppercase tracking-wider">12x Stellingen</div>
        <div class="flex flex-wrap gap-2 pb-2">
            <?php foreach ($racks as $rack): ?>
                <a href="?rack=<?php echo $rack; ?>" 
                   class="w-10 h-10 flex items-center justify-center rounded-lg text-sm font-black transition-all <?php echo ($rack === $current_rack) ? 'bg-slate-800 text-white shadow-md' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'; ?>">
                    <?php echo $rack; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Het fysieke Stelling Raster -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 sm:p-6 overflow-x-auto">
        
        <div class="min-w-[600px]">
            <!-- Kolom Headers (A t/m H) -->
            <div class="grid grid-cols-9 gap-2 mb-2">
                <div class="flex items-center justify-center text-slate-400 text-xs font-bold">LAAG</div>
                <?php foreach ($cols as $col): ?>
                    <div class="text-center font-black text-xl text-slate-800 bg-slate-100 rounded-t-lg py-1 border-b-2 border-slate-300">
                        <?php echo $col; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Rijen (Laag 10 omlaag naar Laag 1) -->
            <?php foreach ($levels as $level): ?>
                <div class="grid grid-cols-9 gap-2 mb-2">
                    
                    <!-- Rij Label -->
                    <div class="flex items-center justify-end pr-3 font-black text-slate-400 text-lg border-r-2 border-slate-100">
                        <?php echo $level; ?>
                    </div>
                    
                    <!-- De Vakken (A-H) -->
                    <?php foreach ($cols as $col): 
                        $count = isset($gridData[$col][$level]) ? $gridData[$col][$level] : 0;
                        $style = getBinStyle($count);
                        $bin_code = $current_rack . $col . $level; // Bijv. 8B4
                    ?>
                        <div class="border-2 <?php echo $style['border'] . ' ' . $style['bg']; ?> rounded-lg p-1 sm:p-2 flex flex-col items-center justify-center relative group transition-all hover:shadow-md">
                            
                            <!-- Vaknaam met verbeterde leesbaarheid -->
                            <div class="font-sans text-[10px] sm:text-xs font-black text-slate-800 bg-white/60 px-1.5 py-0.5 rounded shadow-sm mb-1 tracking-wider border border-white/50">
                                <?php echo $bin_code; ?>
                            </div>
                            
                            <!-- Aantal banden -->
                            <div class="font-black text-xl sm:text-2xl <?php echo $style['text']; ?> leading-none">
                                <?php echo $count; ?>
                            </div>
                            
                            <?php if ($count > 0): ?>
                                <!-- Verborgen knopje dat verschijnt als je er met de muis overheen gaat -->
                                <a href="voorraad.php?search=<?php echo $bin_code; ?>" class="absolute inset-0 bg-black bg-opacity-80 text-white rounded-[6px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-[10px] sm:text-xs font-bold text-center p-1 backdrop-blur-sm z-10">
                                    Bekijk
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                </div>
            <?php endforeach; ?>
            
            <!-- Vloer visualisatie -->
            <div class="grid grid-cols-9 gap-2 mt-3">
                <div></div>
                <div class="col-span-8 border-t-[6px] border-slate-800 rounded-sm"></div>
            </div>
        </div>

    </div>

</main>

<?php include 'footer.php'; ?>