-- ========================================
-- BASE DE DONNÉES : assistant_open_ai
-- ========================================

-- Créer la base si elle n'existe pas
CREATE DATABASE IF NOT EXISTS assistant_open_ai 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE assistant_open_ai;

-- ========================================
-- TABLE : conversations
-- Stocke les conversations anonymes
-- ========================================
CREATE TABLE IF NOT EXISTS conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    anonymous_user_id VARCHAR(36) NOT NULL COMMENT 'UUID du client (localStorage)',
    title VARCHAR(255) NOT NULL DEFAULT 'Nouvelle conversation',
    thread_id VARCHAR(255) NULL COMMENT 'ID du thread OpenAI',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index pour optimiser les recherches
    INDEX idx_user (anonymous_user_id),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLE : messages
-- Stocke tous les messages (user + assistant)
-- ========================================
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    role ENUM('user', 'assistant') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Relation avec conversations (suppression en cascade)
    FOREIGN KEY (conversation_id) 
        REFERENCES conversations(id) 
        ON DELETE CASCADE,
    
    -- Index pour optimiser les requêtes
    INDEX idx_conversation (conversation_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- DONNÉES DE TEST (optionnel)
-- ========================================
-- Décommentez pour tester
/*
INSERT INTO conversations (anonymous_user_id, title, thread_id) VALUES
('550e8400-e29b-41d4-a716-446655440000', 'Première conversation', 'thread_abc123'),
('550e8400-e29b-41d4-a716-446655440000', 'Questions PHP', 'thread_def456');

INSERT INTO messages (conversation_id, role, content) VALUES
(1, 'user', 'Bonjour, comment vas-tu ?'),
(1, 'assistant', 'Bonjour ! Je vais bien, merci. Comment puis-je vous aider ?'),
(2, 'user', 'Comment se connecter à MySQL avec PDO ?'),
(2, 'assistant', 'Voici un exemple de connexion PDO...');
*/