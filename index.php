<?php
session_start();
require_once 'conn.php';

$login_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $login_error = "Please enter both email and password.";
    } else {
        $sql = "SELECT user_id, user_name, user_email, user_pass, user_address FROM tbl_users WHERE user_email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['user_pass'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['user_email'];
                $_SESSION['user_name'] = $user['user_name'];
                $_SESSION['user_address'] = $user['user_address'];
                $_SESSION['login_time'] = time();
                
                header("Location: products.php");
                exit();
            } else {
                $login_error = "Invalid email or password.";
            }
        } else {
            $login_error = "Invalid email or password.";
        }
        $stmt->close();
    }
}

$offers = [];

$result = mysqli_query($conn, "SELECT * FROM tbl_offers");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $offers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="style.css">
    <title>Student Shop - UCLan</title>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="contents">
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="welcome-message">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                <p>Ready to find your perfect UCLan merchandise?</p>
                <a href="products.php" class="shop-link">Start Shopping →</a>
            </div>
        <?php else: ?>
            <div class="login-section" id="login">
                <h3>Login to Start Shopping</h3>
                
                <?php if ($login_error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" name="login">Login</button>
                </form>
                
                <div class="demo-credentials">
                 
                    <p> Email: mbates5@lancashire.ac.uk</p>
                    <p> Password: password123</p>
                </div>
            </div>
        <?php endif; ?>
        
        <h1>Where opportunity creates success</h1>
        <p>
            Every student at The University of Central Lancashire is automatically a member of the Students' Union.
            We're here to make life better for students - inspiring you to succeed and achieve your goals.
        </p>
        <p>
            Everything you need to know about UCLan Students' Union. Your membership starts here.
        </p>
        <div class="videos">
            <div class="subheading">Together</div><br />
            <video controls>
                <source src="video.mp4" type="video/mp4">
            </video>
        </div>
        <div>
            <div class="subheading">Join our global community</div><br />
            <iframe src="https://player.vimeo.com/video/1071072056" allow="autoplay; fullscreen;"></iframe>
        </div>

        <h1>Current Offers</h1>
        
        <?php if (!empty($offers)): ?>
            <?php foreach($offers as $offer): ?>
                <div style="border:1px solid black; margin:10px; padding:10px;">
                    <h2><?php echo htmlspecialchars($offer['offer_title']); ?></h2>
                    <p><?php echo htmlspecialchars($offer['offer_desc']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No offers available at the moment.</p>
        <?php endif; ?>

    </div>
    <div class="contents" id="listshirts"></div>
    
    <script src="assets/js/Script1.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>