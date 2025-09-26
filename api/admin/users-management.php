<?php
/**
 * API GESTION UTILISATEURS
 * GET - Liste des utilisateurs avec statistiques
 * GET (avec user_id) - Détails d'un utilisateur spécifique
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

// Charger les fonctions DB et auth
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth.php';

// Vérifier l'authentification admin
$admin = requireAdminAuth();

// ========================================
// ROUTER
// ========================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

$userId = $_GET['user_id'] ?? null;

if ($userId) {
    // Détails d'un utilisateur spécifique
    getUserDetails($userId);
} else {
    // Liste de tous les utilisateurs
    getUsersList();
}

// ========================================
// LISTE DES UTILISATEURS
// ========================================
function getUsersList() {
    try {
        $db = getDB();
        
        // Paramètres de pagination et filtres
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $search = $_GET['search'] ?? '';
        $sortBy = $_GET['sort_by'] ?? 'last_activity'; // last_activity, total_conversations, total_messages
        $sortOrder = $_GET['sort_order'] ?? 'DESC';
        
        // Validation
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;
        if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'DESC';
        
        $offset = ($page - 1) * $limit;
        
        // ========================================
        // REQUÊTE PRINCIPALE
        // ========================================
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE c.anonymous_user_id LIKE ? OR c.title LIKE ?";
            $params = ["%$search%", "%$search%"];
        }
        
        // Mappage colonnes de tri
        $sortColumns = [
            'last_activity' => 'last_activity',
            'total_conversations' => 'total_conversations',
            'total_messages' => 'total_messages',
            'first_seen' => 'first_seen'
        ];
        
        $sortColumn = $sortColumns[$sortBy] ?? 'last_activity';
        
        $query = "
            SELECT 
                c.anonymous_user_id,
                COUNT(DISTINCT c.id) as total_conversations,
                COUNT(m.id) as total_messages,
                MIN(c.created_at) as first_seen,
                MAX(c.updated_at) as last_activity,
                
                -- Dernière conversation
                (SELECT title FROM conversations WHERE anonymous_user_id = c.anonymous_user_id ORDER BY updated_at DESC LIMIT 1) as last_conversation_title,
                (SELECT id FROM conversations WHERE anonymous_user_id = c.anonymous_user_id ORDER BY updated_at DESC LIMIT 1) as last_conversation_id,
                
                -- Analyse émotions (dernière)
                (SELECT sentiment FROM emotion_analysis ea 
                 JOIN messages m2 ON ea.message_id = m2.id 
                 JOIN conversations c2 ON m2.conversation_id = c2.id 
                 WHERE c2.anonymous_user_id = c.anonymous_user_id 
                 ORDER BY ea.analyzed_at DESC LIMIT 1) as last_sentiment,
                 
                (SELECT emotion FROM emotion_analysis ea 
                 JOIN messages m2 ON ea.message_id = m2.id 
                 JOIN conversations c2 ON m2.conversation_id = c2.id 
                 WHERE c2.anonymous_user_id = c.anonymous_user_id 
                 ORDER BY ea.analyzed_at DESC LIMIT 1) as last_emotion,
                 
                (SELECT urgence FROM emotion_analysis ea 
                 JOIN messages m2 ON ea.message_id = m2.id 
                 JOIN conversations c2 ON m2.conversation_id = c2.id 
                 WHERE c2.anonymous_user_id = c.anonymous_user_id 
                 ORDER BY ea.analyzed_at DESC LIMIT 1) as last_urgence,
                
                -- Nombre de conversations urgentes (niveau 4-5)
                (SELECT COUNT(DISTINCT ea.conversation_id) 
                 FROM emotion_analysis ea 
                 JOIN conversations c2 ON ea.conversation_id = c2.id 
                 WHERE c2.anonymous_user_id = c.anonymous_user_id 
                 AND ea.urgence >= 4) as urgent_conversations_count
                
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            $whereClause
            GROUP BY c.anonymous_user_id
            ORDER BY $sortColumn $sortOrder
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $db->prepare($query);
        $executeParams = array_merge($params, [$limit, $offset]);
        $stmt->execute($executeParams);
        $users = $stmt->fetchAll();
        
        // ========================================
        // PAGINATION
        // ========================================
        
        $countQuery = "
            SELECT COUNT(DISTINCT c.anonymous_user_id) as total
            FROM conversations c
            $whereClause
        ";
        
        $stmtCount = $db->prepare($countQuery);
        $stmtCount->execute($params);
        $totalUsers = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalUsers / $limit);
        
        // ========================================
        // STATISTIQUES GLOBALES
        // ========================================
        
        $statsQuery = "
            SELECT 
                COUNT(DISTINCT c.anonymous_user_id) as total_users,
                COUNT(DISTINCT c.id) as total_conversations,
                COUNT(m.id) as total_messages,
                AVG(msg_count.count) as avg_messages_per_user
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            LEFT JOIN (
                SELECT c2.anonymous_user_id, COUNT(m2.id) as count
                FROM conversations c2
                LEFT JOIN messages m2 ON m2.conversation_id = c2.id
                GROUP BY c2.anonymous_user_id
            ) as msg_count ON msg_count.anonymous_user_id = c.anonymous_user_id
        ";
        
        $stmtStats = $db->query($statsQuery);
        $globalStats = $stmtStats->fetch();
        
        // ========================================
        // FORMATER LES DONNÉES
        // ========================================
        
        foreach ($users as &$user) {
            $user['first_seen'] = date('Y-m-d H:i:s', strtotime($user['first_seen']));
            $user['last_activity'] = date('Y-m-d H:i:s', strtotime($user['last_activity']));
            $user['total_conversations'] = (int)$user['total_conversations'];
            $user['total_messages'] = (int)$user['total_messages'];
            $user['urgent_conversations_count'] = (int)$user['urgent_conversations_count'];
            $user['last_urgence'] = $user['last_urgence'] ? (int)$user['last_urgence'] : null;
        }
        
        // ========================================
        // RÉPONSE
        // ========================================
        
        jsonResponse([
            'success' => true,
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_users' => (int)$totalUsers,
                'per_page' => $limit
            ],
            'global_stats' => [
                'total_users' => (int)$globalStats['total_users'],
                'total_conversations' => (int)$globalStats['total_conversations'],
                'total_messages' => (int)$globalStats['total_messages'],
                'avg_messages_per_user' => round($globalStats['avg_messages_per_user'], 1)
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
// DÉTAILS D'UN UTILISATEUR
// ========================================
function getUserDetails($userId) {
    try {
        $db = getDB();
        
        // Validation UUID
        if (!isValidUUID($userId)) {
            jsonResponse(['error' => 'UUID invalide'], 400);
        }
        
        // ========================================
        // 1. INFORMATIONS GÉNÉRALES
        // ========================================
        
        $stmtUser = $db->prepare("
            SELECT 
                c.anonymous_user_id,
                COUNT(DISTINCT c.id) as total_conversations,
                COUNT(m.id) as total_messages,
                COUNT(DISTINCT CASE WHEN m.role = 'user' THEN m.id END) as user_messages,
                COUNT(DISTINCT CASE WHEN m.role = 'assistant' THEN m.id END) as assistant_messages,
                MIN(c.created_at) as first_seen,
                MAX(c.updated_at) as last_activity
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            WHERE c.anonymous_user_id = ?
            GROUP BY c.anonymous_user_id
        ");
        
        $stmtUser->execute([$userId]);
        $userInfo = $stmtUser->fetch();
        
        if (!$userInfo) {
            jsonResponse(['error' => 'Utilisateur introuvable'], 404);
        }
        
        // ========================================
        // 2. LISTE DES CONVERSATIONS
        // ========================================
        
        $stmtConversations = $db->prepare("
            SELECT 
                c.id,
                c.title,
                c.created_at,
                c.updated_at,
                (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
                (SELECT sentiment FROM emotion_analysis WHERE conversation_id = c.id ORDER BY analyzed_at DESC LIMIT 1) as last_sentiment,
                (SELECT emotion FROM emotion_analysis WHERE conversation_id = c.id ORDER BY analyzed_at DESC LIMIT 1) as last_emotion,
                (SELECT urgence FROM emotion_analysis WHERE conversation_id = c.id ORDER BY analyzed_at DESC LIMIT 1) as urgence
            FROM conversations c
            WHERE c.anonymous_user_id = ?
            ORDER BY c.updated_at DESC
        ");
        
        $stmtConversations->execute([$userId]);
        $conversations = $stmtConversations->fetchAll();
        
        // ========================================
        // 3. ANALYSE ÉMOTIONNELLE GLOBALE
        // ========================================
        
        // Répartition des sentiments
        $stmtSentiments = $db->prepare("
            SELECT 
                ea.sentiment,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (
                    SELECT COUNT(*) 
                    FROM emotion_analysis ea2
                    JOIN conversations c2 ON ea2.conversation_id = c2.id
                    WHERE c2.anonymous_user_id = ?
                ), 1) as percentage
            FROM emotion_analysis ea
            JOIN conversations c ON ea.conversation_id = c.id
            WHERE c.anonymous_user_id = ?
            GROUP BY ea.sentiment
        ");
        
        $stmtSentiments->execute([$userId, $userId]);
        $sentiments = $stmtSentiments->fetchAll();
        
        // Top 5 émotions
        $stmtEmotions = $db->prepare("
            SELECT 
                ea.emotion,
                COUNT(*) as count
            FROM emotion_analysis ea
            JOIN conversations c ON ea.conversation_id = c.id
            WHERE c.anonymous_user_id = ?
            GROUP BY ea.emotion
            ORDER BY count DESC
            LIMIT 5
        ");
        
        $stmtEmotions->execute([$userId]);
        $topEmotions = $stmtEmotions->fetchAll();
        
        // Types de violences signalées
        $stmtViolence = $db->prepare("
            SELECT 
                ea.type_violence,
                COUNT(*) as count
            FROM emotion_analysis ea
            JOIN conversations c ON ea.conversation_id = c.id
            WHERE c.anonymous_user_id = ?
            AND ea.type_violence IS NOT NULL
            AND ea.type_violence != 'aucun'
            GROUP BY ea.type_violence
            ORDER BY count DESC
        ");
        
        $stmtViolence->execute([$userId]);
        $violenceTypes = $stmtViolence->fetchAll();
        
        // Niveau d'urgence moyen
        $stmtUrgence = $db->prepare("
            SELECT 
                AVG(ea.urgence) as avg_urgence,
                MAX(ea.urgence) as max_urgence,
                COUNT(CASE WHEN ea.urgence >= 4 THEN 1 END) as high_urgency_count
            FROM emotion_analysis ea
            JOIN conversations c ON ea.conversation_id = c.id
            WHERE c.anonymous_user_id = ?
        ");
        
        $stmtUrgence->execute([$userId]);
        $urgenceStats = $stmtUrgence->fetch();
        
        // ========================================
        // 4. ACTIVITÉ TEMPORELLE (30 derniers jours)
        // ========================================
        
        $stmtActivity = $db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(DISTINCT conversation_id) as conversations,
                COUNT(*) as messages
            FROM messages m
            JOIN conversations c ON m.conversation_id = c.id
            WHERE c.anonymous_user_id = ?
            AND DATE(m.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(m.created_at)
            ORDER BY date ASC
        ");
        
        $stmtActivity->execute([$userId]);
        $activity = $stmtActivity->fetchAll();
        
        // ========================================
        // FORMATER ET RÉPONDRE
        // ========================================
        
        // Formater les conversations
        foreach ($conversations as &$conv) {
            $conv['created_at'] = date('Y-m-d H:i:s', strtotime($conv['created_at']));
            $conv['updated_at'] = date('Y-m-d H:i:s', strtotime($conv['updated_at']));
            $conv['message_count'] = (int)$conv['message_count'];
            $conv['urgence'] = $conv['urgence'] ? (int)$conv['urgence'] : null;
        }
        
        jsonResponse([
            'success' => true,
            'user' => [
                'id' => $userInfo['anonymous_user_id'],
                'total_conversations' => (int)$userInfo['total_conversations'],
                'total_messages' => (int)$userInfo['total_messages'],
                'user_messages' => (int)$userInfo['user_messages'],
                'assistant_messages' => (int)$userInfo['assistant_messages'],
                'first_seen' => date('Y-m-d H:i:s', strtotime($userInfo['first_seen'])),
                'last_activity' => date('Y-m-d H:i:s', strtotime($userInfo['last_activity']))
            ],
            'conversations' => $conversations,
            'emotions' => [
                'sentiments' => $sentiments,
                'top_emotions' => $topEmotions,
                'violence_types' => $violenceTypes,
                'urgence_stats' => [
                    'average' => round($urgenceStats['avg_urgence'], 1),
                    'max' => (int)$urgenceStats['max_urgence'],
                    'high_urgency_count' => (int)$urgenceStats['high_urgency_count']
                ]
            ],
            'activity_timeline' => $activity
        ]);
        
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
    'view_users', 
    'users', 
    null, 
    'Consultation de la liste des utilisateurs'
);