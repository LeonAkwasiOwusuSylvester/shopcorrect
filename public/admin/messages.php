<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/mailer.php"; // Bring in the fixed mailer!

// Redirect if not logged in
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent', 'support'])) {
    header("Location: ../login.php");
    exit;
}

$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// 2. HANDLE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $id = (int)$_POST['id'];
        
        try {
            // ONLY Super Admins can permanently delete messages
            if ($_POST['action'] === 'delete' && $userRole === 'supadmin') {
                $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
                $_SESSION['msg'] = "Inquiry removed from database.";
                $_SESSION['msg_type'] = "success";
            } 
            elseif ($_POST['action'] === 'mark_read') {
                $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ? AND status = 'unread'")->execute([$id]);
                exit; // Exit early for background AJAX calls
            }
            // Send Reply Logic
            elseif ($_POST['action'] === 'send_reply') {
                $replyText    = trim($_POST['reply_text']);
                $userEmail    = $_POST['user_email'];
                $userName     = $_POST['user_name'];
                $origSubject  = $_POST['original_subject'];
                $origMessage  = $_POST['original_message'];

                if (!empty($replyText) && !empty($userEmail)) {
                    $subject = "Re: " . $origSubject;
                    $title   = "Support Response";
                    
                    // Format a beautiful email body with their original message quoted
                    $emailBody  = "Hello <strong>" . htmlspecialchars($userName) . "</strong>,<br><br>";
                    $emailBody .= nl2br(htmlspecialchars($replyText));
                    $emailBody .= "<br><br><br><hr style='border:none; border-top:1px solid #e2e8f0;'>";
                    $emailBody .= "<span style='font-size:12px; color:#94a3b8;'><em>On " . date('M d, Y') . ", you wrote:</em><br>";
                    $emailBody .= "<blockquote style='border-left:3px solid #cbd5e1; padding-left:10px; margin-left:0; color:#64748b;'>" . nl2br(htmlspecialchars($origMessage)) . "</blockquote></span>";

                    // Fire the email securely (with native fallback)
                    if (function_exists('sendMail')) {
                        sendMail($userEmail, $subject, $title, $emailBody);
                    } else {
                        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: ShopCorrect Support <support@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                        @mail($userEmail, $subject, $emailBody, $headers);
                    }

                    // Update database status
                    $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?")->execute([$id]);

                    $_SESSION['msg'] = "Your reply has been successfully emailed to " . htmlspecialchars($userName) . ".";
                    $_SESSION['msg_type'] = "success";
                } else {
                    $_SESSION['msg'] = "Failed to send reply. Message cannot be empty.";
                    $_SESSION['msg_type'] = "danger";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['msg'] = "Database error occurred while processing your request.";
            $_SESSION['msg_type'] = "danger";
        }
    }
    
    // Redirect back to avoid form resubmission (skip for AJAX mark_read)
    if (isset($_POST['action']) && $_POST['action'] !== 'mark_read') {
        header("Location: messages.php");
        exit;
    }
}

// 3. FETCH MESSAGES (Filtered by Country if needed)
try {
    $sql = "
        SELECT m.*, u.country 
        FROM contact_messages m
        LEFT JOIN users u ON m.email = u.email
    ";

    $params = [];

    if ($userRole === 'country_agent') {
        // Country agents see messages from users in their country OR guest messages
        $sql .= " WHERE u.country = ? OR u.country IS NULL";
        $params[] = $managedCountry;
    }

    // Float 'unread' messages to the top, then sort by date
    $sql .= " ORDER BY FIELD(m.status, 'unread') DESC, m.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messages = [];
}

// 4. INCLUDE HEADER (Brings in Sidebar, Top Nav, and Global CSS)
require_once __DIR__ . "/includes/header.php";
?>

