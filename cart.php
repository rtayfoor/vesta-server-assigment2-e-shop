<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php#login");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

if (isset($_COOKIE['cart_data']) && !empty($_COOKIE['cart_data'])) {
    $cart_items = json_decode($_COOKIE['cart_data'], true);
    if (!is_array($cart_items)) {
        $cart_items = [];
    }
} else {
    $cart_items = [];
}

if (isset($_SESSION['cart']) && !empty($_SESSION['cart']) && empty($cart_items)) {
    $cart_items = $_SESSION['cart'];
    setcookie('cart_data', json_encode($cart_items), time() + (86400 * 30), "/", "", false, true);
    unset($_SESSION['cart']);
}

if (isset($_GET['remove'])) {
    $remove_id = intval($_GET['remove']);
    if (isset($cart_items[$remove_id])) {
        unset($cart_items[$remove_id]);
        setcookie('cart_data', json_encode($cart_items), time() + (86400 * 30), "/", "", false, true);
        $_SESSION['cart_message'] = "Item removed from cart.";
        header("Location: cart.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $id => $qty) {
        $id = intval($id);
        $qty = intval($qty);
        if ($qty > 0 && isset($cart_items[$id])) {
            $cart_items[$id]['quantity'] = $qty;
        } elseif ($qty <= 0 && isset($cart_items[$id])) {
            unset($cart_items[$id]);
        }
    }
    setcookie('cart_data', json_encode($cart_items), time() + (86400 * 30), "/", "", false, true);
    $_SESSION['cart_message'] = "Cart updated successfully.";
    header("Location: cart.php");
    exit();
}

$offer_applied = null;
$discount_amount = 0;
$offer_id = null;

if (isset($_POST['apply_offer']) && isset($_POST['offer_code'])) {
    $offer_code = trim($_POST['offer_code']);
    
    $sql = "SELECT * FROM offers WHERE offer_code = ? AND offer_active = 1 AND offer_expiry_date > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $offer_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($offer = $result->fetch_assoc()) {
        $_SESSION['applied_offer'] = $offer;
        $message = "Offer '{$offer['offer_name']}' applied successfully!";
        $message_type = 'success';
    } else {
        $message = "Invalid or expired offer code.";
        $message_type = 'error';
        unset($_SESSION['applied_offer']);
    }
    $stmt->close();
}

if (isset($_GET['remove_offer'])) {
    unset($_SESSION['applied_offer']);
    header("Location: cart.php");
    exit();
}

if (isset($_SESSION['applied_offer'])) {
    $offer_applied = $_SESSION['applied_offer'];
}

$subtotal = 0;
$cart_items_with_details = [];

