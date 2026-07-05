<?php
// print.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    die("Niet geautoriseerd of geen band geselecteerd.");
}

$qr_id = $_GET['id'];

// Haal de bandgegevens op
$stmt = $pdo->prepare("SELECT t.*, l.code as location_code FROM tires t LEFT JOIN locations l ON t.location_id = l.id WHERE t.qr_id = ?");
$stmt->execute([$qr_id]);
$tire = $stmt->fetch();

if (!$tire) {
    die("Band niet gevonden.");
}

// Genereer de QR URL 
$qr_url = "https://forward.nl/booij/detail.php?id=" . urlencode($qr_id);
// Gebruik een externe API om simpel een QR code afbeelding te genereren
$qr_image_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_url);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Print Etiket - <?php echo htmlspecialchars($qr_id); ?></title>
    <style>
        /* Specifieke instructies voor de Zebra printer */
        @page {
            size: 7cm 10cm portrait;
            margin: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        
        /* De werkelijke label afmeting */
        .label-container {
            width: 7cm;
            height: 10cm;
            padding: 0.4cm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            overflow: hidden;
        }
        
        /* Header sectie */
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 0.1cm;
            margin-bottom: 0.1cm;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Band specificaties */
        .info-section {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .brand {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 2px 0;
        }
        .model {
            font-size: 12px;
            margin: 0 0 4px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .size {
            font-size: 22px;
            font-weight: 900;
            margin: 4px 0;
            letter-spacing: -0.5px;
        }
        .season {
            font-size: 13px;
            font-style: italic;
            font-weight: bold;
            margin-bottom: 2px;
        }

        /* QR Code sectie */
        .qr-section {
            text-align: center;
            margin-top: 0.1cm;
        }
        .qr-code {
            width: 3.5cm;
            height: 3.5cm;
            margin: 0 auto;
            display: block;
        }
        .qr-id {
            font-family: monospace;
            font-size: 14px;
            margin-top: 4px;
            font-weight: bold;
        }

        /* Kleine info onderaan */
        .meta-info {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
            border-top: 1px dashed #000;
            padding-top: 4px;
        }
        
        /* Verberg de print-knoppen op het etiket zelf */
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">

    <!-- Menuutje voor in de browser (wordt niet geprint) -->
    <div class="no-print" style="padding: 20px; background: #f8fafc; text-align: center; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">
        <p style="margin-bottom: 10px; font-family: sans-serif;">Print dialoogvenster opent automatisch. Controleer of papierformaat op <strong>7x10 (Portrait)</strong> staat.</p>
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Opnieuw Printen</button>
        <button onclick="window.close()" style="padding: 8px 16px; background: #64748b; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px; font-weight: bold;">Sluiten</button>
    </div>

    <!-- Het fysieke label -->
    <div class="label-container">
        
        <div class="header">
            <h1>BOOIJ BANDEN</h1>
        </div>
        
        <div class="info-section">
            <div class="brand"><?php echo htmlspecialchars(strtoupper($tire['brand'])); ?></div>
            <div class="model"><?php echo htmlspecialchars($tire['model']); ?></div>
            <div class="size"><?php echo $tire['width'] . '/' . $tire['ratio'] . ' R' . $tire['rim']; ?></div>
            <div class="season">
                <?php 
                echo htmlspecialchars($tire['season']); 
                if ($tire['is_new']) {
                    echo ' - Nieuw';
                } elseif ($tire['tread_depth']) {
                    echo ' - ' . $tire['tread_depth'] . ' mm';
                }
                ?>
            </div>
        </div>

        <div class="qr-section">
            <img src="<?php echo $qr_image_url; ?>" alt="QR Code" class="qr-code" />
            <div class="qr-id"><?php echo htmlspecialchars($tire['qr_id']); ?></div>
        </div>
        
        <div class="meta-info">
            <span>SET: <?php echo $tire['set_id'] ? '#' . $tire['set_id'] : '-'; ?></span>
            <span>LOC: <?php echo htmlspecialchars($tire['location_code'] ?? 'GEEN'); ?></span>
        </div>
        
    </div>

</body>
</html>