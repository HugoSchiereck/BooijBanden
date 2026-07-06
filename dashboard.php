<?php
// dashboard.php

session_start();
require_once 'db.php';

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Haal basis statistieken op
$stats = $pdo->query("SELECT COUNT(*) as total, COUNT(DISTINCT set_id) as sets FROM tires")->fetch();
$totaal_banden = $stats['total'];
$totaal_sets = $stats['sets'];

// Haal de voorraad op gegroepeerd per velgmaat (inch)
$rim_stats = $pdo->query("SELECT rim, COUNT(*) as amount FROM tires GROUP BY rim ORDER BY rim ASC")->fetchAll();

// Laatste 10 ingevoerde banden/sets
$stmtRecent = $pdo->query("
    SELECT 
        t.set_id,
        GROUP_CONCAT(t.qr_id ORDER BY t.id ASC SEPARATOR ',') as all_qr_ids,
        MAX(t.brand) as brand,
        MAX(t.model) as model,
        MAX(t.width) as width,
        MAX(t.ratio) as ratio,
        MAX(t.rim) as rim,
        MAX(l.code) as location_code,
        COUNT(t.id) as aantal_banden
    FROM tires t 
    LEFT JOIN locations l ON t.location_id = l.id 
    GROUP BY COALESCE(CONCAT('set_', t.set_id), CONCAT('tire_', t.id))
    ORDER BY MAX(t.created_at) DESC 
    LIMIT 10
");
$recent_tires = $stmtRecent->fetchAll();

// Haal Magazijn Status op voor de Blueprint visualisatie
$stmtMagazijn = $pdo->query("
    SELECT l.rack, l.col, l.level, COUNT(t.id) as tire_count 
    FROM locations l 
    LEFT JOIN tires t ON t.location_id = l.id AND t.status IN ('voorraad', 'gereserveerd') 
    GROUP BY l.rack, l.col, l.level
");
$all_locations = $stmtMagazijn->fetchAll();

$mapData = [];
$loc_vrij = 0; $loc_bijna = 0; $loc_vol = 0;

foreach ($all_locations as $loc) {
    $c = (int)$loc['tire_count'];
    $mapData[$loc['rack']][$loc['col']][$loc['level']] = $c;
    
    if ($c > 0 && $c <= 4) $loc_vrij++;
    elseif ($c > 4 && $c <= 7) $loc_bijna++;
    elseif ($c >= 8) $loc_vol++;
}
$loc_leeg = 960 - ($loc_vrij + $loc_bijna + $loc_vol);

$pageTitle = "Dashboard";
include 'header.php';
?>

<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 overflow-hidden">
    
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="w-full md:w-auto">
            <h1 class="text-3xl font-bold text-slate-800">Welkom, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p class="text-slate-500 mt-1">Hier is een overzicht van de huidige voorraad in het magazijn.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto mt-2 md:mt-0">
            <a href="invoer.php" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 sm:py-2.5 px-5 rounded-lg shadow-sm transition-all flex justify-center items-center gap-2 hover:-translate-y-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                Nieuwe Invoer
            </a>
            <a href="combineren.php" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 sm:py-2.5 px-5 rounded-lg shadow-sm transition-all flex justify-center items-center gap-2 hover:-translate-y-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" /></svg>
                Sets Combineren
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <a href="voorraad.php" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 hover:shadow-md hover:border-blue-400 hover:-translate-y-1 transition-all cursor-pointer group flex flex-col justify-between">
            <div class="flex justify-between items-start">
                <div class="text-sm font-semibold text-slate-500 uppercase tracking-wide group-hover:text-blue-600 transition-colors">Totale Voorraad</div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-300 group-hover:text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="mt-2 text-4xl font-black text-slate-800 group-hover:text-blue-700 transition-colors"><?php echo $totaal_banden; ?> <span class="text-lg text-slate-400 font-medium">banden</span></div>
        </a>
        
        <a href="voorraad.php" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 hover:shadow-md hover:border-indigo-400 hover:-translate-y-1 transition-all cursor-pointer group flex flex-col justify-between">
            <div class="flex justify-between items-start">
                <div class="text-sm font-semibold text-slate-500 uppercase tracking-wide group-hover:text-indigo-600 transition-colors">Complete Sets</div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-300 group-hover:text-indigo-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="mt-2 text-4xl font-black text-slate-800 group-hover:text-indigo-700 transition-colors"><?php echo $totaal_sets; ?> <span class="text-lg text-slate-400 font-medium">sets</span></div>
        </a>

        <a href="magazijn.php" class="lg:col-span-2 bg-slate-800 p-5 sm:p-6 rounded-xl shadow-sm border border-slate-700 hover:shadow-lg hover:border-slate-500 transition-all cursor-pointer group flex flex-col justify-between relative overflow-hidden">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center relative z-10 mb-4 gap-3">
                <div class="text-sm font-semibold text-slate-300 uppercase tracking-wide group-hover:text-white transition-colors flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    Blueprint Magazijn
                </div>
                
                <div class="text-[10px] sm:text-xs text-slate-300 font-medium flex flex-wrap gap-2 sm:gap-3 bg-slate-900/60 px-2.5 py-1.5 rounded-lg border border-slate-700">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-slate-700 opacity-50"></span> <?php echo $loc_leeg; ?></span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-blue-400"></span> <?php echo $loc_vrij; ?></span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-amber-400"></span> <?php echo $loc_bijna; ?></span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-red-500"></span> <?php echo $loc_vol; ?></span>
                </div>
            </div>
            
            <div class="w-full overflow-x-auto hide-scrollbar pb-2 relative z-10">
                <div class="grid grid-cols-6 lg:grid-cols-12 gap-2 sm:gap-3 min-w-[550px] lg:min-w-0">
                    <?php 
                    $racks = range(1, 12);
                    $cols = ['A','B','C','D','E','F','G','H'];
                    $levels = range(10, 1);
                    
                    foreach($racks as $rack): ?>
                        <div class="flex flex-col items-center">
                            <div class="text-[8px] sm:text-[9px] font-bold text-slate-500 mb-0.5">ST <?php echo $rack; ?></div>
                            <div class="grid grid-cols-8 gap-[1px] bg-slate-900 p-[2px] rounded border border-slate-700 w-full">
                                <?php 
                                foreach($levels as $level):
                                    foreach($cols as $col):
                                        $c = isset($mapData[$rack][$col][$level]) ? $mapData[$rack][$col][$level] : 0;
                                        
                                        if ($c === 0) { $color = 'bg-slate-700 opacity-30'; } // LEEG
                                        elseif ($c <= 4) { $color = 'bg-blue-400'; }         // VRIJ
                                        elseif ($c <= 7) { $color = 'bg-amber-400'; }        // BIJNA VOL
                                        else { $color = 'bg-red-500 shadow-[0_0_2px_rgba(239,68,68,0.8)] z-10 relative'; } // VOL
                                        
                                        echo '<div class="w-full aspect-square rounded-[1px] ' . $color . '" title="Locatie '.$rack.$col.$level.': '.$c.' band(en)"></div>';
                                    endforeach;
                                endforeach;
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-blue-500 rounded-full opacity-10 blur-xl group-hover:opacity-20 transition-opacity"></div>
        </a>
    </div>

    <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
        Voorraad per Velgmaat <span class="text-sm font-normal text-slate-400">(Klik om te filteren)</span>
    </h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-10">
        <?php foreach ($rim_stats as $stat): ?>
            <a href="voorraad.php?rim=<?php echo $stat['rim']; ?>" class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col items-center justify-center hover:shadow-md hover:border-blue-400 hover:-translate-y-1 transition-all cursor-pointer group">
                <div class="text-slate-400 text-sm font-bold mb-1 group-hover:text-blue-500 transition-colors">INCH</div>
                <div class="text-3xl font-black text-slate-800 mb-2 group-hover:text-blue-700 transition-colors"><?php echo $stat['rim']; ?>"</div>
                <div class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-sm font-bold group-hover:bg-blue-50 group-hover:text-blue-700 transition-colors">
                    <?php echo $stat['amount']; ?> banden
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (count($rim_stats) === 0): ?>
            <div class="col-span-full text-slate-500 italic p-4">Nog geen banden in voorraad.</div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
            <h2 class="font-bold text-lg text-slate-800">Laatst Ingevoerde Banden/Sets</h2>
            <a href="voorraad.php" class="text-sm font-bold text-blue-600 hover:text-blue-800">Bekijk alles &rarr;</a>
        </div>
        <div class="overflow-x-auto w-full">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-white">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type/Set & QR Codes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Maat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Merk & Model</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Locatie</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actie</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <?php foreach ($recent_tires as $tire): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="mb-2">
                                    <?php if ($tire['aantal_banden'] > 1): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-bold rounded-full bg-indigo-100 text-indigo-800">
                                            Set van <?php echo $tire['aantal_banden']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-bold rounded-full bg-slate-100 text-slate-800">1 Band</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap gap-1 max-w-[150px]">
                                    <?php 
                                    $qrs = explode(',', $tire['all_qr_ids']);
                                    foreach($qrs as $q): 
                                    ?>
                                        <a href="detail.php?id=<?php echo htmlspecialchars($q); ?>" class="text-[11px] font-mono bg-white border border-slate-300 text-slate-600 hover:border-blue-500 hover:text-blue-600 px-1.5 py-0.5 rounded transition-colors shadow-sm">
                                            <?php echo htmlspecialchars($q); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-900">
                                <?php echo $tire['width'].'/'.$tire['ratio'].' R'.$tire['rim']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($tire['brand']); ?></div>
                                <div class="text-sm text-slate-500"><?php echo htmlspecialchars($tire['model']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($tire['location_code']): ?>
                                    <span class="font-mono font-bold text-slate-800 bg-slate-100 px-2 py-1 rounded border border-slate-300"><?php echo htmlspecialchars($tire['location_code']); ?></span>
                                <?php else: ?>
                                    <span class="text-sm text-red-500 font-medium">Geen locatie</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="detail.php?id=<?php echo $qrs[0]; ?>" class="text-blue-600 hover:text-blue-900 font-bold bg-blue-50 hover:bg-blue-100 px-3 py-2 rounded-md transition-colors">Beheren</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>