<style>
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
    .clickable-row { cursor: pointer; transition: 0.2s; }
    .clickable-row:hover { background-color: #F8FAFC; }
    .unread-row { background-color: #FFF9E6 !important; }
    
    .sender-avatar {
        width: 44px; height: 44px;
        background: #EEF2FF; color: #4318FF;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.1rem;
        flex-shrink: 0;
    }

    .badge-status { padding: 6px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 800; text-transform: capitalize; }
    .status-unread { background-color: #FFF9E6; color: #FFB547; }
    .status-read { background-color: #F1F5F9; color: #64748B; }
    .status-replied { background-color: #E6FAF5; color: #05CD99; }

    .btn-action {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: 1px solid #E2E8F0;
        background: white; color: #64748B;
        transition: 0.2s;
    }
    .btn-action:hover { background: #F1F5F9; color: var(--shop-brand); }
    .btn-delete:hover { background: #FEE2E2; color: #DC2626; border-color: #FECACA; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Support Center</h6>
        <h4 class="fw-bold mb-0" style="color: var(--shop-brand);">Customer Inquiries</h4>
    </div>
    <div class="bg-white px-3 py-2 rounded-4 shadow-sm border d-inline-flex align-items-center">
        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px;height:24px;">
            <span class="fw-bold" style="font-size:12px;"><?= count($messages) ?></span>
        </div>
        <span class="fw-bold small text-muted">Total Messages</span>
    </div>
</div>

<?php if (isset($_SESSION['msg'])): ?>
    <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
        <i class="bi bi-check-circle-fill me-2"></i> <strong>Notice:</strong> <?= $_SESSION['msg'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
<?php endif; ?>

<div class="glass-card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Status</th>
                    <th>Sender</th>
                    <th>Subject</th>
                    <th>Preview</th>
                    <th>Received</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted small">Inbox is currently empty.</td></tr>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <tr class="clickable-row <?= ($msg['status'] === 'unread' ? 'unread-row' : '') ?>" onclick="openMessageModal(<?= $msg['id'] ?>, '<?= $msg['status'] ?>', this)">
                            <td class="ps-4">
                                <span class="badge-status status-<?= $msg['status'] ?>" id="badge-<?= $msg['id'] ?>"><?= ucfirst($msg['status']) ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="sender-avatar me-3">
                                        <?= strtoupper(substr($msg['name'] ?? 'G', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0 lh-1"><?= htmlspecialchars($msg['name'] ?? 'Guest') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($msg['email'] ?? '') ?></small>
                                        <?php if ($userRole === 'supadmin' && !empty($msg['country'])): ?>
                                            <div class="small text-muted mt-1"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($msg['country']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($msg['subject'] ?? '') ?></td>
                            <td class="text-muted small">
                                <div class="text-truncate" style="max-width: 250px;">
                                    <?= htmlspecialchars($msg['message'] ?? '') ?>
                                </div>
                            </td>
                            <td class="text-muted small fw-bold">
                                <?= date('M d, h:i A', strtotime($msg['created_at'])) ?>
                            </td>
                            <td class="text-end pe-4" onclick="event.stopPropagation();">
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn-action" onclick="openMessageModal(<?= $msg['id'] ?>, '<?= $msg['status'] ?>', this.closest('tr'))" title="Read">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    
                                    <?php if ($userRole === 'supadmin'): ?>
                                    <form action="messages.php" method="POST" onsubmit="return confirm('Delete this message?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <button type="submit" class="btn-action btn-delete" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($messages as $msg): ?>
<div class="modal fade" id="viewModal<?= $msg['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="sender-avatar" style="width: 44px; height: 44px;"><?= strtoupper(substr($msg['name'] ?? 'G', 0, 1)) ?></div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($msg['subject'] ?? '') ?></h5>
                        <p class="text-muted small mb-0">From: <?= htmlspecialchars($msg['name'] ?? 'Guest') ?> &lt;<?= htmlspecialchars($msg['email'] ?? '') ?>&gt;</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="bg-light p-3 rounded-3 border mb-4 text-dark" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;"><?= htmlspecialchars($msg['message'] ?? '') ?></div>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted">Received: <?= date('F d, Y • h:i A', strtotime($msg['created_at'])) ?></small>
                    
                    <?php if ($msg['status'] !== 'replied'): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 rounded-3" onclick="document.getElementById('replyBox<?= $msg['id'] ?>').classList.toggle('d-none')">
                            <i class="bi bi-reply-fill"></i> Write Reply
                        </button>
                    <?php else: ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-3">
                            <i class="bi bi-check2-all me-1"></i> Already Replied
                        </span>
                    <?php endif; ?>
                </div>

                <div id="replyBox<?= $msg['id'] ?>" class="d-none border-top pt-3 mt-2">
                    <form action="messages.php" method="POST">
                        <input type="hidden" name="action" value="send_reply">
                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                        <input type="hidden" name="user_email" value="<?= htmlspecialchars($msg['email'] ?? '') ?>">
                        <input type="hidden" name="user_name" value="<?= htmlspecialchars($msg['name'] ?? 'Guest') ?>">
                        <input type="hidden" name="original_subject" value="<?= htmlspecialchars($msg['subject'] ?? '') ?>">
                        <input type="hidden" name="original_message" value="<?= htmlspecialchars($msg['message'] ?? '') ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Your Reply to <?= htmlspecialchars($msg['name'] ?? 'Guest') ?></label>
                            <textarea name="reply_text" class="form-control bg-light border-0 p-3" rows="5" placeholder="Type your response here..." required></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn text-white fw-bold px-4 rounded-3" style="background: var(--shop-brand);" onclick="this.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Sending...'; this.style.pointerEvents='none'; this.form.submit();">
                                <i class="bi bi-send-fill me-2"></i> Send Email
                            </button>
                        </div>
                    </form>
                </div>
                
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
    function openMessageModal(id, currentStatus, rowElement) {
        // Show the Modal
        const modal = new bootstrap.Modal(document.getElementById('viewModal' + id));
        modal.show();

        // If message is unread, trigger an AJAX call to mark it as read in the database
        if(currentStatus === 'unread') {
            const formData = new URLSearchParams();
            formData.append('action', 'mark_read');
            formData.append('id', id);

            fetch('messages.php', {
                method: 'POST',
                body: formData,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            }).then(() => {
                // Instantly update the UI so the admin doesn't need to refresh the page
                rowElement.classList.remove('unread-row');
                
                // Update the badge
                const badge = document.getElementById('badge-' + id);
                if(badge) {
                    badge.className = 'badge-status status-read';
                    badge.textContent = 'Read';
                }
                
                // Update the onclick event so it doesn't trigger again
                rowElement.setAttribute('onclick', `openMessageModal(${id}, 'read', this)`);
                
                // Optional: Update the master notification bell in the header silently
                const mainBell = document.querySelector('.pulse-badge');
                if (mainBell) {
                    let currentCount = parseInt(mainBell.innerText);
                    if (currentCount > 1) {
                        mainBell.innerText = currentCount - 1;
                    } else {
                        mainBell.remove(); // Remove bell badge if 0
                    }
                }
            });
        }
    }
</script>

<?php 
// 5. INCLUDE FOOTER
require_once __DIR__ . "/includes/footer.php"; 
?>