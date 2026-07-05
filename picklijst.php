<?php
// picklijst.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Haal alle te picken banden op (status = gereserveerd)
$stmt = $pdo->query("
    SELECT 
        t.set_id,
        GROUP_CONCAT(t.qr_id ORDER BY t.id ASC SEPARATOR ',') as all_qr_ids,
        MAX(t.brand) as brand, 
        MAX(t.model) as model, 
        MAX(t.width) as width, 
        MAX(t.ratio) as ratio, 
        MAX(t.rim) as rim, 
        MAX(t.status) as status,
        MAX(l.code) as location_code,
        MAX(l.rack) as rack,
        MAX(o.customer_name) as customer_name,
        COUNT(t.id) as aantal_banden
    FROM tires t
    LEFT JOIN locations l ON t.location_id = l.id
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.status = 'gereserveerd'
    GROUP BY COALESCE(CONCAT('set_', t.set_id), CONCAT('tire_', t.id))
    ORDER BY MAX(l.rack) ASC, MAX(l.col) ASC, MAX(l.level) DESC
");
$pick_items = $stmt->fetchAll();

// Haal specifieke magazijn map op voor picklocaties
$stmtMap = $pdo->query("
    SELECT l.rack, l.col, l.level, COUNT(t.id) as tire_count 
    FROM locations l 
    INNER JOIN tires t ON t.location_id = l.id AND t.status = 'gereserveerd'
    GROUP BY l.rack, l.col, l.level
");
$pick_locations = $stmtMap->fetchAll();

$mapData = [];
foreach ($pick_locations as $loc) {
    $mapData[$loc['rack']][$loc['col']][$loc['level']] = (int)$loc['tire_count'];
}

$pageTitle = "Picklijst & Taken";
include 'header.php';
?>

<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Picklijst & Magazijn Taken</h1>
            <p class="text-slate-500 mt-1">Hier zie je precies welke banden uit de stellingen gepakt moeten worden.</p>
        </div>
        <a href="scanner.php" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-colors flex items-center gap-2">
            📷 Open Scanner
        </a>
    </div>

    <!-- Visuele Routekaart voor de Picken -->
    <div class="bg-slate-800 p-5 sm:p-6 rounded-xl shadow-sm border border-slate-700 mb-8 relative overflow-hidden">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_rgba(239,68,68,1)]"></div>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Actieve Pick Locaties</h2>
        </div>
        
        <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 sm:gap-3 relative z-10 w-full">
            <?php 
            $racks = range(1, 12);
            $cols = ['A','B','C','D','E','F','G','H'];
            $levels = range(10, 1);
            
            foreach($racks as $rack): ?>
                <div class="flex flex-col items-center">
                    <div class="text-[8px] sm:text-[9px] font-bold text-slate-400 mb-0.5">ST <?php echo $rack; ?></div>
                    <div class="grid grid-cols-8 gap-[1px] bg-slate-900 p-[2px] rounded border border-slate-700 w-full">
                        <?php 
                        foreach($levels as $level):
                            foreach($cols as $col):
                                $c = isset($mapData[$rack][$col][$level]) ? $mapData[$rack][$col][$level] : 0;
                                
                                if ($c === 0) { $color = 'bg-slate-700 opacity-20'; } 
                                else { $color = 'bg-red-500 shadow-[0_0_3px_rgba(239,68,68,1)] z-10 relative animate-pulse'; }
                                
                                echo '<div class="w-full aspect-square rounded-[1px] ' . $color . '" title="Locatie '.$rack.$col.$level.': '.$c.' te picken"></div>';
                            endforeach;
                        endforeach;
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- De echte lijst -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-amber-50">
            <h2 class="font-bold text-lg text-amber-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" /><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" /></svg>
                Te verzamelen (Gesorteerd op looproute)
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Magazijn Locatie</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Aantal & Band</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Klant / Order</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actie</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <?php foreach ($pick_items as $item): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($item['location_code']): ?>
                                    <span class="font-mono text-xl font-black text-slate-800 bg-amber-100 px-3 py-1.5 rounded border border-amber-300">
                                        <?php echo htmlspecialchars($item['location_code']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-sm text-red-500 font-bold">Onbekend</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold text-lg text-slate-900 mb-1">
                                    <?php echo $item['aantal_banden']; ?>x <?php echo $item['width'].'/'.$item['ratio'].' R'.$item['rim']; ?>
                                </div>
                                <div class="text-sm text-slate-500"><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></div>
                                <div class="text-xs text-slate-400 font-mono mt-1">ID: <?php echo explode(',', $item['all_qr_ids'])[0]; ?><?php echo $item['aantal_banden']>1 ? ' e.v.' : ''; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($item['customer_name'] ?? 'Klant (Onbekend)'); ?></div>
                                <div class="text-xs text-slate-500">Klaarzetten</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                <a href="detail.php?id=<?php echo explode(',', $item['all_qr_ids'])[0]; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-colors">
                                    Pakken & Afmelden
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($pick_items) === 0): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 font-medium text-lg">🎉 Alles is gepickt! Er staan geen taken open.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>