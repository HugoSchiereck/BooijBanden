<?php
// combineren.php

session_start();
require_once 'db.php';

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$msg = "";
$error = "";
$first_qr = ""; // Om een linkje te maken naar de detailpagina na succes

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- 1. VERWERK HET MAKEN VAN EEN SET ---
    if (isset($_POST['make_set'])) {
        $raw_qrs = $_POST['qr_codes'];
        
        // Splits de invoer op enters, komma's of spaties (handig voor scanners)
        $qrs = preg_split('/[\s,]+/', trim($raw_qrs), -1, PREG_SPLIT_NO_EMPTY);
        $qrs = array_unique($qrs); // Verwijder dubbele scans
        
        if (!in_array(count($qrs), [2, 4])) {
            $error = "Een set mag uitsluitend uit exact 2 of 4 banden bestaan. Je hebt nu " . count($qrs) . " codes gescand.";
        } else {
            // Controleer of al deze banden bestaan en de status 'voorraad' hebben
            $in_clause = implode(',', array_fill(0, count($qrs), '?'));
            $stmtCheck = $pdo->prepare("SELECT qr_id FROM tires WHERE qr_id IN ($in_clause) AND status = 'voorraad'");
            $stmtCheck->execute($qrs);
            $found_qrs = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($found_qrs) !== count($qrs)) {
                $error = "Eén of meerdere gescande codes zijn niet gevonden of liggen momenteel niet op voorraad. (Gevonden: " . count($found_qrs) . " van de " . count($qrs) . ")";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Maak een nieuwe lege set aan
                    $pdo->exec("INSERT INTO tire_sets () VALUES ()");
                    $new_set_id = $pdo->lastInsertId();
                    
                    // Koppel de banden aan deze nieuwe set
                    $stmtUpdate = $pdo->prepare("UPDATE tires SET set_id = ? WHERE qr_id IN ($in_clause)");
                    $params = array_merge([$new_set_id], $found_qrs);
                    $stmtUpdate->execute($params);
                    
                    // Loggen dat ze in een set zijn gezet
                    foreach($found_qrs as $q) {
                        $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$q, $_SESSION['user_id'], "Aan set toegevoegd (#$new_set_id)"]);
                    }
                    
                    $pdo->commit();
                    
                    $msg = "Succes! Set #$new_set_id is aangemaakt met " . count($found_qrs) . " banden.";
                    $first_qr = $found_qrs[0]; // Voor de "Verplaats Set" knop
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Er is een databasefout opgetreden: " . $e->getMessage();
                }
            }
        }
    }
    
    // --- 2. VERWERK HET SPLITSEN VAN EEN SET ---
    if (isset($_POST['split_set'])) {
        $qr_split = strtoupper(trim($_POST['qr_split']));
        
        $stmtCheck = $pdo->prepare("SELECT set_id FROM tires WHERE qr_id = ?");
        $stmtCheck->execute([$qr_split]);
        $tire = $stmtCheck->fetch();
        
        if (!$tire) {
            $error = "Band met code '$qr_split' niet gevonden.";
        } elseif (!$tire['set_id']) {
            $error = "De band '$qr_split' zit momenteel niet in een set en is al los.";
        } else {
            try {
                $pdo->beginTransaction();
                $set_id = $tire['set_id'];
                
                // Haal alle qr_ids op voor de logging
                $qrs = $pdo->query("SELECT qr_id FROM tires WHERE set_id = $set_id")->fetchAll(PDO::FETCH_COLUMN);
                
                // Haal de banden uit de set
                $stmtUpdate = $pdo->prepare("UPDATE tires SET set_id = NULL WHERE set_id = ?");
                $stmtUpdate->execute([$set_id]);
                
                // Verwijder de set zelf
                $pdo->prepare("DELETE FROM tire_sets WHERE id = ?")->execute([$set_id]);
                
                // Loggen
                foreach($qrs as $q) {
                    $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$q, $_SESSION['user_id'], "Uit set gehaald (Set #$set_id gesplitst)"]);
                }
                
                $pdo->commit();
                $msg = "Succes! De set (Set #$set_id) is opgeheven. De banden zijn nu weer los.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Fout bij splitsen: " . $e->getMessage();
            }
        }
    }
}

