<?php
// voorraad.php

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$rim = $_GET['rim'] ?? '';
$season = $_GET['season'] ?? '';
$status = $_GET['status'] ?? '';

$error = '';
$tires = [];

// Query opbouwen
// Let op: we gebruiken MAX() en GROUP_CONCAT() om alles van een set in één rij te bundelen!
$sql = "
    SELECT 
        MAX(t.set_id) as set_id,
        GROUP_CONCAT(t.qr_id ORDER BY t.id ASC SEPARATOR ',') as all_qr_ids,
        MAX(t.brand) as brand, 
        MAX(t.model) as model, 
        MAX(t.season) as season, 
        MAX(t.width) as width, 
        MAX(t.ratio) as ratio, 
        MAX(t.rim) as rim, 
        MAX(t.is_new) as is_new, 
        GROUP_CONCAT(COALESCE(t.tread_depth, 'Nieuw') ORDER BY t.id ASC SEPARATOR '|') as all_treads,
        MAX(t.status) as status,
        MAX(l.code) as location_code,
        COUNT(t.id) as aantal_banden,
        MAX(t.price) as piece_price,
        SUM(t.price) as total_price
    FROM tires t
    LEFT JOIN locations l ON t.location_id = l.id
    WHERE t.status != 'uitgeboekt'
";
$params = [];

// Slimme zoekfunctie: splitst op spaties zodat "Michelin 17" ook werkt
if ($search !== '') {
    $terms = explode(' ', $search);
    foreach ($terms as $term) {
        $term = trim($term);
        if ($term === '') continue;
        
        $sql .= " AND (t.brand LIKE ? OR t.model LIKE ? OR t.qr_id LIKE ? OR l.code LIKE ? 
                  OR CONCAT(t.width, '/', t.ratio, ' R', t.rim) LIKE ? 
                  OR CONCAT(t.width, '/', t.ratio, 'R', t.rim) LIKE ?
                  OR CONCAT(t.width, ' ', t.ratio, ' ', t.rim) LIKE ?
                  OR CONCAT(t.width, t.ratio, t.rim) LIKE ?)";
        for ($i = 0; $i < 8; $i++) { $params[] = "%$term%"; }
    }
}

if ($rim) { $sql .= " AND t.rim = ?"; $params[] = $rim; }
if ($season) { $sql .= " AND t.season = ?"; $params[] = $season; }
if ($status) { $sql .= " AND t.status = ?"; $params[] = $status; }

$sql .= " GROUP BY COALESCE(CONCAT('set_', t.set_id), CONCAT('tire_', t.id))";
$sql .= " ORDER BY MAX(t.created_at) DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tires = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Databasefout bij ophalen voorraad: " . $e->getMessage();
}

$pageTitle = "Voorraad";
include 'header.php';
?>

