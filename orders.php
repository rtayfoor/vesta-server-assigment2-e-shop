<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php#login");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$orders = [];

// Get all orders for this user
$sql = "SELECT order_id, user_id, product_ids, order_timestamp FROM tbl_orders WHERE user_id = ? ORDER BY order_timestamp DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $product_ids_str = $row['product_ids'];
        
        $items = array();
        $order_total = 0;
        
        // Parse the product_ids string (format: "product_id:quantity,product_id:quantity")
        $product_items = explode(',', $product_ids_str);
        
        foreach ($product_items as $item) {
            // Skip empty items
            if (empty($item)) continue;
            
            $parts = explode(':', $item);
            if (count($parts) != 2) continue;
            
            $product_id = intval($parts[0]);
            $quantity = intval($parts[1]);
            
            // Use tbl_products table (correct table name)
            $product_sql = "SELECT product_title, product_price, product_src FROM tbl_products WHERE product_id = ?";
            $product_stmt = $conn->prepare($product_sql);
            $product_stmt->bind_param("i", $product_id);
            $product_stmt->execute();
            $product_result = $product_stmt->get_result();
            
            if ($product = $product_result->fetch_assoc()) {
                $subtotal = $product['product_price'] * $quantity;
                $order_total += $subtotal;
                
                $items[] = array(
                    'name' => $product['product_title'],
                    'price' => $product['product_price'],
                    'quantity' => $quantity,
                    'src' => $product['product_src'],
                    'subtotal' => $subtotal
                );
            }
            $product_stmt->close();
        }
        
        // Only add order if it has items
        if (!empty($items)) {
            $orders[] = array(
                'order_id' => $row['order_id'],
                'order_timestamp' => $row['order_timestamp'],
                'items' => $items,
                'order_total' => $order_total
            );
        }
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>My Orders - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="orders-container">
        <h1 class="orders-title">My Orders</h1>
        
        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <p>You haven't placed any orders yet.</p>
                <a href="products.php">Start Shopping →</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <h3>Order #<?php echo $order['order_id']; ?></h3>
                        <div class="order-date"><?php echo date('F j, Y \a\t g:i A', strtotime($order['order_timestamp'])); ?></div>
                    </div>
                    <div class="order-body">
                        <table class="order-items-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>£<?php echo number_format($item['price'], 2); ?></td>
                                        <td>£<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="order-summary">
                            <div class="order-total">Total: £<?php echo number_format($order['order_total'], 2); ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
