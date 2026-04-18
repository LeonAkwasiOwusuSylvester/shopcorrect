<?php
session_start();
require_once __DIR__ . "/../../app/config/db.php";

// 1. Strict Security Check: Only Super Admins can view this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: login.php");
    exit;
}

// 2. Fetch Quick Security Metrics
$metricsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN status = 'failed' AND attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as failed_7d,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as total_success
    FROM admin_login_logs
");
$metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);

$totalAttempts = $metrics['total_attempts'] ?: 0;
$failed7d = $metrics['failed_7d'] ?: 0;
$successRate = $totalAttempts > 0 ? round(($metrics['total_success'] / $totalAttempts) * 100) : 0;

// 3. Fetch the last 100 login attempts
$logsStmt = $pdo->query("SELECT * FROM admin_login_logs ORDER BY attempted_at DESC LIMIT 100");
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Load the Admin Header
require_once __DIR__ . "/includes/header.php";
?>

<style>
    /* Security Dashboard Specific Styles */
    .metric-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        display: flex;
        align-items: center;
        gap: 15px;
        height: 100%;
    }
    
    .metric-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .log-table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 700;
        border-bottom: 2px solid #f1f5f9;
        padding-top: 15px;
        padding-bottom: 15px;
    }

    .log-table td {
        vertical-align: middle;
        font-size: 0.9rem;
        color: #334155;
        border-bottom: 1px solid #f8fafc;
    }

    .ip-badge {
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.85rem;
        background: #f1f5f9;
        color: #475569;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        text-decoration: none;
        transition: 0.2s;
    }

    .ip-badge:hover {
        background: var(--shop-brand);
        color: white;
        border-color: var(--shop-brand);
    }

    .agent-text {
        max-width: 250px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: inline-block;
        font-size: 0.8rem;
        color: #94a3b8;
    }
</style>

<div class="container-fluid">
    
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="metric-card">
                <div class="metric-icon" style="background: #eff6ff; color: #3b82f6;">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Total Logins</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($totalAttempts) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="metric-card">
                <div class="metric-icon" style="background: <?= $failed7d > 0 ? '#fef2f2' : '#f0fdf4' ?>; color: <?= $failed7d > 0 ? '#ef4444' : '#22c55e' ?>;">
                    <i class="bi <?= $failed7d > 0 ? 'bi-shield-x' : 'bi-shield-check' ?>"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Failed (Last 7 Days)</div>
                    <h3 class="fw-bold mb-0 text-dark">
                        <?= number_format($failed7d) ?>
                        <?php if ($failed7d > 0): ?>
                            <span class="badge bg-danger ms-2" style="font-size: 0.6rem; vertical-align: middle;">Warning</span>
                        <?php endif; ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="metric-card">
                <div class="metric-icon" style="background: #f8fafc; color: #0f172a;">
                    <i class="bi bi-activity"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Success Rate</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= $successRate ?>%</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card p-0 overflow-hidden">
        <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-list-columns-reverse me-2"></i> Access History</h5>
                <div class="small text-muted mt-1">Showing the 100 most recent authentication attempts.</div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table log-table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Timestamp</th>
                        <th>Email Attempted</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Device / Browser</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-clipboard-x display-4 d-block mb-3 opacity-25"></i>
                                No login attempts recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= date('M j, Y', strtotime($log['attempted_at'])) ?></div>
                                    <div class="small text-muted"><?= date('h:i:s A', strtotime($log['attempted_at'])) ?></div>
                                </td>
                                
                                <td class="fw-bold" style="color: var(--shop-brand);">
                                    <?= htmlspecialchars($log['email_attempted']) ?>
                                </td>
                                
                                <td>
                                    <a href="https://ipinfo.io/<?= htmlspecialchars($log['ip_address']) ?>" target="_blank" class="ip-badge" title="Click to trace IP location">
                                        <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($log['ip_address']) ?>
                                    </a>
                                </td>
                                
                                <td>
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Success
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                            <i class="bi bi-x-circle-fill me-1"></i> Failed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span class="agent-text" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                        <?= htmlspecialchars($log['user_agent']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php 
// 5. Load the Admin Footer
if (file_exists(__DIR__ . "/includes/footer.php")) {
    require_once __DIR__ . "/includes/footer.php"; 
} else {
    echo "</div><script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script></body></html>";
}
?>