<?php
// import.php - Eenmalig script om CSV in te lezen
require_once 'db.php';

$csvFile = 'marktplaats_import.csv';

if (!file_exists($csvFile)) {
    die("Fout: Bestand '$csvFile' niet gevonden.");
}

// Open de CSV (leesrechten)
$file = fopen($csvFile, 'r');

// Sla de eerste regel (headers) over
$header = fgetcsv($file, 1000, ';'); 

$succesCount = 0;

// Bereid de SQL query voor. 
// LET OP: Controleer of de tabelnaam 'voorraad' is en pas indien nodig je kolomnamen aan.
$query = "INSERT INTO voorraad (breedte, hoogte, inch, merk, model, seizoen, profiel, prijs) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($query);

// Lees de rest van de regels één voor één uit
while (($data = fgetcsv($file, 1000, ';')) !== false) {
    // $data array komt overeen met de kolommen in de CSV:
    // 0: Breedte, 1: Hoogte, 2: Inch, 3: Merk, 4: Model, 5: Seizoen, 6: Profiel, 7: Prijs
    
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
        echo "Fout bij rij (Merk: {$data[3]}): " . $e->getMessage() . "<br>";
    }
}

fclose($file);
echo "<h2>Import voltooid!</h2>";
echo "<p>Er zijn in totaal <strong>$succesCount</strong> banden succesvol toegevoegd aan de database.</p>";
?>