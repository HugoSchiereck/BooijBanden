<?php
// detail.php

session_start();
require_once 'db.php';

if (!isset($_GET['id'])) {
    die("Geen band ID opgegeven.");
}
$qr_id = $_GET['id'];

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?id=" . urlencode($qr_id));
    exit;
}

$msg = "";
$error = "";

$stmtInit = $pdo->prepare("SELECT set_id, order_id FROM tires WHERE qr_id = ?");
$stmtInit->execute([$qr_id]);
$initialTire = $stmtInit->fetch();
if (!$initialTire) {
    die("Band niet gevonden.");
}
$set_id = $initialTire['set_id'];
$current_order_id = $initialTire['order_id'];

// --- Verwerk Formulieren ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 0. Set Splitsen
    if (isset($_POST['split_set']) && $set_id) {
        try {
            $pdo->beginTransaction();
            $qrs = $pdo->query("SELECT qr_id FROM tires WHERE set_id = $set_id")->fetchAll(PDO::FETCH_COLUMN);
            $pdo->prepare("UPDATE tires SET set_id = NULL WHERE set_id = ?")->execute([$set_id]);
            foreach($qrs as $q) {
                $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$q, $_SESSION['user_id'], "Uit set gehaald"]);
            }
            $pdo->prepare("DELETE FROM tire_sets WHERE id = ?")->execute([$set_id]);
            $pdo->commit();
            $msg = "Set opgeheven.";
            $set_id = null; 
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Fout: " . $e->getMessage();
        }
    }

    // 1. Partner Samenvoegen
    if (isset($_POST['merge_partner'])) {
        $partner_qr = $_POST['partner_qr'];
        try {
            $pdo->beginTransaction();
            $pdo->exec("INSERT INTO tire_sets () VALUES ()");
            $new_set_id = $pdo->lastInsertId();
            $pdo->prepare("UPDATE tires SET set_id = ? WHERE qr_id IN (?, ?)")->execute([$new_set_id, $qr_id, $partner_qr]);
            
            $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$qr_id, $_SESSION['user_id'], "Samengevoegd met $partner_qr"]);
            $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$partner_qr, $_SESSION['user_id'], "Samengevoegd met $qr_id"]);
            
            $pdo->commit();
            $msg = "Samengevoegd tot een nieuwe set!";
            $set_id = $new_set_id;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Fout: " . $e->getMessage();
        }
    }

    // 2. Status & Workflow update (INCLUSIEF CRM OPSLAG)
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['status'];
        $existing_order_id = $_POST['existing_order_id'] ?? 'new';
        $order_id_to_save = $current_order_id;

        // CRM KOPPELING
        if ($new_status === 'gereserveerd' && !$current_order_id) {
            if ($existing_order_id !== 'new' && !empty($existing_order_id)) {
                $order_id_to_save = (int)$existing_order_id;
            } else {
                // Sla de nieuwe klant + auto op in het CRM!
                $customer_name = trim($_POST['customer_name'] ?? '');
                if ($customer_name === '') $customer_name = 'Anonieme Klant';
                
                // Filter kenteken netjes
                $kenteken = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['kenteken'] ?? ''));
                $car_brand = $_POST['car_brand'] ?? '';
                $car_model = $_POST['car_model'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';

                $stmtCust = $pdo->prepare("INSERT INTO customers (name, email, phone, license_plate, car_brand, car_model) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtCust->execute([$customer_name, $email, $phone, $kenteken, $car_brand, $car_model]);
                $customer_id = $pdo->lastInsertId();

                // Maak de order aan, nu gekoppeld aan het customer_id
                $stmt = $pdo->prepare("INSERT INTO orders (customer_id, customer_name) VALUES (?, ?)");
                $stmt->execute([$customer_id, $customer_name]);
                $order_id_to_save = $pdo->lastInsertId();
            }
        }

        $clear_loc_sql = "";
        if ($new_status === 'verkocht' || $new_status === 'uitgeboekt') {
            $clear_loc_sql = ", location_id = NULL";
        }
        
        if ($set_id) {
            $stmt = $pdo->prepare("UPDATE tires SET status = ?, order_id = ? $clear_loc_sql WHERE set_id = ?");
            if ($stmt->execute([$new_status, $order_id_to_save, $set_id])) {
                $msg = "Status succesvol bijgewerkt voor de set.";
                $qrs = $pdo->query("SELECT qr_id FROM tires WHERE set_id = $set_id")->fetchAll(PDO::FETCH_COLUMN);
                foreach($qrs as $q) {
                    $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$q, $_SESSION['user_id'], "Status: $new_status"]);
                }
            }
        } else {
            $stmt = $pdo->prepare("UPDATE tires SET status = ?, order_id = ? $clear_loc_sql WHERE qr_id = ?");
            if ($stmt->execute([$new_status, $order_id_to_save, $qr_id])) {
                $msg = "Status succesvol bijgewerkt.";
                $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$qr_id, $_SESSION['user_id'], "Status: $new_status"]);
            }
        }
        $current_order_id = $order_id_to_save;
    }
    
    // 3. Locatie update
    if (isset($_POST['update_location'])) {
        $loc_input = strtoupper(trim($_POST['location_code']));
        if (empty($loc_input)) {
            if ($set_id) { $pdo->prepare("UPDATE tires SET location_id = NULL WHERE set_id = ?")->execute([$set_id]); $msg = "Locatie gewist."; }
            else { $pdo->prepare("UPDATE tires SET location_id = NULL WHERE qr_id = ?")->execute([$qr_id]); $msg = "Locatie gewist."; }
        } else {
            $loc_input_clean = strtolower($loc_input);
            $special_locations = ['werkplaats', 'buiten', 'achter'];
            if (in_array($loc_input_clean, $special_locations)) { $loc_input = ucfirst($loc_input_clean); $is_valid = true; $rack = null; $col = null; $level = null; } 
            elseif (preg_match('/^([1-9]|1[0-2])([A-H])([1-9]|10)$/', $loc_input, $matches)) { $is_valid = true; $rack = $matches[1]; $col = $matches[2]; $level = $matches[3]; } 
            else { $is_valid = false; $error = "Ongeldige locatiecode."; }
            
            if ($is_valid) {
                $stmtLoc = $pdo->prepare("SELECT id FROM locations WHERE code = ?");
                $stmtLoc->execute([$loc_input]);
                $loc = $stmtLoc->fetch();
                $loc_id = $loc ? $loc['id'] : null;
                if(!$loc_id) {
                    $stmtIns = $pdo->prepare("INSERT INTO locations (code, rack, col, level) VALUES (?, ?, ?, ?)");
                    $stmtIns->execute([$loc_input, $rack, $col, $level]);
                    $loc_id = $pdo->lastInsertId();
                }
                
                if ($set_id) { $pdo->prepare("UPDATE tires SET location_id = ? WHERE set_id = ?")->execute([$loc_id, $set_id]); $msg = "Locatie: $loc_input"; } 
                else { $pdo->prepare("UPDATE tires SET location_id = ? WHERE qr_id = ?")->execute([$loc_id, $qr_id]); $msg = "Locatie: $loc_input"; }
            }
        }
    }
}

