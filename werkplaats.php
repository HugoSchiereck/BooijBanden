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

// --- 1. Order markeren als Gemonteerd ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_mounted'])) {
    $order_id = (int)$_POST['order_id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE tires SET status = 'gemonteerd' WHERE order_id = ?")->execute([$order_id]);
        
        // Log deze actie
        $qrs = $pdo->query("SELECT qr_id FROM tires WHERE order_id = $order_id")->fetchAll(PDO::FETCH_COLUMN);
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
        // Haal eerst de actuele prijs van de dienst op
        $svc = $pdo->query("SELECT price FROM services WHERE id = $service_id")->fetch();
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
        
        // Update order status naar 'afgerond' en sla de financiële data op
        $stmtOrder = $pdo->prepare("UPDATE orders SET status = 'afgerond', payment_method = ?, total_amount = ?, completed_at = NOW() WHERE id = ?");
        $stmtOrder->execute([$payment_method, $total_amount, $order_id]);
        
        // Update alle banden naar 'verkocht' en verwijder locatie
        $pdo->prepare("UPDATE tires SET status = 'verkocht', location_id = NULL WHERE order_id = ?")->execute([$order_id]);
        
        // Log dit
        $qrs = $pdo->query("SELECT qr_id FROM tires WHERE order_id = $order_id")->fetchAll(PDO::FETCH_COLUMN);
        foreach($qrs as $q) {
            $pdo->prepare("INSERT INTO tire_logs (qr_id, user_id, action) VALUES (?, ?, ?)")->execute([$q, $_SESSION['user_id'], "Verkocht / Afgerekend ($payment_method)"]);
        }
        
        $pdo->commit();
        // SUCCES MELDING AANGEPAST MET PRINT KNOP
        $msg = "Succes! Order #$order_id is afgerekend via $payment_method. <a href='bon.php?id=$order_id' target='_blank' class='inline-block ml-3 bg-white text-green-700 hover:bg-green-100 font-bold px-3 py-1 rounded shadow-sm text-sm transition-colors'>🖨️ Bekijk & Print Kassabon</a>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout bij afrekenen: " . $e->getMessage();
    }
}

// Haal actieve orders op (Met error handling zodat hij niet stil crasht)
try {
    $stmtOrders = $pdo->query("SELECT * FROM orders WHERE status = 'open' ORDER BY created_at ASC");
    $orders = $stmtOrders->fetchAll();
} catch (Exception $e) {
    $orders = [];
    $error = "Databasefout bij ophalen orders: " . $e->getMessage();
}

// Haal de recent afgeronde orders op (NIEUW)
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

    <?php if ($msg): ?><div class="bg-green-50 border-l-4 border-green-500 p-3 mb-4 rounded shadow-sm text-green-800 text-sm font-bold"><?php echo $msg; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border-l-4 border-red-500 p-3 mb-4 rounded shadow-sm text-red-800 text-sm font-bold"><?php echo $error; ?></div><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($orders as $order): 
            $order_id = $order['id'];
            
            // Haal banden op voor deze order
            $stmtTires = $pdo->prepare("SELECT * FROM tires WHERE order_id = ?");
            $stmtTires->execute([$order_id]);
            $order_tires = $stmtTires->fetchAll();
            
            // Check of álle banden gemonteerd (of minimaal gepickt/gereserveerd) zijn
            $all_mounted = true;
            $tire_total = 0;
            foreach ($order_tires as $t) {
                if ($t['status'] !== 'gemonteerd') $all_mounted = false;
                $tire_total += (float)$t['price'];
            }
            
            // Haal toegevoegde diensten op (Aangepast naar order_services tabel!)
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
            <!-- Order Header -->
            <div class="px-5 py-3 border-b border-slate-100 <?php echo $all_mounted ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800'; ?>">
                <div class="flex justify-between items-center">
                    <h3 class="font-black text-lg">Order #<?php echo str_pad($order['id'], 4, "0", STR_PAD_LEFT); ?></h3>
                    <span class="text-xs font-bold opacity-80"><?php echo date('H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <p class="text-sm font-medium opacity-90 mt-0.5">Klant: <?php echo htmlspecialchars($order['customer_name']); ?></p>
            </div>
            
            <div class="p-5 flex-grow">
                <!-- Banden Sectie -->
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

                <!-- Diensten Sectie -->
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
                <!-- Diensten toevoegen formulier -->
                <form method="POST" class="flex gap-2 mb-6 bg-slate-50 p-2 rounded border border-slate-200">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <select name="service_id" class="flex-grow border border-slate-300 rounded px-2 py-1.5 text-xs bg-white">
                        <?php foreach($services as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?> (&euro;<?php echo number_format($s['price'], 2); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantity" value="<?php echo count($order_tires); ?>" min="1" class="w-12 border border-slate-300 rounded px-2 py-1.5 text-xs text-center">
                    <button type="submit" name="add_service" class="bg-slate-800 text-white px-2 py-1.5 rounded text-xs font-bold">+</button>
                </form>
                <?php endif; ?>

                <!-- Totalen -->
                <div class="border-t-2 border-slate-800 pt-2 flex justify-between items-end">
                    <span class="text-sm font-bold text-slate-600 uppercase">Totaal (incl. BTW)</span>
                    <span class="text-2xl font-black text-slate-900">&euro;<?php echo number_format($grand_total, 2, ',', '.'); ?></span>
                </div>
            </div>
            
            <!-- Actie Sectie -->
            <div class="p-5 border-t border-slate-100 bg-slate-50">
                <?php if (!$all_mounted): ?>
                    <!-- DE NIEUWE MONTEER KNOP -->
                    <div class="text-center">
                        <p class="text-xs text-amber-600 font-bold mb-2">Banden zijn nog niet 'gemonteerd' in het systeem.</p>
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            <button type="submit" name="mark_mounted" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded shadow-sm transition-colors flex justify-center items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                </svg>
                                Auto is klaar (Gemonteerd)
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- KASSA / AFREKENEN -->
                    <form method="POST" class="grid grid-cols-2 gap-2">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        <input type="hidden" name="total_amount" value="<?php echo $grand_total; ?>">
                        <button type="submit" name="checkout" value="checkout" class="col-span-2 bg-green-600 hover:bg-green-700 text-white font-black py-3 rounded shadow transition-colors uppercase tracking-wider mb-2">
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
                <p class="text-sm text-slate-400 mt-2">Zodra banden de status 'Gereserveerd' of 'Gemonteerd' krijgen, verschijnen ze hier.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- NIEUWE SECTIE: RECENT AFGEREKEND -->
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
                                <a href="bon.php?id=<?php echo $comp['id']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800 bg-blue-50 px-3 py-1.5 rounded text-xs font-bold transition-colors">🖨️ Bon</a>
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