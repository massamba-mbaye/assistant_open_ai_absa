<?php
/**
 * API GESTION CONVERSATIONS
 * GET - Liste des conversations avec filtres
 * GET (avec conversation_id) - Détails d'une conversation avec messages
 * DELETE - Supprimer une conversation
 */

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Charger les fonctions DB et auth
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth.php';

// Vérifier l'authentification admin
$admin = requireAdminAuth();

// ========================================
// ROUTER
// ========================================

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $conversationId = $_GET['conversation_id'] ?? null;
        if ($conversationId) {
            getConversationDetails($conversationId);
        } else {
            getConversationsList();
        }
        break;
    
    case 'DELETE':
        deleteConversationAdmin();
        break;
    
    default:
        jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

// ========================================
// LISTE DES CONVERSATIONS
// ========================================
function getConversationsList() {
    try {
        $db = getDB();
        
        // Paramètres de pagination et filtres
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $search = $_GET['search'] ?? '';
        $sentiment = $_GET['sentiment'] ?? ''; // positif, neutre, negatif
        $urgence = isset($_GET['urgence']) ? (int)$_GET['urgence'] : null; // 1-5
        $sortBy = $_GET['sort_by'] ?? 'updated_at'; // updated_at, created_at, message_count
        $sortOrder = $_GET['sort_order'] ?? 'DESC';
        
        // Validation
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;
        if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'DESC';
        
        $offset = ($page - 1) * $limit;
        
        // ========================================
        // CONSTRUCTION DE LA REQUÊTE
        // ========================================
        
        $whereConditions = [];
        $params = [];
        
        // Recherche par titre ou UUID
        if (!empty($search)) {
            $whereConditions[] = "(c.title LIKE ? OR c.anonymous_user_id LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Filtre par sentiment
        if (!empty($sentiment) && in_array($sentiment, ['positif', 'neutre', 'negatif'])) {
            $whereConditions[] = "last_sentiment = ?";
            $params[] = $sentiment;
        }
        
        // Filtre par urgence
        if ($urgence !== null && $urgence >= 1 && $urgence <= 5) {
            $whereConditions[] = "last_urgence >= ?";
            $params[] = $urgence;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Mappage colonnes de tri
        $sortColumns = [
            'updated_at' => 'c.updated_at',
            'created_at' => 'c.created_at',
            'message_count' => 'message_count'
        ];
        
        $sortColumn = $sortColumns[$sortBy] ?? 'c.updated_at';
        
        // ========================================
        // REQUÊTE PRINCIPALE
        // ========================================
        
        $query = "
            SELECT 
                c.id,
                c.anonymous_user_id,
                c.title,
                c.thread_id,
                c.created_at,
                c.updated_at,
                
                -- Nombre de messages
                (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
                
                -- Premier message utilisateur
                (SELECT content FROM messages WHERE conversation_id = c.id AND role = 'user' ORDER BY created_at ASC LIMIT 1) as first_message,
                
                -- Dernière analyse émotionnelle
                (SELECT sentiment FROM emotion_analysis WHERE conversation_id = c.id ORDER BY analyzed_at DESC LIMIT 1) as last_sentiment,
                (SELECT emotion FROM emotion_analysis WHERE conversation_id = c.id ORDER BY analyzed_at DESC LIMIT 1) as last_emotion,
                (SELECT urgence FROM emotion_analysis WHERE conversation_id = c.id ORDER BY analyzed_at DESC LIMIT 1) as last_urgence,
                (SELECT type_violence FROM emotion_analysis WHERE conversation_id = c.id AND type_violence IS NOT NULL AND type_violence != 'aucun' ORDER BY analyzed_at DESC LIMIT 1) as last_violence_type
                
            FROM conversations c
            $whereClause
            ORDER BY $sortColumn $sortOrder
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $db->prepare($query);
        $executeParams = array_merge($params, [$limit, $offset]);
        $stmt->execute($executeParams);
        $conversations = $stmt->fetchAll();
        
        // ========================================
        // PAGINATION
        // ========================================
        
        $countQuery = "
            SELECT COUNT(DISTINCT c.id) as total
            FROM conversations c
            LEFT JOIN emotion_analysis ea ON ea.conversation_id = c.id
            $whereClause
        ";
        
        $stmtCount = $db->prepare($countQuery);
        $stmtCount->execute($params);
        $totalConversations = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalConversations / $limit);
        
        // ========================================
        // STATISTIQUES RAPIDES
        // ========================================
        
        $statsQuery = "
            SELECT 
                COUNT(DISTINCT c.id) as total_conversations,
                COUNT(DISTINCT c.anonymous_user_id) as unique_users,
                SUM(msg_count.count) as total_messages,
                
                -- Sentiments
                SUM(CASE WHEN last_sent.sentiment = 'positif' THEN 1 ELSE 0 END) as positif_count,
                SUM(CASE WHEN last_sent.sentiment = 'neutre' THEN 1 ELSE 0 END) as neutre_count,
                SUM(CASE WHEN last_sent.sentiment = 'negatif' THEN 1 ELSE 0 END) as negatif_count,
                
                -- Urgence élevée
                SUM(CASE WHEN last_urg.urgence >= 4 THEN 1 ELSE 0 END) as high_urgency_count
                
            FROM conversations c
            LEFT JOIN (
                SELECT conversation_id, COUNT(*) as count 
                FROM messages 
                GROUP BY conversation_id
            ) as msg_count ON msg_count.conversation_id = c.id
            LEFT JOIN (
                SELECT ea1.conversation_id, ea1.sentiment
                FROM emotion_analysis ea1
                INNER JOIN (
                    SELECT conversation_id, MAX(analyzed_at) as max_date
                    FROM emotion_analysis
                    GROUP BY conversation_id
                ) ea2 ON ea1.conversation_id = ea2.conversation_id AND ea1.analyzed_at = ea2.max_date
            ) as last_sent ON last_sent.conversation_id = c.id
            LEFT JOIN (
                SELECT ea1.conversation_id, ea1.urgence
                FROM emotion_analysis ea1
                INNER JOIN (
                    SELECT conversation_id, MAX(analyzed_at) as max_date
                    FROM emotion_analysis
                    GROUP BY conversation_id
                ) ea2 ON ea1.conversation_id = ea2.conversation_id AND ea1.analyzed_at = ea2.max_date
            ) as last_urg ON last_urg.conversation_id = c.id
        ";
        
        $stmtStats = $db->query($statsQuery);
        $stats = $stmtStats->fetch();
        
        // ========================================
        // FORMATER LES DONNÉES
        // ========================================
        
        foreach ($conversations as &$conv) {
            $conv['created_at'] = date('Y-m-d H:i:s', strtotime($conv['created_at']));
            $conv['updated_at'] = date('Y-m-d H:i:s', strtotime($conv['updated_at']));
            $conv['message_count'] = (int)$conv['message_count'];
            $conv['last_urgence'] = $conv['last_urgence'] ? (int)$conv['last_urgence'] : null;
            
            // Preview du premier message (100 caractères)
            if ($conv['first_message']) {
                $conv['preview'] = mb_substr($conv['first_message'], 0, 100) . '...';
            } else {
                $conv['preview'] = 'Aucun message';
            }
            
            unset($conv['first_message']); // Ne pas envoyer le message complet
        }
        
        // ========================================
        // RÉPONSE
        // ========================================
        
        jsonResponse([
            'success' => true,
            'conversations' => $conversations,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_conversations' => (int)$totalConversations,
                'per_page' => $limit
            ],
            'stats' => [
                'total_conversations' => (int)$stats['total_conversations'],
                'unique_users' => (int)$stats['unique_users'],
                'total_messages' => (int)$stats['total_messages'],
                'sentiment_distribution' => [
                    'positif' => (int)$stats['positif_count'],
                    'neutre' => (int)$stats['neutre_count'],
                    'negatif' => (int)$stats['negatif_count']
                ],
                'high_urgency_count' => (int)$stats['high_urgency_count']
            ]
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            'error' => 'Erreur serveur',
            'details' => $e->getMessage()
        ], 500);
    }
}

// ========================================
// DÉTAILS D'UNE CONVERSATION
// ========================================
function getConversationDetails($conversationId) {
    try {
        $db = getDB();
        
        // Validation
        if (!is_numeric($conversationId) || $conversationId < 1) {
            jsonResponse(['error' => 'conversation_id invalide'], 400);
        }
        
        // ========================================
        // 1. INFORMATIONS GÉNÉRALES
        // ========================================
        
        $stmtConv = $db->prepare("
            SELECT 
                c.*,
                (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count
            FROM conversations c
            WHERE c.id = ?
        ");
        
        $stmtConv->execute([$conversationId]);
        $conversation = $stmtConv->fetch();
        
        if (!$conversation) {
            jsonResponse(['error' => 'Conversation introuvable'], 404);
        }
        
        // ========================================
        // 2. TOUS LES MESSAGES
        // ========================================
        
        $stmtMessages = $db->prepare("
            SELECT 
                m.id,
                m.role,
                m.content,
                m.created_at,
                
                -- Analyse émotionnelle si message utilisateur
                ea.sentiment,
                ea.emotion,
                ea.urgence,
                ea.type_violence,
                ea.analyzed_at
                
            FROM messages m
            LEFT JOIN emotion_analysis ea ON ea.message_id = m.id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        
        $stmtMessages->execute([$conversationId]);
        $messages = $stmtMessages->fetchAll();
        
        // ========================================
        // 3. ANALYSE ÉMOTIONNELLE GLOBALE
        // ========================================
        
        // Répartition des sentiments
        $stmtSentiments = $db->prepare("
            SELECT 
                sentiment,
                COUNT(*) as count
            FROM emotion_analysis
            WHERE conversation_id = ?
            GROUP BY sentiment
        ");
        
        $stmtSentiments->execute([$conversationId]);
        $sentiments = $stmtSentiments->fetchAll();
        
        // Évolution de l'urgence
        $stmtUrgence = $db->prepare("
            SELECT 
                urgence,
                analyzed_at
            FROM emotion_analysis
            WHERE conversation_id = ?
            ORDER BY analyzed_at ASC
        ");
        
        $stmtUrgence->execute([$conversationId]);
        $urgenceEvolution = $stmtUrgence->fetchAll();
        
        // Types de violences
        $stmtViolence = $db->prepare("
            SELECT 
                type_violence,
                COUNT(*) as count
            FROM emotion_analysis
            WHERE conversation_id = ?
            AND type_violence IS NOT NULL
            AND type_violence != 'aucun'
            GROUP BY type_violence
        ");
        
        $stmtViolence->execute([$conversationId]);
        $violenceTypes = $stmtViolence->fetchAll();
        
        // ========================================
        // FORMATER ET RÉPONDRE
        // ========================================
        
        // Formater les messages
        foreach ($messages as &$msg) {
            $msg['created_at'] = date('Y-m-d H:i:s', strtotime($msg['created_at']));
            $msg['analyzed_at'] = $msg['analyzed_at'] ? date('Y-m-d H:i:s', strtotime($msg['analyzed_at'])) : null;
            $msg['urgence'] = $msg['urgence'] ? (int)$msg['urgence'] : null;
        }
        
        jsonResponse([
            'success' => true,
            'conversation' => [
                'id' => (int)$conversation['id'],
                'anonymous_user_id' => $conversation['anonymous_user_id'],
                'title' => $conversation['title'],
                'thread_id' => $conversation['thread_id'],
                'message_count' => (int)$conversation['message_count'],
                'created_at' => date('Y-m-d H:i:s', strtotime($conversation['created_at'])),
                'updated_at' => date('Y-m-d H:i:s', strtotime($conversation['updated_at']))
            ],
            'messages' => $messages,
            'emotions' => [
                'sentiments' => $sentiments,
                'urgence_evolution' => $urgenceEvolution,
                'violence_types' => $violenceTypes
            ]
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            'error' => 'Erreur serveur',
            'details' => $e->getMessage()
        ], 500);
    }
}

// ========================================
// SUPPRIMER UNE CONVERSATION
// ========================================
function deleteConversationAdmin() {
    try {
        $conversationId = $_GET['conversation_id'] ?? null;
        
        // Validation
        if (!$conversationId || !is_numeric($conversationId)) {
            jsonResponse(['error' => 'conversation_id requis'], 400);
        }
        
        $db = getDB();
        
        // Vérifier que la conversation existe
        $conversation = getConversationById($conversationId);
        if (!$conversation) {
            jsonResponse(['error' => 'Conversation introuvable'], 404);
        }
        
        // Supprimer (CASCADE supprime aussi messages et analyses)
        $success = deleteConversation($conversationId);
        
        if ($success) {
            // Logger l'action
            logAdminAction(
                $_SESSION['admin_id'],
                'delete_conversation',
                'conversation',
                $conversationId,
                "Suppression conversation: {$conversation['title']}"
            );
            
            jsonResponse([
                'success' => true,
                'message' => 'Conversation supprimée avec succès'
            ]);
        } else {
            jsonResponse(['error' => 'Échec de la suppression'], 500);
        }
        
    } catch (Exception $e) {
        jsonResponse([
            'error' => 'Erreur serveur',
            'details' => $e->getMessage()
        ], 500);
    }
}

// Logger l'action
logAdminAction(
    $_SESSION['admin_id'], 
    'view_conversations', 
    'conversations', 
    null, 
    'Consultation liste conversations'
);