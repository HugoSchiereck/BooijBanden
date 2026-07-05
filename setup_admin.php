<?php
// setup_admin.php

require_once 'db.php';

try {
    // We checken of de admin gebruiker al bestaat om dubbele invoer te voorkomen
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "De 'admin' gebruiker bestaat al.<br><br><b>Veiligheidswaarschuwing:</b> Verwijder dit bestand (setup_admin.php) direct van de server.";
        exit;
    }

    $username = 'admin';
    // Let op: Verander dit wachtwoord na je eerste keer inloggen via het toekomstige beheerderspaneel!
    $raw_password = 'WelkomBooij2026!'; 
    
    // Wachtwoord veilig hashen (nooit plain-text opslaan)
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
    $role = 'admin';

    $insert = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $insert->execute([$username, $hashed_password, $role]);

    echo "Admin gebruiker is succesvol aangemaakt!<br><br>";
    echo "Gebruikersnaam: <b>" . htmlspecialchars($username) . "</b><br>";
    echo "Wachtwoord: <b>" . htmlspecialchars($raw_password) . "</b><br><br>";
    echo "<b>Let op:</b> Ga nu naar <a href='login.php'>login.php</a> en <b>verwijder dit bestand (setup_admin.php)</b> van je server om beveiligingsrisico's te voorkomen!";
    
} catch (PDOException $e) {
    echo "Fout bij aanmaken gebruiker: " . $e->getMessage();
}
?>