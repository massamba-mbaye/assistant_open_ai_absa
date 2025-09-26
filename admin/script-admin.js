/**
 * SCRIPT ADMIN GLOBAL
 * Fonctions utilitaires réutilisables pour le back-office
 */

// ========================================
// CONFIGURATION GLOBALE
// ========================================

const API_BASE = '../api/admin/';
const SESSION_TIMEOUT = 7200000; // 2 heures en millisecondes

// ========================================
// GESTION DE LA SESSION
// ========================================

/**
 * Vérifie périodiquement si la session est toujours active
 */
function checkSessionPeriodically() {
    setInterval(async () => {
        try {
            const response = await fetch('../api/admin/auth.php?action=check');
            const data = await response.json();
            
            if (!data.authenticated) {
                alert('Votre session a expiré. Vous allez être redirigé vers la page de connexion.');
                window.location.href = 'index.php';
            }
        } catch (error) {
            console.error('Erreur vérification session:', error);
        }
    }, 300000); // Vérifier toutes les 5 minutes
}

// Démarrer la vérification au chargement
window.addEventListener('DOMContentLoaded', checkSessionPeriodically);

// ========================================
// NAVIGATION MOBILE
// ========================================

/**
 * Toggle sidebar sur mobile
 */
function initMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    
    // Créer le bouton burger s'il n'existe pas
    if (window.innerWidth <= 768 && !document.getElementById('mobile-menu-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'mobile-menu-toggle';
        toggleBtn.className = 'mobile-menu-btn';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleBtn.style.cssText = `
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            cursor: pointer;
            display: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        `;
        
        document.body.appendChild(toggleBtn);
        
        // Afficher sur mobile
        if (window.innerWidth <= 768) {
            toggleBtn.style.display = 'block';
        }
        
        // Toggle sidebar
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            const icon = toggleBtn.querySelector('i');
            icon.className = sidebar.classList.contains('active') 
                ? 'fas fa-times' 
                : 'fas fa-bars';
        });
        
        // Fermer en cliquant sur un lien
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    toggleBtn.querySelector('i').className = 'fas fa-bars';
                }
            });
        });
        
        // Fermer en cliquant en dehors
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 
                && sidebar.classList.contains('active')
                && !sidebar.contains(e.target)
                && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
                toggleBtn.querySelector('i').className = 'fas fa-bars';
            }
        });
    }
}

// Initialiser au chargement et au resize
window.addEventListener('DOMContentLoaded', initMobileMenu);
window.addEventListener('resize', initMobileMenu);

// ========================================
// REQUÊTES API
// ========================================

/**
 * Effectue une requête API sécurisée avec gestion d'erreurs
 * 
 * @param {string} endpoint - Endpoint API (ex: 'stats.php')
 * @param {object} options - Options fetch (method, body, etc.)
 * @returns {Promise<object>} Réponse JSON
 */
async function apiRequest(endpoint, options = {}) {
    try {
        const response = await fetch(API_BASE + endpoint, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        // Redirection si non authentifié
        if (response.status === 401) {
            window.location.href = 'index.php';
            return null;
        }
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `Erreur HTTP: ${response.status}`);
        }
        
        return data;
        
    } catch (error) {
        console.error('Erreur API:', error);
        showNotification('Erreur: ' + error.message, 'error');
        throw error;
    }
}

// ========================================
// NOTIFICATIONS/TOASTS
// ========================================

/**
 * Affiche une notification toast
 * 
 * @param {string} message - Message à afficher
 * @param {string} type - Type: 'success', 'error', 'warning', 'info'
 * @param {number} duration - Durée en ms (défaut: 3000)
 */
function showNotification(message, type = 'info', duration = 3000) {
    // Créer le conteneur s'il n'existe pas
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(container);
    }
    
    // Créer la notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notification.innerHTML = `
        <i class="fas fa-${icons[type]}"></i>
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    notification.style.cssText = `
        background: white;
        color: #212529;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        max-width: 400px;
        border-left: 4px solid ${colors[type]};
        animation: slideIn 0.3s ease-out;
    `;
    
    // Icône
    const icon = notification.querySelector('i');
    icon.style.cssText = `
        font-size: 20px;
        color: ${colors[type]};
    `;
    
    // Bouton fermer
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #999;
        margin-left: auto;
    `;
    
    closeBtn.addEventListener('click', () => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    });
    
    // Ajouter au conteneur
    container.appendChild(notification);
    
    // Auto-suppression
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, duration);
}

// Styles d'animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    .notification:hover {
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }
    
    .notification-close:hover {
        color: #333;
    }
`;
document.head.appendChild(style);

// ========================================
// MODAL GÉNÉRIQUE
// ========================================

/**
 * Affiche une modal générique
 * 
 * @param {string} title - Titre de la modal
 * @param {string} content - Contenu HTML
 * @param {array} buttons - Tableau de boutons [{text, onClick, class}]
 */
