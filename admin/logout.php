<?php
/**
 * SCRIPT DE DÉCONNEXION ADMIN
 * Détruit la session et redirige vers la page de login
 */

// Démarrer la session
session_start();
require_once __DIR__ . '/config-path.php';

// Logger la déconnexion si admin connecté
if (isset($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../api/admin/auth.php';
    
    // Logger l'action
    logAdminAction(
        $_SESSION['admin_id'],
        'logout',
        'admin',
        $_SESSION['admin_id'],
        'Déconnexion manuelle'
    );
}

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire le cookie de session si existe
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(), 
        '', 
        time() - 3600, 
        '/'
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de login
header('Location: index.php');
exit;