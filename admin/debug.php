<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Debug ABSA</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: white; padding: 15px; border-radius: 8px; overflow-x: auto; }
        h2 { color: #4b3795; border-bottom: 2px solid #4b3795; padding-bottom: 10px; }
    </style>
</head>
<body>
    <h1>🔍 Debug ABSA Back-Office</h1>

    <h2>1. Configuration Chemins</h2>
    <pre>
SCRIPT_NAME: <?= $_SERVER['SCRIPT_NAME'] ?>
BASE_DIR: <?= __DIR__ ?>
SERVER_NAME: <?= $_SERVER['SERVER_NAME'] ?>
SERVER_PORT: <?= $_SERVER['SERVER_PORT'] ?>
REQUEST_URI: <?= $_SERVER['REQUEST_URI'] ?>
    </pre>

    <h2>2. Connexion Base de Données</h2>
    <?php
    try {
        $db = getDB();
        echo '<p class="success">✅ Connexion réussie</p>';
        
        // Test tables
        $tables = ['conversations', 'messages', 'admins', 'emotion_analysis'];
        foreach ($tables as $table) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "<p class='info'>📊 Table <strong>$table</strong>: $count lignes</p>";
        }
        
    } catch (Exception $e) {
        echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
    }
    ?>

    <h2>3. Variables d'environnement</h2>
    <pre>
DB_HOST: <?= $_ENV['DB_HOST'] ?? 'NON DÉFINI' ?>
DB_NAME: <?= $_ENV['DB_NAME'] ?? 'NON DÉFINI' ?>
OPENAI_API_KEY: <?= isset($_ENV['OPENAI_API_KEY']) ? '✅ Défini' : '❌ Manquant' ?>
ASSISTANT_ID: <?= isset($_ENV['ASSISTANT_ID']) ? '✅ Défini' : '❌ Manquant' ?>
    </pre>

    <h2>4. Test API Utilisateurs</h2>
    <?php
    try {
        $stmt = $db->query("
            SELECT 
                c.anonymous_user_id,
                COUNT(c.id) as total_conversations
            FROM conversations c
            GROUP BY c.anonymous_user_id
            LIMIT 5
        ");
        
        $users = $stmt->fetchAll();
        
        if (count($users) > 0) {
            echo '<p class="success">✅ ' . count($users) . ' utilisateurs trouvés</p>';
            echo '<pre>' . print_r($users, true) . '</pre>';
        } else {
            echo '<p class="error">❌ Aucun utilisateur trouvé</p>';
        }
        
    } catch (Exception $e) {
        echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
    }
    ?>

    <h2>5. Test API Conversations</h2>
    <?php
    try {
        $stmt = $db->query("
            SELECT 
                id,
                title,
                anonymous_user_id,
                created_at
            FROM conversations
            ORDER BY created_at DESC
            LIMIT 5
        ");
        
        $convs = $stmt->fetchAll();
        
        if (count($convs) > 0) {
            echo '<p class="success">✅ ' . count($convs) . ' conversations trouvées</p>';
            echo '<pre>' . print_r($convs, true) . '</pre>';
        } else {
            echo '<p class="error">❌ Aucune conversation trouvée</p>';
        }
        
    } catch (Exception $e) {
        echo '<p class="error">❌ Erreur: ' . $e->getMessage() . '</p>';
    }
    ?>

    <h2>6. Chemin d'accès recommandé</h2>
    <?php
    $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $host = $_SERVER['SERVER_NAME'];
    $port = $_SERVER['SERVER_PORT'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    
    $baseUrl = "$protocol://$host" . ($port != 80 && $port != 443 ? ":$port" : "") . $scriptPath;
    $baseUrl = str_replace('/admin', '', $baseUrl);
    
    echo "<pre>URL de base: <strong>$baseUrl</strong></pre>";
    echo "<p class='info'>Utilisez cette URL dans votre fichier config-path.php</p>";
    ?>

    <hr>
    <p><a href="index.php">← Retour au login</a></p>
</body>
</html>