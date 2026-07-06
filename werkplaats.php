<?php
// werkplaats.php

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$msg = "";
$error = "";

// --- 0. Band scannen en nieuwe order aanmaken ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_scan_order'])) {
    $qr_id = trim($_POST['scan_qr']);
    $customer_name = trim($_POST['customer_name']);
    if (empty($customer_name)) {
        $customer_name = "Inloopklant";
    }
    
    if (!empty($qr_id)) {
        try {
            $stmtCheck = $pdo->prepare("SELECT * FROM tires WHERE qr_id = ?");
            $stmtCheck->execute([$qr_id]);
            $tireCheck = $stmtCheck->fetch();
            
            if (!$tireCheck) {
                $error = "Fout: Band met code '$qr_id' bestaat niet.";
            } elseif ($tireCheck['status'] === 'verkocht' || $tireCheck['status'] === 'uitgeboekt') {
                $error = "Fout: Deze band is al verkocht of uitgeboekt.";
            } else {
                $pdo->beginTransaction();
                
                if ($tireCheck['order_id']) {
                    $stmtOrderCheck = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
                    $stmtOrderCheck->execute([$tireCheck['order_id']]);
                    $oStatus = $stmtOrderCheck->fetchColumn();
                    if ($oStatus === 'open') {
                        throw new Exception("Deze band is al gekoppeld aan open Order #{$tireCheck['order_id']}.");
                    }
                }
                
                // Maak een nieuwe open order aan
                $stmtNewOrder = $pdo->prepare("INSERT INTO orders (customer_name, status, created_at) VALUES (?, 'open', NOW())");
                $stmtNewOrder->execute([$customer_name]);
                $new_order_id = $pdo->lastInsertId();
                
                // Als de band onderdeel is van een set, voeg de hele beschikbare set toe
                if (!empty($tireCheck['set_id'])) {
                    $stmtUpdateSet = $pdo->prepare("UPDATE tires SET order_id = ?, status = 'gereserveerd' WHERE set_id = ? AND status = 'voorraad'");
                    $stmtUpdateSet->execute([$new_order_id, $tireCheck['set_id']]);
                    
                    $stmtGetSetQrs = $pdo->prepare("SELECT qr_id FROM tires WHERE set_id = ? AND order_id = ?");
                    $stmtGetSetQrs->execute([$tireCheck['set_id'], $new_order_id]);
                    $setQrs = $stmtGetSetQrs->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($setQrs as $q) {
                        $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$q, $_SESSION['user_id'], "Gereserveerd via Kassa Scan (Set)"]);
                    }
                    $msg = "Succes! Order #$new_order_id aangemaakt voor " . htmlspecialchars($customer_name) . " met een set van " . count($setQrs) . " banden.";
                } else {
                    // Losse band koppelen
                    $stmtUpdateTire = $pdo->prepare("UPDATE tires SET order_id = ?, status = 'gereserveerd' WHERE qr_id = ?");
                    $stmtUpdateTire->execute([$new_order_id, $qr_id]);
                    
                    $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$qr_id, $_SESSION['user_id'], "Gereserveerd via Kassa Scan (Los)"]);
                    $msg = "Succes! Order #$new_order_id aangemaakt voor " . htmlspecialchars($customer_name) . " met band " . htmlspecialchars($qr_id) . ".";
                }
                
                $pdo->commit();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Fout bij scannen: " . $e->getMessage();
        }
    } else {
        $error = "Fout: Voer een geldige QR-code of referentiecode in.";
    }
}

// --- 1. Order markeren als Gemonteerd ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_mounted'])) {
    $order_id = (int)$_POST['order_id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE tires SET status = 'gemonteerd' WHERE order_id = ?")->execute([$order_id]);
        
        // Log deze actie
        $stmtQrs = $pdo->prepare("SELECT qr_id FROM tires WHERE order_id = ?");
        $stmtQrs->execute([$order_id]);
        $qrs = $stmtQrs->fetchAll(PDO::FETCH_COLUMN);
        
        foreach($qrs as $q) {
            $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$q, $_SESSION['user_id'], "Status: gemonteerd (Via Kassa)"]);
        }
        $pdo->commit();
        $msg = "Order #$order_id is succesvol gemarkeerd als gemonteerd. Je kunt nu afrekenen!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout bij updaten: " . $e->getMessage();
    }
}

