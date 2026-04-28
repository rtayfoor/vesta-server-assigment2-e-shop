<?php
session_start();
require_once 'conn.php';

if (isset($_SESSION['user_id'])) {
    header("Location: products.php");
    exit();
}

$error = '';
$success = '';

// Simple password validation function
function validatePassword($password) {
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least 1 uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least 1 lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least 1 number";
    }
    return true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_name = trim($_POST['user_name'] ?? '');
    $user_email = trim($_POST['user_email'] ?? '');
    $user_pass = $_POST['user_pass'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_address = trim($_POST['user_address'] ?? '');
    
    if (empty($user_name) || empty($user_email) || empty($user_pass) || empty($confirm_password) || empty($user_address)) {
        $error = "All fields are required.";
    } elseif ($user_pass !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Validate password strength
        $password_check = validatePassword($user_pass);
        if ($password_check !== true) {
            $error = $password_check;
        } else {
            $check_sql = "SELECT user_id FROM tbl_users WHERE user_email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $user_email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Email already registered.";
            } else {
                $hashed_password = password_hash($user_pass, PASSWORD_DEFAULT);
                
                $get_max_id = "SELECT MAX(user_id) as max_id FROM tbl_users";
                $max_result = $conn->query($get_max_id);
                $max_row = $max_result->fetch_assoc();
                $next_id = ($max_row['max_id'] ?? 0) + 1;
                
                $sql = "INSERT INTO tbl_users (user_id, user_name, user_email, user_pass, user_address) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issss", $next_id, $user_name, $user_email, $hashed_password, $user_address);
                
                if ($stmt->execute()) {
                    $success = "Registration successful! You can now login.";
                    $_POST = array();
                } else {
                    $error = "Registration failed. Please try again.";
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Register - Student Shop</title>
    <style>
        .password-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .password-hint ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="register-container">
        <h1>Create an Account</h1>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?php echo $success; ?>
                <p style="margin-top: 10px;"><a href="login.php" style="color: #155724;">Click here to login →</a></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="user_name" required 
                       value="<?php echo htmlspecialchars($_POST['user_name'] ?? ''); ?>"
                       style="width: 100%; padding: 8px;">
            </div>
            
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="user_email" required 
                       value="<?php echo htmlspecialchars($_POST['user_email'] ?? ''); ?>"
                       style="width: 100%; padding: 8px;">
            </div>
            
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="user_pass" id="password" required 
                       style="width: 100%; padding: 8px;">
                <div class="password-hint">
                    <strong>Password requirements:</strong>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>At least 1 uppercase letter (A-Z)</li>
                        <li>At least 1 lowercase letter (a-z)</li>
                        <li>At least 1 number (0-9)</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" id="confirm_password" required 
                       style="width: 100%; padding: 8px;">
            </div>
            
            <div class="form-group">
                <label>Address *</label>
                <textarea name="user_address" rows="3" required 
                          style="width: 100%; padding: 8px;"><?php echo htmlspecialchars($_POST['user_address'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" name="register" style="width: 100%; padding: 10px; background: #8E1622; color: white; border: none; cursor: pointer;">Register</button>
        </form>
        
        <div style="margin-top: 20px; text-align: center;">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
    
    <script>
        // Simple client-side password confirmation check
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        
        function checkPasswordMatch() {
            if (confirm.value.length > 0 && password.value !== confirm.value) {
                confirm.style.borderColor = '#dc3545';
                confirm.setCustomValidity('Passwords do not match');
            } else {
                confirm.style.borderColor = '#ddd';
                confirm.setCustomValidity('');
            }
        }
        
        password.addEventListener('change', checkPasswordMatch);
        confirm.addEventListener('keyup', checkPasswordMatch);
    </script>
    
    <?php include 'footer.php'; ?>
</body>
</html>