// Haal actuele data
$stmt = $pdo->prepare("SELECT t.*, l.code as location_code FROM tires t LEFT JOIN locations l ON t.location_id = l.id WHERE t.qr_id = ?");
$stmt->execute([$qr_id]);
$tire = $stmt->fetch();

$stmtOpenOrders = $pdo->query("SELECT id, customer_name FROM orders WHERE status = 'open' ORDER BY id DESC");
$openOrders = $stmtOpenOrders->fetchAll();

$partner = null;
if ($tire['status'] === 'voorraad' && !$tire['set_id']) {
    $stmtPartner = $pdo->prepare("SELECT t.qr_id, t.tread_depth, l.code as location_code FROM tires t LEFT JOIN locations l ON t.location_id = l.id WHERE t.status = 'voorraad' AND t.set_id IS NULL AND t.qr_id != ? AND t.brand = ? AND t.model = ? AND t.width = ? AND t.ratio = ? AND t.rim = ? AND t.season = ? AND t.is_new = ? ORDER BY ABS(COALESCE(t.tread_depth, 0) - ?) ASC LIMIT 1");
    $stmtPartner->execute([$qr_id, $tire['brand'], $tire['model'], $tire['width'], $tire['ratio'], $tire['rim'], $tire['season'], $tire['is_new'], $tire['tread_depth'] ?? 0]);
    $partner = $stmtPartner->fetch();
}

