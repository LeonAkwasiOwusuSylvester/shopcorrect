<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../../app/config/db.php";

// 1. SESSION & ROLE SECURITY
if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], ['supadmin', 'country_agent', 'support'])) {
    header("Location: login.php");
    exit;
}

$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// 2. HANDLE ACTIONS (Dismiss Complaint or Delete Product)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reportId = (int)$_POST['report_id'];
    
    if ($_POST['action'] === 'dismiss') {
        $stmt = $pdo->prepare("UPDATE product_reports SET status = 'dismissed' WHERE id = ?");
        $stmt->execute([$reportId]);
        $_SESSION['success_msg'] = "Complaint dismissed successfully.";
    } 
    elseif ($_POST['action'] === 'delete_product') {
        
        // SECURITY: Support agents cannot delete products
        if ($userRole === 'support') {
            $_SESSION['error_msg'] = "Permission denied. Support agents cannot delete products. Please escalate this to a Country Agent or Admin.";
        } else {
            $productId = (int)$_POST['product_id'];
            
            // SECURITY: Country Agents can only delete products from their assigned country
            if ($userRole === 'country_agent') {
                $checkStmt = $pdo->prepare("
                    SELECT u.country 
                    FROM products p 
                    JOIN vendors v ON p.vendor_id = v.id 
                    JOIN users u ON v.user_id = u.id 
                    WHERE p.id = ?
                ");
                $checkStmt->execute([$productId]);
                $vendorCountry = $checkStmt->fetchColumn();
                
                if ($vendorCountry !== $managedCountry) {
                    $_SESSION['error_msg'] = "Unauthorized. You can only remove products from vendors in your assigned region.";
                    header("Location: complaints.php");
                    exit;
                }
            }

            // Mark product as deleted
            $stmt1 = $pdo->prepare("UPDATE products SET is_deleted = 1, status = 'inactive' WHERE id = ?");
            $stmt1->execute([$productId]);
            
            // Mark the complaint as reviewed
            $stmt2 = $pdo->prepare("UPDATE product_reports SET status = 'reviewed' WHERE id = ?");
            $stmt2->execute([$reportId]);
            
            $_SESSION['success_msg'] = "Product removed and complaint marked as reviewed.";
        }
    }
    
    header("Location: complaints.php");
    exit;
}

// 3. FETCH PENDING COMPLAINTS (Filtered by Role)
$sql = "
    SELECT r.*, p.name AS product_name, p.image, v.shop_name, v.id AS vendor_id, u.country 
    FROM product_reports r
    JOIN products p ON r.product_id = p.id
    JOIN vendors v ON p.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE r.status = 'pending'
";

$params = [];

// Filter list for Country Agents
if ($userRole === 'country_agent') {
    $sql .= " AND u.country = ?";
    $params[] = $managedCountry;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. INCLUDE HEADER
require_once __DIR__ . '/includes/header.php'; 
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Moderation</h6>
            <h4 class="fw-bold text-dark mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i> Product Complaints</h4>
        </div>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show fw-bold shadow-sm border-0 rounded-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_SESSION['success_msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show fw-bold shadow-sm border-0 rounded-3" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i> <?= htmlspecialchars($_SESSION['error_msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Product</th>
                            <th>Vendor</th>
                            <th>Reason</th>
                            <th>Buyer Notes</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-shield-check opacity-25 d-block mb-2" style="font-size: 3rem;"></i>
                                    <h6 class="fw-bold">No pending complaints.</h6>
                                    <p class="small mb-0">Everything looks good!</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; background: #f8fafc; flex-shrink: 0;">
                                                <?php $img = !empty($report['image']) ? '../public/uploads/products/' . $report['image'] : '../public/assets/images/no-image.png'; ?>
                                                <img src="<?= htmlspecialchars($img) ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Product Image">
                                            </div>
                                            <div>
                                                <a href="../public/product.php?id=<?= $report['product_id'] ?>" target="_blank" class="fw-bold text-dark text-decoration-none">
                                                    <?= htmlspecialchars($report['product_name']) ?>
                                                </a>
                                                <div class="small text-muted font-monospace">ID: #<?= $report['product_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><i class="bi bi-shop me-1 text-primary"></i> <?= htmlspecialchars($report['shop_name']) ?></div>
                                        <?php if($userRole === 'supadmin'): ?>
                                            <div class="small text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($report['country'] ?? 'Global') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger fw-bold text-uppercase">
                                            <?= htmlspecialchars($report['reason']) ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 250px;">
                                        <p class="small text-truncate mb-0" title="<?= htmlspecialchars($report['details']) ?>">
                                            <?= empty($report['details']) ? '<i class="text-muted">No additional details provided</i>' : htmlspecialchars($report['details']) ?>
                                        </p>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark"><?= date('M d, Y', strtotime($report['created_at'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= date("h:i A", strtotime($report['created_at'])) ?></div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <form method="POST" class="d-inline-flex gap-2">
                                            <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                            <input type="hidden" name="product_id" value="<?= $report['product_id'] ?>">
                                            
                                            <button type="submit" name="action" value="dismiss" class="btn btn-sm btn-light fw-bold border text-secondary" title="Dismiss Complaint">
                                                <i class="bi bi-check2 me-1"></i> Dismiss
                                            </button>
                                            
                                            <?php if ($userRole !== 'support'): ?>
                                                <button type="submit" name="action" value="delete_product" class="btn btn-sm btn-danger fw-bold" title="Remove Product" onclick="return confirm('WARNING: Are you sure you want to completely delete this product from the platform?');">
                                                    <i class="bi bi-trash3 me-1"></i> Remove Item
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/includes/footer.php'; 
?>