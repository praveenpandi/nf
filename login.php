<?php
session_start();

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Handle POST Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'redirect' => 'upload.html',
                'message' => 'Login successful! Navigating to Excel Import...'
            ]);
            exit;
        }

        header("Location: upload.html");
        exit;
    } else {
        $errorMsg = 'Please enter username and password.';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorMsg]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Healthcare Revenue Cycle & AR Analysis System</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-main: #0b0f19;
            --bg-card: rgba(21, 30, 49, 0.85);
            --bg-input: #151d30;
            --border-color: rgba(255, 255, 255, 0.1);
            --border-highlight: rgba(99, 102, 241, 0.4);

            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;

            --accent-indigo: #6366f1;
            --accent-purple: #a855f7;
            --accent-cyan: #38bdf8;
            --accent-emerald: #10b981;

            --shadow-card: 0 16px 40px -10px rgba(0, 0, 0, 0.7);
            --shadow-glow: 0 0 40px rgba(99, 102, 241, 0.25);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-image: 
                radial-gradient(at 10% 20%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(168, 85, 247, 0.15) 0px, transparent 50%);
        }

        .login-container {
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-highlight);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple), var(--accent-cyan));
        }

        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.8rem;
            margin: 0 auto 1.25rem;
            box-shadow: var(--shadow-glow);
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.65rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-main), var(--accent-indigo));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.3rem;
        }

        .brand-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 1.35rem;
        }

        .form-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            text-transform: none;
            letter-spacing: 0.03em;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: var(--text-dim);
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 0.8rem 1rem 0.8rem 2.8rem;
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--accent-indigo);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple));
            color: #ffffff;
            border: none;
            padding: 0.9rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 1.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.45);
        }

        .alert-error {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #f43f5e;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        .demo-credentials-banner {
            background: rgba(255, 255, 255, 0.03);
            border: 1px dashed var(--border-color);
            border-radius: 12px;
            padding: 0.85rem;
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.78rem;
            color: var(--text-dim);
        }
    </style>
</head>

<body>

    <div class="login-container">
        <div class="login-card">
            <div class="brand-header">
                <div class="brand-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h1 class="brand-title">Healthcare AR Analytics</h1>
                <p class="brand-subtitle">Step 1: Sign in to your executive portal</p>
            </div>

            <div class="alert-error" id="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="error-text">Invalid login credentials</span>
            </div>

            <form id="login-form">
                <div class="form-group">
                    <label class="form-label" for="username">Username / Email</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" class="form-control" id="username" name="username" placeholder="admin" value="admin" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" value="admin123" required>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btn-submit">
                    <span>Sign In & Continue</span> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="demo-credentials-banner">
                <i class="fa-solid fa-shield-halved"></i> Healthcare Revenue Cycle Workflow System v2.0
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#login-form').on('submit', function(e) {
                e.preventDefault();
                const username = $('#username').val();
                const password = $('#password').val();

                $('#alert-error').hide();
                $('#btn-submit').html('<i class="fa-solid fa-spinner fa-spin"></i> Authenticating...').prop('disabled', true);

                $.ajax({
                    url: 'login.php',
                    method: 'POST',
                    data: { username: username, password: password },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            $('#btn-submit').css('background', 'linear-gradient(135deg, #10b981, #14b8a6)').html('<i class="fa-solid fa-check"></i> Redirecting to Import...');
                            setTimeout(() => {
                                window.location.href = res.redirect || 'upload.html';
                            }, 400);
                        } else {
                            $('#error-text').text(res.message || 'Login failed');
                            $('#alert-error').css('display', 'flex');
                            $('#btn-submit').html('<span>Sign In & Continue</span> <i class="fa-solid fa-arrow-right"></i>').prop('disabled', false);
                        }
                    },
                    error: function() {
                        // Fallback redirect to upload.html
                        window.location.href = 'upload.html';
                    }
                });
            });
        });
    </script>
</body>
</html>
