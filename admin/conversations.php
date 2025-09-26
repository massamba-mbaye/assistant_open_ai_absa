<?php
/**
 * PAGE GESTION CONVERSATIONS
 * Liste et détails des conversations avec analyses
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
    <title>Conversations - ABSA Back-Office</title>
    
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
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Utilisateurs</span>
            </a>
            <a href="conversations.php" class="nav-item active">
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
                <h1>Gestion des Conversations</h1>
                <p class="subtitle">Suivi et analyse des échanges ABSA</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="exportConversations()" class="btn-secondary">
                    <i class="fas fa-download"></i>
                    Exporter CSV
                </button>
                <button onclick="loadConversations()" class="btn-refresh">
                    <i class="fas fa-sync-alt"></i>
                    Actualiser
                </button>
            </div>
        </header>

        <!-- Statistiques globales -->
        <div id="global-stats" class="stats-grid" style="margin-bottom: 25px;">
            <!-- Généré dynamiquement -->
        </div>

        <!-- Filtres et recherche -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input 
                    type="text" 
                    id="search-input" 
                    placeholder="Rechercher par titre ou UUID..."
                    onkeyup="handleSearch()"
                >
            </div>

            <div class="filter-group">
                <label>Sentiment :</label>
                <select id="filter-sentiment" onchange="loadConversations()">
                    <option value="">Tous</option>
                    <option value="positif">Positif</option>
                    <option value="neutre">Neutre</option>
                    <option value="negatif">Négatif</option>
                </select>

                <label>Urgence ≥ :</label>
                <select id="filter-urgence" onchange="loadConversations()">
                    <option value="">Toutes</option>
                    <option value="4">4 (Élevée)</option>
                    <option value="5">5 (Critique)</option>
                </select>

                <label>Trier par :</label>
                <select id="sort-by" onchange="loadConversations()">
                    <option value="updated_at">Dernière activité</option>
                    <option value="created_at">Date création</option>
                    <option value="message_count">Nb messages</option>
                </select>

                <select id="sort-order" onchange="loadConversations()">
                    <option value="DESC">↓ Décroissant</option>
                    <option value="ASC">↑ Croissant</option>
                </select>
            </div>
        </div>

        <!-- Loading -->
        <div id="loading" class="loading-container">
            <div class="spinner"></div>
            <p>Chargement des conversations...</p>
        </div>

        <!-- Tableau conversations -->
        <div id="conversations-table-container" class="chart-card" style="display: none;">
            <div class="card-header">
                <h3><i class="fas fa-comments"></i> Liste des conversations (<span id="total-conv-count">0</span>)</h3>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Titre</th>
                                <th>Utilisateur (UUID)</th>
                                <th>Messages</th>
                                <th>Sentiment</th>
                                <th>Émotion</th>
                                <th>Urgence</th>
                                <th>Violence</th>
                                <th>Dernière activité</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="conversations-tbody">
                            <!-- Généré dynamiquement -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <button id="prev-page" onclick="prevPage()" disabled>
                        <i class="fas fa-chevron-left"></i>
                        Précédent
                    </button>
                    <span id="page-info">Page 1 / 1</span>
                    <button id="next-page" onclick="nextPage()" disabled>
                        Suivant
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script src="script-admin.js"></script>
    <script>
        // ========================================
        // VARIABLES GLOBALES
        // ========================================

        let currentPage = 1;
        let totalPages = 1;
        let searchTimeout = null;
        let conversationsData = [];

        // ========================================
        // CHARGEMENT DES CONVERSATIONS
        // ========================================

        /**
         * Charge la liste des conversations
         */
        async function loadConversations() {
            const loading = document.getElementById('loading');
            const tableContainer = document.getElementById('conversations-table-container');
            
            loading.style.display = 'flex';
            tableContainer.style.display = 'none';

            try {
                const sortBy = document.getElementById('sort-by').value;
                const sortOrder = document.getElementById('sort-order').value;
                const search = document.getElementById('search-input').value;
                const sentiment = document.getElementById('filter-sentiment').value;
                const urgence = document.getElementById('filter-urgence').value;

                const params = new URLSearchParams({
                    page: currentPage,
                    limit: 20,
                    sort_by: sortBy,
                    sort_order: sortOrder
                });

                if (search) params.append('search', search);
                if (sentiment) params.append('sentiment', sentiment);
                if (urgence) params.append('urgence', urgence);

                const response = await fetch(`../api/admin/conversations-management.php?${params}`);
                
                if (response.status === 401) {
                    window.location.href = 'index.php';
                    return;
                }

                const data = await response.json();

                if (data.success) {
                    conversationsData = data.conversations;
                    displayGlobalStats(data.stats);
                    displayConversations(data.conversations);
                    updatePagination(data.pagination);
                    
                    loading.style.display = 'none';
                    tableContainer.style.display = 'block';
                } else {
                    throw new Error(data.error || 'Erreur inconnue');
                }

            } catch (error) {
                console.error('Erreur:', error);
                loading.innerHTML = `
                    <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #dc3545;"></i>
                    <p style="color: #dc3545;">Erreur de chargement des conversations</p>
                    <button onclick="loadConversations()" class="btn-refresh">Réessayer</button>
                `;
            }
        }

        /**
         * Affiche les statistiques globales
         */
        function displayGlobalStats(stats) {
            const sentimentTotal = stats.sentiment_distribution.positif + 
                                   stats.sentiment_distribution.neutre + 
                                   stats.sentiment_distribution.negatif;
            
            const positifPct = sentimentTotal > 0 
                ? Math.round((stats.sentiment_distribution.positif / sentimentTotal) * 100) 
                : 0;

            document.getElementById('global-stats').innerHTML = `
                <div class="stat-card blue">
                    <div class="stat-icon"><i class="fas fa-comments"></i></div>
                    <div class="stat-info">
                        <h3>${stats.total_conversations.toLocaleString()}</h3>
                        <p>Conversations totales</p>
                        <span class="stat-badge">${stats.unique_users} utilisateurs</span>
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-info">
                        <h3>${stats.total_messages.toLocaleString()}</h3>
                        <p>Messages totaux</p>
                    </div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon"><i class="fas fa-smile"></i></div>
                    <div class="stat-info">
                        <h3>${positifPct}%</h3>
                        <p>Sentiments positifs</p>
                        <span class="stat-badge success">${stats.sentiment_distribution.positif} conversations</span>
                    </div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-info">
                        <h3>${stats.high_urgency_count}</h3>
                        <p>Conversations urgentes</p>
                        <span class="stat-badge danger">Niveau 4-5</span>
                    </div>
                </div>
            `;
        }

        /**
         * Affiche les conversations dans le tableau
         */
        function displayConversations(conversations) {
            const tbody = document.getElementById('conversations-tbody');
            
            if (conversations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="no-data">Aucune conversation trouvée</td></tr>';
                return;
            }

            const sentimentBadge = {
                'positif': 'success',
                'neutre': 'warning',
                'negatif': 'danger'
            };

            tbody.innerHTML = conversations.map(c => `
                <tr>
                    <td><strong>#${c.id}</strong></td>
                    <td class="text-truncate" title="${c.title}">${c.title}</td>
                    <td>
                        <span class="uuid-short" title="${c.anonymous_user_id}">
                            ${c.anonymous_user_id.substring(0, 8)}...
                        </span>
                    </td>
                    <td>${c.message_count}</td>
                    <td>
                        ${c.last_sentiment 
                            ? `<span class="badge ${sentimentBadge[c.last_sentiment]}">${c.last_sentiment}</span>` 
                            : '-'}
                    </td>
                    <td>${c.last_emotion || '-'}</td>
                    <td>
                        ${c.last_urgence 
                            ? `<span class="urgency-badge level-${c.last_urgence}">${c.last_urgence}/5</span>` 
                            : '-'}
                    </td>
                    <td>
                        ${c.last_violence_type 
                            ? `<span class="violence-badge">${c.last_violence_type.replace('_', ' ')}</span>` 
                            : '-'}
                    </td>
                    <td>${formatRelativeDate(c.updated_at)}</td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <button class="btn-action" onclick="viewConversationDetails(${c.id})" title="Voir détails">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-action btn-danger" onclick="deleteConversation(${c.id}, '${c.title}')" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            document.getElementById('total-conv-count').textContent = conversations.length;
        }

        /**
         * Met à jour la pagination
         */
        function updatePagination(pagination) {
            currentPage = pagination.current_page;
            totalPages = pagination.total_pages;

            document.getElementById('page-info').textContent = 
                `Page ${currentPage} / ${totalPages}`;
            
            document.getElementById('prev-page').disabled = currentPage === 1;
            document.getElementById('next-page').disabled = currentPage === totalPages;
        }

        // ========================================
        // NAVIGATION PAGINATION
        // ========================================

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                loadConversations();
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                loadConversations();
            }
        }

        // ========================================
        // RECHERCHE
        // ========================================

        /**
         * Gère la recherche avec debounce
         */
        function handleSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadConversations();
            }, 500);
        }

        // ========================================
        // ACTIONS
        // ========================================

        /**
         * Affiche les détails d'une conversation dans une modal
         */
        async function viewConversationDetails(conversationId) {
            try {
                const response = await fetch(`../api/admin/conversations-management.php?conversation_id=${conversationId}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error);
                }

                const conv = data.conversation;
                const messages = data.messages;
                const emotions = data.emotions;

                // Construire le contenu de la modal
                const content = `
                    <div class="conversation-details">
                        <div class="detail-section">
                            <h4><i class="fas fa-info-circle"></i> Informations générales</h4>
                            <div class="detail-grid">
                                <div><strong>ID:</strong> #${conv.id}</div>
                                <div><strong>Titre:</strong> ${conv.title}</div>
                                <div><strong>Utilisateur:</strong> ${conv.anonymous_user_id}</div>
                                <div><strong>Messages:</strong> ${conv.message_count}</div>
                                <div><strong>Créée le:</strong> ${formatDate(conv.created_at)}</div>
                                <div><strong>Dernière activité:</strong> ${formatDate(conv.updated_at)}</div>
                            </div>
                        </div>

                        ${emotions.violence_types.length > 0 ? `
                            <div class="detail-section alert-section">
                                <h4><i class="fas fa-exclamation-triangle"></i> ⚠️ Violences signalées</h4>
                                <ul>
                                    ${emotions.violence_types.map(v => `
                                        <li><strong>${v.type_violence.replace('_', ' ')}:</strong> ${v.count} fois</li>
                                    `).join('')}
                                </ul>
                            </div>
                        ` : ''}

                        <div class="detail-section">
                            <h4><i class="fas fa-comments"></i> Messages (${messages.length})</h4>
                            <div class="messages-timeline">
                                ${messages.map(m => `
                                    <div class="message-item ${m.role}">
                                        <div class="message-header">
                                            <span class="message-role">
                                                <i class="fas fa-${m.role === 'user' ? 'user' : 'robot'}"></i>
                                                ${m.role === 'user' ? 'Utilisateur' : 'ABSA'}
                                            </span>
                                            <span class="message-time">${formatDate(m.created_at)}</span>
                                        </div>
                                        <div class="message-content">${m.content}</div>
                                        ${m.sentiment ? `
                                            <div class="message-analysis">
                                                <span class="badge ${m.sentiment === 'positif' ? 'success' : m.sentiment === 'negatif' ? 'danger' : 'warning'}">
                                                    ${m.sentiment}
                                                </span>
                                                <span class="emotion-tag">${m.emotion}</span>
                                                ${m.urgence ? `<span class="urgency-badge level-${m.urgence}">${m.urgence}/5</span>` : ''}
                                            </div>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;

                adminUtils.showModal(
                    `Conversation #${conv.id}`,
                    content,
                    [
                        {
                            text: 'Fermer',
                            class: 'btn-primary',
                            onClick: () => {}
                        }
                    ]
                );

            } catch (error) {
                console.error('Erreur:', error);
                adminUtils.showNotification('Erreur de chargement des détails', 'error');
            }
        }

        /**
         * Supprime une conversation
         */
        async function deleteConversation(conversationId, title) {
            adminUtils.confirmAction(
                `Voulez-vous vraiment supprimer la conversation "<strong>${title}</strong>" ?<br><br>⚠️ Cette action est irréversible et supprimera tous les messages et analyses associés.`,
                async () => {
                    try {
                        const response = await fetch(
                            `../api/admin/conversations-management.php?conversation_id=${conversationId}`,
                            { method: 'DELETE' }
                        );
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            adminUtils.showNotification('Conversation supprimée avec succès', 'success');
                            loadConversations();
                        } else {
                            throw new Error(data.error);
                        }
                        
                    } catch (error) {
                        console.error('Erreur:', error);
                        adminUtils.showNotification('Erreur lors de la suppression', 'error');
                    }
                }
            );
        }

        /**
         * Exporte les conversations en CSV
         */
        function exportConversations() {
            if (conversationsData.length === 0) {
                adminUtils.showNotification('Aucune donnée à exporter', 'warning');
                return;
            }

            const csvData = conversationsData.map(c => ({
                'ID': c.id,
                'Titre': c.title,
                'UUID Utilisateur': c.anonymous_user_id,
                'Messages': c.message_count,
                'Sentiment': c.last_sentiment || '-',
                'Émotion': c.last_emotion || '-',
                'Urgence': c.last_urgence || '-',
                'Violence': c.last_violence_type || '-',
                'Créée le': c.created_at,
                'Dernière activité': c.updated_at
            }));

            adminUtils.exportToCSV(csvData, `conversations-${new Date().toISOString().split('T')[0]}.csv`);
        }

        /**
         * Formate une date
         */
        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        /**
         * Formate une date relative
         */
        function formatRelativeDate(dateStr) {
            return adminUtils.formatRelativeDate(dateStr);
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

        // ========================================
        // INITIALISATION
        // ========================================

        window.addEventListener('DOMContentLoaded', loadConversations);
    </script>

    <style>
        /* Styles supplémentaires spécifiques à cette page */
        
        .violence-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            text-transform: capitalize;
        }

        .btn-danger {
            background: #dc3545 !important;
        }

        .btn-danger:hover {
            background: #c82333 !important;
        }

        /* Styles modal conversation */
        .conversation-details {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .alert-section {
            background: rgba(220, 53, 69, 0.05);
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 8px;
        }

        .alert-section ul {
            margin: 10px 0 0 20px;
            color: #721c24;
        }

        .messages-timeline {
            max-height: 400px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message-item {
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }

        .message-item.user {
            background: #f0f0ff;
            border-left-color: #4b3795;
        }

        .message-item.assistant {
            background: #f0fff0;
            border-left-color: #28a745;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .message-role {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .message-time {
            color: #999;
            font-size: 12px;
        }

        .message-content {
            margin: 10px 0;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .message-analysis {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .emotion-tag {
            padding: 4px 10px;
            background: #e0e0e0;
            border-radius: 12px;
            font-size: 12px;
            text-transform: capitalize;
        }

        .filters-bar {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    gap: 20px;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.search-box input {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary);
}

.filter-group {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group label {
    font-weight: 500;
    color: #666;
    font-size: 14px;
    white-space: nowrap;
}

.filter-group select {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    background: white;
}

.filter-group select:focus {
    outline: none;
    border-color: var(--primary);
}

@media (max-width: 768px) {
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-group {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-group label {
        margin-top: 10px;
    }
}

        @media (max-width: 768px) {
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group label {
                margin-top: 10px;
            }
        }
    </style>
</body>
</html>