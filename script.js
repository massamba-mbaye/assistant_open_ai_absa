// ========================================
// VARIABLES GLOBALES
// ========================================
const chatMessages = document.getElementById('chat-messages');
const userInput = document.getElementById('user-input');
const sendButton = document.getElementById('send-button');
const typingIndicator = document.getElementById('typing-indicator');
const ITEMS_PER_PAGE = 10;

let isProcessing = false;
let currentConversationId = null;
let currentPage = 1;
let searchQuery = '';
let userId = null; // UUID de l'utilisateur

// ========================================
// INITIALISATION - GÉNÉRATION UUID
// ========================================

/**
 * Génère ou récupère l'UUID de l'utilisateur
 * Stocké dans localStorage pour persistance
 */
function getOrCreateUserId() {
    let storedUserId = localStorage.getItem('user_id');
    
    if (!storedUserId) {
        // Générer un UUID v4
        storedUserId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
        
        localStorage.setItem('user_id', storedUserId);
        console.log('✅ Nouvel utilisateur créé:', storedUserId);
    } else {
        console.log('✅ Utilisateur existant:', storedUserId);
    }
    
    return storedUserId;
}

// Initialiser l'userId au chargement
userId = getOrCreateUserId();

// ========================================
// CONFIGURATION MARKDOWN
// ========================================
marked.setOptions({
    breaks: true,
    highlight: (code) => hljs.highlightAuto(code).value
});

// ========================================
// AFFICHAGE DES MESSAGES
// ========================================

/**
 * Affiche un message dans le chat
 * @param {string} content - Contenu du message
 * @param {boolean} isUser - true si message utilisateur
 */
function addMessage(content, isUser) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isUser ? 'user-message' : 'bot-message'}`;
    
    // Traitement Markdown et sécurité
    const processedContent = isUser 
        ? DOMPurify.sanitize(content)
        : DOMPurify.sanitize(marked.parse(content));

    messageDiv.innerHTML = `
        <div class="message-content">${processedContent}</div>
        ${!isUser ? `
            <button class="copy-btn">
                <i class="far fa-copy"></i>
            </button>
        ` : ''}
    `;

    // Gestion de la copie pour les messages bot
    if (!isUser) {
        const copyBtn = messageDiv.querySelector('.copy-btn');
        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(content);
                copyBtn.innerHTML = '<i class="fas fa-check"></i>';
                copyBtn.classList.add('copy-success');
                
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="far fa-copy"></i>';
                    copyBtn.classList.remove('copy-success');
                }, 2000);
            } catch (err) {
                console.error('Échec de la copie:', err);
            }
        });
    }

    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// ========================================
// GESTION DES CONVERSATIONS (API)
// ========================================

/**
 * Charge les conversations depuis l'API
 */
async function loadConversationsFromAPI() {
    try {
        const response = await fetch(`./api/conversations.php?user_id=${userId}&page=${currentPage}&limit=${ITEMS_PER_PAGE}`);
        
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            updateConversationList(data.conversations, data.pagination);
        } else {
            console.error('Erreur API:', data.error);
        }
        
    } catch (error) {
        console.error('Erreur chargement conversations:', error);
    }
}

/**
 * Charge les messages d'une conversation depuis l'API
 * @param {number} conversationId - ID de la conversation
 */
async function loadConversation(conversationId) {
    try {
        const response = await fetch(`./api/messages.php?conversation_id=${conversationId}`);
        
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            chatMessages.innerHTML = '';
            
            data.messages.forEach(msg => {
                addMessage(msg.content, msg.role === 'user');
            });
            
            currentConversationId = conversationId;
            
            // Mettre à jour l'état actif dans la sidebar
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.toggle('active', item.dataset.id === conversationId.toString());
            });
        } else {
            console.error('Erreur API:', data.error);
        }
        
    } catch (error) {
        console.error('Erreur chargement messages:', error);
        addMessage("**Erreur** : Impossible de charger la conversation", false);
    }
}

/**
 * Supprime une conversation
 * @param {number} conversationId - ID de la conversation
 */
async function deleteConversation(conversationId) {
    if (!confirm('Supprimer cette conversation ?')) {
        return;
    }
    
    try {
        const response = await fetch(`./api/conversations.php?id=${conversationId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Si c'était la conversation active, vider le chat
            if (currentConversationId === conversationId) {
                currentConversationId = null;
                chatMessages.innerHTML = '';
                addMessage("Bonjour ! Je suis votre assistant spécialisé. Posez-moi vos questions.", false);
            }
            
            // Recharger la liste
            loadConversationsFromAPI();
        } else {
            alert('Erreur: ' + data.error);
        }
        
    } catch (error) {
        console.error('Erreur suppression:', error);
        alert('Erreur lors de la suppression');
    }
}

/**
 * Modifie le titre d'une conversation
 * @param {number} conversationId - ID de la conversation
 * @param {string} newTitle - Nouveau titre
 */
async function updateConversationTitle(conversationId, newTitle) {
    try {
        const response = await fetch('./api/conversations.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: conversationId,
                title: newTitle
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadConversationsFromAPI();
        } else {
            alert('Erreur: ' + data.error);
        }
        
    } catch (error) {
        console.error('Erreur mise à jour titre:', error);
        alert('Erreur lors de la mise à jour');
    }
}

// ========================================
// AFFICHAGE DE LA LISTE DES CONVERSATIONS
// ========================================

/**
 * Met à jour l'affichage de la liste des conversations
 * @param {Array} conversations - Liste des conversations
 * @param {Object} pagination - Infos de pagination
 */
