<?php
// print_label.php

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Niet ingelogd.");
}

// Check of we één of meerdere ID's hebben gekregen
$ids = [];
if (isset($_GET['id'])) {
    $ids = explode(',', $_GET['id']);
} elseif (isset($_POST['ids'])) {
    $ids = $_POST['ids']; // Voor massaal printen via checkboxen
}

if (empty($ids)) {
    die("Geen banden geselecteerd om te printen.");
}

// Haal de gegevens op van de geselecteerde banden
$in_clause = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM tires WHERE qr_id IN ($in_clause) ORDER BY id ASC");
$stmt->execute($ids);
$tires = $stmt->fetchAll();

if (empty($tires)) {
    die("Geen bandengegevens gevonden.");
}

// Helper functie om de EU-label kleur te bepalen o.b.v. profieldiepte
function getTreadDepthColor($is_new, $depth) {
    if ($is_new) return ['bg' => '#009640', 'letter' => 'A', 'text' => 'NIEUW']; // Donkergroen
    if ($depth >= 7.0) return ['bg' => '#50B848', 'letter' => 'B', 'text' => $depth . ' mm']; // Lichtgroen
    if ($depth >= 5.0) return ['bg' => '#C4D42A', 'letter' => 'C', 'text' => $depth . ' mm']; // Geelgroen
    if ($depth >= 4.0) return ['bg' => '#F2C700', 'letter' => 'D', 'text' => $depth . ' mm']; // Geel
    if ($depth >= 3.0) return ['bg' => '#F39200', 'letter' => 'E', 'text' => $depth . ' mm']; // Oranje
    if ($depth >= 2.0) return ['bg' => '#E37222', 'letter' => 'F', 'text' => $depth . ' mm']; // Donkeroranje
    return ['bg' => '#E3000F', 'letter' => 'G', 'text' => $depth . ' mm']; // Rood (Vervangen)
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EU Style Labels Printen</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Helvetica+Neue:wght@400;700;900&display=swap');

        body {
            background-color: #e2e8f0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        /* 70x100mm is een standaard Zebra label (Portret) */
        .eu-label-container {
            width: 70mm;
            height: 100mm;
            background: white;
            box-sizing: border-box;
            padding: 3mm;
            page-break-after: always;
            position: relative;
        }

        /* De karakteristieke blauwe buitenrand van het EU Label */
        .eu-border {
            border: 3px solid #005A9C;
            border-radius: 10px;
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 2mm;
            box-sizing: border-box;
        }

        /* Top Box: Merk & Model */
        .eu-top-box {
            border: 2px solid #005A9C;
            border-radius: 8px 8px 0 0;
            padding: 2mm;
            text-align: left;
            margin-bottom: 2mm;
            position: relative;
        }
        .eu-top-box::after {
            content: '';
            position: absolute;
            bottom: -2mm;
            left: -2px;
            width: 30%;
            border-bottom: 2px solid #005A9C;
        }

        .eu-brand { font-size: 16px; font-weight: 900; text-transform: uppercase; color: #000; line-height: 1; margin-bottom: 1mm;}
        .eu-model { font-size: 11px; font-weight: 700; color: #333; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        
        .eu-season { position: absolute; top: 2mm; right: 2mm; font-size: 10px; font-weight: bold; color: #005A9C; text-transform: uppercase; }

        /* Midden: Maat */
        .eu-size {
            font-size: 22px;
            font-weight: 900;
            text-align: center;
            margin: 2mm 0;
            letter-spacing: -0.5px;
        }

        /* De Gekleurde Pijlen (Profieldiepte) */
        .eu-middle-box {
            display: flex;
            flex-direction: column;
            gap: 1.5mm;
            margin-bottom: 3mm;
            padding: 0 2mm;
        }

        .eu-arrow-row {
            display: flex;
            align-items: center;
            height: 5mm;
        }

        .eu-arrow {
            position: relative;
            color: white;
            font-weight: bold;
            font-size: 10px;
            padding-left: 2mm;
            display: flex;
            align-items: center;
            height: 100%;
            width: 25mm; /* Breedte van de pijl */
        }
        .eu-arrow::after {
            content: '';
            position: absolute;
            right: -2.5mm;
            top: 0;
            width: 0;
            height: 0;
            border-top: 2.5mm solid transparent;
            border-bottom: 2.5mm solid transparent;
        }

        .eu-tread-value {
            margin-left: auto;
            font-size: 14px;
            font-weight: 900;
            color: #000;
        }

        /* Bottom Box: QR Code */
        .eu-bottom-box {
            border: 2px solid #005A9C;
            border-radius: 0 0 8px 8px;
            margin-top: auto;
            padding: 2mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .eu-bottom-box::before {
            content: '';
            position: absolute;
            top: -2mm;
            left: -2px;
            width: 30%;
            border-top: 2px solid #005A9C;
        }

        .qr-wrapper {
            width: 22mm;
            height: 22mm;
        }

        .qr-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
        }

        .qr-label {
            background-color: #000;
            color: #fff;
            font-size: 14px;
            font-weight: 900;
            padding: 1mm 3mm;
            clip-path: polygon(10px 0, 100% 0, 100% 100%, 0 100%);
            margin-bottom: 2mm;
        }

        .qr-id {
            font-size: 11px;
            font-family: monospace;
            font-weight: bold;
            color: #333;
        }

        .no-print {
            padding: 10px 20px;
            background-color: #2563eb;
            color: white;
            font-weight: bold;
            text-decoration: none;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            margin-bottom: 10px;
        }

        @media print {
            body { margin: 0; background: white; display: block; }
            .no-print { display: none !important; }
            .eu-label-container { 
                margin: 0; 
                padding: 2mm; /* Kleine veiligheidsmarge voor printer */
            }
            @page {
                size: 70mm 100mm; 
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="no-print text-center">
        <h2>EU-Style Labels Printen</h2>
        <p>Zorg dat in het printscherm 'Marges' op <b>Geen</b> staat. Papierformaat: <b>70x100mm</b>.</p>
        <button onclick="window.print();" class="no-print">🖨️ Print Labels Nu</button>
        <a href="javascript:history.back()" style="display:block; margin-top:10px; color:#475569;">&larr; Terug naar overzicht</a>
    </div>

    <?php foreach ($tires as $index => $tire): 
        // Bepaal de kleuren en letters
        $treadInfo = getTreadDepthColor($tire['is_new'], $tire['tread_depth']);
        $activeColor = $treadInfo['bg'];
        $activeLetter = $treadInfo['letter'];
        $activeText = $treadInfo['text'];
    ?>
        <div class="eu-label-container">
            <div class="eu-border">
                
                <div class="eu-top-box">
                    <div class="eu-season"><?php echo htmlspecialchars($tire['season']); ?></div>
                    <div class="eu-brand"><?php echo htmlspecialchars($tire['brand']); ?></div>
                    <div class="eu-model"><?php echo htmlspecialchars($tire['model']); ?></div>
                </div>

                <div class="eu-size">
                    <?php echo $tire['width'].'/'.$tire['ratio'].' R'.$tire['rim']; ?>
                </div>

                <div class="eu-middle-box">
                    <div class="text-[9px] font-bold text-[#005A9C] mb-1 uppercase text-center border-b border-[#005A9C] pb-0.5">Profieldiepte / Staat</div>
                    
                    <?php 
                    // We tekenen 4 representatieve EU-pijlen
                    $bars = [
                        ['bg' => '#009640', 'l' => 'A'],
                        ['bg' => '#C4D42A', 'l' => 'C'],
                        ['bg' => '#F39200', 'l' => 'E'],
                        ['bg' => '#E3000F', 'l' => 'G']
                    ];
                    
                    foreach($bars as $bar) {
                        $isActive = ($bar['l'] === $activeLetter || 
                                    ($activeLetter === 'B' && $bar['l'] === 'A') || 
                                    ($activeLetter === 'D' && $bar['l'] === 'C') ||
                                    ($activeLetter === 'F' && $bar['l'] === 'E'));
                                    
                        echo '<div class="eu-arrow-row">';
                        echo '<div class="eu-arrow" style="background-color: '.$bar['bg'].'; width: '.($isActive ? '30mm' : '20mm').';">';
                        echo '<style>.eu-arrow[style*="'.$bar['bg'].'"]::after { border-left: 2.5mm solid '.$bar['bg'].'; }</style>';
                        echo $bar['l'];
                        echo '</div>';
                        
                        if ($isActive) {
                            echo '<div class="eu-tread-value" style="color: '.$activeColor.';">'.$activeText.'</div>';
                        }
                        echo '</div>';
                    }
                    ?>
                </div>

                <div class="eu-bottom-box">
                    <div class="qr-wrapper" id="qr_<?php echo $index; ?>"></div>
                    <div class="qr-info">
                        <div class="qr-label">QR-ID</div>
                        <div class="qr-id"><?php echo htmlspecialchars($tire['qr_id']); ?></div>
                    </div>
                </div>

            </div>
        </div>
        
        <script>
            new QRCode(document.getElementById("qr_<?php echo $index; ?>"), {
                text: "<?php echo htmlspecialchars($tire['qr_id']); ?>",
                width: 80, // Past perfect in de 22mm wrapper
                height: 80,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
            });
        </script>
    <?php endforeach; ?>

    <script>
        // Auto-print functie
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 600);
        };
    </script>
</body>
</html>