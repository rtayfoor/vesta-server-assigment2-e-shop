<?php
session_start();
require_once 'conn.php';
$is_logged_in = isset($_SESSION['user_id']);
$review_success = '';
$review_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_review']) && $is_logged_in) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $review_desc = trim($_POST['review_desc'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    if ($product_id <= 0) {
        $review_error = "Invalid product selection.";
    } elseif ($rating < 1 || $rating > 5) {
        $review_error = "Please select a rating between 1 and 5 stars.";
    } elseif (empty($review_desc)) {
        $review_error = "Please enter your review text.";
    } elseif (strlen($review_desc) < 10) {
        $review_error = "Review must be at least 10 characters long.";
    } else {
        $sql = "INSERT INTO tbl_reviews (user_id, product_id, review_title, review_desc, review_rating, review_timestamp) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $review_title = substr($review_desc, 0, 50) . (strlen($review_desc) > 50 ? '...' : '');
        $stmt->bind_param("iissi", $user_id, $product_id, $review_title, $review_desc, $rating);
        if ($stmt->execute()) {
            $review_success = "Thank you for your review! It has been submitted successfully.";
            $_POST = array();
        } else {
            $review_error = "Error submitting review. Please try again.";
        }
        $stmt->close();
    }
}

$reviews = [];
$sql = "SELECT r.*, p.product_title, u.user_name 
        FROM tbl_reviews r
        JOIN products p ON r.product_id = p.product_id
        JOIN tbl_users u ON r.user_id = u.user_id
        ORDER BY r.review_timestamp DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}

$products = [];
$product_sql = "SELECT product_id, product_title, product_price FROM products WHERE product_stock != 'out-of-stock'";
$product_result = $conn->query($product_sql);
if ($product_result && $product_result->num_rows > 0) {
    while ($row = $product_result->fetch_assoc()) {
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
    <title>Product Reviews - Student Shop</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="reviews-container">
        <h1 class="page-title">Customer Reviews</h1>
        
        <?php if ($is_logged_in): ?>
            <div class="review-form-container">
                <h2>Write a Review</h2>
                
                <?php if ($review_success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($review_success); ?></div>
                <?php endif; ?>
                
                <?php if ($review_error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($review_error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="product_id">Select Product *</label>
                        <select id="product_id" name="product_id" required>
                            <option value="">-- Choose a product --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['product_id']; ?>">
                                    <?php echo htmlspecialchars($product['product_title']); ?> - £<?php echo $product['product_price']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Rating *</label>
                        <div class="rating-stars">
                            <input type="radio" name="rating" value="5" id="star5" required>
                            <label for="star5">★</label>
                            <input type="radio" name="rating" value="4" id="star4">
                            <label for="star4">★</label>
                            <input type="radio" name="rating" value="3" id="star3">
                            <label for="star3">★</label>
                            <input type="radio" name="rating" value="2" id="star2">
                            <label for="star2">★</label>
                            <input type="radio" name="rating" value="1" id="star1">
                            <label for="star1">★</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="review_desc">Your Review *</label>
                        <textarea id="review_desc" name="review_desc" required 
                                  placeholder="Share your experience with this product... (minimum 10 characters)"></textarea>
                    </div>
                    
                    <button type="submit" name="submit_review" class="submit-btn">Submit Review</button>
                </form>
            </div>
            
        <?php else: ?>
            <div class="login-message">
                <h3>Login to Write Reviews</h3>
                <p>Please <a href="index.php#login">login</a> to share your experience and read customer reviews.</p>
                <p>Only registered users can submit product reviews.</p>
            </div>
        <?php endif; ?>
      
        <div class="reviews-list">
            <h2>What Our Customers Say</h2>
            
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <span class="review-product"><?php echo htmlspecialchars($review['product_title']); ?></span>
                            <div class="review-stars">
                                <?php
                                $rating = intval($review['review_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<span class="filled">★</span>';
                                    } else {
                                        echo '<span class="empty">☆</span>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="review-author">
                            By: <?php echo htmlspecialchars($review['user_name']); ?>
                            <span class="review-date"> | <?php echo date('F j, Y', strtotime($review['review_timestamp'])); ?></span>
                        </div>
                        <div class="review-title"><?php echo htmlspecialchars($review['review_title']); ?></div>
                        <div class="review-text"><?php echo nl2br(htmlspecialchars($review['review_desc'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-reviews">
                    <p>No reviews yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>