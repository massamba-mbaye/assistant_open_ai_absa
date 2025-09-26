<?php
/**
 * Configuration des chemins pour le back-office
 */

// Détecter le chemin de base automatiquement
$scriptPath = $_SERVER['SCRIPT_NAME'];
$pathParts = explode('/', $scriptPath);

// Retirer le dernier élément (nom du fichier)
array_pop($pathParts);

// Si on est dans /admin, remonter d'un niveau
if (end($pathParts) === 'admin') {
    array_pop($pathParts);
}

$basePath = implode('/', $pathParts);

// URLs absolues
define('BASE_URL', $basePath);
define('API_URL', BASE_URL . '/api/admin');
define('API_FRONTEND_URL', BASE_URL . '/api');