// --- 2. Diensten toevoegen aan factuur ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $order_id = (int)$_POST['order_id'];
    $service_id = (int)$_POST['service_id'];
    $quantity = (int)$_POST['quantity'];
    
    try {
        $stmtSvc = $pdo->prepare("SELECT price FROM services WHERE id = ?");
        $stmtSvc->execute([$service_id]);
        $svc = $stmtSvc->fetch();
        if ($svc) {
            $stmt = $pdo->prepare("INSERT INTO order_services (order_id, service_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $service_id, $quantity, $svc['price']]);
            $msg = "Dienst succesvol toegevoegd aan de order.";
        }
    } catch (Exception $e) {
        $error = "Fout bij toevoegen dienst: " . $e->getMessage();
    }
}

// --- 3. Order afrekenen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $order_id = (int)$_POST['order_id'];
    $payment_method = $_POST['payment_method'];
    $total_amount = (float)$_POST['total_amount'];
    
    try {
        $pdo->beginTransaction();
        
        $stmtOrder = $pdo->prepare("UPDATE orders SET status = 'afgerond', payment_method = ?, total_amount = ?, completed_at = NOW() WHERE id = ?");
        $stmtOrder->execute([$payment_method, $total_amount, $order_id]);
        
        $pdo->prepare("UPDATE tires SET status = 'verkocht', location_id = NULL WHERE order_id = ?")->execute([$order_id]);
        
        $stmtQrs = $pdo->prepare("SELECT qr_id FROM tires WHERE order_id = ?");
        $stmtQrs->execute([$order_id]);
        $qrs = $stmtQrs->fetchAll(PDO::FETCH_COLUMN);
        
        foreach($qrs as $q) {
            $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$q, $_SESSION['user_id'], "Verkocht / Afgerekend ($payment_method)"]);
        }
        
        $pdo->commit();
        $msg = "Succes! Order #$order_id is afgerekend via $payment_method. <a href='bon.php?id=$order_id' target='_blank' class='inline-flex ml-3 bg-white text-green-700 hover:bg-green-100 border border-green-200 font-bold px-3 py-1 rounded shadow-sm text-sm transition-colors flex items-center gap-1'><svg class='w-4 h-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z' /></svg> Bekijk & Print Kassabon</a>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout bij afrekenen: " . $e->getMessage();
    }
}

// Haal actieve orders op
try {
    $stmtOrders = $pdo->query("SELECT * FROM orders WHERE status = 'open' ORDER BY created_at ASC");
    $orders = $stmtOrders->fetchAll();
} catch (Exception $e) {
    $orders = [];
    $error = "Databasefout bij ophalen orders: " . $e->getMessage();
}

// Haal de recent afgeronde orders op
try {
    $stmtCompleted = $pdo->query("SELECT * FROM orders WHERE status = 'afgerond' ORDER BY completed_at DESC LIMIT 6");
    $completed_orders = $stmtCompleted->fetchAll();
} catch (Exception $e) {
    $completed_orders = [];
}

// Haal beschikbare diensten op
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY price ASC")->fetchAll();
} catch (Exception $e) {
    $services = [];
}

$pageTitle = "Kassa & Werkplaats";
include 'header.php';
?>

