<?php
/**
 * API CONVERSATIONS
 * GET    - Récupérer les conversations d'un utilisateur
 * PUT    - Modifier le titre d'une conversation
 * DELETE - Supprimer une conversation
 */

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Charger les fonctions DB
require_once __DIR__ . '/../config/database.php';

// ========================================
// ROUTER selon la méthode HTTP
// ========================================

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;
    
    case 'PUT':
        handlePut();
        break;
    
    case 'DELETE':
        handleDelete();
        break;
    
    default:
        jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

// ========================================
// GET - Récupérer les conversations
// ========================================
function handleGet() {
    // Récupérer les paramètres
    $userId = $_GET['user_id'] ?? null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // Validation
    if (!$userId) {
        jsonResponse(['error' => 'user_id requis'], 400);
    }
    
    if (!isValidUUID($userId)) {
        jsonResponse(['error' => 'user_id invalide'], 400);
    }
    
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 50) $limit = 10;
    
    try {
        // Récupérer les conversations
        $conversations = getConversations($userId, $page, $limit);
        
        // Compter le total pour la pagination
        $total = countConversations($userId);
        $totalPages = ceil($total / $limit);
        
        // Formater les dates pour le front
        foreach ($conversations as &$conv) {
            $conv['created_at'] = date('Y-m-d H:i:s', strtotime($conv['created_at']));
            $conv['updated_at'] = date('Y-m-d H:i:s', strtotime($conv['updated_at']));
            
            // Preview du premier message (50 premiers caractères)
            if ($conv['first_message']) {
                $conv['preview'] = mb_substr($conv['first_message'], 0, 50) . '...';
            } else {
                $conv['preview'] = 'Aucun message';
            }
        }
        
        jsonResponse([
            'success' => true,
            'conversations' => $conversations,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_conversations' => $total,
                'per_page' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
    }
}

// ========================================
// PUT - Modifier le titre
// ========================================
function handlePut() {
    // Récupérer le body JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $conversationId = $input['id'] ?? null;
    $newTitle = $input['title'] ?? null;
    
    // Validation
    if (!$conversationId || !$newTitle) {
        jsonResponse(['error' => 'id et title requis'], 400);
    }
    
    $newTitle = trim($newTitle);
    if (empty($newTitle)) {
        jsonResponse(['error' => 'Le titre ne peut pas être vide'], 400);
    }
    
    if (strlen($newTitle) > 255) {
        jsonResponse(['error' => 'Titre trop long (max 255 caractères)'], 400);
    }
    
    try {
        // Vérifier que la conversation existe
        $conversation = getConversationById($conversationId);
        if (!$conversation) {
            jsonResponse(['error' => 'Conversation introuvable'], 404);
        }
        
        // Mettre à jour
        $success = updateConversationTitle($conversationId, $newTitle);
        
        if ($success) {
            jsonResponse([
                'success' => true,
                'message' => 'Titre mis à jour',
                'conversation' => [
                    'id' => $conversationId,
                    'title' => $newTitle
                ]
            ]);
        } else {
            jsonResponse(['error' => 'Échec de la mise à jour'], 500);
        }
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
    }
}

// ========================================
// DELETE - Supprimer une conversation
// ========================================
function handleDelete() {
    $conversationId = $_GET['id'] ?? null;
    
    // Validation
    if (!$conversationId) {
        jsonResponse(['error' => 'id requis'], 400);
    }
    
    try {
        // Vérifier que la conversation existe
        $conversation = getConversationById($conversationId);
        if (!$conversation) {
            jsonResponse(['error' => 'Conversation introuvable'], 404);
        }
        
        // Supprimer (supprime aussi les messages via CASCADE)
        $success = deleteConversation($conversationId);
        
        if ($success) {
            jsonResponse([
                'success' => true,
                'message' => 'Conversation supprimée'
            ]);
        } else {
            jsonResponse(['error' => 'Échec de la suppression'], 500);
        }
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
    }
}