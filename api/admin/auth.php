<?php
/**
 * API AUTHENTIFICATION ADMIN
 * POST /login  - Connexion administrateur
 * POST /logout - Déconnexion administrateur
 * GET  /check  - Vérifier si connecté
 */

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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
// ROUTER selon l'action demandée
// ========================================

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    
    case 'logout':
        handleLogout();
        break;
    
    case 'check':
        handleCheckSession();
        break;
    
    default:
        jsonResponse(['error' => 'Action non spécifiée. Utilisez ?action=login|logout|check'], 400);
}

// ========================================
// LOGIN - Connexion administrateur
// ========================================
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Méthode non autorisée'], 405);
    }
    
    // Récupérer les données
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validation
    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'Username et password requis'], 400);
    }
    
    try {
        // Rechercher l'admin dans la DB
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, username, password_hash, email, full_name, is_active 
            FROM admins 
            WHERE username = ? 
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        // Vérifier si l'admin existe
        if (!$admin) {
            // Log de tentative échouée (sécurité)
            logAdminAction(null, 'login_failed', 'admin', null, "Username: $username");
            
            jsonResponse(['error' => 'Identifiants incorrects'], 401);
        }
        
        // Vérifier si le compte est actif
        if ($admin['is_active'] != 1) {
            jsonResponse(['error' => 'Compte désactivé. Contactez un administrateur.'], 403);
        }
        
        // Vérifier le mot de passe
        if (!password_verify($password, $admin['password_hash'])) {
            logAdminAction($admin['id'], 'login_failed', 'admin', $admin['id'], "Mot de passe incorrect");
            
            jsonResponse(['error' => 'Identifiants incorrects'], 401);
        }
        
        // ✅ Connexion réussie
        
        // Créer la session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Mettre à jour last_login
        $stmtUpdate = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
        $stmtUpdate->execute([$admin['id']]);
        
        // Logger l'action
        logAdminAction($admin['id'], 'login_success', 'admin', $admin['id']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Connexion réussie',
            'admin' => [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'name' => $admin['full_name']
            ]
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
    }
}

// ========================================
// LOGOUT - Déconnexion administrateur
// ========================================
function handleLogout() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Méthode non autorisée'], 405);
    }
    
    // Logger la déconnexion avant de détruire la session
    if (isset($_SESSION['admin_id'])) {
        logAdminAction($_SESSION['admin_id'], 'logout', 'admin', $_SESSION['admin_id']);
    }
    
    // Détruire la session
    session_unset();
    session_destroy();
    
    jsonResponse([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
}

// ========================================
// CHECK - Vérifier si l'admin est connecté
// ========================================
function handleCheckSession() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Méthode non autorisée'], 405);
    }
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        jsonResponse([
            'authenticated' => false,
            'message' => 'Non connecté'
        ], 401);
    }
    
    // Session expirée après 2 heures (7200 secondes)
    if (time() - $_SESSION['login_time'] > 7200) {
        session_unset();
        session_destroy();
        
        jsonResponse([
            'authenticated' => false,
            'message' => 'Session expirée'
        ], 401);
    }
    
    jsonResponse([
        'authenticated' => true,
        'admin' => [
            'id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'email' => $_SESSION['admin_email'],
            'name' => $_SESSION['admin_name']
        ]
    ]);
}

// ========================================
// FONCTION UTILITAIRE - Logger les actions admin
// ========================================

/**
 * Enregistre une action admin dans la table admin_logs
 * 
 * @param int|null $adminId ID de l'admin (null si login échoué)
 * @param string $action Type d'action (login_success, delete_conversation, etc.)
 * @param string|null $targetType Type de cible (conversation, user, etc.)
 * @param int|null $targetId ID de la cible
 * @param string|null $details Détails supplémentaires
 */
function logAdminAction($adminId, $action, $targetType = null, $targetId = null, $details = null) {
    try {
        $db = getDB();
        
        // Si pas d'admin_id, utiliser un ID fictif pour les logs de sécurité
        $adminIdToLog = $adminId ?? 0;
        
        // Récupérer l'IP et User-Agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $db->prepare("
            INSERT INTO admin_logs 
            (admin_id, action, target_type, target_id, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $adminIdToLog,
            $action,
            $targetType,
            $targetId,
            $details,
            $ipAddress,
            $userAgent
        ]);
        
    } catch (Exception $e) {
        // Ne pas bloquer l'exécution si le log échoue
        error_log("Erreur log admin: " . $e->getMessage());
    }
}

// ========================================
// FONCTION UTILITAIRE - Vérifier si admin connecté
// ========================================

/**
 * Vérifie si un administrateur est connecté
 * À appeler au début de chaque API admin protégée
 * 
 * @return array Infos de l'admin ou termine le script avec erreur 401
 */
function requireAdminAuth() {
    session_start();
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        jsonResponse([
            'error' => 'Non authentifié. Veuillez vous connecter.',
            'redirect' => '/admin/index.php'
        ], 401);
    }
    
    // Vérifier expiration session (2 heures)
    if (time() - $_SESSION['login_time'] > 7200) {
        session_unset();
        session_destroy();
        
        jsonResponse([
            'error' => 'Session expirée. Veuillez vous reconnecter.',
            'redirect' => '/admin/index.php'
        ], 401);
    }
    
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'email' => $_SESSION['admin_email'],
        'name' => $_SESSION['admin_name']
    ];
}