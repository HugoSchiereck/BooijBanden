<?php
// seed.php - Genereert 1000 willekeurige banden voor testdoeleinden

require_once 'db.php';

// Controleer of het script niet per ongeluk al te vaak is gedraaid (veiligheidje)
$count = $pdo->query("SELECT COUNT(*) FROM tires WHERE qr_id LIKE 'DUMMY%'")->fetchColumn();
if ($count >= 3000) {
    die("<h1>Ho! Er staan al meer dan 3000 DUMMY banden in.</h1><p>Leeg eerst de database of verwijder dit script om vervuiling te voorkomen.</p>");
}

// Dummy data lijsten
$brands = ['Michelin', 'Continental', 'Goodyear', 'Pirelli', 'Hankook', 'Vredestein', 'Toyo', 'Dunlop', 'Falken', 'Nankang', 'Nexen', 'Maxxis'];
$models = ['SportContact', 'Eco Saver', 'Pilot Sport 4', 'WinterContact', 'AllSeason Plus', 'Proxes TR1', 'Kinergy', 'Quatrac'];
$seasons = ['Zomer', 'Zomer', 'Zomer', 'Winter', 'Winter', 'All Season']; // Zomer komt vaker voor
$widths = [175, 185, 195, 205, 215, 225, 235, 245, 255, 265, 275];
$ratios = [35, 40, 45, 50, 55, 60, 65];
$rims = [15, 16, 17, 18, 19, 20, 21];

$racks = range(1, 12);
$cols = ['A','B','C','D','E','F','G','H'];
$levels = range(1, 10);

$totaal_toe_te_voegen = 1000;
$banden_toegevoegd = 0;

echo "<h1>Starten met genereren van 1000 banden...</h1>";

$pdo->beginTransaction();

try {
    while ($banden_toegevoegd < $totaal_toe_te_voegen) {
        
        // Bepaal set grootte (Meestal 4, soms 2, zelden 1)
        $kans = rand(1, 10);
        if ($kans <= 6) $set_size = 4;
        elseif ($kans <= 9) $set_size = 2;
        else $set_size = 1;
        
        // Zorg dat we niet over de 1000 heengaan
        if ($banden_toegevoegd + $set_size > $totaal_toe_te_voegen) {
            $set_size = $totaal_toe_te_voegen - $banden_toegevoegd;
        }
        
        // --- 1. Kies een willekeurige locatie ---
        // We focussen 70% van de voorraad op stelling 1 t/m 6 om mooie volle rode blokken te forceren
        $rack = (rand(1, 100) <= 70) ? rand(1, 6) : rand(7, 12);
        $col = $cols[array_rand($cols)];
        $level = $levels[array_rand($levels)];
        $loc_code = $rack . $col . $level;
        
        // Zoek of maak locatie
        $stmtLoc = $pdo->prepare("SELECT id FROM locations WHERE code = ?");
        $stmtLoc->execute([$loc_code]);
        $loc = $stmtLoc->fetch();
        if ($loc) {
            $loc_id = $loc['id'];
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO locations (code, rack, col, level) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$loc_code, $rack, $col, $level]);
            $loc_id = $pdo->lastInsertId();
        }
        
        // --- 2. Maak eventueel een set aan ---
        $set_id = null;
        if ($set_size > 1) {
            $pdo->exec("INSERT INTO tire_sets () VALUES ()");
            $set_id = $pdo->lastInsertId();
        }
        
        // --- 3. Genereer de band specificaties voor deze set ---
        $brand = $brands[array_rand($brands)];
        $model = $models[array_rand($models)];
        $season = $seasons[array_rand($seasons)];
        $width = $widths[array_rand($widths)];
        $ratio = $ratios[array_rand($ratios)];
        $rim = $rims[array_rand($rims)];
        
        // Is het nieuw of gebruikt?
        $is_new = (rand(1, 100) > 40) ? 1 : 0; // 60% is nieuw
        $tread_depth = $is_new ? null : (rand(35, 75) / 10); // Profiel tussen 3.5 en 7.5
        
        // --- 4. Voeg de band(en) in ---
        for ($i = 0; $i < $set_size; $i++) {
            // Unieke ID, bijv. DUMMY-8237-1
            $qr_id = "DUMMY-" . rand(1000, 9999) . "-" . ($banden_toegevoegd + $i);
            
            $stmtTire = $pdo->prepare("
                INSERT INTO tires (qr_id, set_id, brand, model, season, width, ratio, rim, is_new, tread_depth, status, location_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'voorraad', ?)
            ");
            $stmtTire->execute([$qr_id, $set_id, $brand, $model, $season, $width, $ratio, $rim, $is_new, $tread_depth, $loc_id]);
        }
        
        $banden_toegevoegd += $set_size;
    }
    
    $pdo->commit();
    echo "<h2 style='color: green;'>Succes! Er zijn " . $banden_toegevoegd . " willekeurige banden gegenereerd en in de stellingen geplaatst.</h2>";
    echo "<p><a href='dashboard.php' style='padding: 10px 20px; background: blue; color: white; text-decoration: none; border-radius: 5px; font-family: sans-serif;'>Ga naar het Dashboard</a> om het resultaat te bekijken!</p>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color: red;'>Er is een fout opgetreden: " . $e->getMessage() . "</h2>";
}