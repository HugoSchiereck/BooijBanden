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
    return ['bg' => '#E3000F', 'letter' => 'G', 'text' => $depth . ' mm']; // Rood
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labels Printen - Booij Banden</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Helvetica+Neue:wght@400;700;900&display=swap');

        /* ALGEMENE PRINT REGELS (Zorgt dat kleuren bewaard blijven) */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            background-color: #f8fafc; /* Tailwind slate-50 */
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* ----------------------------------------------------
           ETIKET SPECIFIEKE STYLING (Voor 70x100mm) 
           ---------------------------------------------------- */
        .eu-label-container {
            width: 70mm;
            height: 100mm;
            background: white;
            box-sizing: border-box;
            padding: 3mm;
            margin: 0 auto 20px auto; /* Voor weergave op scherm */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); /* Schaduw op scherm */
            position: relative;
        }

        .eu-border {
            border: 3px solid #005A9C;
            border-radius: 10px;
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 2mm;
            box-sizing: border-box;
        }

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

        .eu-size {
            font-size: 22px;
            font-weight: 900;
            text-align: center;
            margin: 2mm 0;
            letter-spacing: -0.5px;
            color: #000;
        }

        .eu-middle-box {
            display: flex;
            flex-direction: column;
            margin-bottom: 3mm;
            padding: 0 2mm;
        }

        .eu-bottom-box {
            border: 2px solid #005A9C;
            border-radius: 0 0 8px 8px;
            margin-top: auto;
            padding: 2mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
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

        /* ----------------------------------------------------
           MEDIA QUERY VOOR HET DAADWERKELIJKE PRINTEN
           ---------------------------------------------------- */
        @media print {
            body { 
                background: white; 
                margin: 0; 
                padding: 0;
            }
            .no-print { 
                display: none !important; 
            }
            .eu-label-container { 
                margin: 0; 
                padding: 1mm; /* Kleine marge voor de rand van de zebra sticker */
                box-shadow: none; /* Verwijder scherm-schaduw */
                page-break-after: always; /* Zorg dat elke band op een nieuwe sticker komt */
            }
            @page {
                size: 70mm 100mm; 
                margin: 0;
            }
        }
    </style>
</head>
<body class="pb-20">

    <div class="no-print max-w-2xl mx-auto mt-10 mb-12 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
            <h2 class="text-white font-black text-xl flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                Zebra Magazijn Labels Printen
            </h2>
            <span class="bg-blue-800 text-blue-100 font-bold px-3 py-1 rounded-full text-xs">
                <?php echo count($tires); ?> Etiketten
            </span>
        </div>
        
        <div class="p-6">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-amber-800 text-sm">
                <h3 class="font-bold mb-1 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Print Instellingen Controleren!
                </h3>
                <ul class="list-disc pl-5 space-y-1 mt-2">
                    <li>Zorg dat <strong>Formaat (Paper Size)</strong> op <b>70x100mm</b> staat.</li>
                    <li>Zet <strong>Marges (Margins)</strong> op <b>Geen (None)</b>.</li>
                    <li>Zet <strong>Kop- en voetteksten (Headers/footers)</strong> <b>Uit</b>.</li>
                    <li>Zet <strong>Achtergrondafbeeldingen (Background graphics)</strong> <b>AAN</b> (Cruciaal voor de kleuren!).</li>
                </ul>
            </div>
            
            <div class="flex gap-4">
                <button onclick="window.print();" class="flex-grow bg-blue-600 hover:bg-blue-700 text-white font-black py-3 px-4 rounded-xl shadow-sm transition-colors text-lg flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                    Print Nu (Ctrl+P)
                </button>
                <button onclick="window.history.back();" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-6 rounded-xl transition-colors">
                    Annuleren
                </button>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap justify-center gap-8">
        <?php foreach ($tires as $index => $tire): 
            // Bepaal de kleuren en letters o.b.v. profiel
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
                        <div style="font-size: 10px; font-weight: bold; color: #005A9C; margin-bottom: 2mm; text-transform: uppercase; border-bottom: 1px solid #005A9C; padding-bottom: 1mm;">Profieldiepte / Staat</div>
                        
                        <?php 
                        // Hardgecodeerde HTML blokken voor de pijlen zodat ze 100% werken op print
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
                                        
                            $width = $isActive ? '30mm' : '15mm';
                            ?>
                            
                            <div style="display: flex; align-items: center; height: 5mm; margin-bottom: 1.5mm;">
                                <div style="background-color: <?php echo $bar['bg']; ?>; width: <?php echo $width; ?>; height: 100%; color: white; font-weight: bold; font-size: 11px; padding-left: 2mm; display: flex; align-items: center; box-sizing: border-box;">
                                    <?php echo $bar['l']; ?>
                                </div>
                                <div style="width: 0; height: 0; border-top: 2.5mm solid transparent; border-bottom: 2.5mm solid transparent; border-left: 2.5mm solid <?php echo $bar['bg']; ?>;"></div>
                                
                                <?php if ($isActive): ?>
                                    <div style="margin-left: auto; font-size: 15px; font-weight: 900; color: <?php echo $activeColor; ?>;">
                                        <?php echo $activeText; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php } ?>
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
                    width: 80, 
                    height: 80,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            </script>
        <?php endforeach; ?>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 600);
        };
    </script>
</body>
</html>