<?php
require_once '../config/session_check.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ../dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #4a90e2;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e1e1e1;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-check-input:checked {
            background-color: #4a90e2;
            border-color: #4a90e2;
        }

        .signup-link {
            text-align: center;
            margin-top: 20px;
        }

        .signup-link a {
            color: #4a90e2;
            text-decoration: none;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <h1>Golden Dream</h1>
            <p class="text-muted">Welcome back! Please login to your account.</p>
        </div>

        <div class="error-message" id="errorMessage"></div>

        <form id="loginForm" action="process_login.php" method="POST">
            <div class="mb-3">
                <label for="phoneNumber" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phoneNumber" name="phoneNumber"
                    placeholder="Enter your phone number" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="position-relative">
                    <input type="password" class="form-control" id="password" name="password"
                        placeholder="Enter your password" required>
                    <i class="fas fa-eye position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer;"
                        onclick="togglePassword()"></i>
                </div>
            </div>

            <div class="mb-3 remember-me">
                <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                <label class="form-check-label" for="rememberMe">Remember me for 30 days</label>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </div>
        </form>

        <div class="signup-link">
            <p class="mb-0">Don't have an account? <a href="signup.php">Sign up here</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.fa-eye');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Check for error message in URL
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        if (error) {
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = decodeURIComponent(error);
            errorMessage.style.display = 'block';
        }
    </script>
</body>

</html>