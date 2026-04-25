<?php
session_start();
require_once 'conn.php';

$is_logged_in = isset($_SESSION['user_id']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    if (!$is_logged_in) {
        header("Location: index.php#login");
        exit();
    }
    
    $product_id = intval($_POST['product_id']);
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $sql = "SELECT product_id, product_title, product_price FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity']++;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => $row['product_id'],
                'name' => $row['product_title'],
                'price' => $row['product_price'],
                'quantity' => 1
            ];
        }
        $_SESSION['cart_message'] = "Product added to cart!";
    }
    $stmt->close();
}

$products = [];
$sql = "SELECT product_id, product_title, product_price, product_stock, product_src, product_desc 
        FROM products 
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
            <div class="cart-message"><?php echo $_SESSION['cart_message']; unset($_SESSION['cart_message']); ?></div>
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
                                    $stock_text = ' In Stock';
                                    break;
                                case 'low-stock':
                                    $stock_class = 'stock-low';
                                    $stock_text = ' Low Stock';
                                    break;
                                case 'out-of-stock':
                                    $stock_class = 'stock-out';
                                    $stock_text = ' Out of Stock';
                                    $in_stock = false;
                                    break;
                                default:
                                    $stock_class = 'stock-good';
                                    $stock_text = ' In Stock';
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
                                        <form method="POST" action="" style="flex: 1;">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" name="add_to_cart" class="btn-add-cart">
                                                Add to Cart
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="index.php#login" class="btn-add-cart" style="text-decoration: none; text-align: center; display: inline-block;">
                                            Login to Buy
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn-add-cart" disabled>Out of Stock</button>
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