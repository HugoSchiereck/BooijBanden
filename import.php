<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$csvFile = 'marktplaats_import.csv';

if (!file_exists($csvFile)) {
    die("Fout: Bestand '$csvFile' niet gevonden.");
}

$file = fopen($csvFile, 'r');
$header = fgetcsv($file, 1000, ';'); 
$succesCount = 0;

$query = "INSERT INTO tires (qr_id, width, ratio, rim, brand, model, season, tread_depth, price, is_new, status) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'voorraad')";

try {
    $stmt = $pdo->prepare($query);
} catch (\PDOException $e) {
    die("<h1>Database Fout:</h1><p>" . $e->getMessage() . "</p>");
}

while (($data = fgetcsv($file, 1000, ';')) !== false) {
    $qr_id = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
    
    $breedte = $data[0];
    $hoogte  = $data[1];
    $inch    = $data[2];
    $merk    = $data[3];
    $model   = $data[4];
    $seizoen = $data[5];
    $profiel = (float)str_replace(',', '.', $data[6]);
    $prijs   = (float)str_replace(',', '.', $data[7]);
    
    $is_new = ($profiel >= 7.0) ? 1 : 0;

    try {
        $stmt->execute([
            $qr_id,
            $breedte,
            $hoogte,
            $inch,
            $merk,
            $model,
            $seizoen,
            $profiel,
            $prijs,
            $is_new
        ]);
        $succesCount++;
    } catch (\PDOException $e) {
        echo "Fout bij toevoegen van band (Merk: $merk): " . $e->getMessage() . "<br>";
    }
}

fclose($file);
echo "<h2>Import voltooid!</h2>";
echo "<p>Er zijn in totaal <strong>$succesCount</strong> banden succesvol toegevoegd aan de voorraad.</p>";
?>