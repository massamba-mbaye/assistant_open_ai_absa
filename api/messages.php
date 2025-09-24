<?php
/**
 * API MESSAGES
 * GET - Récupérer tous les messages d'une conversation
 */

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Charger les fonctions DB
require_once __DIR__ . '/../config/database.php';

// ========================================
// GET - Récupérer les messages
// ========================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

// Récupérer les paramètres
$conversationId = $_GET['conversation_id'] ?? null;

// Validation
if (!$conversationId) {
    jsonResponse(['error' => 'conversation_id requis'], 400);
}

if (!is_numeric($conversationId) || $conversationId < 1) {
    jsonResponse(['error' => 'conversation_id invalide'], 400);
}

try {
    // Vérifier que la conversation existe
    $conversation = getConversationById($conversationId);
    
    if (!$conversation) {
        jsonResponse(['error' => 'Conversation introuvable'], 404);
    }
    
    // Récupérer tous les messages
    $messages = getMessages($conversationId);
    
    // Formater les dates
    foreach ($messages as &$msg) {
        $msg['created_at'] = date('Y-m-d H:i:s', strtotime($msg['created_at']));
    }
    
    jsonResponse([
        'success' => true,
        'conversation' => [
            'id' => $conversation['id'],
            'title' => $conversation['title'],
            'thread_id' => $conversation['thread_id']
        ],
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
}