<?php 
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; 

$baseUrl = defined('BASE_URL') ? BASE_URL : "http://localhost/shopcorrect";

/* --------------------------------------------------
   1. Get Vendor ID
   -------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id, shop_name FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header("Location: /shopcorrect/public/create-shop.php");
    exit;
}

$vendorId = $vendor['id'];
$shopName = $vendor['shop_name'];
$errorMessage = '';
$search = $_GET['search'] ?? '';

/* --------------------------------------------------
   2. Handle Soft Delete
   -------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];
    
    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ? AND is_deleted = 0");
    $checkStmt->execute([$deleteId, $vendorId]);
    $product = $checkStmt->fetch();

    if ($product) {
        try {
            $delStmt = $pdo->prepare("UPDATE products SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND vendor_id = ?");
            $delStmt->execute([$deleteId, $vendorId]);
            $_SESSION['success_msg'] = "Product moved to trash.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $errorMessage = "Something went wrong. Please try again.";
        }
    }
}

/* --------------------------------------------------
   3. Handle Quick Stock Update
   -------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update_id'])) {
    $updateId = (int) $_POST['quick_update_id'];
    $newStock = (int) $_POST['new_stock'];

    if ($newStock < 0) {
        $newStock = 0;
    }

    try {
        $updStmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ? AND vendor_id = ? AND is_deleted = 0");
        $updStmt->execute([$newStock, $updateId, $vendorId]);
        
        if ($updStmt->rowCount() > 0) {
            $_SESSION['success_msg'] = "Stock updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Could not update stock. Product may not exist.";
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

/* --------------------------------------------------
   4. Fetch Products 
   -------------------------------------------------- */
$sql = "SELECT * FROM products WHERE vendor_id = ? AND is_deleted = 0";
$params = [$vendorId];

