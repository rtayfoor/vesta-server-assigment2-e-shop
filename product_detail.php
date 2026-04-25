<?php
session_start();
require_once 'conn.php';

$is_logged_in = isset($_SESSION['user_id']);

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: products.php");
    exit();
}

$sql = "SELECT * FROM products WHERE product_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title><?php echo htmlspecialchars($product['product_title']); ?> - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="product-detail-container">
        <div class="product-detail">
            <img class="product-detail-image" 
                 src="<?php echo htmlspecialchars($product['product_src'] ?? 'images/placeholder.jpg'); ?>" 
                 alt="<?php echo htmlspecialchars($product['product_title']); ?>">
            
            <div class="product-detail-info">
                <h1 class="product-detail-title"><?php echo htmlspecialchars($product['product_title']); ?></h1>
                <div class="product-detail-price">£<?php echo number_format($product['product_price'], 2); ?></div>
                
                <?php
                $stock_class = '';
                $stock_text = '';
                $in_stock = true;
                
                switch($product['product_stock']) {
                    case 'good-stock':
                        $stock_class = 'stock-good';
                        $stock_text = ' In Stock';
                        break;
                    case 'low-stock':
                        $stock_class = 'stock-low';
                        $stock_text = ' Low Stock - Order Soon!';
                        break;
                    case 'out-of-stock':
                        $stock_class = 'stock-out';
                        $stock_text = ' Out of Stock';
                        $in_stock = false;
                        break;
                }
                ?>
                
                <div class="product-detail-stock <?php echo $stock_class; ?>">
                    <?php echo $stock_text; ?>
                </div>
                
                <div class="product-detail-description">
                    <strong>Description:</strong><br>
                    <?php echo nl2br(htmlspecialchars($product['product_desc'] ?? 'No description available.')); ?>
                </div>
                
                <a href="products.php" class="back-btn">← Back to Products</a>
                
                <?php if ($in_stock): ?>
                    <?php if ($is_logged_in): ?>
                        <form method="POST" action="products.php" style="display: inline;">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <button type="submit" name="add_to_cart" class="add-cart-btn">Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <a href="index.php#login" class="add-cart-btn" style="text-decoration: none;">Login to Add to Cart</a>
                        <div class="login-warning">
                             Please <a href="index.php#login" style="color: #8E1622;">login</a> to add items to your cart.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="add-cart-btn" disabled style="background-color: #999;">Out of Stock</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>