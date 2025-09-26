<?php
/**
 * PAGE DE CONNEXION ADMIN
 * Formulaire de login pour accéder au back-office
 */

// Démarrer la session
session_start();
require_once __DIR__ . '/config-path.php';

// Si déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - ABSA Back-Office</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========================================
           VARIABLES CSS
           ======================================== */
        :root {
            --primary: #4b3795;
            --primary-hover: #3a2b75;
            --success: #28a745;
            --danger: #dc3545;
            --white: #ffffff;
            --bg: #f0f2f5;
            --border: #e0e0e0;
            --text: #212529;
            --text-gray: #666;
        }

        /* ========================================
           BASE STYLES
           ======================================== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #51c6e1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* ========================================
           CONTAINER LOGIN
           ======================================== */
        .login-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========================================
           HEADER
           ======================================== */
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), #51c6e1);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-logo i {
            font-size: 40px;
            color: var(--white);
        }

        .login-title {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .login-subtitle {
            font-size: 14px;
            color: var(--text-gray);
        }

        /* ========================================
           FORMULAIRE
           ======================================== */
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 43px;
            color: var(--text-gray);
        }

        /* ========================================
           BOUTON SUBMIT
           ======================================== */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.1s;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: var(--primary-hover);
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        .btn-login:disabled {
            background: var(--border);
            cursor: not-allowed;
        }

        /* ========================================
           MESSAGES
           ======================================== */
        .message {
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .message.show {
            display: flex;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message i {
            font-size: 18px;
        }

        /* ========================================
           LOADING
           ======================================== */
        .loading {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid var(--white);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-login.loading .loading {
            display: inline-block;
        }

        .btn-login.loading .btn-text {
            display: none;
        }

        /* ========================================
           FOOTER
           ======================================== */
        .login-footer {
            margin-top: 25px;
            text-align: center;
            font-size: 13px;
            color: var(--text-gray);
        }

        /* ========================================
           RESPONSIVE
           ======================================== */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .login-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">ABSA Back-Office</h1>
            <p class="login-subtitle">Connectez-vous pour accéder au tableau de bord</p>
        </div>

        <!-- Messages d'erreur/succès -->
        <div id="message" class="message">
            <i class="fas fa-exclamation-circle"></i>
            <span id="message-text"></span>
        </div>

        <!-- Formulaire de connexion -->
        <form id="login-form" class="login-form">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <i class="fas fa-user"></i>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="Entrez votre nom d'utilisateur"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <i class="fas fa-lock"></i>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Entrez votre mot de passe"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-login" id="btn-submit">
                <span class="btn-text">Se connecter</span>
                <span class="loading"></span>
            </button>
        </form>

        <!-- Footer -->
        <div class="login-footer">
            <p>© 2025 ABSA by Byfilling - Tous droits réservés</p>
        </div>
    </div>

    <script>
        // ========================================
        // GESTION DU FORMULAIRE DE CONNEXION
        // ========================================

        const loginForm = document.getElementById('login-form');
        const btnSubmit = document.getElementById('btn-submit');
        const messageDiv = document.getElementById('message');
        const messageText = document.getElementById('message-text');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');

        /**
         * Affiche un message (erreur ou succès)
         */
        function showMessage(text, type = 'error') {
            messageDiv.className = `message ${type} show`;
            messageText.textContent = text;
            
            const icon = messageDiv.querySelector('i');
            icon.className = type === 'error' 
                ? 'fas fa-exclamation-circle' 
                : 'fas fa-check-circle';
        }

        /**
         * Cache le message
         */
        function hideMessage() {
            messageDiv.classList.remove('show');
        }

        /**
         * Gestion de la soumission du formulaire
         */
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            hideMessage();
            
            const username = usernameInput.value.trim();
            const password = passwordInput.value;

            // Validation basique
            if (!username || !password) {
                showMessage('Veuillez remplir tous les champs', 'error');
                return;
            }

            // Désactiver le bouton et afficher le loading
            btnSubmit.disabled = true;
            btnSubmit.classList.add('loading');

            try {
                const response = await fetch('../api/admin/auth.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Connexion réussie
                    showMessage('Connexion réussie ! Redirection...', 'success');
                    
                    // Rediriger après 1 seconde
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    // Erreur de connexion
                    showMessage(data.error || 'Identifiants incorrects', 'error');
                    btnSubmit.disabled = false;
                    btnSubmit.classList.remove('loading');
                }

            } catch (error) {
                console.error('Erreur:', error);
                showMessage('Erreur de connexion au serveur', 'error');
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('loading');
            }
        });

        // Focus automatique sur le champ username
        usernameInput.focus();
    </script>
</body>
</html>