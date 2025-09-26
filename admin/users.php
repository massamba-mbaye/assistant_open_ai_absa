<?php
/**
 * PAGE GESTION UTILISATEURS
 * Liste et détails des utilisateurs avec analyses émotionnelles
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
    <title>Utilisateurs - ABSA Back-Office</title>
    
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
            <a href="users.php" class="nav-item active">
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
                <h1>Gestion des Utilisateurs</h1>
                <p class="subtitle">Vue d'ensemble et analyse des utilisateurs</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="exportUsers()" class="btn-secondary">
                    <i class="fas fa-download"></i>
                    Exporter CSV
                </button>
                <button onclick="loadUsers()" class="btn-refresh">
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
                    placeholder="Rechercher par UUID..."
                    onkeyup="handleSearch()"
                >
            </div>

            <div class="filter-group">
                <label>Trier par :</label>
                <select id="sort-by" onchange="loadUsers()">
                    <option value="last_activity">Dernière activité</option>
                    <option value="total_conversations">Nb conversations</option>
                    <option value="total_messages">Nb messages</option>
                    <option value="first_seen">Premier contact</option>
                </select>

                <select id="sort-order" onchange="loadUsers()">
                    <option value="DESC">↓ Décroissant</option>
                    <option value="ASC">↑ Croissant</option>
                </select>
            </div>
        </div>

        <!-- Loading -->
        <div id="loading" class="loading-container">
            <div class="spinner"></div>
            <p>Chargement des utilisateurs...</p>
        </div>

        <!-- Tableau utilisateurs -->
        <div id="users-table-container" class="chart-card" style="display: none;">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Liste des utilisateurs (<span id="total-users-count">0</span>)</h3>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>UUID</th>
                                <th>Conversations</th>
                                <th>Messages</th>
                                <th>Dernière conversation</th>
                                <th>Sentiment</th>
                                <th>Urgence</th>
                                <th>Première vue</th>
                                <th>Dernière activité</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody">
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
        let usersData = [];

        // ========================================
        // CHARGEMENT DES UTILISATEURS
        // ========================================

        /**
         * Charge la liste des utilisateurs
         */
        async function loadUsers() {
            const loading = document.getElementById('loading');
            const tableContainer = document.getElementById('users-table-container');
            
            loading.style.display = 'flex';
            tableContainer.style.display = 'none';

            try {
                const sortBy = document.getElementById('sort-by').value;
                const sortOrder = document.getElementById('sort-order').value;
                const search = document.getElementById('search-input').value;

                const params = new URLSearchParams({
                    page: currentPage,
                    limit: 20,
                    sort_by: sortBy,
                    sort_order: sortOrder
                });

                if (search) {
                    params.append('search', search);
                }

                const response = await fetch(`../api/admin/users-management.php?${params}`);
                
                if (response.status === 401) {
                    window.location.href = 'index.php';
                    return;
                }

                const data = await response.json();

                if (data.success) {
                    usersData = data.users;
                    displayGlobalStats(data.global_stats);
                    displayUsers(data.users);
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
                    <p style="color: #dc3545;">Erreur de chargement des utilisateurs</p>
                    <button onclick="loadUsers()" class="btn-refresh">Réessayer</button>
                `;
            }
        }

        /**
         * Affiche les statistiques globales
         */
        function displayGlobalStats(stats) {
            document.getElementById('global-stats').innerHTML = `
                <div class="stat-card blue">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3>${stats.total_users.toLocaleString()}</h3>
                        <p>Utilisateurs totaux</p>
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon"><i class="fas fa-comments"></i></div>
                    <div class="stat-info">
                        <h3>${stats.total_conversations.toLocaleString()}</h3>
                        <p>Conversations totales</p>
                    </div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-info">
                        <h3>${stats.total_messages.toLocaleString()}</h3>
                        <p>Messages totaux</p>
                    </div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="stat-info">
                        <h3>${stats.avg_messages_per_user}</h3>
                        <p>Moyenne msgs/user</p>
                    </div>
                </div>
            `;
        }

        /**
         * Affiche les utilisateurs dans le tableau
         */
        function displayUsers(users) {
            const tbody = document.getElementById('users-tbody');
            
            if (users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">Aucun utilisateur trouvé</td></tr>';
                return;
            }

            const sentimentBadge = {
                'positif': 'success',
                'neutre': 'warning',
                'negatif': 'danger'
            };

            tbody.innerHTML = users.map(u => `
                <tr>
                    <td>
                        <span class="uuid-short" title="${u.anonymous_user_id}">
                            ${u.anonymous_user_id.substring(0, 8)}...
                        </span>
                        <button class="btn-icon" onclick="copyUUID('${u.anonymous_user_id}')" title="Copier UUID">
                            <i class="fas fa-copy"></i>
                        </button>
                    </td>
                    <td><strong>${u.total_conversations}</strong></td>
                    <td>${u.total_messages}</td>
                    <td class="text-truncate" title="${u.last_conversation_title || '-'}">${u.last_conversation_title || '-'}</td>
                    <td>
                        ${u.last_sentiment 
                            ? `<span class="badge ${sentimentBadge[u.last_sentiment]}">${u.last_sentiment}</span>` 
                            : '-'}
                    </td>
                    <td>
                        ${u.last_urgence 
                            ? `<span class="urgency-badge level-${u.last_urgence}">${u.last_urgence}/5</span>` 
                            : '-'}
                        ${u.urgent_conversations_count > 0 
                            ? `<span class="badge danger" style="margin-left: 5px;">${u.urgent_conversations_count} urgentes</span>` 
                            : ''}
                    </td>
                    <td>${formatRelativeDate(u.first_seen)}</td>
                    <td>${formatRelativeDate(u.last_activity)}</td>
                    <td>
                        <button class="btn-action" onclick="viewUserDetails('${u.anonymous_user_id}')" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('total-users-count').textContent = users.length;
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
                loadUsers();
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                loadUsers();
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
                currentPage = 1; // Reset à la page 1
                loadUsers();
            }, 500); // 500ms de délai
        }

        // ========================================
        // ACTIONS
        // ========================================

        /**
         * Copie l'UUID dans le presse-papier
         */
        async function copyUUID(uuid) {
            await adminUtils.copyToClipboard(uuid, 'UUID copié !');
        }

        /**
         * Affiche les détails d'un utilisateur dans une modal
         */
        async function viewUserDetails(userId) {
            try {
                const response = await fetch(`../api/admin/users-management.php?user_id=${userId}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error);
                }

                const user = data.user;
                const emotions = data.emotions;

                // Construire le contenu de la modal
                const content = `
                    <div class="user-details">
                        <div class="detail-section">
                            <h4><i class="fas fa-info-circle"></i> Informations générales</h4>
                            <div class="detail-grid">
                                <div><strong>UUID:</strong> ${user.id}</div>
                                <div><strong>Conversations:</strong> ${user.total_conversations}</div>
                                <div><strong>Messages totaux:</strong> ${user.total_messages}</div>
                                <div><strong>Messages utilisateur:</strong> ${user.user_messages}</div>
                                <div><strong>Première vue:</strong> ${formatDate(user.first_seen)}</div>
                                <div><strong>Dernière activité:</strong> ${formatDate(user.last_activity)}</div>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h4><i class="fas fa-heart"></i> Analyse émotionnelle</h4>
                            <div class="emotions-summary">
                                <div><strong>Urgence moyenne:</strong> ${emotions.urgence_stats.average}/5</div>
                                <div><strong>Urgence max:</strong> ${emotions.urgence_stats.max}/5</div>
                                <div><strong>Conversations urgentes:</strong> ${emotions.urgence_stats.high_urgency_count}</div>
                            </div>
                            ${emotions.violence_types.length > 0 ? `
                                <div style="margin-top: 15px;">
                                    <strong>⚠️ Violences signalées:</strong>
                                    <ul style="margin-top: 10px;">
                                        ${emotions.violence_types.map(v => `
                                            <li>${v.type_violence.replace('_', ' ')} (${v.count} fois)</li>
                                        `).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>

                        <div class="detail-section">
                            <h4><i class="fas fa-comments"></i> Conversations (${data.conversations.length})</h4>
                            <div class="conversations-list">
                                ${data.conversations.slice(0, 5).map(c => `
                                    <div class="conv-item">
                                        <strong>${c.title}</strong>
                                        <span>${c.message_count} messages</span>
                                        ${c.last_sentiment ? `<span class="badge ${c.last_sentiment === 'positif' ? 'success' : c.last_sentiment === 'negatif' ? 'danger' : 'warning'}">${c.last_sentiment}</span>` : ''}
                                    </div>
                                `).join('')}
                                ${data.conversations.length > 5 ? `<p style="margin-top: 10px; color: #666;">... et ${data.conversations.length - 5} autres</p>` : ''}
                            </div>
                        </div>
                    </div>
                `;

                adminUtils.showModal(
                    `Détails utilisateur`,
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
         * Exporte les utilisateurs en CSV
         */
        function exportUsers() {
            if (usersData.length === 0) {
                adminUtils.showNotification('Aucune donnée à exporter', 'warning');
                return;
            }

            const csvData = usersData.map(u => ({
                'UUID': u.anonymous_user_id,
                'Conversations': u.total_conversations,
                'Messages': u.total_messages,
                'Dernière conversation': u.last_conversation_title || '-',
                'Sentiment': u.last_sentiment || '-',
                'Urgence': u.last_urgence || '-',
                'Première vue': u.first_seen,
                'Dernière activité': u.last_activity
            }));

            adminUtils.exportToCSV(csvData, `utilisateurs-${new Date().toISOString().split('T')[0]}.csv`);
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

        window.addEventListener('DOMContentLoaded', loadUsers);
    </script>

    <style>
        /* Styles supplémentaires spécifiques à cette page */
        
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
        }

        .filter-group label {
            font-weight: 500;
            color: #666;
            font-size: 14px;
        }

        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            background: white;
        }

        .uuid-short {
            font-family: monospace;
            color: #666;
        }

        .btn-icon {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 5px;
            margin-left: 5px;
            transition: color 0.3s;
        }

        .btn-icon:hover {
            color: var(--primary);
        }

        .btn-action {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-action:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-secondary {
            padding: 10px 20px;
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
        }

        .urgency-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .urgency-badge.level-1,
        .urgency-badge.level-2 {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .urgency-badge.level-3 {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .urgency-badge.level-4,
        .urgency-badge.level-5 {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 25px;
            padding: 20px;
        }

        .pagination button {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .pagination button:hover:not(:disabled) {
            background: var(--primary-hover);
        }

        .pagination button:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* Styles pour la modal de détails */
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .detail-section h4 {
            margin-bottom: 15px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            font-size: 14px;
        }

        .emotions-summary {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
        }

        .conversations-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .conv-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-wrap: wrap;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>