<?php
session_start();
require_once 'conn.php';

if (isset($_SESSION['user_id'])) {
    header("Location: products.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $sql = "SELECT user_id, user_name, user_email, user_pass, user_address FROM tbl_users WHERE user_email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['user_pass'])) {
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['user_email'];
                $_SESSION['user_name'] = $user['user_name'];
                $_SESSION['user_address'] = $user['user_address'];
                
                $redirect = $_SESSION['redirect_after_login'] ?? 'products.php';
                unset($_SESSION['redirect_after_login']);
                
                header("Location: $redirect");
                exit();
            } else {
                $error = "Invalid email or password.";
                error_log("Failed login attempt for email: $email");
            }
        } else {
            $error = "Invalid email or password.";
        }
        $stmt->close();
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
    <title>Login - UCLAN E-SHOP</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main>
        <div class="login-container">
            <h2>Login to UCLAN E-SHOP</h2>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <div class="demo-credentials">
                <h4>Demo Credentials</h4>
                <p><strong>Email:</strong> mbates5@lancashire.ac.uk</p>
                <p><strong>Password:</strong> password123</p>
                <p><strong>Name:</strong> Matthew</p>
                <p><strong>Address:</strong> University of Lancashire, Preston.</p>
            </div>
            
            <div class="register-link">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>