if (!empty($cart_items)) {
    $ids = array_keys($cart_items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT product_id, product_title, product_price, product_stock FROM products WHERE product_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($product = $result->fetch_assoc()) {
        $id = $product['product_id'];
        $quantity = $cart_items[$id]['quantity'];
        $price = $product['product_price'];
        $subtotal += $price * $quantity;
        
        $cart_items_with_details[$id] = [
            'id' => $id,
            'name' => htmlspecialchars($product['product_title']),
            'price' => $price,
            'quantity' => $quantity,
            'stock' => $product['product_stock'],
            'subtotal' => $price * $quantity
        ];
    }
    $stmt->close();
}

$final_total = $subtotal;
if ($offer_applied) {
    if ($offer_applied['offer_type'] == 'percentage') {
        $discount_amount = $subtotal * ($offer_applied['offer_value'] / 100);
        $final_total = $subtotal - $discount_amount;
    } elseif ($offer_applied['offer_type'] == 'fixed') {
        $discount_amount = min($offer_applied['offer_value'], $subtotal);
        $final_total = $subtotal - $discount_amount;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
    if (empty($cart_items_with_details)) {
        $message = "Your cart is empty. Please add items before checking out.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        
        try {
            $order_total = $final_total;
            
            $sql = "INSERT INTO tbl_orders (user_id, order_date, order_total, order_status, offer_id, discount_amount) 
                    VALUES (?, NOW(), ?, 'pending', ?, ?)";
            $stmt = $conn->prepare($sql);
            $offer_id_val = $offer_applied ? $offer_applied['offer_id'] : null;
            $stmt->bind_param("idsi", $user_id, $order_total, $offer_id_val, $discount_amount);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            $stmt->close();
            
            $sql = "INSERT INTO tbl_order_items (order_id, product_id, quantity, price_at_time) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach ($cart_items_with_details as $item) {
                $stmt->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
                $stmt->execute();
            }
            $stmt->close();
            
            $cart_items = [];
            setcookie('cart_data', json_encode($cart_items), time() - 3600, "/", "", false, true);
            unset($_SESSION['applied_offer']);
            
            $conn->commit();
            
            $_SESSION['checkout_success'] = "Thank you for your order! Order #{$order_id} has been placed successfully. Total: £" . number_format($order_total, 2);
            header("Location: cart.php?checkout_success=1");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error processing your order. Please try again.";
            $message_type = 'error';
        }
    }
}

if (isset($_GET['checkout_success']) && isset($_SESSION['checkout_success'])) {
    $message = $_SESSION['checkout_success'];
    $message_type = 'success';
    unset($_SESSION['checkout_success']);
}

if (isset($_SESSION['cart_message'])) {
    $message = $_SESSION['cart_message'];
    $message_type = 'info';
    unset($_SESSION['cart_message']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="style.css">
    <title>Your Cart - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="cart-container">
        <h1 class="cart-title">🛒 Your Shopping Cart</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($cart_items_with_details)): ?>
            <div class="cart-empty">
                <p>✨ Your cart is empty ✨</p>
                <a href="products.php">Continue Shopping →</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="cart-table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items_with_details as $id => $item): ?>
                                <tr>
                                    <td class="product-name">
                                        <?php echo $item['name']; ?>
                                        <?php if ($item['stock'] == 'low-stock'): ?>
                                            <div class="stock-warning">⚠ Low Stock</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>£<?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <input type="number" name="quantity[<?php echo $id; ?>]" 
                                               value="<?php echo $item['quantity']; ?>" 
                                               min="1" class="quantity-input">
                                    </td>
                                    <td>£<?php echo number_format($item['subtotal'], 2); ?></td>
                                    <td>
                                        <a href="?remove=<?php echo $id; ?>" class="remove-link" onclick="return confirm('Remove this item from your cart?')">✗ Remove</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="cart-summary">
                    <div class="offer-section">
                        <h3>🎁 Apply Offer Code</h3>
                        <div class="offer-form">
                            <input type="text" name="offer_code" placeholder="Enter your offer code" class="offer-input">
                            <button type="submit" name="apply_offer" class="apply-offer-btn">Apply Code</button>
                        </div>
                        
                        <?php if ($offer_applied): ?>
                            <div class="applied-offer">
                                ✅ Offer Applied: <?php echo htmlspecialchars($offer_applied['offer_name']); ?>
                                (<?php echo $offer_applied['offer_type'] == 'percentage' ? $offer_applied['offer_value'] . '% off' : '£' . number_format($offer_applied['offer_value'], 2) . ' off'; ?>)
                                <a href="?remove_offer=1" class="remove-offer-btn" onclick="return confirm('Remove this offer?')">[Remove]</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>£<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <?php if ($discount_amount > 0): ?>
                        <div class="summary-row discount-row">
                            <span>Discount:</span>
                            <span>-£<?php echo number_format($discount_amount, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>£<?php echo number_format($final_total, 2); ?></span>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="products.php" class="continue-btn">← Continue Shopping</a>
                        <button type="submit" name="update_cart" class="update-btn">Update Cart</button>
                        <button type="submit" name="checkout" class="checkout-btn" 
                                onclick="return confirm('Proceed to checkout? Total: £<?php echo number_format($final_total, 2); ?>')">
                            Proceed to Checkout →
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>