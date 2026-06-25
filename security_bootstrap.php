<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDataPath($subpath = '') {
    static $dataDir = null;
    if ($dataDir === null) {
        $settingsFile = __DIR__ . '/editor_settings.json';
        $settings = [];
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
        }
        $dataDir = isset($settings['data_path']) ? $settings['data_path'] : '/var/www/html/data';
        if (!is_dir($dataDir)) {
            if (!@mkdir($dataDir, 0777, true)) {
                $dataDir = __DIR__ . '/data/';
            }
        } elseif (!is_writable($dataDir)) {
            $dataDir = __DIR__ . '/data/';
        }
        // Ensure trailing slash and correct path separators
        $dataDir = rtrim(str_replace('\\', '/', $dataDir), '/') . '/';
    }
    return $dataDir . $subpath;
}

function getDataUrl($subpath = '') {
    static $webPrefix = null;
    if ($webPrefix === null) {
        $dataDir = getDataPath();
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) : '';
        $docRoot = rtrim($docRoot, '/');
        
        if (!empty($docRoot) && strpos($dataDir, $docRoot) === 0) {
            $webPrefix = '/' . ltrim(substr($dataDir, strlen($docRoot)), '/');
            $webPrefix = rtrim($webPrefix, '/') . '/';
        } else {
            // Find script directory prefix to handle subfolders correctly
            $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
            $subDir = '';
            if (!empty($scriptName) && php_sapi_name() !== 'cli') {
                $subDir = rtrim(dirname($scriptName), '/\\');
            }
            $webPrefix = (!empty($subDir) ? $subDir : '') . '/serve_data.php?file=';
        }
    }
    return $webPrefix . $subpath;
}

// Check authentication
$settingsFile = __DIR__ . '/editor_settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

$passwordHash = isset($settings['password_hash']) ? $settings['password_hash'] : '';

if (!empty($passwordHash) && php_sapi_name() !== 'cli') {
    // Session is valid for 24 hours (86400 seconds)
    $isAuthorized = isset($_SESSION['authenticated']) && 
                    $_SESSION['authenticated'] === true && 
                    isset($_SESSION['auth_time']) && 
                    (time() - $_SESSION['auth_time'] < 86400);
                    
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    
    if (!$isAuthorized && $currentScript !== 'login.php' && $currentScript !== 'serve_data.php') {
        if ($currentScript === 'index.php') {
            // Render beautiful login page and exit
            renderLoginPage($settings);
            exit();
        } else {
            // API endpoints get 401 response
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'unauthorized', 'message' => 'Необходима авторизация']);
            exit();
        }
    }
}

