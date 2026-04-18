<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

// 1. Check Authentication
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION["user_id"];

// 2. Validate Order ID
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die("Invalid Order Request.");
}

$orderId = (int)$_GET['order_id'];

// 3. Fetch Order Items
// Logic: Removed order_id check from results to match your DB schema
$stmt = $pdo->prepare("
    SELECT oi.product_id, p.name, p.image, p.price 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.id = ? AND o.user_id = ? AND o.status = 'delivered'
");
$stmt->execute([$orderId, $userId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$products) {
    die("No delivered products found for this order to review.");
}

// 4. Handle Review Submission
$showSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reviews'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['reviews'] as $productId => $data) {
            $rating = (int)$data['rating'];
            $comment = trim($data['comment']);
            
            if ($rating >= 1 && $rating <= 5) {
                // Logic Fix: Check duplicate based only on user_id and product_id
                $check = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
                $check->execute([$userId, $productId]);
                
                if (!$check->fetch()) {
                    // Logic Fix: Removed order_id from INSERT to match your DB structure
                    $ins = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $ins->execute([$userId, $productId, $rating, $comment]);
                }
            }
        }
        
        $pdo->commit();
        $showSuccess = true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error saving reviews: " . $e->getMessage();
    }
}

require_once __DIR__ . "/partials/navbar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Your Order | ShopCorrect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .review-card { border-radius: 16px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: 0.2s; }
        .review-card:hover { transform: translateY(-3px); }
        
        .star-rating { direction: rtl; display: flex; justify-content: flex-end; gap: 8px; }
        .star-rating input { display: none; }
        .star-rating label { cursor: pointer; font-size: 2.2rem; color: #e2e8f0; transition: 0.2s; margin: 0; }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label { color: #f59e0b; }

        .product-thumb { width: 85px; height: 85px; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0; }
        .char-counter { font-size: 0.75rem; color: #94a3b8; text-align: right; margin-top: 5px; }
        .brand-footer { border-top: 1px solid #e2e8f0; margin-top: 4rem; padding-top: 2rem; }
        .btn-submit { background-color: #0B2447; color: white; padding: 12px 30px; border: none; }
        .btn-submit:hover { background-color: #19376D; color: white; }
    </style>
</head>
<body>

<div class="container py-5" style="max-width: 750px;">
    <div class="mb-4">
        <a href="my-orders.php" class="text-decoration-none text-muted small fw-bold">
            <i class="bi bi-arrow-left"></i> BACK TO ORDERS
        </a>
        <h2 class="fw-bold mt-2 mb-0">Review Purchase</h2>
        <p class="text-secondary small">Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?></p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger rounded-3"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" id="reviewForm">
        <?php foreach ($products as $prod): ?>
            <?php
                $dbImage = $prod['image'];
                $imagePath = 'assets/images/placeholder.png'; 
                if (!empty($dbImage)) {
                    $paths = ['uploads/products/' . $dbImage, 'uploads/' . $dbImage];
                    foreach ($paths as $path) {
                        if (file_exists(__DIR__ . '/' . $path)) {
                            $imagePath = $path;
                            break;
                        }
                    }
                }
            ?>
            <div class="card review-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <img src="<?= $imagePath ?>" class="product-thumb me-3" alt="Product">
                        <div>
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($prod['name']) ?></h6>
                            <span class="badge bg-light text-dark border fw-normal">₵<?= number_format($prod['price'] ?? 0, 2) ?></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted">Overall Rating</label>
                        <div class="star-rating">
                            <?php for($s=5; $s>=1; $s--): ?>
                                <input type="radio" id="s<?= $s ?>-<?= $prod['product_id'] ?>" name="reviews[<?= $prod['product_id'] ?>][rating]" value="<?= $s ?>" required>
                                <label for="s<?= $s ?>-<?= $prod['product_id'] ?>" class="bi bi-star-fill"></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase text-muted">Your Review</label>
                        <textarea 
                            name="reviews[<?= $prod['product_id'] ?>][comment]" 
                            class="form-control border-0 bg-light p-3" 
                            rows="3" 
                            maxlength="200"
                            placeholder="How was the product quality and delivery?"
                            oninput="updateCount(this, <?= $prod['product_id'] ?>)"
                        ></textarea>
                        <div class="char-counter"><span id="count-<?= $prod['product_id'] ?>">0</span>/200 characters</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="d-grid gap-2 mt-4">
            <button type="submit" name="submit_reviews" class="btn btn-submit btn-lg rounded-pill fw-bold shadow-sm">
                Submit All Reviews
            </button>
            <a href="my-orders.php" class="btn btn-link text-muted fw-bold">Cancel</a>
        </div>
    </form>

    <div class="brand-footer text-center mb-5">
        <h6 class="fw-bold text-muted mb-2">Thank you for shopping with ShopCorrect!</h6>
        <div class="small text-secondary">
            www.shopcorrect.com &bull; support@shopcorrect.com
        </div>
    </div>
</div>

<script>
    function updateCount(textarea, id) {
        const counter = document.getElementById(`count-${id}`);
        counter.innerText = textarea.value.length;
    }

    <?php if ($showSuccess): ?>
    Swal.fire({
        title: 'Reviews Submitted!',
        text: 'Your feedback helps the ShopCorrect community.',
        icon: 'success',
        confirmButtonColor: '#0B2447',
        confirmButtonText: 'Back to Orders',
        timer: 3500,
        timerProgressBar: true,
        willClose: () => {
            window.location.href = 'my-orders.php';
        }
    });
    <?php endif; ?>
</script>

</body>
</html>