if ($search) {
    $sql .= " AND (name LIKE ? OR id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

/* --------------------------------------------------
   5. Statistics Logic
   -------------------------------------------------- */
$countStmt = $pdo->prepare("SELECT stock FROM products WHERE vendor_id = ? AND is_deleted = 0");
$countStmt->execute([$vendorId]);
$allProducts = $countStmt->fetchAll();

$totalActive = count($allProducts);
$outOfStock = count(array_filter($allProducts, fn($p) => $p['stock'] <= 0));
$lowStock = count(array_filter($allProducts, fn($p) => $p['stock'] > 0 && $p['stock'] < 5));

// --------------------------------------------------
// 6. Include Header
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Page Specific Styles */
    .stat-card { background: white; border-radius: 12px; border: 1px solid var(--card-border); padding: 1.2rem 1.5rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
    .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .stat-label { font-size: 0.85rem; color: #64748B; font-weight: 600; text-transform: uppercase; }
    .stat-value { font-size: 1.75rem; font-weight: 800; color: var(--primary-accent); line-height: 1; margin-top: 5px; }

    .table-card { background: white; border-radius: 12px; border: 1px solid var(--card-border); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .table-custom thead th { background: #F8FAFC; color: #64748B; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; padding: 1rem 1.5rem; border-bottom: 1px solid var(--card-border); }
    .table-custom tbody td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--card-border); vertical-align: middle; font-size: 0.9rem; }
    .table-custom tr:last-child td { border-bottom: none; }
    
    .thumb-box { width: 45px; height: 45px; border-radius: 8px; background: #F1F5F9; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
    .thumb-box img { width: 100%; height: 100%; object-fit: cover; }
    
    .badge-subtle { padding: 0.4em 0.8em; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
    .bg-subtle-success { background-color: #DCFCE7; color: #166534; }
    .bg-subtle-warning { background-color: #FEF9C3; color: #854D0E; }
    .bg-subtle-danger { background-color: #FEE2E2; color: #991B1B; }

    .search-input { border-radius: 50px; border: 1px solid var(--card-border); padding: 0.5rem 1rem 0.5rem 2.5rem; background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E") no-repeat 12px center; width: 250px; }
    
    .btn-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; color: #64748B; transition: 0.2s; background: #F8FAFC; border: 1px solid transparent; cursor: pointer; }
    .btn-action:hover { border-color: #CBD5E1; color: var(--primary-accent); }
    .btn-action.qr:hover { background: #F3E8FF; color: #9333EA; border-color: #E9D5FF; }
    .btn-action.share:hover { background: #ECFCCB; color: #16A34A; border-color: #BBF7D0; }
    .btn-action.stock:hover { background: #E0F2FE; color: #2563EB; border-color: #BFDBFE; }
    .btn-action.delete:hover { background: #FEF2F2; color: #DC2626; border-color: #FECACA; }
</style>

<div class="container-fluid px-4 py-4">

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Items</div>
                    <div class="stat-value"><?= $totalActive ?></div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-box-seam"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label text-warning">Low Stock</div>
                    <div class="stat-value"><?= $lowStock ?></div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label text-danger">Out of Stock</div>
                    <div class="stat-value"><?= $outOfStock ?></div>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <form action="" method="GET" class="d-flex">
            <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
        </form>
        <a href="add-product.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="bi bi-plus-lg me-2"></i> Add Product
        </a>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4"><?= $errorMessage ?></div>
    <?php endif; ?>

    <?php if (!$products): ?>
        <div class="table-card text-center py-5">
            <div class="mb-3 opacity-25"><i class="bi bi-inbox display-1"></i></div>
            <h5 class="fw-bold text-dark">No products found</h5>
            <p class="text-muted mb-4">Adjust your search or add a new item.</p>
            <?php if($search): ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Clear Search</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-custom m-0">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Product</th>
                            <th style="width: 20%;">Price</th>
                            <th style="width: 25%;">Availability</th>
                            <th style="width: 15%;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <?php 
                            $basePrice = (float) $p['price'];
                            $salePrice = (float) $p['sale_price'];
                            $isOnSale  = ($p['discount_percent'] > 0 && $salePrice > 0);
                            $stock = (int)$p['stock'];

                            // Image Logic
                            $dbImage = $p['image'];
                            $imagePath = ''; 
                            if (!empty($dbImage)) {
                                $serverPathNew = __DIR__ . '/../uploads/products/' . $dbImage;
                                if (file_exists($serverPathNew)) $imagePath = '../uploads/products/' . $dbImage;
                                else $imagePath = '../uploads/' . $dbImage;
                            }

                            // Dynamic Marketing Link
                            $productShareUrl = $baseUrl . "/public/product.php?id=" . $p['id'];
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="thumb-box">
                                        <?php if ($imagePath): ?>
                                            <img src="<?= htmlspecialchars($imagePath) ?>" alt="Product">
                                        <?php else: ?>
                                            <i class="bi bi-image text-muted opacity-50"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($p['name']) ?>">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </div>
                                        <div class="text-muted small font-monospace">ID: #<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($isOnSale): ?>
                                    <div class="fw-bold text-dark"><?= formatPrice($salePrice) ?></div>
                                    <div class="text-muted small text-decoration-line-through"><?= formatPrice($basePrice) ?></div>
                                <?php else: ?>
                                    <div class="fw-bold text-dark"><?= formatPrice($basePrice) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['status'] === 'flagged'): ?>
                                    <span class="badge-subtle bg-subtle-danger" title="<?= htmlspecialchars($p['flagged_reason']) ?>">
                                        <i class="bi bi-shield-exclamation me-1"></i>Flagged / Review
                                    </span>
                                <?php else: ?>
                                    <?php if ($stock >= 5): ?>
                                        <span class="badge-subtle bg-subtle-success"><i class="bi bi-check-circle me-1"></i>In Stock</span>
                                    <?php elseif ($stock > 0): ?>
                                        <span class="badge-subtle bg-subtle-warning"><i class="bi bi-exclamation-circle me-1"></i>Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge-subtle bg-subtle-danger"><i class="bi bi-x-circle me-1"></i>Sold Out</span>
                                    <?php endif; ?>
                                    <span class="text-muted small ms-1">(<?= $stock ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    
                                    <?php if ($p['status'] !== 'flagged'): ?>
                                    <button type="button" class="btn-action share" title="Copy Marketing Link" onclick="copyProductLink('<?= $productShareUrl ?>', this)">
                                        <i class="bi bi-link-45deg fs-5"></i>
                                    </button>
                                    <?php endif; ?>

                                    <button type="button" class="btn-action qr" title="Print Authenticity QR" onclick="openQrModal('<?= htmlspecialchars($p['qr_path'] ?? '') ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>', '<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?>')">
                                        <i class="bi bi-qr-code-scan"></i>
                                    </button>

                                    <?php if ($p['status'] !== 'flagged'): ?>
                                    <button type="button" class="btn-action stock" title="Quick Update Stock" onclick="openStockModal(<?= $p['id'] ?>, <?= $stock ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')">
                                        <i class="bi bi-layers"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <a href="edit-product.php?id=<?= $p['id'] ?>" class="btn-action" title="Edit Product Details">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to move this item to trash?');">
                                        <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-action delete" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-top bg-light text-end">
                <small class="text-muted">Showing <?= count($products) ?> items</small>
            </div>
        </div>
    <?php endif; ?>

</div> 

<div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 pb-0">
                <h6 class="modal-title fw-bold text-dark">Update Stock</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3" id="stockProductName" class="text-truncate" style="max-width: 100%;"></p>
                
                <form method="POST" action="">
                    <input type="hidden" name="quick_update_id" id="quickUpdateId">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-dark">New Quantity</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-box"></i></span>
                            <input type="number" class="form-control border-start-0" name="new_stock" id="quickUpdateStock" min="0" required>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary rounded-pill fw-semibold py-2">Save Quantity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-qr-code me-2"></i>Product Authenticity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="p-3 border rounded-3 mb-4 bg-white shadow-sm" style="border-style: dashed !important;">
                    <img id="qrImageDisplay" src="" alt="QR Code" style="width: 150px; height: 150px; object-fit: contain;">
                    <div class="mt-3 fw-bold text-dark small text-truncate px-2" id="qrProductName"></div>
                    <div class="text-muted font-monospace mt-1" style="font-size: 0.75rem;" id="qrProductId"></div>
                    <div class="mt-2 pt-2 border-top text-uppercase text-success" style="font-size: 0.65rem; font-weight: 800; letter-spacing: 1px;">Scan to Verify</div>
                </div>
                
                <button type="button" class="btn btn-dark rounded-pill fw-semibold w-100 py-2" onclick="printQrLabel()">
                    <i class="bi bi-printer me-2"></i> Print Sticker Label
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // PROFESSIONAL FALLBACK COPY LOGIC ADDED
    function copyProductLink(link, btn) {
        const performCopy = new Promise((resolve) => {
            // Modern secure browser context (HTTPS or True Localhost)
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(link).then(resolve);
            } else {
                // Legacy Fallback for local HTTP networks (e.g. 192.168.x.x)
                let textArea = document.createElement("textarea");
                textArea.value = link;
                textArea.style.position = "fixed"; 
                textArea.style.left = "-999999px"; 
                textArea.style.top = "-999999px"; 
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                } catch (err) {
                    console.error('Fallback Copy Failed:', err);
                }
                textArea.remove();
                resolve();
            }
        });

        performCopy.then(() => {
            const icon = btn.querySelector('i');
            const originalClass = icon.className;
            
            // Visual success feedback
            icon.className = 'bi bi-check-circle-fill text-success fs-5';
            btn.style.backgroundColor = '#DCFCE7';
            btn.style.borderColor = '#22C55E';
            
            setTimeout(() => {
                icon.className = originalClass;
                btn.style.backgroundColor = '';
                btn.style.borderColor = '';
            }, 2500);
        });
    }

    function openStockModal(productId, currentStock, productName) {
        document.getElementById('quickUpdateId').value = productId;
        document.getElementById('quickUpdateStock').value = currentStock;
        
        let displayTitle = productName.length > 30 ? productName.substring(0, 30) + '...' : productName;
        document.getElementById('stockProductName').innerText = "Item: " + displayTitle;
        
        new bootstrap.Modal(document.getElementById('stockModal')).show();
    }

    function openQrModal(qrPath, productName, productId) {
        if (!qrPath) {
            alert('This product does not have a QR code yet. Please click Edit and save the product again to automatically generate one.');
            return;
        }
        document.getElementById('qrImageDisplay').src = '../uploads/qrcodes/' + qrPath;
        document.getElementById('qrProductName').innerText = productName;
        document.getElementById('qrProductId').innerText = 'SKU: SC-' + productId;
        
        new bootstrap.Modal(document.getElementById('qrModal')).show();
    }

    function printQrLabel() {
        const qrImgSrc = document.getElementById('qrImageDisplay').src;
        const pName = document.getElementById('qrProductName').innerText;
        const pSku = document.getElementById('qrProductId').innerText;

        const printWindow = window.open('', '_blank', 'width=400,height=500');
        let html = '<html><head><title>Print Label - ' + pSku + '</title>';
        html += '<style>';
        html += 'body { font-family: "Arial", sans-serif; display: flex; justify-content: center; margin: 0; padding: 20px; } ';
        html += '.label-box { text-align: center; border: 2px solid #000; padding: 20px; width: 220px; border-radius: 8px; } ';
        html += 'img { width: 160px; height: 160px; margin-bottom: 12px; } ';
        html += '.product-name { font-weight: bold; font-size: 14px; color: #000; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; } ';
        html += '.sku { font-size: 12px; color: #333; margin-bottom: 12px; font-family: monospace; } ';
        html += '.verify-tag { font-size: 11px; font-weight: bold; text-transform: uppercase; background: #000; color: #fff; padding: 4px 0; display: block; border-radius: 4px; letter-spacing: 1px; }';
        html += '</style></head><body>';
        
        html += '<div class="label-box">';
        html += '<img src="' + qrImgSrc + '" alt="QR" onload="window.setTimeout(function(){ window.print(); window.close(); }, 500);">';
        html += '<div class="product-name">' + pName + '</div>';
        html += '<div class="sku">' + pSku + '</div>';
        html += '<div class="verify-tag">ShopCorrect Authentic</div>';
        html += '</div></body></html>';

        printWindow.document.write(html);
        printWindow.document.close();
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>