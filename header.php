<?php
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="header-left">
            <img src="https://explorecyprus.uclancyprus.ac.cy/wp-content/uploads/2023/01/uclan_cy_logo-e1675093803283.png" alt="UCLan Cyprus Logo" class="uclanlogo">
            <a href="index.php" class="shop-title">Student Shop</a>
        </div>
        
        <div class="navigation">
            <a href="index.php">Home</a>
            <a href="products.php">Products</a>
            <a href="reviews.php">Reviews</a>
            <a href="cart.php">Cart</a>
            <?php if ($is_logged_in): ?>
                <span style="color: white;">Hello, <?php echo htmlspecialchars($user_name); ?></span>
                <a href="logout.php" onclick="return confirm('Logout?')">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
        
        <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </header>
    
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-nav">
            <a href="index.php">Home</a>
            <a href="products.php"> Products</a>
            <a href="reviews.php"> Reviews</a>
            <a href="cart.php"> Cart</a>
        </div>
        
        <div class="mobile-user-info">
            <?php if ($is_logged_in): ?>
                <div class="user-greeting"> Hello, <?php echo htmlspecialchars($user_name); ?></div>
                <a href="logout.php" class="logout-btn" onclick="return confirm('Logout?')"> Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-link">Login</a>
                <a href="register.php" class="register-link"> Register</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="overlay" id="overlay"></div>
    
    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('overlay');
        
        function toggleMenu() {
            hamburgerBtn.classList.toggle('active');
            mobileMenu.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        }
        
        function closeMenu() {
            hamburgerBtn.classList.remove('active');
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('menu-open');
        }
        
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', toggleMenu);
        }
        
        if (overlay) {
            overlay.addEventListener('click', closeMenu);
        }
        
        const mobileLinks = mobileMenu.querySelectorAll('a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', closeMenu);
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
                closeMenu();
            }
        });
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && mobileMenu.classList.contains('active')) {
                closeMenu();
            }
        });
    </script>