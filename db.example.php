<?php
// db.example.php - Dit is een sjabloon. Deze mag WEL in Git.
// Kopieer dit bestand naar 'db.php' op je lokale pc of server en vul je gegevens in.

$host    = 'localhost';
$db      = 'naam_van_database';
$user    = 'gebruikersnaam';
$pass    = 'wachtwoord';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    // Op de live server wil je GEEN foutmeldingen op het scherm (veiligheid)
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log($e->getMessage()); // Schrijft fouten naar een onzichtbaar logbestand
    exit('Database verbindingsfout. Neem contact op met de beheerder.');
}
?>