<main class="max-w-7xl mx-auto py-4 sm:py-8 px-2 sm:px-6 lg:px-8">
    <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Voorraad</h1>
        </div>
        <!-- Compacte knop op mobiel -->
        <a href="invoer.php" class="bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-bold py-2 px-4 rounded-lg shadow-sm transition-colors">
            + Nieuwe Invoer
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4 rounded shadow-sm text-red-800 text-sm font-bold">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Compact Filter Formulier -->
    <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-slate-200 mb-4 sm:mb-6">
        <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Zoeken</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Michelin, 8B4 of 275/35R18" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Inch</label>
                <input type="number" name="rim" value="<?php echo htmlspecialchars($rim); ?>" placeholder="Alle maten" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1">Seizoen</label>
                <select name="season" class="w-full border border-slate-300 rounded-md py-1.5 px-3 text-sm bg-white focus:ring-2 focus:ring-blue-500">
                    <option value="">Alles</option>
                    <option value="Zomer" <?php echo $season == 'Zomer' ? 'selected' : ''; ?>>Zomer</option>
                    <option value="Winter" <?php echo $season == 'Winter' ? 'selected' : ''; ?>>Winter</option>
                    <option value="All Season" <?php echo $season == 'All Season' ? 'selected' : ''; ?>>All Season</option>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-1.5 px-3 text-sm rounded-md shadow-sm transition-colors">
                    Filteren
                </button>
            </div>
        </form>
    </div>

    <!-- Compacte Results Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider">Set / QR</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider">Maat & Merk</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider">Staat & Profiel</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider">Locatie</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 text-right text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider">Prijs</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 text-right text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider">Actie</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <?php if (count($tires) > 0): ?>
                        <?php foreach ($tires as $tire): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                
                                <!-- Kolom 1: IDs en Set badges -->
                                <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap">
                                    <div class="mb-1">
                                        <?php if ($tire['aantal_banden'] > 1): ?>
                                            <span class="px-1.5 py-0.5 inline-flex text-[10px] sm:text-xs font-bold rounded bg-indigo-100 text-indigo-800">Set (<?php echo $tire['aantal_banden']; ?>x)</span>
                                        <?php else: ?>
                                            <span class="px-1.5 py-0.5 inline-flex text-[10px] sm:text-xs font-bold rounded bg-slate-100 text-slate-800">1x</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-wrap gap-1 max-w-[100px] sm:max-w-[150px]">
                                        <?php 
                                        $qrs = explode(',', $tire['all_qr_ids']);
                                        foreach($qrs as $q): 
                                        ?>
                                            <a href="detail.php?id=<?php echo htmlspecialchars($q); ?>" class="text-[9px] sm:text-[10px] font-mono bg-white border border-slate-300 text-slate-600 hover:border-blue-500 px-1 py-0.5 rounded shadow-sm">
                                                <?php echo substr(htmlspecialchars($q), 0, 8); ?>..
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                
                                <!-- Kolom 2: Maat en Merk samengevoegd -->
                                <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap">
                                    <div class="font-black text-slate-900 text-sm sm:text-base"><?php echo $tire['width'].'/'.$tire['ratio'].' R'.$tire['rim']; ?></div>
                                    <div class="font-bold text-slate-700 text-[10px] sm:text-xs mt-0.5"><?php echo htmlspecialchars($tire['brand']); ?></div>
                                    <div class="text-[9px] sm:text-[10px] text-slate-500"><?php echo htmlspecialchars($tire['model']); ?></div>
                                </td>
                                
                                <!-- Kolom 3: Alle profieldieptes! -->
                                <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-normal">
                                    <div class="text-[10px] sm:text-xs font-bold text-slate-500 mb-1 flex items-center gap-1">
                                        <?php 
                                        if($tire['season'] == 'Zomer') echo '☀️ Zomer';
                                        elseif($tire['season'] == 'Winter') echo '❄️ Winter';
                                        else echo '🌦️ All Season';
                                        ?>
                                    </div>
                                    <div class="flex flex-wrap max-w-[120px] sm:max-w-[160px]">
                                        <?php 
                                        $treads = explode('|', $tire['all_treads']);
                                        foreach($treads as $tr): 
                                            $label = ($tr === 'Nieuw' || $tr === '') ? 'Nieuw' : $tr . 'mm';
                                            $color = ($label === 'Nieuw') ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-100 text-slate-700 border-slate-200';
                                        ?>
                                            <span class="px-1 py-0.5 inline-block <?php echo $color; ?> border rounded text-[9px] sm:text-[10px] font-bold mr-1 mb-1">
                                                <?php echo $label; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                
                                <!-- Kolom 4: Locatie -->
                                <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap">
                                    <?php if ($tire['location_code']): ?>
                                        <span class="font-mono font-bold text-slate-800 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-300 text-xs">
                                            <?php echo htmlspecialchars($tire['location_code']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] sm:text-xs text-red-500 font-medium">--</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Kolom 5: PRIJZEN! -->
                                <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap text-right">
                                    <div class="font-bold text-slate-800 text-xs sm:text-sm">
                                        &euro; <?php echo number_format((float)$tire['piece_price'], 2, ',', '.'); ?> <span class="text-[9px] text-slate-400 font-normal">/st</span>
                                    </div>
                                    <?php if ($tire['aantal_banden'] > 1): ?>
                                        <div class="font-black text-blue-700 text-xs sm:text-sm mt-0.5">
                                            &euro; <?php echo number_format((float)$tire['total_price'], 2, ',', '.'); ?> <span class="text-[9px] text-blue-400 font-normal">/set</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Kolom 6: Actie -->
                                <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap text-right">
                                    <a href="detail.php?id=<?php echo $qrs[0]; ?>" class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-2 sm:px-3 py-1 sm:py-2 rounded-md transition-colors text-[10px] sm:text-xs font-bold inline-block">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">Geen resultaten.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>