function renderLoginPage($settings) {
    $lockout_until = isset($settings['lockout_until']) ? (int)$settings['lockout_until'] : 0;
    $remaining_lockout = $lockout_until - time();
    $is_locked = $remaining_lockout > 0;
    
    $lockout_msg = '';
    if ($is_locked) {
        $minutes = ceil($remaining_lockout / 60);
        $lockout_msg = "Превышено количество попыток ввода. Доступ заблокирован на $minutes мин.";
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="ru" <?php echo isset($settings['amoledTheme']) && $settings['amoledTheme'] ? 'data-amoled="true"' : ''; ?>>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Вход в NPBlog</title>
        <script>
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        </script>
        <style>
            :root {
                --bg-color: #ffffff;
                --text-color: #333333;
                --border-color: #000000;
                --shadow-color: rgba(0, 0, 0, 0.08);
                --danger-color: #d32f2f;
                --input-focus-shadow: rgba(0, 0, 0, 0.1);
            }
            [data-theme="dark"] {
                --bg-color: #121212;
                --text-color: #f5f5f5;
                --border-color: #ffffff;
                --shadow-color: rgba(0, 0, 0, 0.5);
                --danger-color: #f44336;
                --input-focus-shadow: rgba(255, 255, 255, 0.25);
            }
            html[data-amoled="true"] {
                --bg-color: #000000;
            }
            html[data-theme="dark"][data-amoled="true"] .login-dialog {
                border-color: #222222;
                box-shadow: 0 12px 32px rgba(0, 0, 0, 0.8);
            }
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
            body {
                font-family: Arial, sans-serif;
                background-color: var(--bg-color);
                color: var(--text-color);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
                transition: background-color 0.3s, color 0.3s;
            }
            .login-dialog {
                background: var(--bg-color);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                padding: 32px;
                width: 100%;
                max-width: 360px;
                box-shadow: 0 12px 32px var(--shadow-color);
                box-sizing: border-box;
                animation: dialogContentIn 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) forwards;
            }
            @keyframes dialogContentIn {
                from { opacity: 0; transform: scale(0.95); }
                to { opacity: 1; transform: scale(1); }
            }
            .login-header {
                text-align: center;
                margin-bottom: 24px;
            }
            .login-title {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 4px;
                letter-spacing: -0.02em;
            }
            .login-subtitle {
                font-size: 13px;
                opacity: 0.6;
            }
            .form-group {
                width: 100%;
                margin-bottom: 20px;
                box-sizing: border-box;
            }
            .form-label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 8px;
            }
            .input-wrapper {
                position: relative;
                width: 100%;
            }
            .form-input {
                width: 100%;
                box-sizing: border-box;
                padding: 10px 40px 10px 12px;
                background: var(--bg-color);
                border: 1px solid var(--border-color);
                border-radius: 8px;
                color: var(--text-color);
                font-size: 14px;
                font-family: Arial, sans-serif;
                outline: none;
                transition: box-shadow 0.15s ease;
            }
            .form-input:focus {
                box-shadow: 0 0 0 2px var(--input-focus-shadow);
            }
            .toggle-password {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: var(--text-color);
                cursor: pointer;
                font-size: 16px;
                outline: none;
                padding: 4px;
                opacity: 0.5;
                transition: opacity 0.15s ease;
            }
            .toggle-password:hover {
                opacity: 1;
            }
            .submit-btn {
                width: 100%;
                height: 38px;
                box-sizing: border-box;
                background: var(--bg-color);
                color: var(--text-color);
                border: 1px solid var(--border-color);
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .submit-btn:hover:not(:disabled) {
                background: var(--text-color);
                color: var(--bg-color);
            }
            .submit-btn:disabled {
                opacity: 0.4;
                cursor: not-allowed;
            }
            .error-message {
                color: var(--danger-color);
                font-size: 13px;
                margin-top: 12px;
                text-align: center;
                display: none;
                animation: fadeIn 0.15s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .lockout-info {
                padding: 12px;
                background: rgba(211, 47, 47, 0.08);
                border: 1px solid var(--danger-color);
                border-radius: 8px;
                color: var(--danger-color);
                font-size: 13px;
                line-height: 1.4;
                margin-bottom: 20px;
                text-align: center;
            }
            [data-theme="dark"] .lockout-info {
                background: rgba(244, 67, 54, 0.1);
            }
        </style>
    </head>
    <body>
        <div class="login-dialog">
            <div class="login-header">
                <div class="login-title">NPBlog</div>
                <div class="login-subtitle">Панель управления редактором</div>
            </div>
            
            <div class="lockout-info" id="lockoutInfo" style="display: <?php echo $is_locked ? 'block' : 'none'; ?>;">
                <?php echo $lockout_msg; ?>
            </div>
            
            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label class="form-label" for="password">Пароль доступа</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" class="form-input" required placeholder="Введите ваш пароль" <?php echo $is_locked ? 'disabled' : ''; ?> autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()" aria-label="Показать/скрыть пароль">👁️</button>
                    </div>
                </div>
                
                <button type="submit" id="submitBtn" class="submit-btn" <?php echo $is_locked ? 'disabled' : ''; ?>>Войти</button>
                <div class="error-message" id="errorMessage"></div>
            </form>
        </div>

        <script>
            let lockoutTimeRemaining = <?php echo $is_locked ? $remaining_lockout : 0; ?>;
            
            if (lockoutTimeRemaining > 0) {
                startLockoutCountdown();
            }

            function togglePasswordVisibility() {
                const passwordInput = document.getElementById('password');
                const btn = document.querySelector('.toggle-password');
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    btn.textContent = '🔒';
                } else {
                    passwordInput.type = 'password';
                    btn.textContent = '👁️';
                }
            }

            async function handleLogin(e) {
                e.preventDefault();
                const password = document.getElementById('password').value;
                const errDiv = document.getElementById('errorMessage');
                const submitBtn = document.getElementById('submitBtn');
                
                errDiv.style.display = 'none';
                submitBtn.disabled = true;
                
                try {
                    const response = await fetch('login.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ password })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.reload();
                    } else {
                        errDiv.textContent = data.message || 'Ошибка входа';
                        errDiv.style.display = 'block';
                        
                        if (data.lockoutTimeRemaining) {
                            lockoutTimeRemaining = data.lockoutTimeRemaining;
                            document.getElementById('password').disabled = true;
                            submitBtn.disabled = true;
                            document.getElementById('lockoutInfo').style.display = 'block';
                            updateLockoutMessage();
                            startLockoutCountdown();
                        } else {
                            submitBtn.disabled = false;
                        }
                    }
                } catch (err) {
                    errDiv.textContent = 'Произошла сетевая ошибка';
                    errDiv.style.display = 'block';
                    submitBtn.disabled = false;
                }
            }

            function startLockoutCountdown() {
                const interval = setInterval(() => {
                    lockoutTimeRemaining--;
                    if (lockoutTimeRemaining <= 0) {
                        clearInterval(interval);
                        document.getElementById('password').disabled = false;
                        document.getElementById('submitBtn').disabled = false;
                        document.getElementById('lockoutInfo').style.display = 'none';
                        document.getElementById('errorMessage').style.display = 'none';
                    } else {
                        updateLockoutMessage();
                    }
                }, 1000);
            }

            function updateLockoutMessage() {
                const minutes = Math.ceil(lockoutTimeRemaining / 60);
                document.getElementById('lockoutInfo').innerHTML = `Превышено количество попыток ввода. Доступ заблокирован на ${minutes} мин.`;
            }
        </script>
    </body>
    </html>
    <?php
}