<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Kassa & Orders</h1>
            <p class="text-slate-500 text-sm mt-1">Beheer lopende orders, voeg montagekosten toe en reken af.</p>
        </div>
    </div>

    <?php if ($msg): ?><div class="bg-green-50 border-l-4 border-green-500 p-3 mb-4 rounded shadow-sm text-green-800 text-sm font-bold flex items-center"><?php echo $msg; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border-l-4 border-red-500 p-3 mb-4 rounded shadow-sm text-red-800 text-sm font-bold"><?php echo $error; ?></div><?php endif; ?>

    <div class="bg-white rounded-xl shadow-md border border-slate-200 p-5 mb-8">
        <form method="POST" class="flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-grow w-full">
                <label for="scan_qr" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Snelkoppeling handscanner (Scan QR / Referentie)</label>
                <div class="relative rounded-lg shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                    <input type="text" name="scan_qr" id="scan_qr" autofocus placeholder="Scan QR-code op de band..." class="block w-full pl-10 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-sm font-mono font-bold tracking-wider text-slate-800">
                </div>
            </div>
            <div class="w-full md:w-64">
                <label for="customer_name" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Klantnaam (Optioneel)</label>
                <input type="text" name="customer_name" id="customer_name" placeholder="Inloopklant" class="block w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-sm text-slate-800">
            </div>
            <button type="submit" name="create_scan_order" class="w-full md:w-auto bg-blue-600 hover:bg-blue-500 text-white font-black py-2.5 px-6 rounded-lg shadow transition-colors text-sm uppercase tracking-wider whitespace-nowrap">
                Toevoegen
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($orders as $order): 
            $order_id = $order['id'];
            
            $stmtTires = $pdo->prepare("SELECT * FROM tires WHERE order_id = ?");
            $stmtTires->execute([$order_id]);
            $order_tires = $stmtTires->fetchAll();
            
            $all_mounted = true;
            $tire_total = 0;
            foreach ($order_tires as $t) {
                if ($t['status'] !== 'gemonteerd') $all_mounted = false;
                $tire_total += (float)$t['price'];
            }
            
            $stmtLines = $pdo->prepare("SELECT os.*, s.name FROM order_services os JOIN services s ON os.service_id = s.id WHERE os.order_id = ?");
            $stmtLines->execute([$order_id]);
            $lines = $stmtLines->fetchAll();
            
            $service_total = 0;
            foreach ($lines as $l) {
                $service_total += ($l['price'] * $l['quantity']);
            }
            
            $grand_total = $tire_total + $service_total;
        ?>
            
        <div class="bg-white rounded-xl shadow-md border border-slate-200 flex flex-col relative overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 <?php echo $all_mounted ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800'; ?>">
                <div class="flex justify-between items-center">
                    <h3 class="font-black text-lg">Order #<?php echo str_pad($order['id'], 4, "0", STR_PAD_LEFT); ?></h3>
                    <span class="text-xs font-bold opacity-80"><?php echo date('H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <p class="text-sm font-medium opacity-90 mt-0.5">Klant: <?php echo htmlspecialchars($order['customer_name']); ?></p>
            </div>
            
            <div class="p-5 flex-grow">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 border-b pb-1">Producten (Banden)</h4>
                <ul class="space-y-1.5 mb-4">
                    <?php foreach ($order_tires as $t): ?>
                        <li class="flex justify-between text-sm items-center hover:bg-slate-50 -mx-2 px-2 py-1 rounded transition-colors">
                            <a href="detail.php?id=<?php echo urlencode($t['qr_id']); ?>" class="truncate pr-2 font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                1x <?php echo $t['width'].'/'.$t['ratio'].' R'.$t['rim'].' '.$t['brand']; ?>
                                <span class="text-xs text-slate-400 ml-1">(<?php echo htmlspecialchars($t['qr_id']); ?>)</span>
                            </a>
                            <span class="font-bold text-slate-900">&euro;<?php echo number_format($t['price'], 2, ',', '.'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 border-b pb-1">Diensten & Arbeid</h4>
                <ul class="space-y-1.5 mb-4">
                    <?php foreach ($lines as $l): ?>
                        <li class="flex justify-between text-sm text-slate-600">
                            <span><?php echo $l['quantity']; ?>x <?php echo htmlspecialchars($l['name']); ?></span>
                            <span>&euro;<?php echo number_format($l['price'] * $l['quantity'], 2, ',', '.'); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($lines)): ?>
                        <li class="text-xs italic text-slate-400">Nog geen diensten toegevoegd.</li>
                    <?php endif; ?>
                </ul>
                
                <?php if ($all_mounted): ?>
                <form method="POST" class="flex gap-2 mb-6 bg-slate-50 p-2 rounded border border-slate-200">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <select name="service_id" class="flex-grow border border-slate-300 rounded px-2 py-1.5 text-xs bg-white text-slate-800">
                        <?php foreach($services as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?> (&euro;<?php echo number_format($s['price'], 2); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantity" value="<?php echo count($order_tires); ?>" min="1" class="w-12 border border-slate-300 rounded px-2 py-1.5 text-xs text-center text-slate-800">
                    <button type="submit" name="add_service" class="bg-slate-800 text-white px-2 py-1.5 rounded text-xs font-bold">+</button>
                </form>
                <?php endif; ?>

                <div class="border-t-2 border-slate-800 pt-2 flex justify-between items-end">
                    <span class="text-sm font-bold text-slate-600 uppercase">Totaal (incl. BTW)</span>
                    <span class="text-2xl font-black text-slate-900">&euro;<?php echo number_format($grand_total, 2, ',', '.'); ?></span>
                </div>
            </div>
            
            <div class="p-5 border-t border-slate-100 bg-slate-50">
                <?php if (!$all_mounted): ?>
                    <div class="text-center">
                        <p class="text-xs text-amber-600 font-bold mb-2">Banden zijn nog niet 'gemonteerd' in het systeem.</p>
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            <button type="submit" name="mark_mounted" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded shadow-sm transition-colors flex justify-center items-center gap-2 text-sm uppercase tracking-wider">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                </svg>
                                Auto is klaar (Gemonteerd)
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="POST" class="grid grid-cols-2 gap-2">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        <input type="hidden" name="total_amount" value="<?php echo $grand_total; ?>">
                        <button type="submit" name="checkout" value="checkout" class="col-span-2 bg-green-600 hover:bg-green-700 text-white font-black py-3 rounded shadow transition-colors uppercase tracking-wider mb-2 text-sm">
                            Afrekenen
                        </button>
                        <div class="col-span-2 flex justify-center gap-4 text-sm font-bold text-slate-600">
                            <label class="flex items-center gap-1 cursor-pointer"><input type="radio" name="payment_method" value="PIN" checked> PIN</label>
                            <label class="flex items-center gap-1 cursor-pointer"><input type="radio" name="payment_method" value="Contant"> Contant</label>
                            <label class="flex items-center gap-1 cursor-pointer"><input type="radio" name="payment_method" value="Factuur"> Factuur</label>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($orders)): ?>
            <div class="col-span-full bg-white rounded-xl shadow-sm border border-dashed border-slate-300 p-10 text-center">
                <p class="text-slate-500 font-medium text-lg">Er zijn momenteel geen actieve orders in de kassa.</p>
                <p class="text-sm text-slate-400 mt-2">Gebruik het bovenstaande scanveld om direct een band of set aan een nieuwe order te koppelen.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if(!empty($completed_orders)): ?>
    <div class="mt-12">
        <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            Recent Afgerekend
        </h2>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Tijdstip</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Order / Klant</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Betaalwijze</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Bedrag</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Actie</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <?php foreach($completed_orders as $comp): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-600 font-medium"><?php echo date('H:i', strtotime($comp['completed_at'])); ?></td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800 text-sm">#<?php echo str_pad($comp['id'], 4, "0", STR_PAD_LEFT); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($comp['customer_name']); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded font-bold text-xs uppercase">
                                    <?php echo htmlspecialchars($comp['payment_method']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-black text-slate-900">
                                &euro;<?php echo number_format($comp['total_amount'], 2, ',', '.'); ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="bon.php?id=<?php echo $comp['id']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800 bg-blue-50 px-3 py-1.5 rounded text-xs font-bold transition-colors inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                    Bon
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<?php include 'footer.php'; ?>