function showModal(title, content, buttons = []) {
    // Créer l'overlay
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s;
    `;
    
    // Créer la modal
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.cssText = `
        background: white;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow: auto;
        animation: scaleIn 0.3s;
    `;
    
    // Header
    const header = document.createElement('div');
    header.style.cssText = `
        padding: 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    `;
    header.innerHTML = `
        <h3 style="margin: 0; font-size: 20px;">${title}</h3>
        <button class="modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
    `;
    
    // Body
    const body = document.createElement('div');
    body.style.cssText = 'padding: 20px;';
    body.innerHTML = content;
    
    // Footer avec boutons
    const footer = document.createElement('div');
    footer.style.cssText = `
        padding: 15px 20px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    `;
    
    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.textContent = btn.text;
        button.className = btn.class || 'btn-primary';
        button.style.cssText = `
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        `;
        
        if (btn.class === 'btn-danger') {
            button.style.background = '#dc3545';
            button.style.color = 'white';
        } else {
            button.style.background = '#4b3795';
            button.style.color = 'white';
        }
        
        button.addEventListener('click', () => {
            if (btn.onClick) btn.onClick();
            closeModal();
        });
        
        footer.appendChild(button);
    });
    
    // Assembler
    modal.appendChild(header);
    modal.appendChild(body);
    if (buttons.length > 0) {
        modal.appendChild(footer);
    }
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Fermeture
    function closeModal() {
        overlay.style.animation = 'fadeOut 0.3s';
        setTimeout(() => overlay.remove(), 300);
    }
    
    header.querySelector('.modal-close').addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });
}

// Animations modal
const modalStyle = document.createElement('style');
modalStyle.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0.9);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
`;
document.head.appendChild(modalStyle);

// ========================================
// FORMATAGE DE DONNÉES
// ========================================

/**
 * Formate une date relative (il y a X minutes/heures/jours)
 * 
 * @param {string} dateStr - Date au format ISO
 * @returns {string} Date formatée
 */
function formatRelativeDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);
    
    if (diffMin < 1) return 'À l\'instant';
    if (diffMin < 60) return `Il y a ${diffMin} min`;
    if (diffHour < 24) return `Il y a ${diffHour}h`;
    if (diffDay < 7) return `Il y a ${diffDay}j`;
    
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Formate un nombre avec séparateur de milliers
 * 
 * @param {number} num - Nombre à formater
 * @returns {string} Nombre formaté
 */
function formatNumber(num) {
    return num.toLocaleString('fr-FR');
}

/**
 * Tronque un texte avec ellipse
 * 
 * @param {string} text - Texte à tronquer
 * @param {number} maxLength - Longueur maximale
 * @returns {string} Texte tronqué
 */
function truncateText(text, maxLength = 50) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

// ========================================
// COPIER DANS LE PRESSE-PAPIER
// ========================================

/**
 * Copie du texte dans le presse-papier
 * 
 * @param {string} text - Texte à copier
 * @param {string} successMessage - Message de succès
 */
async function copyToClipboard(text, successMessage = 'Copié !') {
    try {
        await navigator.clipboard.writeText(text);
        showNotification(successMessage, 'success', 2000);
    } catch (error) {
        console.error('Erreur copie:', error);
        showNotification('Erreur lors de la copie', 'error');
    }
}

// ========================================
// EXPORT CSV
// ========================================

/**
 * Exporte des données en CSV
 * 
 * @param {array} data - Tableau d'objets
 * @param {string} filename - Nom du fichier
 */
function exportToCSV(data, filename = 'export.csv') {
    if (data.length === 0) {
        showNotification('Aucune donnée à exporter', 'warning');
        return;
    }
    
    // Créer les headers
    const headers = Object.keys(data[0]);
    const csvContent = [
        headers.join(','),
        ...data.map(row => 
            headers.map(header => 
                JSON.stringify(row[header] || '')
            ).join(',')
        )
    ].join('\n');
    
    // Télécharger
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    
    showNotification('Export CSV réussi', 'success');
}

// ========================================
// CONFIRMATION
// ========================================

/**
 * Affiche une confirmation avant action
 * 
 * @param {string} message - Message de confirmation
 * @param {function} onConfirm - Callback si confirmé
 */
function confirmAction(message, onConfirm) {
    showModal(
        'Confirmation',
        `<p>${message}</p>`,
        [
            {
                text: 'Annuler',
                class: 'btn-secondary',
                onClick: () => {}
            },
            {
                text: 'Confirmer',
                class: 'btn-danger',
                onClick: onConfirm
            }
        ]
    );
}

// ========================================
// EXPORT DES FONCTIONS
// ========================================

window.adminUtils = {
    apiRequest,
    showNotification,
    showModal,
    formatRelativeDate,
    formatNumber,
    truncateText,
    copyToClipboard,
    exportToCSV,
    confirmAction
};

console.log('✅ Script admin chargé');