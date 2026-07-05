<?php
// logout.php

session_start();

// Maak alle sessievariabelen leeg
$_SESSION = array();

// Vernietig de sessiecookie als deze bestaat
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Vernietig de sessie daadwerkelijk op de server
session_destroy();

// Terugsturen naar het inlogscherm
header("Location: login.php");
exit;
?>