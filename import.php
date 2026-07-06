<?php
// import.php - Volledige code met debug-modus
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// Forceer PDO om fouten te tonen, overschrijft db.php instellingen
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$csvFile = 'marktplaats_import.csv';

if (!file_exists($csvFile)) {
    die("Fout: Bestand '$csvFile' niet gevonden.");
}

$file = fopen($csvFile, 'r');
$header = fgetcsv($file, 1000, ';'); 
$succesCount = 0;

$query = "INSERT INTO voorraad (breedte, hoogte, inch, merk, model, seizoen, profiel, prijs) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

try {
    // Hier ging het script waarschijnlijk stuk op een verkeerde tabel- of kolomnaam
    $stmt = $pdo->prepare($query);
} catch (\PDOException $e) {
    die("<h1>Database Fout:</h1><p>" . $e->getMessage() . "</p><p>Controleer in je database of de tabelnaam en kolommen exact overeenkomen met de query.</p>");
}

while (($data = fgetcsv($file, 1000, ';')) !== false) {
    try {
        $stmt->execute([
            $data[0], // breedte
            $data[1], // hoogte
            $data[2], // inch
            $data[3], // merk
            $data[4], // model
            $data[5], // seizoen
            $data[6], // profiel
            $data[7]  // prijs
        ]);
        $succesCount++;
    } catch (\PDOException $e) {
        echo "Fout bij toevoegen van band (Merk: {$data[3]}): " . $e->getMessage() . "<br>";
    }
}

fclose($file);
echo "<h2>Import voltooid!</h2>";
echo "<p>Er zijn in totaal <strong>$succesCount</strong> banden succesvol toegevoegd aan de database.</p>";
?>