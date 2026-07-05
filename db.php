<?php
// Database configuratie voor Booij Banden
$host    = 'localhost'; // Pas dit aan als de database op een andere server draait
$db      = 'Booij_';
$user    = 'Booij_sql';
$pass    = '$URs3q&f3Fbbyij6';
$charset = 'utf8mb4'; // Zorgt voor volledige ondersteuning van speciale tekens

// DSN (Data Source Name) opbouwen
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// PDO opties instellen voor de beste foutafhandeling en veiligheid
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Gooi een Exception bij een fout
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Resultaten standaard als associatieve array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Gebruik echte prepared statements voor veiligheid
];

try {
    // Verbinding maken met de database
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Let op: in een echte live-omgeving (productie) wil je $e->getMessage() liever wegschrijven 
    // naar een logbestand en de gebruiker een algemene foutmelding tonen in plaats van de letterlijke fout.
    error_log($e->getMessage());
    exit('Database verbindingsfout. Neem contact op met de beheerder.');
}
?>