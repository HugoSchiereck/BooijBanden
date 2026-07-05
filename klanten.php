<?php
// klanten.php

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Haal alle klanten op, inclusief het aantal orders dat ze geplaatst hebben
$stmt = $pdo->query("
    SELECT c.*, COUNT(o.id) as total_orders
    FROM customers c
    LEFT JOIN orders o ON c.id = o.customer_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$customers = $stmt->fetchAll();

$pageTitle = "Klanten (CRM)";
include 'header.php';
?>

<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Klanten & Auto's (CRM)</h1>
        <p class="text-slate-500 text-sm mt-1">Beheer klantinformatie en bekijk gekoppelde voertuigen via de RDW.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Klantgegevens</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Auto (RDW)</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Historie</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <?php foreach ($customers as $c): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($c['name']); ?></div>
                            <div class="text-xs text-slate-400">Klant sinds <?php echo date('M Y', strtotime($c['created_at'])); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-slate-600"><?php echo htmlspecialchars($c['email'] ?: '-'); ?></div>
                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($c['license_plate']): ?>
                                <span class="inline-block bg-yellow-400 text-black font-mono font-bold px-2 py-0.5 rounded text-xs border border-yellow-500 mb-1">
                                    <?php 
                                        // Formatteer kenteken netjes (bijv AB123C naar AB-123-C)
                                        echo htmlspecialchars(preg_replace('/([A-Z0-9]{2})([A-Z0-9]{2})([A-Z0-9]{2})/', '$1-$2-$3', $c['license_plate'])); 
                                    ?>
                                </span>
                                <div class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($c['car_brand'] . ' ' . $c['car_model']); ?></div>
                            <?php else: ?>
                                <span class="text-xs italic text-slate-400">Geen auto gekoppeld</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold rounded-full <?php echo $c['total_orders'] > 0 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600'; ?>">
                                <?php echo $c['total_orders']; ?> Orders
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($customers)): ?>
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500 text-sm italic">
                            Nog geen klanten in het systeem. Maak reserveringen aan om de database te vullen!
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php include 'footer.php'; ?>