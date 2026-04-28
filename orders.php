<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$order_id = isset($_SESSION['last_order']) ? $_SESSION['last_order'] : (isset($_GET['order_id']) ? $_GET['order_id'] : 0);

if ($order_id <= 0) {
    header("Location: products.php");
    exit();
}

unset($_SESSION['last_order']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Order Confirmation - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    <div style="text-align: center; padding: 80px 20px; max-width: 600px; margin: 0 auto;">
        <h1 style="color: #28a745; font-size: 48px;">Thank You!</h1>
        <h2>Your Order Has Been Placed</h2>
        <p style="font-size: 18px; margin: 20px 0;">Order #<?php echo $order_id; ?> has been confirmed.</p>
        <p>You will receive a confirmation email shortly.</p>
        <div style="margin-top: 40px;">
            <a href="orders.php" class="btn-read-more" style="padding: 12px 24px;">View My Orders</a>
            <a href="products.php" class="btn-add-cart" style="padding: 12px 24px;">Continue Shopping</a>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
