<?php
/**
 * DASHBOARD ADMIN - TABLEAU DE BORD
 * Affichage des statistiques et analyses
 */

// Démarrer la session
session_start();
require_once __DIR__ . '/config-path.php';

// Vérifier l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Vérifier expiration session (2 heures)
if (time() - $_SESSION['login_time'] > 7200) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? $_SESSION['admin_username'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ABSA Back-Office</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style-admin.css">
</head>
<body>
    <!-- ========================================
         SIDEBAR NAVIGATION
         ======================================== -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>ABSA Admin</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Utilisateurs</span>
            </a>
            <a href="conversations.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Conversations</span>
            </a>
            <a href="emotions.php" class="nav-item">
                <i class="fas fa-heart"></i>
                <span>Analyse Émotions</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($adminName) ?></span>
            </div>
            <button onclick="logout()" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </button>
        </div>
    </aside>

    <!-- ========================================
         MAIN CONTENT
         ======================================== -->
    <main class="main-content">
        <!-- Header -->
        <header class="page-header">
            <div>
                <h1>Tableau de bord</h1>
                <p class="subtitle">Vue d'ensemble des activités ABSA</p>
            </div>
            <button onclick="loadStats()" class="btn-refresh">
                <i class="fas fa-sync-alt"></i>
                Actualiser
            </button>
        </header>

        <!-- Loading -->
        <div id="loading" class="loading-container">
            <div class="spinner"></div>
            <p>Chargement des statistiques...</p>
        </div>

        <!-- Content -->
        <div id="content" class="dashboard-content" style="display: none;">
            
            <!-- ========================================
                 CARTES STATISTIQUES PRINCIPALES
                 ======================================== -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-users">0</h3>
                        <p>Utilisateurs</p>
                        <span class="stat-badge" id="users-today">+0 aujourd'hui</span>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-conversations">0</h3>
                        <p>Conversations</p>
                        <span class="stat-badge" id="conv-today">+0 aujourd'hui</span>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-messages">0</h3>
                        <p>Messages</p>
                        <span class="stat-badge" id="msg-today">+0 aujourd'hui</span>
                    </div>
                </div>

                <div class="stat-card orange">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="urgent-count">0</h3>
                        <p>Conversations urgentes</p>
                        <span class="stat-badge danger">Niveau 4-5</span>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 SENTIMENTS & ÉMOTIONS
                 ======================================== -->
            <div class="charts-grid">
                <!-- Sentiments -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Sentiments (7 derniers jours)</h3>
                    </div>
                    <div class="card-body">
                        <div id="sentiment-chart" class="sentiment-bars">
                            <!-- Généré dynamiquement -->
                        </div>
                    </div>
                </div>

                <!-- Top Émotions -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-heart"></i> Top 5 Émotions</h3>
                    </div>
                    <div class="card-body">
                        <div id="emotions-list">
                            <!-- Généré dynamiquement -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 TYPES DE VIOLENCES
                 ======================================== -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-shield-alt"></i> Types de violences signalées (7 derniers jours)</h3>
                </div>
                <div class="card-body">
                    <div id="violence-types">
                        <!-- Généré dynamiquement -->
                    </div>
                </div>
            </div>

            <!-- ========================================
                 CONVERSATIONS RÉCENTES
                 ======================================== -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Conversations récentes</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Messages</th>
                                    <th>Sentiment</th>
                                    <th>Émotion</th>
                                    <th>Dernière activité</th>
                                </tr>
                            </thead>
                            <tbody id="recent-conversations">
                                <!-- Généré dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="script-admin.js"></script>
    <script>
        // ========================================
        // CHARGEMENT DES STATISTIQUES
        // ========================================

        /**
         * Charge les statistiques depuis l'API
         */
        async function loadStats() {
            const loading = document.getElementById('loading');
            const content = document.getElementById('content');
            
            loading.style.display = 'flex';
            content.style.display = 'none';

            try {
                const response = await fetch('<?= API_URL ?>/stats.php');
                
                if (response.status === 401) {
                    window.location.href = 'index.php';
                    return;
                }

                const data = await response.json();

                if (data.success) {
                    displayStats(data.stats);
                    loading.style.display = 'none';
                    content.style.display = 'block';
                } else {
                    throw new Error(data.error || 'Erreur inconnue');
                }

            } catch (error) {
                console.error('Erreur:', error);
                loading.innerHTML = `
                    <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #dc3545;"></i>
                    <p style="color: #dc3545;">Erreur de chargement des statistiques</p>
                    <button onclick="loadStats()" class="btn-refresh">Réessayer</button>
                `;
            }
        }

        /**
         * Affiche les statistiques dans l'interface
         */
        function displayStats(stats) {
            // Cartes principales
            document.getElementById('total-users').textContent = stats.total_users.toLocaleString();
            document.getElementById('total-conversations').textContent = stats.total_conversations.toLocaleString();
            document.getElementById('total-messages').textContent = stats.total_messages.toLocaleString();
            document.getElementById('urgent-count').textContent = stats.emotions.high_urgency_count;

            document.getElementById('users-today').textContent = `+${stats.today.new_users} aujourd'hui`;
            document.getElementById('conv-today').textContent = `+${stats.today.conversations} aujourd'hui`;
            document.getElementById('msg-today').textContent = `+${stats.today.messages} aujourd'hui`;

            // Sentiments
            displaySentiments(stats.emotions.sentiment_distribution);

            // Top émotions
            displayEmotions(stats.emotions.top_emotions);

            // Types de violences
            displayViolenceTypes(stats.emotions.violence_types);

            // Conversations récentes
            displayRecentConversations(stats.recent_conversations);
        }

        /**
         * Affiche les sentiments avec barres de progression
         */
        function displaySentiments(sentiments) {
            const container = document.getElementById('sentiment-chart');
            
            const emotionColors = {
                'positif': '#28a745',
                'neutre': '#ffc107',
                'negatif': '#dc3545'
            };

            const emotionIcons = {
                'positif': 'smile',
                'neutre': 'meh',
                'negatif': 'frown'
            };

            container.innerHTML = sentiments.map(s => `
                <div class="sentiment-item">
                    <div class="sentiment-label">
                        <i class="fas fa-${emotionIcons[s.sentiment]}"></i>
                        <span>${s.sentiment.charAt(0).toUpperCase() + s.sentiment.slice(1)}</span>
                        <strong>${s.percentage}%</strong>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${s.percentage}%; background: ${emotionColors[s.sentiment]};"></div>
                    </div>
                    <span class="sentiment-count">${s.count} messages</span>
                </div>
            `).join('');
        }

        /**
         * Affiche le top 5 des émotions
         */
        function displayEmotions(emotions) {
            const container = document.getElementById('emotions-list');
            
            const emotionIcons = {
                'tristesse': '😢',
                'peur': '😰',
                'colere': '😡',
                'honte': '😔',
                'soulagement': '😌',
                'confusion': '😕',
                'espoir': '💪'
            };

            container.innerHTML = emotions.map(e => `
                <div class="emotion-item">
                    <span class="emotion-icon">${emotionIcons[e.emotion] || '❓'}</span>
                    <span class="emotion-name">${e.emotion.charAt(0).toUpperCase() + e.emotion.slice(1)}</span>
                    <span class="emotion-count">${e.count}</span>
                </div>
            `).join('');
        }

        /**
         * Affiche les types de violences
         */
        function displayViolenceTypes(types) {
            const container = document.getElementById('violence-types');
            
            if (types.length === 0) {
                container.innerHTML = '<p class="no-data">Aucune violence signalée cette semaine 🎉</p>';
                return;
            }

            container.innerHTML = types.map(v => `
                <div class="violence-item">
                    <div class="violence-label">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>${v.type_violence.replace('_', ' ')}</span>
                    </div>
                    <span class="violence-count">${v.count} cas</span>
                </div>
            `).join('');
        }

        /**
         * Affiche les conversations récentes
         */
        function displayRecentConversations(conversations) {
            const tbody = document.getElementById('recent-conversations');
            
            if (conversations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">Aucune conversation récente</td></tr>';
                return;
            }

            const sentimentBadge = {
                'positif': 'success',
                'neutre': 'warning',
                'negatif': 'danger'
            };

            tbody.innerHTML = conversations.map(c => `
                <tr>
                    <td>#${c.id}</td>
                    <td class="text-truncate" title="${c.title}">${c.title}</td>
                    <td>${c.message_count}</td>
                    <td>
                        ${c.last_sentiment 
                            ? `<span class="badge ${sentimentBadge[c.last_sentiment]}">${c.last_sentiment}</span>` 
                            : '-'}
                    </td>
                    <td>${c.last_emotion || '-'}</td>
                    <td>${formatDate(c.updated_at)}</td>
                </tr>
            `).join('');
        }

        /**
         * Formate une date
         */
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 60) return `Il y a ${diffMins} min`;
            if (diffMins < 1440) return `Il y a ${Math.floor(diffMins / 60)}h`;
            return date.toLocaleDateString('fr-FR');
        }

        /**
         * Déconnexion
         */
        async function logout() {
            if (!confirm('Voulez-vous vraiment vous déconnecter ?')) return;

            try {
                await fetch('../api/admin/auth.php?action=logout', { method: 'POST' });
                window.location.href = 'index.php';
            } catch (error) {
                console.error('Erreur logout:', error);
                window.location.href = 'index.php';
            }
        }

        // Charger au démarrage
        window.addEventListener('DOMContentLoaded', loadStats);

        // Actualiser toutes les 30 secondes
        setInterval(loadStats, 30000);
    </script>
</body>
</html>