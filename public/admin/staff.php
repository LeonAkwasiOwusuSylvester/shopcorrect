<?php
require_once __DIR__ . "/../../app/config/db.php";

// 1. SECURITY: Only Super Admins can manage staff
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: ../login.php");
    exit;
}

$successMsg = '';
$errorMsg = '';

// =========================================================
// 2. ACTION HANDLERS
// =========================================================

// A. Add New Staff Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['temp_password'];
    $role = $_POST['role'];
    $country = ($role === 'country_agent') ? $_POST['managed_country'] : null;

    if (empty($name) || empty($email) || empty($password)) {
        $errorMsg = "All core fields (Name, Email, Password) are required.";
    } else {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $errorMsg = "An account with this email already exists.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, managed_country, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $hash, $role, $country]);
                $successMsg = "Staff member added successfully!";
            } catch (PDOException $e) {
                $errorMsg = "System Error: " . $e->getMessage();
            }
        }
    }
}

// B. Remove Staff Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_staff') {
    $staffId = (int)$_POST['staff_id'];
    
    // Prevent the super admin from deleting themselves!
    if ($staffId === $_SESSION['user_id']) {
        $errorMsg = "You cannot delete your own Super Admin account.";
    } else {
        try {
            // Instead of deleting the record completely, we downgrade them to a standard user 
            // so their past activity doesn't break the database.
            $stmt = $pdo->prepare("UPDATE users SET role = 'user', managed_country = NULL WHERE id = ? AND role IN ('supadmin', 'country_agent', 'support')");
            $stmt->execute([$staffId]);
            $successMsg = "Staff privileges revoked successfully.";
        } catch (PDOException $e) {
            $errorMsg = "Error updating record: " . $e->getMessage();
        }
    }
}

// =========================================================
// 3. FETCH STAFF DATA
// =========================================================
$staffMembers = [];
$stats = ['total' => 0, 'country_agents' => 0, 'support' => 0];