// --- 3. HAAL SLIMME SUGGESTIES OP ---
$sql_suggestions = "
    SELECT 
        t.brand, t.model, t.width, t.ratio, t.rim, t.season, t.is_new,
        COUNT(t.id) as match_count,
        GROUP_CONCAT(t.qr_id ORDER BY t.tread_depth DESC SEPARATOR ',') as qr_ids,
        GROUP_CONCAT(COALESCE(l.code, 'Geen Locatie') SEPARATOR ',') as locations,
        GROUP_CONCAT(COALESCE(t.tread_depth, 'Nieuw') ORDER BY t.tread_depth DESC SEPARATOR ' & ') as tread_depths
    FROM tires t
    LEFT JOIN locations l ON t.location_id = l.id
    WHERE t.status = 'voorraad'
      AND t.set_id IS NULL
    GROUP BY t.brand, t.model, t.width, t.ratio, t.rim, t.season, t.is_new
    HAVING match_count >= 2
    ORDER BY match_count DESC, t.rim DESC
";
$suggestions = $pdo->query($sql_suggestions)->fetchAll();

$pageTitle = "Banden Combineren";
include 'header.php';
?>

<script src="https://unpkg.com/html5-qrcode"></script>

<main class="w-full max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 overflow-hidden">
    
    <div class="mb-6 w-full min-w-0">
        <h1 class="text-3xl font-bold text-slate-800">Banden Combineren tot Sets</h1>
        <p class="text-slate-500 mt-1">Smeed losse banden samen tot sets van 2 of 4 voor de verkoop.</p>
    </div>

    <?php if ($msg): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-5 mb-6 rounded-r shadow-sm w-full min-w-0">
            <h3 class="text-green-800 font-bold text-lg"><?php echo $msg; ?></h3>
            <p class="text-green-700 mt-1">Vergeet niet om de banden fysiek te verplaatsen in het magazijn!</p>
            <?php if ($first_qr): ?>
            <div class="mt-3">
                <a href="detail.php?id=<?php echo htmlspecialchars($first_qr); ?>" class="bg-white border border-green-300 text-green-700 hover:bg-green-100 font-bold py-2 px-4 rounded shadow-sm inline-block">
                    Locatie in systeem updaten &rarr;
                </a>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded shadow-sm text-red-800 font-medium w-full min-w-0"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start w-full min-w-0">
        
        <div class="lg:col-span-1 flex flex-col gap-6 sticky top-24 min-w-0 w-full">
            
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden w-full min-w-0">
                <div class="px-6 py-4 border-b border-slate-100 bg-blue-50 flex justify-between items-center">
                    <h2 class="font-bold text-lg text-blue-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z" clip-rule="evenodd" />
                        </svg>
                        Nieuwe Set Maken
                    </h2>
                </div>
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <p class="text-xs text-slate-500 font-bold uppercase tracking-wider">Te koppelen codes</p>
                        <button type="button" onclick="startCameraScanner('textarea')" class="text-blue-600 bg-blue-50 hover:bg-blue-100 font-bold px-2 py-1 rounded text-xs flex items-center gap-1 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /></svg>
                            Scan band
                        </button>
                    </div>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <textarea name="qr_codes" id="qr_codes_textarea" rows="4" class="w-full border border-slate-300 rounded-lg py-3 px-4 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm" placeholder="Typ of scan codes hier...&#10;Druk op Enter na elke code" required autofocus></textarea>
                        </div>
                        <button type="submit" name="make_set" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                            Maak Set van Scans
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden w-full min-w-0">
                <div class="px-6 py-4 border-b border-slate-100 bg-red-50">
                    <h2 class="font-bold text-lg text-red-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                        </svg>
                        Bestaande Set Splitsen
                    </h2>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-600 mb-4">Scan <strong>één band</strong> uit een set om de hele set op te heffen. <br><br><span class="text-xs text-red-600 font-bold">Let op: Alleen banden met status 'In Voorraad' verschijnen daarna weer in de suggesties.</span></p>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Band Code</label>
                                <button type="button" onclick="startCameraScanner('input')" class="text-blue-600 bg-blue-50 hover:bg-blue-100 font-bold px-2 py-1 rounded text-xs flex items-center gap-1 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /></svg>
                                    Camera
                                </button>
                            </div>
                            <input type="text" name="qr_split" id="qr_split_input" class="w-full border border-slate-300 rounded-lg py-3 px-4 focus:outline-none focus:ring-2 focus:ring-red-500 font-mono text-sm uppercase" placeholder="Typ of scan code" required>
                        </div>
                        <button type="submit" name="split_set" class="w-full bg-white border border-red-300 hover:bg-red-50 text-red-700 font-bold py-3 px-4 rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                            Opheffen / Splitsen
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <div class="lg:col-span-2 w-full min-w-0">
            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                ✨ Systeem Suggesties 
                <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-full"><?php echo count($suggestions); ?> gevonden</span>
            </h2>
            
            <?php if (count($suggestions) === 0): ?>
                <div class="bg-slate-50 border border-dashed border-slate-300 rounded-xl p-8 text-center">
                    <p class="text-slate-500 font-medium">Het systeem kan momenteel geen losse banden vinden die exact met elkaar overeenkomen (Merk, Model, Maat, Profiel).</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full min-w-0">
                    <?php foreach ($suggestions as $sug): 
                        $set_size = $sug['match_count'] >= 4 ? 4 : 2;
                        
                        $qrs_array = explode(',', $sug['qr_ids']);
                        $qrs_to_use = array_slice($qrs_array, 0, $set_size);
                        $qr_string_to_use = implode(',', $qrs_to_use);

                        $locs = array_unique(explode(',', $sug['locations']));
                        $loc_string = implode(' & ', $locs);
                        $is_scattered = count($locs) > 1; 
                    ?>
                        <div class="bg-white rounded-xl shadow-sm border <?php echo $is_scattered ? 'border-amber-300' : 'border-slate-200'; ?> p-5 flex flex-col justify-between hover:shadow-md transition-shadow min-w-0">
                            
                            <div>
                                <div class="flex justify-between items-start mb-2">
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-800 text-white">
                                        Voorstel: Set van <?php echo $set_size; ?>
                                    </span>
                                    <?php if ($is_scattered): ?>
                                        <span class="text-[10px] uppercase font-bold text-amber-600 bg-amber-50 px-2 py-1 rounded border border-amber-200">
                                            Verspreid in magazijn!
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3 class="font-black text-xl text-slate-900 mt-2 truncate">
                                    <?php echo $sug['width'].'/'.$sug['ratio'].' R'.$sug['rim']; ?>
                                </h3>
                                <p class="font-bold text-slate-700 truncate"><?php echo htmlspecialchars($sug['brand'] . ' ' . $sug['model']); ?></p>
                                
                                <div class="flex items-center gap-2 mt-2 text-sm">
                                    <span class="text-slate-600 font-medium"><?php echo htmlspecialchars($sug['season']); ?></span>
                                    <span class="text-slate-300">•</span>
                                    <span class="font-bold text-slate-700 truncate">Profielen: <?php echo $sug['tread_depths']; ?></span>
                                </div>
                                
                                <div class="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-100">
                                    <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Huidige Locaties</p>
                                    <p class="font-mono font-bold text-slate-800 truncate"><?php echo htmlspecialchars($loc_string); ?></p>
                                </div>
                            </div>
                            
                            <div class="mt-5 pt-4 border-t border-slate-100">
                                <form method="POST" action="">
                                    <input type="hidden" name="qr_codes" value="<?php echo htmlspecialchars(str_replace(',', "\n", $qr_string_to_use)); ?>">
                                    <button type="submit" name="make_set" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold py-2 px-4 rounded-lg transition-colors border border-slate-300">
                                        Accepteer & Maak Set
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</main>

