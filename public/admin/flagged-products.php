<?php
require_once __DIR__ . "/../../app/config/session.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; 

// --------------------------------------------------
// 1. ADMIN AUTHORIZATION
// --------------------------------------------------
if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], ['supadmin', 'country_agent', 'support'])) {
    header("Location: ../login.php");
    exit;
}

$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// --------------------------------------------------
// 2. HANDLE ACTIONS (APPROVE OR DELETE)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // SECURITY: Support agents cannot approve or delete products
    if ($userRole === 'support') {
        $_SESSION['error_msg'] = "Permission denied. Support agents cannot approve or reject products.";
        header("Location: flagged-products.php");
        exit;
    }

    if (isset($_POST['approve_id']) || isset($_POST['delete_id'])) {
        $targetId = isset($_POST['approve_id']) ? (int) $_POST['approve_id'] : (int) $_POST['delete_id'];

        // SECURITY: Country Agents can only manage products from their assigned country
        if ($userRole === 'country_agent') {
            $chk = $pdo->prepare("
                SELECT u.country 
                FROM products p 
                JOIN vendors v ON p.vendor_id = v.id 
                JOIN users u ON v.user_id = u.id 
                WHERE p.id = ?
            ");
            $chk->execute([$targetId]);
            $vendorCountry = $chk->fetchColumn();
            
            if ($vendorCountry !== $managedCountry) {
                $_SESSION['error_msg'] = "Unauthorized. You can only manage products from vendors in your assigned region.";
                header("Location: flagged-products.php");
                exit;
            }
        }

        // ACTION: APPROVE PRODUCT
        if (isset($_POST['approve_id'])) {
            try {
                // Check stock to determine if it should be active or inactive
                $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                $stmt->execute([$targetId]);
                $stock = $stmt->fetchColumn();
                
                $newStatus = ($stock > 0) ? 'active' : 'inactive';

                // Clear the flagged reason and set to active
                $upd = $pdo->prepare("UPDATE products SET status = ?, flagged_reason = NULL WHERE id = ?");
                $upd->execute([$newStatus, $targetId]);
                
                $_SESSION['success_msg'] = "Product approved and published successfully.";
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Error approving product.";
            }
        }

        // ACTION: DELETE & REJECT PRODUCT
        if (isset($_POST['delete_id'])) {
            try {
                // Soft delete the fake product
                $del = $pdo->prepare("UPDATE products SET is_deleted = 1, status = 'inactive' WHERE id = ?");
                $del->execute([$targetId]);
                
                $_SESSION['success_msg'] = "Fake product rejected and removed.";
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Error deleting product.";
            }
        }

        header("Location: flagged-products.php");
        exit;
    }
}

// --------------------------------------------------
// 3. FETCH FLAGGED PRODUCTS (Filtered by Role)
// --------------------------------------------------
$sql = "
    SELECT p.*, v.shop_name, u.email as vendor_email, u.country
    FROM products p
    JOIN vendors v ON p.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE p.status = 'flagged' AND p.is_deleted = 0
";

$params = [];

// Filter list for Country Agents
if ($userRole === 'country_agent') {
    $sql .= " AND u.country = ?";
    $params[] = $managedCountry;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flaggedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 4. INCLUDE ADMIN HEADER
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php'; 
?>

<style>
    .card-modern {
        background: #fff;
        border: 1px solid var(--card-border, #E2E8F0);
        border-radius: 16px; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .flagged-item-row {
        padding: 1.5rem;
        border-bottom: 1px solid #E2E8F0;
        transition: background-color 0.2s;
    }
    .flagged-item-row:last-child {
        border-bottom: none;
    }
    .flagged-item-row:hover {
        background-color: #F8FAFC;
    }
    .product-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #E2E8F0;
        background: #F1F5F9;
    }
    .reason-box {
        background: #FEF2F2;
        border: 1px solid #FECACA;
        color: #991B1B;
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 0.9rem;
        margin-top: 12px;
        display: flex;
        align-items: start;
        gap: 10px;
    }
    .btn-approve {
        background-color: #10B981;
        color: white;
        border: none;
    }
    .btn-approve:hover {
        background-color: #059669;
        color: white;
    }
</style>

<div class="container-fluid px-4 py-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-shield-lock-fill text-warning me-2"></i> Fraud Detection Center
            </h4>
            <p class="text-muted small mb-0">Review items flagged by the AI engine before they go live on ShopCorrect.</p>
        </div>
        <span class="badge bg-danger rounded-pill fs-6 px-3 py-2 shadow-sm"><?= count($flaggedItems) ?> Pending Reviews</span>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card-modern">
        <?php if (empty($flaggedItems)): ?>
            <div class="text-center py-5">
                <i class="bi bi-shield-check text-success display-1 mb-3 opacity-50 d-block"></i>
                <h5 class="fw-bold text-dark">Platform is Secure</h5>
                <p class="text-muted">There are currently no flagged products waiting for manual review.</p>
            </div>
        <?php else: ?>
            <div>
                <?php foreach ($flaggedItems as $item): 
                    $imgUrl = !empty($item['image']) ? "../../public/uploads/products/" . htmlspecialchars($item['image']) : "https://placehold.co/100x100?text=No+Image";
                ?>
                    <div class="flagged-item-row">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <img src="<?= $imgUrl ?>" alt="Product" class="product-img">
                            </div>
                            
                            <div class="col">
                                <div class="d-flex justify-content-between align-items-start flex-column flex-md-row gap-3">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($item['name']) ?></h5>
                                        <div class="text-muted small mb-2 d-flex flex-wrap gap-3">
                                            <span><i class="bi bi-shop me-1 text-primary"></i> <?= htmlspecialchars($item['shop_name']) ?></span>
                                            <span><i class="bi bi-envelope me-1"></i> <a href="mailto:<?= htmlspecialchars($item['vendor_email']) ?>" class="text-decoration-none"><?= htmlspecialchars($item['vendor_email']) ?></a></span>
                                            <?php if($userRole === 'supadmin'): ?>
                                                <span><i class="bi bi-geo-alt-fill me-1"></i> <?= htmlspecialchars($item['country'] ?? 'Global') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fw-bold text-dark fs-5">
                                            <?= formatPrice($item['price']) ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($userRole !== 'support'): ?>
                                        <div class="d-flex gap-2">
                                            <form method="POST" onsubmit="return confirm('Approve this product? It will immediately become visible to buyers.');">
                                                <input type="hidden" name="approve_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-approve btn-sm px-3 fw-bold rounded-pill shadow-sm">
                                                    <i class="bi bi-check-lg me-1"></i> Approve
                                                </button>
                                            </form>
                                            
                                            <form method="POST" onsubmit="return confirm('Reject and delete this fake product?');">
                                                <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold rounded-pill shadow-sm">
                                                    <i class="bi bi-trash3-fill me-1"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="badge bg-light text-muted border px-3 py-2 rounded-pill shadow-sm">
                                            <i class="bi bi-eye-fill me-1"></i> View Only
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="reason-box">
                                    <i class="bi bi-robot fs-5 mt-1"></i>
                                    <div>
                                        <strong class="d-block mb-1 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">AI Flag Reason</strong>
                                        <?= htmlspecialchars($item['flagged_reason']) ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php 
// --------------------------------------------------
// 5. INCLUDE ADMIN FOOTER
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php'; 
?>