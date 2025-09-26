<?php
/**
 * PAGE ANALYSE ÉMOTIONS
 * Dashboard d'analyse émotionnelle et sentiments
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
    <title>Analyse Émotions - ABSA Back-Office</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style-admin.css">
    
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            <a href="conversations.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Conversations</span>
            </a>
            <a href="emotions.php" class="nav-item active">
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
                <h1>Analyse des Émotions</h1>
                <p class="subtitle">Suivi émotionnel et détection de détresse</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <label style="font-weight: 500; color: #666;">Période :</label>
                <select id="period-select" onchange="loadEmotionsData()" style="padding: 10px; border-radius: 6px; border: 2px solid #e0e0e0;">
                    <option value="7">7 derniers jours</option>
                    <option value="30">30 derniers jours</option>
                    <option value="all">Toute la période</option>
                </select>
                <button onclick="loadEmotionsData()" class="btn-refresh">
                    <i class="fas fa-sync-alt"></i>
                    Actualiser
                </button>
            </div>
        </header>

        <!-- Loading -->
        <div id="loading" class="loading-container">
            <div class="spinner"></div>
            <p>Chargement des analyses émotionnelles...</p>
        </div>

        <!-- Content -->
        <div id="content" class="dashboard-content" style="display: none;">
            
            <!-- ========================================
                 STATISTIQUES PRINCIPALES
                 ======================================== -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-analyses">0</h3>
                        <p>Analyses effectuées</p>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="avg-urgence">0</h3>
                        <p>Urgence moyenne</p>
                        <span class="stat-badge" id="urgence-badge">Sur 5</span>
                    </div>
                </div>

                <div class="stat-card orange">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="high-urgency">0</h3>
                        <p>Cas urgents (≥4)</p>
                        <span class="stat-badge danger">À surveiller</span>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="violence-count">0</h3>
                        <p>Violences signalées</p>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 GRAPHIQUES PRINCIPAUX
                 ======================================== -->
            <div class="charts-grid">
                <!-- Sentiments -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Répartition des sentiments</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="sentiments-chart" height="200"></canvas>
                    </div>
                </div>

                <!-- Émotions -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-heart"></i> Top 10 Émotions</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="emotions-chart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 URGENCE & VIOLENCES
                 ======================================== -->
            <div class="charts-grid">
                <!-- Distribution urgence -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-tachometer-alt"></i> Distribution de l'urgence</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="urgence-chart" height="200"></canvas>
                    </div>
                </div>

                <!-- Types de violences -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-circle"></i> Types de violences signalées</h3>
                    </div>
                    <div class="card-body">
                        <div id="violence-list">
                            <!-- Généré dynamiquement -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 ÉVOLUTION TEMPORELLE
                 ======================================== -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-area"></i> Évolution des sentiments (30 derniers jours)</h3>
                </div>
                <div class="card-body">
                    <canvas id="daily-evolution-chart" height="100"></canvas>
                </div>
            </div>

        </div>
    </main>

    <script src="script-admin.js"></script>
    <script>
        // ========================================
        // VARIABLES GLOBALES
        // ========================================

        let chartsInstances = {
            sentiments: null,
            emotions: null,
            urgence: null,
            evolution: null
        };

        // ========================================
        // CHARGEMENT DES DONNÉES
        // ========================================

        /**
         * Charge les données d'analyse émotionnelle
         */
        async function loadEmotionsData() {
            const loading = document.getElementById('loading');
            const content = document.getElementById('content');
            
            loading.style.display = 'flex';
            content.style.display = 'none';

            try {
                const period = document.getElementById('period-select').value;
                const response = await fetch(`../api/admin/emotions-analysis.php?period=${period}`);
                
                if (response.status === 401) {
                    window.location.href = 'index.php';
                    return;
                }

                const data = await response.json();

                if (data.success) {
                    displayStats(data.stats);
                    displaySentimentsChart(data.sentiments);
                    displayEmotionsChart(data.emotions);
                    displayUrgenceChart(data.urgence_distribution);
                    displayViolenceList(data.violence_types);
                    displayDailyEvolution(data.daily_evolution);
                    
                    loading.style.display = 'none';
                    content.style.display = 'block';
                } else {
                    throw new Error(data.error || 'Erreur inconnue');
                }

            } catch (error) {
                console.error('Erreur:', error);
                loading.innerHTML = `
                    <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #dc3545;"></i>
                    <p style="color: #dc3545;">Erreur de chargement des analyses</p>
                    <button onclick="loadEmotionsData()" class="btn-refresh">Réessayer</button>
                `;
            }
        }

        /**
         * Affiche les statistiques principales
         */
        function displayStats(stats) {
            document.getElementById('total-analyses').textContent = stats.total_analyses.toLocaleString();
            document.getElementById('avg-urgence').textContent = stats.avg_urgence;
            document.getElementById('high-urgency').textContent = stats.high_urgency_count;
            document.getElementById('violence-count').textContent = stats.violence_count;

            // Couleur badge urgence
            const badge = document.getElementById('urgence-badge');
            if (stats.avg_urgence >= 4) {
                badge.className = 'stat-badge danger';
            } else if (stats.avg_urgence >= 3) {
                badge.className = 'stat-badge warning';
            } else {
                badge.className = 'stat-badge';
            }
        }

        /**
         * Affiche le graphique des sentiments (Pie Chart)
         */
        function displaySentimentsChart(sentiments) {
            const ctx = document.getElementById('sentiments-chart').getContext('2d');
            
            // Détruire l'ancien graphique si existe
            if (chartsInstances.sentiments) {
                chartsInstances.sentiments.destroy();
            }

            const colors = {
                'positif': '#28a745',
                'neutre': '#ffc107',
                'negatif': '#dc3545'
            };

            const labels = sentiments.map(s => s.sentiment.charAt(0).toUpperCase() + s.sentiment.slice(1));
            const data = sentiments.map(s => s.count);
            const backgroundColors = sentiments.map(s => colors[s.sentiment]);

            chartsInstances.sentiments = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 14 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const sentiment = sentiments[context.dataIndex];
                                    return `${context.label}: ${sentiment.count} (${sentiment.percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Affiche le graphique des émotions (Bar Chart)
         */
        function displayEmotionsChart(emotions) {
            const ctx = document.getElementById('emotions-chart').getContext('2d');
            
            if (chartsInstances.emotions) {
                chartsInstances.emotions.destroy();
            }

            const labels = emotions.map(e => e.emotion.charAt(0).toUpperCase() + e.emotion.slice(1));
            const data = emotions.map(e => e.count);

            chartsInstances.emotions = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre d\'occurrences',
                        data: data,
                        backgroundColor: '#6f42c1',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        /**
         * Affiche le graphique de distribution de l'urgence
         */
        function displayUrgenceChart(urgenceData) {
            const ctx = document.getElementById('urgence-chart').getContext('2d');
            
            if (chartsInstances.urgence) {
                chartsInstances.urgence.destroy();
            }

            // Couleurs selon niveau
            const colors = ['#28a745', '#90ee90', '#ffc107', '#ff8c00', '#dc3545'];

            const labels = urgenceData.map(u => `Niveau ${u.urgence}`);
            const data = urgenceData.map(u => u.count);
            const backgroundColors = urgenceData.map(u => colors[u.urgence - 1]);

            chartsInstances.urgence = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre de messages',
                        data: data,
                        backgroundColor: backgroundColors,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        /**
         * Affiche la liste des types de violences
         */
        function displayViolenceList(violences) {
            const container = document.getElementById('violence-list');
            
            if (violences.length === 0) {
                container.innerHTML = '<p class="no-data">✅ Aucune violence signalée sur cette période</p>';
                return;
            }

            container.innerHTML = violences.map(v => `
                <div class="violence-item-emotion">
                    <div class="violence-info">
                        <i class="fas fa-exclamation-circle"></i>
                        <span class="violence-type">${v.type_violence.replace('_', ' ')}</span>
                    </div>
                    <span class="violence-count-badge">${v.count} cas</span>
                </div>
            `).join('');
        }

        /**
         * Affiche l'évolution quotidienne des sentiments
         */
        function displayDailyEvolution(dailyData) {
            const ctx = document.getElementById('daily-evolution-chart').getContext('2d');
            
            if (chartsInstances.evolution) {
                chartsInstances.evolution.destroy();
            }

            // Organiser les données par date et sentiment
            const dates = [...new Set(dailyData.map(d => d.date))].sort();
            
            const positifData = dates.map(date => {
                const item = dailyData.find(d => d.date === date && d.sentiment === 'positif');
                return item ? item.count : 0;
            });

            const neutreData = dates.map(date => {
                const item = dailyData.find(d => d.date === date && d.sentiment === 'neutre');
                return item ? item.count : 0;
            });

            const negatifData = dates.map(date => {
                const item = dailyData.find(d => d.date === date && d.sentiment === 'negatif');
                return item ? item.count : 0;
            });

            // Formater les dates
            const formattedDates = dates.map(d => {
                const date = new Date(d);
                return `${date.getDate()}/${date.getMonth() + 1}`;
            });

            chartsInstances.evolution = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: formattedDates,
                    datasets: [
                        {
                            label: 'Positif',
                            data: positifData,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Neutre',
                            data: neutreData,
                            borderColor: '#ffc107',
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Négatif',
                            data: negatifData,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
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

        window.addEventListener('DOMContentLoaded', loadEmotionsData);
    </script>

    <style>
        /* Styles supplémentaires pour cette page */
        
        .violence-item-emotion {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(220, 53, 69, 0.05);
            border-left: 4px solid #dc3545;
            border-radius: 8px;
            margin-bottom: 12px;
            transition: transform 0.2s;
        }

        .violence-item-emotion:hover {
            transform: translateX(5px);
        }

        .violence-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .violence-info i {
            color: #dc3545;
            font-size: 20px;
        }

        .violence-type {
            font-weight: 500;
            text-transform: capitalize;
            font-size: 15px;
        }

        .violence-count-badge {
            background: #dc3545;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .stat-badge.warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        #violence-list {
            max-height: 350px;
            overflow-y: auto;
        }

        /* Scrollbar pour la liste des violences */
        #violence-list::-webkit-scrollbar {
            width: 6px;
        }

        #violence-list::-webkit-scrollbar-track {
            background: var(--bg);
        }

        #violence-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

        canvas {
            max-height: 300px;
        }
    </style>
</body>
</html>