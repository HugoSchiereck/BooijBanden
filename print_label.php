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
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labels Printen</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <style>
        /* Basis styling voor het scherm (zodat je ziet wat je doet) */
        body {
            background-color: #f1f5f9;
            font-family: Arial, sans-serif;
            margin: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        /* * HET ETIKET ONTWERP
         * Pas hier de width en height aan naar de maat van jouw Zebra rollen! 
         * Voorbeeld: 70mm x 50mm is een veelgebruikte magazijnmaat.
         */
        .label-container {
            width: 70mm;
            height: 50mm;
            background: white;
            border: 1px dashed #cbd5e1; /* Alleen zichtbaar op scherm, onzichtbaar in print */
            box-sizing: border-box;
            padding: 4mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            overflow: hidden;
            page-break-after: always; /* Zorgt dat elke sticker op een nieuw velletje komt */
        }

        .label-text {
            flex-grow: 1;
            padding-right: 4mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .l-brand { font-size: 14px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .l-model { font-size: 10px; color: #333; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 35mm;}
        .l-size { font-size: 16px; font-weight: 900; margin-bottom: 6px; }
        .l-info { font-size: 10px; font-weight: bold; }
        .l-code { font-size: 10px; margin-top: auto; font-family: monospace; }

        .label-qr {
            width: 30mm;
            height: 30mm;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Verberg knoppen tijdens het printen */
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

        /* De Print Specifieke regels */
        @media print {
            body { margin: 0; background: white; display: block; }
            .no-print { display: none !important; }
            .label-container { 
                border: none; 
                margin: 0; 
                page-break-after: always;
            }
            /* Dwing de browser om exact dit formaat papier te gebruiken */
            @page {
                size: 70mm 50mm; 
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="no-print text-center">
        <h2>Controleer de labels</h2>
        <p>Zorg dat in het printscherm 'Marges' op <b>Geen</b> staat, en het juiste papierformaat is geselecteerd.</p>
        <button onclick="window.print();" class="no-print">🖨️ Print Labels Nu</button>
        <a href="javascript:history.back()" style="display:block; margin-top:10px; color:#475569;">&larr; Terug</a>
    </div>

    <?php foreach ($tires as $index => $tire): ?>
        <div class="label-container">
            <div class="label-text">
                <div>
                    <div class="l-brand"><?php echo htmlspecialchars($tire['brand']); ?></div>
                    <div class="l-model"><?php echo htmlspecialchars($tire['model']); ?></div>
                    <div class="l-size"><?php echo $tire['width'].'/'.$tire['ratio'].' R'.$tire['rim']; ?></div>
                    <div class="l-info">
                        <?php echo $tire['season']; ?> | <?php echo $tire['is_new'] ? 'NIEUW' : $tire['tread_depth'].' mm'; ?>
                    </div>
                </div>
                <div class="l-code">ID: <?php echo htmlspecialchars($tire['qr_id']); ?></div>
            </div>
            <div class="label-qr" id="qr_<?php echo $index; ?>"></div>
        </div>
        
        <script>
            new QRCode(document.getElementById("qr_<?php echo $index; ?>"), {
                text: "<?php echo htmlspecialchars($tire['qr_id']); ?>",
                width: 100, // Resolutie van de QR
                height: 100,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        </script>
    <?php endforeach; ?>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500); // Korte pauze zodat de QR codes tijd hebben om te tekenen
        };
    </script>
</body>
</html>