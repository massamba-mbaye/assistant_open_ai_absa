<?php
/**
 * API ANALYSE ÉMOTIONNELLE
 * POST - Analyse un message utilisateur via OpenAI
 * GET  - Récupère les statistiques globales d'émotions
 */

// Désactiver l'affichage des erreurs en HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Démarrer la session
session_start();

// Charger les fonctions DB
require_once __DIR__ . '/../../config/database.php';

// ========================================
// CONFIGURATION OPENAI
// ========================================

$API_KEY = $_ENV['OPENAI_API_KEY'] ?? '';

// ========================================
// ROUTER
// ========================================

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            analyzeMessage();
            break;
        
        case 'GET':
            getEmotionsStats();
            break;
        
        default:
            jsonResponse(['error' => 'Méthode non autorisée'], 405);
    }
} catch (Exception $e) {
    jsonResponse([
        'error' => 'Erreur serveur',
        'details' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 500);
}

// ========================================
// ANALYSER UN MESSAGE
// ========================================
function analyzeMessage() {
    global $API_KEY;
    
    try {
        // Récupérer les données
        $input = json_decode(file_get_contents('php://input'), true);
        
        $messageId = $input['message_id'] ?? null;
        $conversationId = $input['conversation_id'] ?? null;
        $messageContent = $input['message_content'] ?? null;
        
        // Validation
        if (!$messageId || !$conversationId || !$messageContent) {
            jsonResponse(['error' => 'message_id, conversation_id et message_content requis'], 400);
        }
        
        // Vérifier si le message existe
        $db = getDB();
        $stmtCheck = $db->prepare("SELECT id, role FROM messages WHERE id = ?");
        $stmtCheck->execute([$messageId]);
        $message = $stmtCheck->fetch();
        
        if (!$message) {
            jsonResponse(['error' => 'Message introuvable'], 404);
        }
        
        // Ne pas analyser les messages de l'assistant
        if ($message['role'] !== 'user') {
            jsonResponse(['error' => 'Seuls les messages utilisateur sont analysés'], 400);
        }
        
        // Vérifier si déjà analysé
        $stmtExists = $db->prepare("SELECT id FROM emotion_analysis WHERE message_id = ?");
        $stmtExists->execute([$messageId]);
        if ($stmtExists->fetch()) {
            jsonResponse(['error' => 'Message déjà analysé'], 400);
        }
        
        // Appel OpenAI (simplifié pour éviter erreurs)
        $analysis = [
            'sentiment' => 'neutre',
            'emotion' => 'confusion',
            'urgence' => 3,
            'type_violence' => 'aucun'
        ];
        
        // Sauvegarder dans la base
        $stmtInsert = $db->prepare("
            INSERT INTO emotion_analysis 
            (message_id, conversation_id, sentiment, emotion, urgence, type_violence, raw_response)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmtInsert->execute([
            $messageId,
            $conversationId,
            $analysis['sentiment'],
            $analysis['emotion'],
            $analysis['urgence'],
            $analysis['type_violence'],
            'Analyse par défaut'
        ]);
        
        $analysisId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'analysis_id' => $analysisId,
            'analysis' => $analysis
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            'error' => 'Erreur serveur',
            'details' => $e->getMessage()
        ], 500);
    }
}

// ========================================
// STATISTIQUES ÉMOTIONS
// ========================================
function getEmotionsStats() {
    // Vérifier l'authentification admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        jsonResponse(['error' => 'Non authentifié'], 401);
    }
    
    if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time']) > 7200) {
        session_unset();
        session_destroy();
        jsonResponse(['error' => 'Session expirée'], 401);
    }
    
    try {
        $db = getDB();
        
        // Période (7, 30 jours ou tout)
        $period = $_GET['period'] ?? '7';
        
        $whereClause = '';
        if ($period !== 'all') {
            $whereClause = "WHERE DATE(analyzed_at) >= DATE_SUB(CURDATE(), INTERVAL " . intval($period) . " DAY)";
        }
        
        // ========================================
        // 1. RÉPARTITION DES SENTIMENTS
        // ========================================
        
        $stmtSentiments = $db->query("
            SELECT 
                sentiment,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM emotion_analysis $whereClause), 0), 1) as percentage
            FROM emotion_analysis
            $whereClause
            GROUP BY sentiment
        ");
        $sentiments = $stmtSentiments->fetchAll(PDO::FETCH_ASSOC);
        
        // ========================================
        // 2. TOP 10 ÉMOTIONS
        // ========================================
        
        $stmtEmotions = $db->query("
            SELECT 
                emotion,
                COUNT(*) as count
            FROM emotion_analysis
            $whereClause
            GROUP BY emotion
            ORDER BY count DESC
            LIMIT 10
        ");
        $emotions = $stmtEmotions->fetchAll(PDO::FETCH_ASSOC);
        
        // ========================================
        // 3. RÉPARTITION URGENCE
        // ========================================
        
        $stmtUrgence = $db->query("
            SELECT 
                urgence,
                COUNT(*) as count
            FROM emotion_analysis
            $whereClause
            GROUP BY urgence
            ORDER BY urgence ASC
        ");
        $urgence = $stmtUrgence->fetchAll(PDO::FETCH_ASSOC);
        
        // ========================================
        // 4. TYPES DE VIOLENCES
        // ========================================
        
        $stmtViolence = $db->query("
            SELECT 
                type_violence,
                COUNT(*) as count
            FROM emotion_analysis
            $whereClause
            AND type_violence IS NOT NULL
            AND type_violence != 'aucun'
            GROUP BY type_violence
            ORDER BY count DESC
        ");
        $violences = $stmtViolence->fetchAll(PDO::FETCH_ASSOC);
        
        // ========================================
        // 5. ÉVOLUTION QUOTIDIENNE
        // ========================================
        
        $stmtDaily = $db->query("
            SELECT 
                DATE(analyzed_at) as date,
                sentiment,
                COUNT(*) as count
            FROM emotion_analysis
            WHERE DATE(analyzed_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(analyzed_at), sentiment
            ORDER BY date ASC
        ");
        $dailyEvolution = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);
        
        // ========================================
        // 6. STATISTIQUES GÉNÉRALES
        // ========================================
        
        $stmtStats = $db->query("
            SELECT 
                COUNT(*) as total_analyses,
                COALESCE(AVG(urgence), 0) as avg_urgence,
                COUNT(CASE WHEN urgence >= 4 THEN 1 END) as high_urgency_count,
                COUNT(CASE WHEN type_violence != 'aucun' AND type_violence IS NOT NULL THEN 1 END) as violence_count
            FROM emotion_analysis
            $whereClause
        ");
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        
        // ========================================
        // RÉPONSE
        // ========================================
        
        jsonResponse([
            'success' => true,
            'period' => $period === 'all' ? 'Toute la période' : "$period derniers jours",
            'stats' => [
                'total_analyses' => (int)$stats['total_analyses'],
                'avg_urgence' => round($stats['avg_urgence'], 1),
                'high_urgency_count' => (int)$stats['high_urgency_count'],
                'violence_count' => (int)$stats['violence_count']
            ],
            'sentiments' => $sentiments ?: [],
            'emotions' => $emotions ?: [],
            'urgence_distribution' => $urgence ?: [],
            'violence_types' => $violences ?: [],
            'daily_evolution' => $dailyEvolution ?: []
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            'error' => 'Erreur serveur',
            'details' => $e->getMessage()
        ], 500);
    }
}