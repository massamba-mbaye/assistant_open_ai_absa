<?php
/**
 * API ANALYSE ÉMOTIONNELLE
 * POST - Analyse un message utilisateur via OpenAI
 * GET  - Récupère les statistiques globales d'émotions
 */

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

// Charger les fonctions DB
require_once __DIR__ . '/../../config/database.php';

// ========================================
// CONFIGURATION OPENAI
// ========================================

$API_KEY = $_ENV['OPENAI_API_KEY'];

// ========================================
// ROUTER
// ========================================

$method = $_SERVER['REQUEST_METHOD'];

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
        
        // ========================================
        // PROMPT D'ANALYSE POUR OPENAI
        // ========================================
        
        $analysisPrompt = <<<PROMPT
Tu es un expert en analyse émotionnelle pour ABSA, une assistante sociale qui aide les victimes de violences au Sénégal.

Analyse le message suivant d'un utilisateur victime de violence et retourne UNIQUEMENT un objet JSON valide (sans backticks ni texte supplémentaire) :

MESSAGE À ANALYSER :
"$messageContent"

IMPORTANT : Ta réponse DOIT être UNIQUEMENT un objet JSON valide, sans aucun texte avant ou après. Ne mets pas le JSON entre des backticks. Réponds UNIQUEMENT avec le JSON brut.

Format de réponse EXACT (copie cette structure) :
{
  "sentiment": "positif|neutre|negatif",
  "emotion": "tristesse|peur|colere|honte|soulagement|confusion|espoir",
  "urgence": 1-5,
  "type_violence": "cyberharcelement|revenge_porn|racisme|islamophobie|violence_physique|autre|aucun"
}

RÈGLES D'ANALYSE :

1. SENTIMENT (général du message) :
   - "positif" : espoir, soulagement, gratitude
   - "neutre" : question simple, demande d'info
   - "negatif" : détresse, tristesse, peur, colère

2. EMOTION (dominante) :
   - "tristesse" : pleurs, désespoir, isolement
   - "peur" : anxiété, menaces, danger imminent
   - "colere" : frustration, injustice, rage
   - "honte" : culpabilité, humiliation (ex: revenge porn)
   - "soulagement" : après avoir parlé, se sent mieux
   - "confusion" : ne sait pas quoi faire, perdu
   - "espoir" : veut s'en sortir, motivation

3. URGENCE (niveau de détresse, 1-5) :
   - 1 : Simple question, pas de détresse
   - 2 : Légère préoccupation
   - 3 : Détresse modérée, besoin d'écoute
   - 4 : Détresse élevée, situation difficile
   - 5 : CRITIQUE - danger immédiat, idées suicidaires, violence grave

4. TYPE_VIOLENCE (si mentionné) :
   - "cyberharcelement" : harcèlement en ligne, menaces digitales
   - "revenge_porn" : photos/vidéos intimes diffusées
   - "racisme" : discrimination raciale
   - "islamophobie" : discrimination religieuse (Islam)
   - "violence_physique" : agression physique
   - "autre" : autre type de violence
   - "aucun" : pas de violence mentionnée

