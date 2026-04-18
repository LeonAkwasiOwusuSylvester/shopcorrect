<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../../app/config/db.php";

// Standard Admin Guard check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: ../login.php");
    exit;
}

// 2. FETCH DATA
$products = $pdo->query(
    "SELECT p.id, p.name, p.status, v.shop_name
     FROM products p
     JOIN vendors v ON p.vendor_id = v.id
     ORDER BY p.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// 3. INCLUDE HEADER (Brings in Sidebar, CSS, Translation, etc.)
require_once __DIR__ . "/includes/header.php";
?>

<style>
    .table-responsive { overflow: visible !important; }
    .table thead th { 
        background-color: #FAFCFF; 
        border-bottom: 1px solid #E2E8F0; 
        color: #A3AED0; 
        font-weight: 700; 
        text-transform: uppercase; 
        font-size: 0.75rem; 
        letter-spacing: 0.05em; 
        padding: 1.2rem 1.5rem; 
    }
    .table tbody td { 
        padding: 1.2rem 1.5rem; 
        vertical-align: middle; 
        font-size: 0.9rem;
        color: var(--text-color);
        border-bottom: 1px solid #F4F7FE;
    }

    /* Status Badges */
    .badge-status { padding: 6px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 800; text-transform: capitalize; display: inline-block; text-align: center; min-width: 80px;}
    .status-active { background: #E6FAF5; color: #05CD99; border: 1px solid #bbf7d0; }
    .status-disabled, .status-inactive { background: #FEE2E2; color: #E31A1A; border: 1px solid #fecaca; }
    .status-pending { background: #FFF9E6; color: #FFB547; border: 1px solid #fef08a; }

    /* Action Buttons */
    .btn-action-form { display: inline-block; margin: 0; }
    .btn-toggle-status { 
        font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: 0.2s;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Catalog Management</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Platform Products</h3>
    </div>
    <div class="bg-white px-3 py-2 rounded-4 shadow-sm border d-inline-flex align-items-center">
        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px;height:24px;">
            <i class="bi bi-box-seam small"></i>
        </div>
        <span class="fw-bold small text-muted">Total Products: <?= count($products) ?></span>
    </div>
</div>

<div class="glass-card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Product ID</th>
                    <th>Product Name</th>
                    <th>Vendor / Shop</th>
                    <th>Current Status</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted small">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                            No products found on the platform yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted">#<?= $p["id"] ?></td>
                            
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p["name"]) ?></div>
                            </td>
                            
                            <td>
                                <div class="fw-bold text-secondary notranslate">
                                    <i class="bi bi-shop me-1"></i> <?= htmlspecialchars($p["shop_name"]) ?>
                                </div>
                            </td>
                            
                            <td>
                                <?php 
                                    $statusClass = strtolower(trim($p["status"]));
                                    if ($statusClass === '') $statusClass = 'pending';
                                ?>
                                <span class="badge-status status-<?= $statusClass ?>">
                                    <?= htmlspecialchars($p["status"]) ?>
                                </span>
                            </td>

                            <td class="text-end pe-4">
                                <form method="POST" action="../../routes/product-admin.php" class="btn-action-form">
                                    <input type="hidden" name="product_id" value="<?= $p["id"] ?>">

                                    <?php if (strtolower($p["status"]) === "active"): ?>
                                        <button type="submit" name="disable_product" class="btn btn-outline-danger btn-toggle-status shadow-sm" title="Disable this product">
                                            <i class="bi bi-slash-circle me-1"></i> Disable
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="enable_product" class="btn btn-outline-success btn-toggle-status shadow-sm" title="Approve and enable this product">
                                            <i class="bi bi-check-circle me-1"></i> Enable
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

<?php 
// 4. INCLUDE FOOTER
require_once __DIR__ . "/includes/footer.php"; 
?>