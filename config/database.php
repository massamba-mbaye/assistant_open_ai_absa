<?php
/**
 * CONFIGURATION BASE DE DONNÉES
 * Connexion PDO + Fonctions utilitaires
 */

// ========================================
// CHARGEMENT DES VARIABLES D'ENVIRONNEMENT
// ========================================

/**
 * Charge les variables du fichier .env
 * Parse le fichier ligne par ligne et ajoute à $_ENV
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        die("Erreur: Fichier .env introuvable à $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parser KEY=VALUE
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Stocker dans $_ENV et putenv
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// Charger le .env depuis la racine du projet
loadEnv(__DIR__ . '/../.env');

// ========================================
// CONNEXION À LA BASE DE DONNÉES
// ========================================

/**
 * Retourne une connexion PDO à la base de données
 * Utilise un pattern Singleton pour réutiliser la connexion
 * 
 * @return PDO Instance de connexion
 * @throws PDOException Si la connexion échoue
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $host = $_ENV['DB_HOST'];
            $port = $_ENV['DB_PORT'];
            $dbname = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USER'];
            $pass = $_ENV['DB_PASS'];
            
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $user, $pass, $options);
            
        } catch (PDOException $e) {
            die("Erreur de connexion DB: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// ========================================
// FONCTIONS UTILITAIRES - CONVERSATIONS
// ========================================

/**
 * Crée une nouvelle conversation
 * 
 * @param string $userId UUID de l'utilisateur
 * @param string $title Titre de la conversation
 * @param string|null $threadId ID du thread OpenAI (optionnel)
 * @return int ID de la conversation créée
 */
function createConversation($userId, $title, $threadId = null) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO conversations (anonymous_user_id, title, thread_id) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$userId, $title, $threadId]);
    return $db->lastInsertId();
}

/**
 * Récupère les conversations d'un utilisateur avec pagination
 * 
 * @param string $userId UUID de l'utilisateur
 * @param int $page Numéro de page (commence à 1)
 * @param int $limit Nombre de résultats par page
 * @return array Conversations avec preview du premier message
 */
function getConversations($userId, $page = 1, $limit = 10) {
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.title,
            c.thread_id,
            c.created_at,
            c.updated_at,
            (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at ASC LIMIT 1) as first_message,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count
        FROM conversations c
        WHERE c.anonymous_user_id = ?
        ORDER BY c.updated_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Compte le nombre total de conversations d'un utilisateur
 * 
 * @param string $userId UUID de l'utilisateur
 * @return int Nombre total de conversations
 */
function countConversations($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM conversations WHERE anonymous_user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

/**
 * Met à jour le titre d'une conversation
 * 
 * @param int $conversationId ID de la conversation
 * @param string $title Nouveau titre
 * @return bool Succès de l'opération
 */
function updateConversationTitle($conversationId, $title) {
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE conversations SET title = ? WHERE id = ?
    ");
    return $stmt->execute([$title, $conversationId]);
}

/**
 * Met à jour le thread_id d'une conversation
 * 
 * @param int $conversationId ID de la conversation
 * @param string $threadId ID du thread OpenAI
 * @return bool Succès de l'opération
 */
function updateConversationThread($conversationId, $threadId) {
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE conversations SET thread_id = ? WHERE id = ?
    ");
    return $stmt->execute([$threadId, $conversationId]);
}

/**
 * Supprime une conversation (et tous ses messages via CASCADE)
 * 
 * @param int $conversationId ID de la conversation
 * @return bool Succès de l'opération
 */
function deleteConversation($conversationId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
    return $stmt->execute([$conversationId]);
}

/**
 * Récupère une conversation par son ID
 * 
 * @param int $conversationId ID de la conversation
 * @return array|null Données de la conversation ou null
 */
function getConversationById($conversationId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM conversations WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
    return $stmt->fetch();
}

// ========================================
// FONCTIONS UTILITAIRES - MESSAGES
// ========================================

/**
 * Sauvegarde un message dans la base de données
 * 
 * @param int $conversationId ID de la conversation
 * @param string $role 'user' ou 'assistant'
 * @param string $content Contenu du message
 * @return int ID du message créé
 */
function saveMessage($conversationId, $role, $content) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO messages (conversation_id, role, content) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$conversationId, $role, $content]);
    return $db->lastInsertId();
}

/**
 * Récupère tous les messages d'une conversation
 * 
 * @param int $conversationId ID de la conversation
 * @return array Liste des messages ordonnés par date
 */
function getMessages($conversationId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, role, content, created_at 
        FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll();
}

// ========================================
// FONCTIONS UTILITAIRES - VALIDATION
// ========================================

/**
 * Valide un UUID v4
 * 
 * @param string $uuid UUID à valider
 * @return bool True si valide
 */
function isValidUUID($uuid) {
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    return preg_match($pattern, $uuid) === 1;
}

/**
 * Retourne une réponse JSON et termine le script
 * 
 * @param mixed $data Données à retourner
 * @param int $statusCode Code HTTP (200, 400, 500, etc.)
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}