<div id="cameraModal" class="hidden fixed inset-0 bg-slate-900 bg-opacity-75 flex justify-center items-center z-50 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /></svg>
                Scan QR Code
            </h3>
            <button onclick="stopCameraScanner()" class="text-slate-400 hover:text-slate-600 transition-colors bg-slate-200 rounded p-1">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div id="qr-reader" style="width: 100%;" class="bg-black"></div>
        <div class="p-4 text-center bg-slate-50">
            <p class="text-sm text-slate-500">Richt de camera op de sticker. De code wordt automatisch ingevuld in het geselecteerde veld.</p>
        </div>
    </div>
</div>

<script>
    let html5QrcodeScanner = null;
    let scanTarget = ''; // Kan 'textarea' of 'input' zijn

    function startCameraScanner(target) {
        scanTarget = target;
        
        // Open de modal
        document.getElementById('cameraModal').classList.remove('hidden');
        
        // Start de camera configuratie
        html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader", 
            { fps: 10, qrbox: {width: 250, height: 250} },
            /* verbose= */ false
        );
        
        // Koppel succes-actie
        html5QrcodeScanner.render(onScanSuccess);
    }

    function onScanSuccess(decodedText, decodedResult) {
        // 1. Sluit de scanner af
        html5QrcodeScanner.clear();
        document.getElementById('cameraModal').classList.add('hidden');
        
        // 2. Vul het juiste tekstveld in op basis van de target
        if (scanTarget === 'textarea') {
            let el = document.getElementById('qr_codes_textarea');
            // Voeg de gescande code toe en zet er een enter (\n) achter
            el.value += decodedText + '\n';
        } else if (scanTarget === 'input') {
            let el = document.getElementById('qr_split_input');
            el.value = decodedText;
        }
    }

    function stopCameraScanner() {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
        }
        document.getElementById('cameraModal').classList.add('hidden');
    }
</script>

<?php include 'footer.php'; ?>