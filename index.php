<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>askYesso by Byfilling</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.0/styles/default.min.css">
    <link rel="stylesheet" href="style.css">
    
    <!-- Scripts externes -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify/dist/purify.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.0/highlight.min.js"></script>
</head>
<body>
    <!-- Bouton menu mobile -->
    <button class="menu-toggle" id="menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Overlay pour fermer la sidebar -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="search-container">
                    <input type="text" id="search-input" placeholder="Rechercher...">
                    <i class="fas fa-search"></i>
                </div>
                <button id="new-chat">+ Nouveau</button>
            </div>
    
            <div class="conversation-list" id="conversation-list">
                <!-- Les conversations seront insérées ici dynamiquement -->
            </div>

            <!-- Template d'un élément de conversation (EN DEHORS de conversation-list) -->
            <template id="conversation-template">
                <div class="conversation-item">
                    <div class="conversation-header">
                        <div class="editable-title"></div>
                        <div class="conversation-actions">
                            <button class="edit-btn"><i class="fas fa-pencil-alt"></i></button>
                            <button class="delete-btn"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="conversation-preview"></div>
                    <div class="conversation-footer">
                        <span class="conversation-date"></span>
                        <span class="message-count"></span>
                    </div>
                </div>
            </template>

            <div class="pagination-controls">
                <button id="prev-page"><i class="fas fa-chevron-left"></i></button>
                <span id="page-indicator">Page 1</span>
                <button id="next-page"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>

        <div class="chat-container">
            <div class="chat-messages" id="chat-messages"></div>

            <div class="typing-indicator" id="typing-indicator">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>

            <div class="input-container">
                <input type="text" 
                       id="user-input" 
                       placeholder="Écrivez votre question ici..."
                       autocomplete="off">
                <button id="send-button">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>