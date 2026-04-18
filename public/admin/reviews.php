<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../../app/config/db.php";

// Strict Role Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: ../login.php");
    exit;
}

// 2. FETCH DATA
$reviews = $pdo->query(
    "SELECT r.id, r.rating, r.comment, p.name as product_name
     FROM reviews r
     JOIN products p ON r.product_id = p.id
     ORDER BY r.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// 3. INCLUDE HEADER (Brings in Sidebar, Top Nav, CSS, etc.)
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

    /* Star Rating Colors */
    .star-filled { color: #F59E0B; }
    .star-empty { color: #E2E8F0; }

    /* Action Buttons */
    .btn-action-form { display: inline-block; margin: 0; }
    .btn-delete { 
        width: 35px; height: 35px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: 1px solid #E2E8F0;
        background: white; color: #64748B;
        transition: 0.2s;
    }
    .btn-delete:hover { background: #FEE2E2; color: #DC2626; border-color: #FECACA; }
</style>

<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Content Moderation</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Customer Reviews</h3>
    </div>
    <div class="bg-white px-3 py-2 rounded-4 shadow-sm border d-inline-flex align-items-center">
        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px;height:24px;">
            <i class="bi bi-chat-right-text small"></i>
        </div>
        <span class="fw-bold small text-muted">Total Reviews: <?= count($reviews) ?></span>
    </div>
</div>

<div class="glass-card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4" style="width: 80px;">ID</th>
                    <th style="width: 25%;">Product</th>
                    <th style="width: 15%;">Rating</th>
                    <th>Customer Comment</th>
                    <th class="text-end pe-4" style="width: 100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted small">
                            <i class="bi bi-chat-square-text fs-1 d-block mb-2 opacity-25"></i>
                            No reviews have been posted yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted">#<?= $r["id"] ?></td>
                            
                            <td>
                                <div class="fw-bold text-dark text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($r["product_name"]) ?>">
                                    <?= htmlspecialchars($r["product_name"]) ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="fw-bold me-2"><?= number_format($r["rating"], 1) ?></span>
                                    <div>
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <?php if($i <= $r["rating"]): ?>
                                                <i class="bi bi-star-fill star-filled small"></i>
                                            <?php else: ?>
                                                <i class="bi bi-star-fill star-empty small"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <p class="mb-0 text-muted small" style="white-space: pre-wrap; line-height: 1.5;">
                                    <?= htmlspecialchars($r["comment"]) ?>
                                </p>
                            </td>

                            <td class="text-end pe-4">
                                <form method="POST" action="../../routes/review-admin.php" class="btn-action-form" onsubmit="return confirm('Are you sure you want to permanently delete this review?');">
                                    <input type="hidden" name="review_id" value="<?= $r["id"] ?>">
                                    <button type="submit" name="delete_review" class="btn-delete shadow-sm" title="Delete Review">
                                        <i class="bi bi-trash3"></i>
                                    </button>
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