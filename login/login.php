<?php
session_start();
require_once 'helpers/JWT.php';

// Database connection
class Database
{
  private $host = "localhost";
  private $db_name = "goldendream";
  private $username = "root";
  private $password = "";
  public $conn;

  public function getConnection()
  {
    $this->conn = null;
    try {
      $this->conn = new PDO(
        "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
        $this->username,
        $this->password
      );
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
      echo "Connection Error: " . $e->getMessage();
    }
    return $this->conn;
  }
}

// Check if user is already logged in via session
if (isset($_SESSION['customer_id'])) {
  header('Location: dashboard.php');
  exit;
}

// Check for existing JWT token
if (isset($_COOKIE['auth_token'])) {
  $tokenData = JWT::verifyToken($_COOKIE['auth_token']);
  if ($tokenData && isset($tokenData['data'])) {
    $_SESSION['customer_id'] = $tokenData['data']['customer_id'];
    $_SESSION['customer_unique_id'] = $tokenData['data']['customer_unique_id'];
    $_SESSION['customer_name'] = $tokenData['data']['name'];
    $_SESSION['customer_email'] = $tokenData['data']['email'];
    header('Location: dashboard.php');
    exit;
  }
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
  header('Content-Type: application/json');

  $data = json_decode(file_get_contents('php://input'), true);

  if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit;
  }

  // Validate required fields
  $required_fields = ['email', 'password'];
  foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
      echo json_encode(['success' => false, 'message' => 'All fields are required']);
      exit;
    }
  }

  // Validate email format
  if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
  }

  try {
    $database = new Database();
    $db = $database->getConnection();

    // Get customer data
    $query = "SELECT CustomerID, CustomerUniqueID, Name, Email, PasswordHash, Status 
                  FROM Customers 
                  WHERE Email = :email";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $data['email']);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
      echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
      exit;
    }

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify password
    if (!password_verify($data['password'], $customer['PasswordHash'])) {
      echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
      exit;
    }

    // Check account status
    if ($customer['Status'] !== 'Active') {
      echo json_encode(['success' => false, 'message' => 'Your account is not active. Please contact support.']);
      exit;
    }

    // Set session variables
    $_SESSION['customer_id'] = $customer['CustomerID'];
    $_SESSION['customer_unique_id'] = $customer['CustomerUniqueID'];
    $_SESSION['customer_name'] = $customer['Name'];
    $_SESSION['customer_email'] = $customer['Email'];

    // Generate JWT token if remember me is checked
    if (isset($data['remember']) && $data['remember']) {
      $tokenData = [
        'customer_id' => $customer['CustomerID'],
        'customer_unique_id' => $customer['CustomerUniqueID'],
        'name' => $customer['Name'],
        'email' => $customer['Email']
      ];
      $token = JWT::generateToken($tokenData);

      // Set cookie with token (30 days)
      setcookie('auth_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }

    echo json_encode([
      'success' => true,
      'message' => 'Login successful',
      'redirect' => 'dashboard.php'
    ]);
  } catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during login']);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Golden Dream - Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="./login.css">
</head>

<body>
  <div class="container">
    <div class="login-box">
      <div class="logo">
        <i class="fas fa-crown"></i>
        <h2>Golden Dream</h2>
      </div>
      <form id="loginForm" class="login-form">
        <div class="input-group">
          <div class="input-field">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" placeholder="Email Address" required>
          </div>
          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" placeholder="Password" required>
          </div>
        </div>
        <div class="options">
          <label class="remember-me">
            <input type="checkbox" id="remember">
            <span>Remember me for 30 days</span>
          </label>
          <a href="#" class="forgot-password">Forgot Password?</a>
        </div>
        <button type="submit" class="login-btn">Login</button>
        <div class="register-link">
          <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const loginForm = document.getElementById("loginForm");
      const emailInput = document.getElementById("email");
      const passwordInput = document.getElementById("password");
      const rememberCheckbox = document.getElementById("remember");

      // Check for saved credentials
      const savedEmail = localStorage.getItem("rememberedEmail");
      if (savedEmail) {
        emailInput.value = savedEmail;
        rememberCheckbox.checked = true;
      }

      loginForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const email = emailInput.value;
        const password = passwordInput.value;
        const remember = rememberCheckbox.checked;

        // Basic validation
        if (!email || !password) {
          showError("Please fill in all fields");
          return;
        }

        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
          showError("Please enter a valid email address");
          return;
        }

        // Handle remember me
        if (remember) {
          localStorage.setItem("rememberedEmail", email);
        } else {
          localStorage.removeItem("rememberedEmail");
        }

        // Add loading animation to button
        const loginBtn = loginForm.querySelector(".login-btn");
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        loginBtn.disabled = true;

        try {
          const response = await fetch(window.location.href, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify({
              email,
              password,
              remember
            }),
          });

          const data = await response.json();

          if (data.success) {
            showSuccess(data.message);
            setTimeout(() => {
              window.location.href = data.redirect;
            }, 1500);
          } else {
            showError(data.message);
            loginBtn.innerHTML = "Login";
            loginBtn.disabled = false;
          }
        } catch (error) {
          console.error("Error:", error);
          showError("An error occurred during login");
          loginBtn.innerHTML = "Login";
          loginBtn.disabled = false;
        }
      });

      // Add input focus effects
      const inputFields = document.querySelectorAll(".input-field input");
      inputFields.forEach((input) => {
        input.addEventListener("focus", () => {
          input.parentElement.classList.add("focused");
        });

        input.addEventListener("blur", () => {
          if (!input.value) {
            input.parentElement.classList.remove("focused");
          }
        });
      });

      // Helper function to show error messages
      function showError(message) {
        const errorDiv = document.createElement("div");
        errorDiv.className = "error-message";
        errorDiv.textContent = message;

        const existingError = document.querySelector(".error-message");
        if (existingError) {
          existingError.remove();
        }

        loginForm.insertBefore(errorDiv, loginForm.firstChild);

        setTimeout(() => {
          errorDiv.remove();
        }, 3000);
      }

      // Helper function to show success messages
      function showSuccess(message) {
        const successDiv = document.createElement("div");
        successDiv.className = "success-message";
        successDiv.textContent = message;

        const existingSuccess = document.querySelector(".success-message");
        if (existingSuccess) {
          existingSuccess.remove();
        }

        loginForm.insertBefore(successDiv, loginForm.firstChild);
      }
    });
  </script>
</body>

</html>