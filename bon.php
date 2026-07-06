<?php
session_start();
require_once 'db.php';

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    die("Toegang geweigerd. Log eerst in.");
}

if (!isset($_GET['id'])) {
    die("Geen order ID opgegeven.");
}

$order_id = (int)$_GET['id'];

// Haal de order op
$stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'afgerond'");
$stmtOrder->execute([$order_id]);
$order = $stmtOrder->fetch();

if (!$order) {
    die("Order niet gevonden of nog niet afgerekend.");
}

// Haal alle banden op voor deze order
$stmtTires = $pdo->prepare("SELECT * FROM tires WHERE order_id = ?");
$stmtTires->execute([$order_id]);
$raw_tires = $stmtTires->fetchAll();

// --- NIEUW: Groeperen van identieke banden ---
$grouped_tires = [];
foreach ($raw_tires as $t) {
    // Maak een unieke sleutel op basis van maat, merk en prijs per stuk
    $tire_key = $t['width'] . '/' . $t['ratio'] . 'R' . $t['rim'] . '_' . $t['brand'] . '_' . $t['price'];
    
    if (!isset($grouped_tires[$tire_key])) {
        $grouped_tires[$tire_key] = [
            'qty'   => 1,
            'desc'  => $t['width'] . '/' . $t['ratio'] . 'R' . $t['rim'],
            'brand' => $t['brand'],
            'price' => (float)$t['price']
        ];
    } else {
        $grouped_tires[$tire_key]['qty']++;
    }
}

// Haal diensten op
$stmtServices = $pdo->prepare("SELECT os.*, s.name FROM order_services os JOIN services s ON os.service_id = s.id WHERE os.order_id = ?");
$stmtServices->execute([$order_id]);
$services = $stmtServices->fetchAll();

// Bereken totalen
$total_incl_btw = (float)$order['total_amount'];
$btw_percentage = 21;
$total_excl_btw = $total_incl_btw / (1 + ($btw_percentage / 100));
$btw_amount = $total_incl_btw - $total_excl_btw;

$date_formatted = date('d-m-Y H:i', strtotime($order['completed_at']));
$order_number = str_pad($order['id'], 5, "0", STR_PAD_LEFT);

// Formatteer kenteken voor weergave
function formatteerKenteken($lp) {
    if (strlen($lp) === 6) {
        return substr($lp, 0, 2) . '-' . substr($lp, 2, 2) . '-' . substr($lp, 4, 2);
    }
    return $lp;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kassabon - #<?php echo $order_number; ?></title>
    <style>
        /* Instellingen voor een 80mm bonprinter */
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background: #f4f4f5; /* Grijze achtergrond voor scherm */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        
        .receipt-container {
            width: 80mm;
            background: #fff;
            padding: 5mm;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            vertical-align: top;
            padding: 2px 0;
        }
        .qty-col { width: 15%; }
        .desc-col { width: 60%; }
        .price-col { width: 25%; text-align: right; }

        .header h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        .header p { margin: 2px 0; }

        .controls {
            margin-bottom: 20px;
            text-align: center;
            width: 100%;
            max-width: 80mm;
        }
        .btn {
            background: #2563eb; color: white; border: none; padding: 10px 20px;
            font-size: 14px; font-weight: bold; border-radius: 5px; cursor: pointer;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn:hover { background: #1d4ed8; }

        /* Verberg knoppen tijdens het printen */
        @media print {
            body { background: #fff; padding: 0; display: block; }
            .receipt-container { width: 100%; box-shadow: none; padding: 0; }
            .no-print { display: none; }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

    <div style="display: flex; flex-direction: column; align-items: center;">
        <div class="controls no-print">
            <button onclick="window.print()" class="btn">
                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                Afdrukken
            </button>
            <p style="font-family: sans-serif; font-size: 12px; color: #666; margin-top: 10px;">Geschikt voor 80mm kassabon of A4-formaat.</p>
        </div>

        <div class="receipt-container">
            <div class="header text-center">
                <h1>Booij Banden</h1>
                <p>Plantijnweg 30<br>4104 BB Culemborg</p>
                <p>Tel: 06-41595931</p>
            </div>
            
            <div class="divider"></div>
            
            <table>
                <tr>
                    <td>Datum:</td>
                    <td class="text-right"><?php echo $date_formatted; ?></td>
                </tr>
                <tr>
                    <td>Bon Nr:</td>
                    <td class="text-right">#<?php echo $order_number; ?></td>
                </tr>
                <tr>
                    <td>Klant:</td>
                    <td class="text-right bold"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                </tr>
                <?php if (!empty($order['license_plate'])): ?>
                <tr>
                    <td>Kenteken:</td>
                    <td class="text-right bold uppercase"><?php echo htmlspecialchars(formatteerKenteken($order['license_plate'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <div class="divider"></div>
            
            <table>
                <?php foreach($grouped_tires as $gt): 
                    $subtotal = $gt['price'] * $gt['qty'];
                ?>
                <tr>
                    <td class="qty-col"><?php echo $gt['qty']; ?>x</td>
                    <td class="desc-col">
                        <?php echo $gt['desc']; ?><br>
                        <span style="font-size: 10px;"><?php echo htmlspecialchars($gt['brand']); ?></span>
                    </td>
                    <td class="price-col">&euro;<?php echo number_format($subtotal, 2, ',', ''); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php foreach($services as $s): ?>
                <tr>
                    <td class="qty-col"><?php echo $s['quantity']; ?>x</td>
                    <td class="desc-col"><?php echo htmlspecialchars($s['name']); ?></td>
                    <td class="price-col">&euro;<?php echo number_format($s['price'] * $s['quantity'], 2, ',', ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <div class="divider"></div>
            
            <table>
                <tr>
                    <td class="bold">Totaal (incl. BTW)</td>
                    <td class="text-right bold" style="font-size: 16px;">&euro;<?php echo number_format($total_incl_btw, 2, ',', ''); ?></td>
                </tr>
                <tr>
                    <td style="font-size: 10px;">Waarvan BTW (21%)</td>
                    <td class="text-right" style="font-size: 10px;">&euro;<?php echo number_format($btw_amount, 2, ',', ''); ?></td>
                </tr>
                <tr>
                    <td style="font-size: 10px;">Netto (excl. BTW)</td>
                    <td class="text-right" style="font-size: 10px;">&euro;<?php echo number_format($total_excl_btw, 2, ',', ''); ?></td>
                </tr>
            </table>

            <div class="divider"></div>
            
            <div class="text-center" style="margin-top: 10px;">
                <p>Betaald via: <span class="bold uppercase"><?php echo htmlspecialchars($order['payment_method']); ?></span></p>
                <p style="margin-top: 15px; font-weight: bold;">Bedankt en een veilige reis!</p>
            </div>
            
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>