function updateConversationList(conversations, pagination) {
    const list = document.getElementById('conversation-list');
    const template = document.getElementById('conversation-template');
    
    if (!template) {
        console.error('Template conversation introuvable');
        return;
    }

    list.innerHTML = '';

    conversations.forEach(conv => {
        const clone = template.content.cloneNode(true);
        const item = clone.querySelector('.conversation-item');
        
        // Remplir les données
        item.dataset.id = conv.id;
        clone.querySelector('.editable-title').textContent = conv.title;
        clone.querySelector('.conversation-preview').textContent = conv.preview || 'Aucun message';
        clone.querySelector('.conversation-date').textContent = new Date(conv.updated_at).toLocaleDateString();
        clone.querySelector('.message-count').textContent = conv.message_count + ' messages';

        // Bouton supprimer
        const deleteBtn = clone.querySelector('.delete-btn');
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            deleteConversation(conv.id);
        });

        // Bouton éditer
        const editBtn = clone.querySelector('.edit-btn');
        editBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const newTitle = prompt('Nouveau titre:', conv.title);
            if (newTitle && newTitle.trim()) {
                updateConversationTitle(conv.id, newTitle.trim());
            }
        });

        // Clic sur l'item
        item.addEventListener('click', () => loadConversation(conv.id));

        list.appendChild(clone);
    });

    // Mettre à jour la pagination
    updatePagination(pagination);
}

/**
 * Met à jour l'affichage de la pagination
 * @param {Object} pagination - Infos de pagination
 */
function updatePagination(pagination) {
    document.getElementById('page-indicator').textContent = 
        `Page ${pagination.current_page}/${pagination.total_pages}`;
    
    document.getElementById('prev-page').disabled = pagination.current_page === 1;
    document.getElementById('next-page').disabled = pagination.current_page === pagination.total_pages;
}

// ========================================
// ENVOI DE MESSAGE
// ========================================

/**
 * Envoie un message au serveur
 */
async function handleSend() {
    if (isProcessing) return;
    
    const message = userInput.value.trim();
    if (!message) return;

    isProcessing = true;
    userInput.disabled = true;
    sendButton.disabled = true;
    typingIndicator.style.display = 'flex';
    
    addMessage(message, true);
    userInput.value = '';

    try {
        const response = await fetch('./api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                conversation_id: currentConversationId,
                message: message
            })
        });

        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            addMessage(`**Erreur** : ${data.error}`, false);
            return;
        }

        // Afficher la réponse
        addMessage(data.response, false);
        
        // Mettre à jour l'ID de conversation si c'était une nouvelle
        if (!currentConversationId) {
            currentConversationId = data.conversation_id;
        }
        
        // Recharger la liste des conversations avec un petit délai
        // Pour s'assurer que la DB est bien à jour
        setTimeout(() => {
            loadConversationsFromAPI();
        }, 300);

    } catch (error) {
        addMessage("**Erreur** : Impossible de contacter le serveur", false);
        console.error('Erreur:', error);
    } finally {
        isProcessing = false;
        userInput.disabled = false;
        sendButton.disabled = false;
        typingIndicator.style.display = 'none';
        userInput.focus();
    }
}


// ========================================
// NOUVELLE CONVERSATION
// ========================================

document.getElementById('new-chat').addEventListener('click', () => {
    chatMessages.innerHTML = '';
    currentConversationId = null;
    
    // Retirer l'état actif de toutes les conversations
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Message de bienvenue
    addMessage("Bonjour ! Je suis votre assistant spécialisé. Posez-moi vos questions.", false);
});

// ========================================
// PAGINATION
// ========================================

document.getElementById('prev-page').addEventListener('click', () => {
    if (currentPage > 1) {
        currentPage--;
        loadConversationsFromAPI();
    }
});

document.getElementById('next-page').addEventListener('click', () => {
    currentPage++;
    loadConversationsFromAPI();
});

// ========================================
// RECHERCHE (À IMPLÉMENTER PLUS TARD)
// ========================================

document.getElementById('search-input').addEventListener('input', (e) => {
    searchQuery = e.target.value;
    // TODO: Implémenter la recherche côté serveur
    console.log('Recherche:', searchQuery);
});

// ========================================
// ÉVÉNEMENTS ENVOI MESSAGE
// ========================================

sendButton.addEventListener('click', handleSend);

userInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
});

// ========================================
// GESTION MENU MOBILE
// ========================================

const menuToggle = document.getElementById('menu-toggle');
const sidebar = document.querySelector('.sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
    
    // Changer l'icône du bouton burger
    const icon = menuToggle.querySelector('i');
    if (sidebar.classList.contains('active')) {
        icon.className = 'fas fa-times'; // Croix
    } else {
        icon.className = 'fas fa-bars'; // Burger
    }
}

if (menuToggle) {
    menuToggle.addEventListener('click', toggleSidebar);
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', toggleSidebar);
}

// Fermer après sélection d'une conversation (mobile uniquement)
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && e.target.closest('.conversation-item')) {
        setTimeout(() => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            
            const icon = menuToggle.querySelector('i');
            icon.className = 'fas fa-bars';
        }, 300);
    }
});

// ========================================
// INITIALISATION AU CHARGEMENT
// ========================================

window.addEventListener('DOMContentLoaded', () => {
    // Charger highlight.js
    if (typeof hljs !== 'undefined') {
        hljs.highlightAll();
    }
    
    // Charger les conversations
    loadConversationsFromAPI();
    
    // Message de bienvenue
    addMessage("Bonjour ! Je suis votre assistant spécialisé. Posez-moi vos questions.", false);
    
    console.log('✅ Application initialisée avec user_id:', userId);
});