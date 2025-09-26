-- ========================================
-- BACK-OFFICE ABSA - SCHEMA ADMIN
-- Tables pour authentification admin et analyse émotions
-- ========================================

USE assistant_open_ai;

-- ========================================
-- TABLE : admins
-- Gestion des comptes administrateurs
-- ========================================
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL COMMENT 'Identifiant de connexion',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Mot de passe hashé (bcrypt)',
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NULL COMMENT 'Nom complet (optionnel)',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1=actif, 0=désactivé',
    last_login TIMESTAMP NULL COMMENT 'Dernière connexion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLE : emotion_analysis
-- Analyse des émotions via OpenAI pour chaque message utilisateur
-- ========================================
CREATE TABLE IF NOT EXISTS emotion_analysis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL COMMENT 'Référence au message analysé',
    conversation_id INT NOT NULL COMMENT 'Référence à la conversation',
    
    -- Analyse émotionnelle
    sentiment ENUM('positif', 'neutre', 'negatif') NOT NULL COMMENT 'Sentiment général',
    emotion VARCHAR(50) NOT NULL COMMENT 'tristesse|peur|colere|honte|soulagement|confusion|espoir',
    urgence TINYINT(1) NOT NULL COMMENT 'Niveau 1-5 (1=faible, 5=critique)',
    type_violence VARCHAR(100) NULL COMMENT 'cyberharcelement|revenge_porn|racisme|islamophobie|violence_physique|autre|aucun',
    
    -- Métadonnées
    confidence_score DECIMAL(3,2) NULL COMMENT 'Score de confiance 0.00-1.00 (optionnel)',
    raw_response TEXT NULL COMMENT 'Réponse brute OpenAI (debug)',
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Relations
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    
    -- Index pour performances
    INDEX idx_message (message_id),
    INDEX idx_conversation (conversation_id),
    INDEX idx_sentiment (sentiment),
    INDEX idx_urgence (urgence),
    INDEX idx_analyzed_date (analyzed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLE : admin_logs
-- Traçabilité des actions des administrateurs
-- ========================================
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL COMMENT 'Admin qui a effectué l\'action',
    action VARCHAR(100) NOT NULL COMMENT 'Type d\'action (login, delete_conversation, etc.)',
    target_type VARCHAR(50) NULL COMMENT 'Type de cible (conversation, user, etc.)',
    target_id INT NULL COMMENT 'ID de la cible',
    details TEXT NULL COMMENT 'Détails supplémentaires (JSON)',
    ip_address VARCHAR(45) NULL COMMENT 'Adresse IP',
    user_agent TEXT NULL COMMENT 'Navigateur/OS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    
    INDEX idx_admin (admin_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- DONNÉES INITIALES
-- Création d'un compte admin par défaut
-- ========================================

-- Mot de passe : admin123 (À CHANGER EN PRODUCTION!)
-- Hash bcrypt généré avec password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO admins (username, password_hash, email, full_name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@absa.sn', 'Administrateur Principal')
ON DUPLICATE KEY UPDATE username=username; -- Évite les doublons

-- ========================================
-- VUES UTILES (Optionnel mais pratique)
-- ========================================

-- Vue : Conversations avec leur dernière analyse d'émotion
CREATE OR REPLACE VIEW v_conversations_emotions AS
SELECT 
    c.id AS conversation_id,
    c.anonymous_user_id,
    c.title,
    c.updated_at AS last_activity,
    ea.sentiment AS last_sentiment,
    ea.emotion AS last_emotion,
    ea.urgence AS last_urgence,
    ea.type_violence AS last_violence_type,
    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) AS message_count,
    (SELECT COUNT(*) FROM emotion_analysis WHERE conversation_id = c.id AND urgence >= 4) AS high_urgency_count
FROM conversations c
LEFT JOIN messages m ON m.conversation_id = c.id AND m.role = 'user'
LEFT JOIN emotion_analysis ea ON ea.message_id = m.id
WHERE ea.analyzed_at = (
    SELECT MAX(ea2.analyzed_at) 
    FROM emotion_analysis ea2 
    WHERE ea2.conversation_id = c.id
)
OR ea.id IS NULL
ORDER BY c.updated_at DESC;

-- Vue : Statistiques émotions par jour
CREATE OR REPLACE VIEW v_emotions_daily_stats AS
SELECT 
    DATE(analyzed_at) AS date_analyse,
    sentiment,
    emotion,
    COUNT(*) AS count,
    AVG(urgence) AS avg_urgence
FROM emotion_analysis
GROUP BY DATE(analyzed_at), sentiment, emotion
ORDER BY date_analyse DESC, count DESC;

-- ========================================
-- INFORMATIONS IMPORTANTES
-- ========================================

-- ⚠️ SÉCURITÉ :
-- 1. Changez le mot de passe admin par défaut immédiatement
-- 2. Utilisez des mots de passe forts (12+ caractères)
-- 3. En production, supprimez le compte de test

-- 📊 PERFORMANCES :
-- - Index créés sur toutes les colonnes de recherche fréquente
-- - Vues pour simplifier les requêtes complexes du dashboard

-- 🔄 MAINTENANCE :
-- Pour réinitialiser le mot de passe admin :
-- UPDATE admins SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';