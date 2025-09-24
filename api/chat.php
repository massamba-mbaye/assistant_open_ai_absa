<?php
/**
 * API CHAT - Envoi de message et sauvegarde DB
 * POST - Envoyer un message et recevoir la réponse de l'assistant
 */

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Charger les fonctions DB
require_once __DIR__ . '/../config/database.php';

// Démarrer la session pour stocker le thread_id
session_start();

// ========================================
// CONFIGURATION
// ========================================
$MAX_LENGTH = (int)$_ENV['MAX_MESSAGE_LENGTH'];
$API_KEY = $_ENV['OPENAI_API_KEY'];
$ASSISTANT_ID = $_ENV['ASSISTANT_ID'];

// ========================================
// VALIDATION DE L'INPUT
// ========================================
$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['user_id'] ?? null;
$conversationId = $input['conversation_id'] ?? null;
$message = trim($input['message'] ?? '');

// Validation user_id
if (!$userId) {
    jsonResponse(['error' => 'user_id requis'], 400);
}

if (!isValidUUID($userId)) {
    jsonResponse(['error' => 'user_id invalide'], 400);
}

// Validation message
if (empty($message)) {
    jsonResponse(['error' => 'Message vide'], 400);
}

if (strlen($message) > $MAX_LENGTH) {
    jsonResponse(['error' => 'Message trop long (max ' . $MAX_LENGTH . ' caractères)'], 413);
}

// ========================================
// GESTION DE LA CONVERSATION
// ========================================
try {
    $threadId = null;
    
    // Si conversation_id fourni, récupérer le thread_id existant
    if ($conversationId) {
        $conversation = getConversationById($conversationId);
        
        if (!$conversation) {
            jsonResponse(['error' => 'Conversation introuvable'], 404);
        }
        
        // Vérifier que la conversation appartient bien à cet utilisateur
        if ($conversation['anonymous_user_id'] !== $userId) {
            jsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $threadId = $conversation['thread_id'];
        
    } else {
        // Nouvelle conversation : créer avec un titre temporaire
        $tempTitle = mb_substr($message, 0, 50);
        $conversationId = createConversation($userId, $tempTitle);
    }
    
    // ========================================
    // CRÉATION/RÉCUPÉRATION DU THREAD OPENAI
    // ========================================
    
    // Si pas de thread_id, créer un nouveau thread OpenAI
    if (!$threadId) {
        $response = openaiRequest('POST', 'https://api.openai.com/v1/threads', $API_KEY);
        $threadId = $response['id'];
        
        // Sauvegarder le thread_id dans la conversation
        updateConversationThread($conversationId, $threadId);
    }
    
    // ========================================
    // ENVOI DU MESSAGE À OPENAI
    // ========================================
    
    // Ajouter le message au thread
    openaiRequest('POST', 
        "https://api.openai.com/v1/threads/$threadId/messages",
        $API_KEY,
        [
            'role' => 'user',
            'content' => $message
        ]
    );
    
    // Créer le run
    $run = openaiRequest('POST', 
        "https://api.openai.com/v1/threads/$threadId/runs",
        $API_KEY,
        [
            'assistant_id' => $ASSISTANT_ID
        ]
    );
    
    // Attendre la réponse
    $runId = $run['id'];
    $maxAttempts = 30; // 30 secondes max
    $attempts = 0;
    
    do {
        usleep(250000);
        $status = openaiRequest('GET', 
            "https://api.openai.com/v1/threads/$threadId/runs/$runId",
            $API_KEY
        );
        $attempts++;
        
        if ($attempts >= $maxAttempts) {
            jsonResponse(['error' => 'Timeout: réponse trop longue'], 504);
        }
        
    } while ($status['status'] === 'in_progress' || $status['status'] === 'queued');
    
    // Vérifier le statut final
    if ($status['status'] !== 'completed') {
        jsonResponse(['error' => 'Erreur lors de l\'exécution: ' . $status['status']], 500);
    }
    
    // Récupérer la réponse
    $messages = openaiRequest('GET', 
        "https://api.openai.com/v1/threads/$threadId/messages",
        $API_KEY
    );
    
    $assistantMessage = $messages['data'][0]['content'][0]['text']['value'];
    
    // ========================================
    // SAUVEGARDE EN BASE DE DONNÉES
    // ========================================
    
    // Sauvegarder le message user
    saveMessage($conversationId, 'user', $message);
    
    // Sauvegarder la réponse assistant
    saveMessage($conversationId, 'assistant', $assistantMessage);
    
    // ========================================
    // RÉPONSE AU CLIENT
    // ========================================
    
    jsonResponse([
        'success' => true,
        'conversation_id' => (int)$conversationId,
        'thread_id' => $threadId,
        'response' => $assistantMessage
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'error' => 'Erreur interne',
        'details' => $e->getMessage()
    ], 500);
}

// ========================================
// FONCTION UTILITAIRE OPENAI
// ========================================

/**
 * Effectue une requête à l'API OpenAI
 * 
 * @param string $method GET ou POST
 * @param string $url URL de l'endpoint
 * @param string $apiKey Clé API OpenAI
 * @param array|null $data Données à envoyer (pour POST)
 * @return array Réponse décodée
 * @throws Exception En cas d'erreur
 */
function openaiRequest($method, $url, $apiKey, $data = null) {
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $data ? json_encode($data) : null,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Erreur cURL: ' . $error);
    }
    
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($statusCode !== 200) {
        $errorMsg = $responseData['error']['message'] ?? 'Erreur inconnue';
        throw new Exception('Erreur OpenAI: ' . $errorMsg);
    }
    
    return $responseData;
}