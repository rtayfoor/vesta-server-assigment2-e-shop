<?php
session_start();
require_once 'conn.php';

$is_logged_in = isset($_SESSION['user_id']);

$products = [];
$sql = "SELECT product_id, product_title, product_price, product_stock, product_src, product_desc 
        FROM tbl_products 
        ORDER BY product_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
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
    <title>Products - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="products-container">
        
        <?php if (isset($_SESSION['cart_message'])): ?>
            <div class="cart-message" id="cartMessage"><?php echo $_SESSION['cart_message']; unset($_SESSION['cart_message']); ?></div>
        <?php endif; ?>
        
        <?php if (empty($products)): ?>
            <p style="text-align: center;">No products available at the moment.</p>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <img class="product-image" 
                             src="<?php echo htmlspecialchars($product['product_src'] ?? 'images/placeholder.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_title']); ?>">
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['product_title']); ?></h3>
                            <div class="product-price">£<?php echo number_format($product['product_price'], 2); ?></div>
                            
                            <?php
                            $stock_class = '';
                            $stock_text = '';
                            $in_stock = true;
                            
                            switch($product['product_stock']) {
                                case 'good-stock':
                                    $stock_class = 'stock-good';
                                    $stock_text = 'In Stock';
                                    $in_stock = true;
                                    break;
                                case 'low-stock':
                                    $stock_class = 'stock-low';
                                    $stock_text = 'Low Stock';
                                    $in_stock = true;
                                    break;
                                case 'out-of-stock':
                                    $stock_class = 'stock-out';
                                    $stock_text = 'Out of Stock';
                                    $in_stock = false;
                                    break;
                                default:
                                    $stock_class = 'stock-good';
                                    $stock_text = 'In Stock';
                                    $in_stock = true;
                            }
                            ?>
                            
                            <div class="product-stock <?php echo $stock_class; ?>">
                                <?php echo $stock_text; ?>
                            </div>
                            
                            <div class="product-description">
                                <?php 
                                $desc = $product['product_desc'] ?? 'No description available.';
                                echo htmlspecialchars(substr($desc, 0, 100)) . (strlen($desc) > 100 ? '...' : '');
                                ?>
                            </div>
                            
                            <div class="product-actions">
                                <a href="item.php?id=<?php echo $product['product_id']; ?>" class="btn-read-more">
                                    Read More
                                </a>
                                
                                <?php if ($in_stock): ?>
                                    <?php if ($is_logged_in): ?>
                                        <form method="POST" action="cart-actions.php?action=add">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn-add-cart">Add to Cart</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="login.php" class="btn-add-cart" style="text-decoration: none; text-align: center; display: inline-block;">Login to Buy</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn-add-cart" disabled style="background-color: #999; cursor: not-allowed;">Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