RAPPEL CRITIQUE : Réponds UNIQUEMENT avec le JSON, rien d'autre. Pas de texte explicatif, pas de backticks, juste le JSON brut.
PROMPT;

        // ========================================
        // APPEL À L'API OPENAI
        // ========================================
        
        $response = callOpenAI($API_KEY, $analysisPrompt);
        
        if (!$response['success']) {
            jsonResponse([
                'error' => 'Erreur lors de l\'analyse',
                'details' => $response['error']
            ], 500);
        }
        
        $analysisText = $response['content'];
        
        // ========================================
        // PARSER LA RÉPONSE JSON
        // ========================================
        
        // Nettoyer la réponse (enlever backticks si présents)
        $analysisText = preg_replace('/```json\s*|\s*```/', '', $analysisText);
        $analysisText = trim($analysisText);
        
        $analysis = json_decode($analysisText, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Tentative de récupération si le JSON est mal formaté
            jsonResponse([
                'error' => 'Réponse OpenAI invalide',
                'raw_response' => $analysisText,
                'json_error' => json_last_error_msg()
            ], 500);
        }
        
        // Validation des champs
        $validSentiments = ['positif', 'neutre', 'negatif'];
        $validEmotions = ['tristesse', 'peur', 'colere', 'honte', 'soulagement', 'confusion', 'espoir'];
        $validViolences = ['cyberharcelement', 'revenge_porn', 'racisme', 'islamophobie', 'violence_physique', 'autre', 'aucun'];
        
        if (!isset($analysis['sentiment']) || !in_array($analysis['sentiment'], $validSentiments)) {
            $analysis['sentiment'] = 'neutre';
        }
        
        if (!isset($analysis['emotion']) || !in_array($analysis['emotion'], $validEmotions)) {
            $analysis['emotion'] = 'confusion';
        }
        
        if (!isset($analysis['urgence']) || $analysis['urgence'] < 1 || $analysis['urgence'] > 5) {
            $analysis['urgence'] = 3;
        }
        
        if (!isset($analysis['type_violence']) || !in_array($analysis['type_violence'], $validViolences)) {
            $analysis['type_violence'] = 'aucun';
        }
        
        // ========================================
        // SAUVEGARDER DANS LA BASE
        // ========================================
        
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
            $analysisText
        ]);
        
        $analysisId = $db->lastInsertId();
        
        // ========================================
        // RÉPONSE
        // ========================================
        
        jsonResponse([
            'success' => true,
            'analysis_id' => $analysisId,
            'analysis' => [
                'sentiment' => $analysis['sentiment'],
                'emotion' => $analysis['emotion'],
                'urgence' => (int)$analysis['urgence'],
                'type_violence' => $analysis['type_violence']
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
// STATISTIQUES ÉMOTIONS
// ========================================
function getEmotionsStats() {
    // Vérifier l'authentification admin
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        jsonResponse(['error' => 'Non authentifié'], 401);
    }
    
    try {
        $db = getDB();
        
        // Période (7, 30 jours ou tout)
        $period = $_GET['period'] ?? '7'; // 7, 30, all
        
        $whereClause = '';
        if ($period !== 'all') {
            $whereClause = "WHERE DATE(analyzed_at) >= DATE_SUB(CURDATE(), INTERVAL $period DAY)";
        }
        
        // ========================================
        // 1. RÉPARTITION DES SENTIMENTS
        // ========================================
        
        $stmtSentiments = $db->query("
            SELECT 
                sentiment,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM emotion_analysis $whereClause), 1) as percentage
            FROM emotion_analysis
            $whereClause
            GROUP BY sentiment
        ");
        $sentiments = $stmtSentiments->fetchAll();
        
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
        $emotions = $stmtEmotions->fetchAll();
        
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
        $urgence = $stmtUrgence->fetchAll();
        
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
        $violences = $stmtViolence->fetchAll();
        
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
        $dailyEvolution = $stmtDaily->fetchAll();
        
        // ========================================
        // 6. STATISTIQUES GÉNÉRALES
        // ========================================
        
        $stmtStats = $db->query("
            SELECT 
                COUNT(*) as total_analyses,
                AVG(urgence) as avg_urgence,
                COUNT(CASE WHEN urgence >= 4 THEN 1 END) as high_urgency_count,
                COUNT(CASE WHEN type_violence != 'aucun' AND type_violence IS NOT NULL THEN 1 END) as violence_count
            FROM emotion_analysis
            $whereClause
        ");
        $stats = $stmtStats->fetch();
        
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
            'sentiments' => $sentiments,
            'emotions' => $emotions,
            'urgence_distribution' => $urgence,
            'violence_types' => $violences,
            'daily_evolution' => $dailyEvolution
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            'error' => 'Erreur serveur',
            'details' => $e->getMessage()
        ], 500);
    }
}

// ========================================
// FONCTION UTILITAIRE - APPEL OPENAI
// ========================================

/**
 * Appelle l'API OpenAI pour l'analyse
 * 
 * @param string $apiKey Clé API OpenAI
 * @param string $prompt Prompt d'analyse
 * @return array Résultat avec 'success' et 'content' ou 'error'
 */
function callOpenAI($apiKey, $prompt) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    
    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Tu es un expert en analyse émotionnelle. Tu réponds UNIQUEMENT avec du JSON valide, sans aucun texte supplémentaire, sans backticks.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.3,
        'max_tokens' => 200
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => 'Erreur cURL: ' . $error];
    }
    
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($statusCode !== 200) {
        $errorMsg = $responseData['error']['message'] ?? 'Erreur inconnue';
        return ['success' => false, 'error' => 'Erreur OpenAI: ' . $errorMsg];
    }
    
    $content = $responseData['choices'][0]['message']['content'] ?? '';
    
    return [
        'success' => true,
        'content' => $content
    ];
}