// Haal ALLE banden op in deze set en bereken totaalprijs
$set_tires = [];
$total_set_price = $tire['price']; // default is prijs enkele band

if ($set_id) {
    $stmtSet = $pdo->prepare("SELECT * FROM tires WHERE set_id = ? ORDER BY id ASC");
    $stmtSet->execute([$set_id]);
    $set_tires = $stmtSet->fetchAll();
    
    $total_set_price = 0;
    foreach($set_tires as $st) {
        $total_set_price += (float)$st['price'];
    }
}

$stmtLogs = $pdo->prepare("SELECT l.*, u.username FROM tire_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.qr_id = ? ORDER BY l.created_at DESC");
$stmtLogs->execute([$qr_id]);
$logs = $stmtLogs->fetchAll();

$pageTitle = "Details: " . $tire['qr_id'];
include 'header.php';
?>

<main class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800"><?php echo htmlspecialchars($tire['brand'] . ' ' . $tire['model']); ?></h1>
            <div class="flex items-center gap-2 mt-2">
                <span class="bg-slate-200 text-slate-800 font-mono px-3 py-1 rounded text-sm font-bold border border-slate-300">ID: <?php echo htmlspecialchars($tire['qr_id']); ?></span>
                <?php if ($set_id): ?>
                    <span class="bg-indigo-100 text-indigo-800 font-bold px-3 py-1 rounded text-sm border border-indigo-200">Deel van Set #<?php echo $set_id; ?></span>
                <?php endif; ?>
                <?php if ($tire['order_id']): ?>
                    <span class="bg-purple-100 text-purple-800 font-mono px-3 py-1 rounded text-sm font-bold border border-purple-200">Order #<?php echo htmlspecialchars($tire['order_id']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="voorraad.php" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-bold py-2 px-4 rounded shadow-sm transition-colors">Terug naar lijst</a>
            <a href="print.php?id=<?php echo $tire['qr_id']; ?>" target="_blank" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold py-2 px-4 rounded shadow-sm transition-colors flex items-center gap-2">🖨️ Etiket Printen</a>
        </div>
    </div>

    <?php if ($msg): ?><div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 mb-6 text-sm rounded shadow-sm text-emerald-800 font-bold"><?php echo $msg; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 text-sm rounded shadow-sm text-red-800 font-bold"><?php echo $error; ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Linker Kolom: Gegevens & SET OVERZICHT -->
        <div class="lg:col-span-2 flex flex-col gap-6">
            
            <!-- Band Info & Prijs -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h2 class="font-bold text-base text-slate-800">Specificaties</h2>
                </div>
                <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-6 text-sm">
                    <div>
                        <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Maat</p>
                        <p class="font-black text-2xl text-slate-900"><?php echo $tire['width'].'/'.$tire['ratio'].' R'.$tire['rim']; ?></p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Seizoen</p>
                        <p class="font-bold text-lg text-slate-900"><?php echo htmlspecialchars($tire['season'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Stukprijs</p>
                        <p class="font-bold text-lg text-slate-900">&euro; <?php echo number_format($tire['price'], 2, ',', '.'); ?></p>
                    </div>
                    
                    <?php if ($set_id): ?>
                    <!-- DE TOTAALPRIJS VAN DE SET -->
                    <div class="bg-blue-50 -m-3 p-3 rounded-lg border border-blue-100 flex flex-col justify-center items-start">
                        <p class="text-blue-800 text-xs font-black uppercase tracking-wider mb-0.5">Totaal Set (<?php echo count($set_tires); ?>x)</p>
                        <p class="font-black text-2xl text-blue-900">&euro; <?php echo number_format($total_set_price, 2, ',', '.'); ?></p>
                    </div>
                    <?php else: ?>
                    <div>
                        <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Profiel</p>
                        <?php if ($tire['is_new']): ?><span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-100 text-emerald-800 border border-emerald-200">Nieuw</span>
                        <?php else: ?><span class="px-2 py-0.5 text-sm font-bold rounded bg-slate-100 text-slate-800 border border-slate-200"><?php echo $tire['tread_depth'] ? $tire['tread_depth'] . ' mm' : '-'; ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NIEUW: SET OVERZICHT (Alle banden onder elkaar) -->
            <?php if ($set_id && !empty($set_tires)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-indigo-100 bg-indigo-50/50 flex justify-between items-center">
                    <h2 class="font-black text-indigo-900 text-base">Onderdelen van deze Set</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 border-b border-slate-100 text-xs text-slate-500 uppercase tracking-wider font-bold">
                            <tr>
                                <th class="px-5 py-3">Band ID</th>
                                <th class="px-5 py-3">Profiel / Staat</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($set_tires as $index => $st): 
                                $isActive = ($st['qr_id'] === $qr_id);
                            ?>
                            <tr class="<?php echo $isActive ? 'bg-blue-50/50' : 'hover:bg-slate-50'; ?> transition-colors">
                                <td class="px-5 py-4 font-mono font-bold <?php echo $isActive ? 'text-blue-700' : 'text-slate-700'; ?>">
                                    <?php echo htmlspecialchars($st['qr_id']); ?>
                                    <?php if($isActive) echo '<span class="ml-2 text-[10px] bg-blue-200 text-blue-800 px-1.5 py-0.5 rounded uppercase tracking-wider">HUIDIG</span>'; ?>
                                </td>
                                <td class="px-5 py-4">
                                    <?php if ($st['is_new']): ?>
                                        <span class="text-emerald-600 font-bold text-xs bg-emerald-50 px-2 py-1 rounded border border-emerald-200">NIEUW</span>
                                    <?php else: ?>
                                        <span class="font-bold text-slate-800"><?php echo $st['tread_depth'] ? $st['tread_depth'] . ' mm' : '-'; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-xs font-bold text-slate-500 uppercase"><?php echo htmlspecialchars($st['status']); ?></td>
                                <td class="px-5 py-4 text-right">
                                    <?php if(!$isActive): ?>
                                        <a href="detail.php?id=<?php echo urlencode($st['qr_id']); ?>" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-800 hover:text-white px-3 py-1.5 rounded text-xs font-bold transition-all shadow-sm">Bekijk details</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Rechter Kolom: Beheer -->
        <div class="flex flex-col gap-6">
            
            <?php if ($partner): ?>
            <div class="bg-purple-50 rounded-xl shadow-sm border border-purple-200 p-5">
                <h2 class="font-black text-base text-purple-900 mb-2 flex items-center gap-2">✨ Match Gevonden!</h2>
                <div class="bg-white rounded-lg border border-purple-100 p-3 mb-4 text-sm shadow-sm">
                    <strong>ID:</strong> <span class="font-mono"><?php echo htmlspecialchars($partner['qr_id']); ?></span><br>
                    <strong>Locatie:</strong> <span class="font-bold text-slate-700"><?php echo htmlspecialchars($partner['location_code'] ?? 'Onbekend'); ?></span><br>
                    <strong>Profiel:</strong> <?php echo $partner['tread_depth'] ? $partner['tread_depth'].' mm' : 'Nieuw'; ?>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="partner_qr" value="<?php echo htmlspecialchars($partner['qr_id']); ?>">
                    <button type="submit" name="merge_partner" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-sm transition-colors text-sm">Samenvoegen tot Set</button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Status Formulier MET RDW/CRM -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50"><h2 class="font-bold text-base text-slate-800">Status & Workflow</h2></div>
                <div class="p-5">
                    <form method="POST" action="" id="statusForm">
                        <div class="mb-4">
                            <select name="status" id="statusSelect" class="w-full border border-slate-300 rounded-lg py-2 px-3 text-sm focus:ring-blue-500 bg-white font-bold" onchange="toggleKlantVeld()">
                                <option value="voorraad" <?php echo $tire['status'] == 'voorraad' ? 'selected' : ''; ?>>📍 In Voorraad</option>
                                <option value="gereserveerd" <?php echo $tire['status'] == 'gereserveerd' ? 'selected' : ''; ?>>🛒 Gereserveerd</option>
                                <option value="gepickt" <?php echo $tire['status'] == 'gepickt' ? 'selected' : ''; ?>>✅ Gepickt</option>
                                <option value="gemonteerd" <?php echo $tire['status'] == 'gemonteerd' ? 'selected' : ''; ?>>🔧 Gemonteerd</option>
                                <option value="verkocht" <?php echo $tire['status'] == 'verkocht' ? 'selected' : ''; ?>>💰 Verkocht</option>
                                <option value="uitgeboekt" <?php echo $tire['status'] == 'uitgeboekt' ? 'selected' : ''; ?>>🗑️ Uitgeboekt</option>
                            </select>
                        </div>
                        
                        <!-- Order & Klant Formulier -->
                        <div id="klantVeld" class="mb-4 p-4 bg-slate-50 border border-slate-200 rounded-lg" style="display: none;">
                            <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Order Koppeling:</label>
                            <select name="existing_order_id" id="existingOrderSelect" class="w-full border border-slate-300 rounded-lg py-2 px-3 text-sm bg-white mb-3 focus:ring-blue-500" onchange="toggleNewCustomerField()">
                                <option value="new" class="font-bold text-blue-600">+ Nieuwe Klant & Order aanmaken</option>
                                <?php foreach($openOrders as $o): ?>
                                    <option value="<?php echo $o['id']; ?>">Order #<?php echo $o['id']; ?> - <?php echo htmlspecialchars($o['customer_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <!-- CRM VELDEN (RDW API) -->
                            <div id="newCustomerFields" style="display:none;" class="mt-4 space-y-3 border-t border-slate-200 pt-3">
                                
                                <div class="flex gap-2 items-start">
                                    <input type="text" name="kenteken" id="kentekenInput" placeholder="Kenteken (AB123C)" class="w-2/3 border border-yellow-400 rounded-lg py-2 px-3 text-sm uppercase font-mono font-bold focus:ring-blue-500 shadow-sm" style="background-color: #ffcc00;">
                                    <button type="button" onclick="fetchRDW()" class="w-1/3 bg-slate-800 text-white rounded-lg py-2 text-xs font-bold hover:bg-slate-700 shadow-sm transition-colors">Zoek Auto</button>
                                </div>
                                
                                <div class="flex gap-2">
                                    <input type="text" name="car_brand" id="carBrandInput" placeholder="Merk (RDW)" class="w-1/2 border border-slate-300 rounded-lg py-2 px-3 text-xs bg-slate-100 text-slate-600" readonly tabindex="-1">
                                    <input type="text" name="car_model" id="carModelInput" placeholder="Model (RDW)" class="w-1/2 border border-slate-300 rounded-lg py-2 px-3 text-xs bg-slate-100 text-slate-600" readonly tabindex="-1">
                                </div>

                                <input type="text" name="customer_name" placeholder="Naam Klant *" class="w-full border border-slate-300 rounded-lg py-2 px-3 text-sm focus:ring-blue-500" required id="reqName">
                                
                                <div class="flex gap-2">
                                    <input type="email" name="email" placeholder="E-mail (Optioneel)" class="w-1/2 border border-slate-300 rounded-lg py-2 px-3 text-sm focus:ring-blue-500">
                                    <input type="text" name="phone" placeholder="Tel (Optioneel)" class="w-1/2 border border-slate-300 rounded-lg py-2 px-3 text-sm focus:ring-blue-500">
                                </div>
                            </div>
                        </div>

                        <div id="pickLocatieVeld" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg" style="display: none;">
                            <label class="block text-xs font-bold text-blue-800 mb-1 uppercase tracking-wider">Klaarzetten op:</label>
                            <select name="location_code" class="w-full border border-blue-300 rounded-lg py-2 px-3 text-sm bg-white focus:ring-blue-500">
                                <option value="Werkplaats">Werkplaats</option><option value="Buiten">Buiten</option><option value="Achter">Achter</option>
                            </select>
                        </div>

                        <button type="submit" name="update_status" class="w-full bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold py-2.5 px-4 rounded-lg shadow-sm transition-colors">
                            Wijziging Opslaan <?php echo $tire['set_id'] ? '(Voor hele set)' : ''; ?>
                        </button>
                    </form>

                    <script>
                        function toggleNewCustomerField() {
                            var sel = document.getElementById('existingOrderSelect');
                            var fields = document.getElementById('newCustomerFields');
                            var reqName = document.getElementById('reqName');
                            if (sel && fields) {
                                let isNew = (sel.value === 'new');
                                fields.style.display = isNew ? 'block' : 'none';
                                reqName.required = isNew;
                            }
                        }

                        function toggleKlantVeld() {
                            var st = document.getElementById('statusSelect').value;
                            var hasOrder = <?php echo $tire['order_id'] ? 'true' : 'false'; ?>;
                            var klantVeld = document.getElementById('klantVeld');
                            var pickLocatieVeld = document.getElementById('pickLocatieVeld');
                            
                            if (klantVeld) {
                                klantVeld.style.display = (st === 'gereserveerd' && !hasOrder) ? 'block' : 'none';
                            }
                            if (pickLocatieVeld) pickLocatieVeld.style.display = (st === 'gepickt') ? 'block' : 'none';
                            
                            toggleNewCustomerField();
                        }
                        
                        async function fetchRDW() {
                            let input = document.getElementById('kentekenInput');
                            let btn = event.target;
                            let kenteken = input.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
                            if(kenteken.length < 6) return;
                            btn.innerHTML = 'Zoeken...';
                            try {
                                let response = await fetch(`https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=${kenteken}`);
                                let data = await response.json();
                                if(data.length > 0) {
                                    document.getElementById('carBrandInput').value = data[0].merk;
                                    document.getElementById('carModelInput').value = data[0].handelsbenaming;
                                    btn.innerHTML = '✅ Gevonden';
                                    btn.classList.replace('bg-slate-800', 'bg-emerald-600');
                                } else {
                                    btn.innerHTML = '❌ Niet gevonden';
                                    btn.classList.replace('bg-slate-800', 'bg-red-600');
                                    document.getElementById('carBrandInput').value = '';
                                    document.getElementById('carModelInput').value = '';
                                }
                            } catch(e) {
                                btn.innerHTML = 'Fout';
                            }
                            setTimeout(() => {
                                btn.innerHTML = 'Zoek Auto';
                                btn.className = 'w-1/3 bg-slate-800 text-white rounded-lg py-2 text-xs font-bold hover:bg-slate-700 shadow-sm transition-colors';
                            }, 3000);
                        }

                        document.addEventListener("DOMContentLoaded", toggleKlantVeld);
                    </script>

                    <?php if ($set_id): ?>
                    <div class="mt-5 pt-5 border-t border-slate-200">
                        <form method="POST" onsubmit="return confirm('Weet je zeker dat je deze set wilt opheffen? Alle banden worden dan weer losse banden.');">
                            <button type="submit" name="split_set" class="w-full bg-white border-2 border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300 text-xs font-bold py-2.5 rounded-lg transition-colors">
                                💔 Set Splitsen (Opheffen)
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Locatie & Historie -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50"><h2 class="font-bold text-base text-slate-800">Locatie & Logboek</h2></div>
                <div class="p-5">
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-2">Huidige Locatie:</p>
                    <p class="font-mono font-black text-2xl text-slate-900 mb-4"><?php echo htmlspecialchars($tire['location_code'] ?? 'Geen'); ?></p>
                    <form method="POST" class="flex gap-2 mb-6">
                        <input type="text" name="location_code" placeholder="Nieuwe locatie (bijv. 8B4)" class="w-full border border-slate-300 rounded-lg py-2 px-3 text-sm uppercase focus:ring-blue-500">
                        <button type="submit" name="update_location" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition-colors shadow-sm">Zet</button>
                    </form>
                    
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-2 border-b border-slate-100 pb-2">Historie van deze band</p>
                    <div class="max-h-40 overflow-y-auto text-xs space-y-2.5">
                        <?php foreach ($logs as $log): ?>
                            <div class="flex flex-col border-b border-slate-50 pb-1">
                                <span class="font-bold text-slate-700"><?php echo date('d-m-Y H:i', strtotime($log['created_at'])); ?></span> 
                                <span class="text-slate-600"><?php echo htmlspecialchars($log['action']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($logs)): ?>
                            <div class="text-slate-400 italic">Geen logboek geschiedenis.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>