try {
    $stmt = $pdo->query("
        SELECT id, name, email, role, managed_country, created_at 
        FROM users 
        WHERE role IN ('supadmin', 'country_agent', 'support') 
        ORDER BY FIELD(role, 'supadmin', 'country_agent', 'support'), created_at DESC
    ");
    $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($staffMembers as $staff) {
        $stats['total']++;
        if ($staff['role'] === 'country_agent') $stats['country_agents']++;
        if ($staff['role'] === 'support') $stats['support']++;
    }
} catch (PDOException $e) {
    $errorMsg = "Failed to load staff data.";
}

// 4. INCLUDE HEADER
require_once __DIR__ . "/includes/header.php";
?>

<style>
    .metric-card { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 15px; height: 100%; }
    .metric-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
    
    .table thead th { background-color: #FAFCFF; color: #A3AED0; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; padding: 1.2rem 1.5rem; border-bottom: 1px solid #E2E8F0; }
    .table tbody td { padding: 1rem 1.5rem; vertical-align: middle; font-size: 0.9rem; color: var(--text-color); border-bottom: 1px solid #F4F7FE; }
    
    .role-badge { padding: 5px 12px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 5px; }
    .role-supadmin { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .role-country { background: #e0e7ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .role-support { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }

    .avatar-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; flex-shrink: 0; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Access Management</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Staff & Agents</h3>
    </div>
    <button class="btn btn-primary shadow-sm fw-bold border-0 py-2 px-4 rounded-3" style="background: var(--shop-brand);" data-bs-toggle="modal" data-bs-target="#addStaffModal">
        <i class="bi bi-person-plus-fill me-2"></i> Add New Staff
    </button>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon" style="background: #f1f5f9; color: #475569;"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="text-muted small fw-bold text-uppercase">Total Team</div>
                <h4 class="fw-bold mb-0 text-dark"><?= $stats['total'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon" style="background: #eff6ff; color: #3b82f6;"><i class="bi bi-globe-americas"></i></div>
            <div>
                <div class="text-muted small fw-bold text-uppercase">Country Agents</div>
                <h4 class="fw-bold mb-0 text-dark"><?= $stats['country_agents'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon" style="background: #fdf4ff; color: #9333ea;"><i class="bi bi-headset"></i></div>
            <div>
                <div class="text-muted small fw-bold text-uppercase">Support Agents</div>
                <h4 class="fw-bold mb-0 text-dark"><?= $stats['support'] ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="glass-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Team Member</th>
                    <th>Role</th>
                    <th>Assigned Region</th>
                    <th>Date Added</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffMembers as $staff): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <?php
                                    $colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#6366f1'];
                                    $color = $colors[strlen($staff['name']) % 5];
                                    $initials = strtoupper(substr($staff['name'], 0, 2));
                                ?>
                                <div class="avatar-circle" style="background-color: <?= $color ?>;"><?= $initials ?></div>
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($staff['name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($staff['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        
                        <td>
                            <?php if ($staff['role'] === 'supadmin'): ?>
                                <span class="role-badge role-supadmin"><i class="bi bi-star-fill"></i> Super Admin</span>
                            <?php elseif ($staff['role'] === 'country_agent'): ?>
                                <span class="role-badge role-country"><i class="bi bi-map-fill"></i> Country Agent</span>
                            <?php elseif ($staff['role'] === 'support'): ?>
                                <span class="role-badge role-support"><i class="bi bi-headset"></i> Support</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($staff['role'] === 'country_agent' && !empty($staff['managed_country'])): ?>
                                <span class="badge bg-light text-dark border"><i class="bi bi-geo-alt-fill text-primary me-1"></i> <?= htmlspecialchars($staff['managed_country']) ?></span>
                            <?php elseif ($staff['role'] === 'supadmin'): ?>
                                <span class="text-muted small">Global (All Regions)</span>
                            <?php else: ?>
                                <span class="text-muted small">Platform Wide</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-muted small">
                            <?= date("M j, Y", strtotime($staff['created_at'])) ?>
                        </td>

                        <td class="text-end pe-4">
                            <?php if ($staff['id'] !== $_SESSION['user_id']): // Don't let superadmin delete themselves ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to revoke access for this user? They will become a standard customer.');">
                                    <input type="hidden" name="action" value="delete_staff">
                                    <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                    <button type="submit" class="btn btn-light text-danger btn-sm rounded-circle p-2 border-0" title="Revoke Access">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-secondary opacity-50">You</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" style="color: var(--shop-brand);">Add Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" action="staff.php">
                <input type="hidden" name="action" value="add_staff">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Full Name</label>
                            <input type="text" name="name" class="form-control bg-light" placeholder="e.g. John Doe" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Email Address</label>
                            <input type="email" name="email" class="form-control bg-light" placeholder="agent@shopcorrect.com" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Assign Role</label>
                            <select name="role" id="roleSelector" class="form-select bg-light fw-bold" required>
                                <option value="" disabled selected>Select a role...</option>
                                <option value="country_agent">🌍 Country Agent (Manages logistics for a specific nation)</option>
                                <option value="support">🎧 Support Agent (Handles messages & inquiries)</option>
                                <option value="supadmin">⭐ Super Admin (Full system access)</option>
                            </select>
                        </div>

                        <div class="col-12 d-none" id="countrySelectorDiv">
                            <label class="form-label small fw-bold text-primary">Assign Country</label>
                            <select name="managed_country" id="managedCountryInput" class="form-select border-primary bg-primary bg-opacity-10 fw-bold">
                                <option value="" disabled selected>Which country are they managing?</option>
                                <optgroup label="Africa">
                                    <option value="Ghana">Ghana</option>
                                    <option value="Nigeria">Nigeria</option>
                                    <option value="Cote d'Ivoire">Cote d'Ivoire</option>
                                    <option value="South Africa">South Africa</option>
                                    <option value="Kenya">Kenya</option>
                                    <option value="Togo">Togo</option>
                                </optgroup>
                                <optgroup label="International">
                                    <option value="United Kingdom">United Kingdom</option>
                                    <option value="United States">United States</option>
                                    <option value="Canada">Canada</option>
                                    <option value="Germany">Germany</option>
                                    <option value="China">China</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Temporary Password</label>
                            <div class="input-group">
                                <input type="text" name="temp_password" id="tempPass" class="form-control bg-light" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="generatePassword()">Generate</button>
                            </div>
                            <div class="form-text small">Give this password to the agent. They can change it later.</div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light fw-bold px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-3" style="background: var(--shop-brand); border: none;">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Logic to show/hide the country dropdown based on the selected role
    document.getElementById('roleSelector').addEventListener('change', function() {
        const countryDiv = document.getElementById('countrySelectorDiv');
        const countryInput = document.getElementById('managedCountryInput');
        
        if (this.value === 'country_agent') {
            countryDiv.classList.remove('d-none');
            countryInput.setAttribute('required', 'required');
        } else {
            countryDiv.classList.add('d-none');
            countryInput.removeAttribute('required');
            countryInput.value = ""; // Reset value
        }
    });

    // Helper to generate a random secure password
    function generatePassword() {
        const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
        let password = "";
        for (let i = 0; i < 10; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('tempPass').value = password;
    }
</script>

<?php require_once __DIR__ . "/includes/footer.php"; ?>