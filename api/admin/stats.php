<?php
/**
 * API STATISTIQUES DASHBOARD
 * GET - Récupère toutes les statistiques pour le tableau de bord admin
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
// RÉCUPÉRATION DES STATISTIQUES
// ========================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

try {
    $db = getDB();
    
    // ========================================
    // 1. STATISTIQUES GÉNÉRALES
    // ========================================
    
    // Nombre total d'utilisateurs uniques
    $stmtUsers = $db->query("SELECT COUNT(DISTINCT anonymous_user_id) as total FROM conversations");
    $totalUsers = $stmtUsers->fetch()['total'];
    
    // Nombre total de conversations
    $stmtConv = $db->query("SELECT COUNT(*) as total FROM conversations");
    $totalConversations = $stmtConv->fetch()['total'];
    
    // Nombre total de messages
    $stmtMsg = $db->query("SELECT COUNT(*) as total FROM messages");
    $totalMessages = $stmtMsg->fetch()['total'];
    
    // Nombre de messages utilisateurs uniquement
    $stmtUserMsg = $db->query("SELECT COUNT(*) as total FROM messages WHERE role = 'user'");
    $totalUserMessages = $stmtUserMsg->fetch()['total'];
    
    // ========================================
    // 2. STATISTIQUES DU JOUR
    // ========================================
    
    $today = date('Y-m-d');
    
    // Conversations créées aujourd'hui
    $stmtConvToday = $db->prepare("
        SELECT COUNT(*) as total 
        FROM conversations 
        WHERE DATE(created_at) = ?
    ");
    $stmtConvToday->execute([$today]);
    $conversationsToday = $stmtConvToday->fetch()['total'];
    
    // Messages envoyés aujourd'hui
    $stmtMsgToday = $db->prepare("
        SELECT COUNT(*) as total 
        FROM messages 
        WHERE DATE(created_at) = ?
    ");
    $stmtMsgToday->execute([$today]);
    $messagesToday = $stmtMsgToday->fetch()['total'];
    
    // Nouveaux utilisateurs aujourd'hui
    $stmtUsersToday = $db->prepare("
        SELECT COUNT(DISTINCT anonymous_user_id) as total 
        FROM conversations 
        WHERE DATE(created_at) = ?
    ");
    $stmtUsersToday->execute([$today]);
    $newUsersToday = $stmtUsersToday->fetch()['total'];
    
    // ========================================
    // 3. ANALYSE DES ÉMOTIONS
    // ========================================
    
    // Répartition des sentiments (7 derniers jours)
    $stmtSentiment = $db->query("
        SELECT 
            sentiment,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM emotion_analysis WHERE DATE(analyzed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)), 1) as percentage
        FROM emotion_analysis
        WHERE DATE(analyzed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY sentiment
    ");
    $sentimentStats = $stmtSentiment->fetchAll();
    
    // Top 5 émotions de la semaine
    $stmtEmotions = $db->query("
        SELECT 
            emotion,
            COUNT(*) as count
        FROM emotion_analysis
        WHERE DATE(analyzed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY emotion
        ORDER BY count DESC
        LIMIT 5
    ");
    $topEmotions = $stmtEmotions->fetchAll();
    
    // Conversations à haute urgence (niveau 4-5)
    $stmtUrgent = $db->query("
        SELECT COUNT(DISTINCT conversation_id) as total
        FROM emotion_analysis
        WHERE urgence >= 4 
        AND DATE(analyzed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $highUrgencyCount = $stmtUrgent->fetch()['total'];
    
    // Types de violences signalées (7 derniers jours)
    $stmtViolence = $db->query("
        SELECT 
            type_violence,
            COUNT(*) as count
        FROM emotion_analysis
        WHERE type_violence IS NOT NULL 
        AND type_violence != 'aucun'
        AND DATE(analyzed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY type_violence
        ORDER BY count DESC
    ");
    $violenceTypes = $stmtViolence->fetchAll();
    
    // ========================================
    // 4. TENDANCES (30 derniers jours)
    // ========================================
    
    // Évolution quotidienne des conversations
    $stmtTrend = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM conversations
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $conversationTrend = $stmtTrend->fetchAll();
    
    // Évolution quotidienne des messages
    $stmtMsgTrend = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM messages
        WHERE role = 'user'
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $messageTrend = $stmtMsgTrend->fetchAll();
    
    // ========================================
    // 5. MOYENNES ET RATIOS
    // ========================================
    
    // Moyenne de messages par conversation
    $avgMessagesPerConv = $totalConversations > 0 
        ? round($totalMessages / $totalConversations, 1) 
        : 0;
    
    // Moyenne de conversations par utilisateur
    $avgConvPerUser = $totalUsers > 0 
        ? round($totalConversations / $totalUsers, 1) 
        : 0;
    
    // ========================================
    // 6. DERNIÈRES ACTIVITÉS
    // ========================================
    
    // 5 dernières conversations avec détails
    $stmtRecent = $db->query("
        SELECT 
            c.id,
            c.anonymous_user_id,
            c.title,
            c.updated_at,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
            (SELECT sentiment FROM emotion_analysis WHERE conversation_id = c.id ORDER BY analyzed_at DESC LIMIT 1) as last_sentiment,
            (SELECT emotion FROM emotion_analysis WHERE conversation_id = c.id ORDER BY analyzed_at DESC LIMIT 1) as last_emotion
        FROM conversations c
        ORDER BY c.updated_at DESC
        LIMIT 5
    ");
    $recentConversations = $stmtRecent->fetchAll();
    
    // ========================================
    // CONSTRUCTION DE LA RÉPONSE
    // ========================================
    
    jsonResponse([
        'success' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username']
        ],
        'stats' => [
            // Générales
            'total_users' => (int)$totalUsers,
            'total_conversations' => (int)$totalConversations,
            'total_messages' => (int)$totalMessages,
            'total_user_messages' => (int)$totalUserMessages,
            
            // Aujourd'hui
            'today' => [
                'conversations' => (int)$conversationsToday,
                'messages' => (int)$messagesToday,
                'new_users' => (int)$newUsersToday
            ],
            
            // Émotions (7 jours)
            'emotions' => [
                'sentiment_distribution' => $sentimentStats,
                'top_emotions' => $topEmotions,
                'high_urgency_count' => (int)$highUrgencyCount,
                'violence_types' => $violenceTypes
            ],
            
            // Tendances (30 jours)
            'trends' => [
                'conversations' => $conversationTrend,
                'messages' => $messageTrend
            ],
            
            // Moyennes
            'averages' => [
                'messages_per_conversation' => $avgMessagesPerConv,
                'conversations_per_user' => $avgConvPerUser
            ],
            
            // Activités récentes
            'recent_conversations' => $recentConversations
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'error' => 'Erreur serveur',
        'details' => $e->getMessage()
    ], 500);
}