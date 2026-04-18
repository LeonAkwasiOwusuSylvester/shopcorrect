<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Support | ShopCorrect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --sc-navy: #0B2447; 
            --sc-accent: #19376D; 
            --sc-gold: #ffc107;
            --sc-bg: #F8FAFC;
            --sc-text: #334155;
        }

        body { 
            background-color: var(--sc-bg); 
            font-family: 'Inter', sans-serif; 
            color: var(--sc-text); 
        }

        /* --- Hero Section --- */
        .account-hero {
            background: radial-gradient(circle at center, #19376D 0%, #0B2447 100%);
            color: white;
            padding: 80px 0 120px 0;
            text-align: center;
            position: relative;
            margin-bottom: -60px; /* Overlap effect */
            z-index: 1;
        }

        /* --- Action Cards --- */
        .action-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            position: relative;
            z-index: 2;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
            border-color: var(--sc-navy);
        }
        
        .icon-circle {
            width: 70px; height: 70px;
            background-color: #f1f5f9; color: var(--sc-navy);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; margin: 0 auto 1.5rem;
            transition: 0.3s;
        }
        .action-card:hover .icon-circle {
            background-color: var(--sc-navy); color: white;
        }

        /* --- Security Section --- */
        .security-section {
            background-color: white;
            border-radius: 16px;
            padding: 3rem;
            margin-top: 3rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
        }

        .list-check {
            padding-left: 0;
        }
        .list-check li {
            list-style: none;
            position: relative;
            padding-left: 35px;
            margin-bottom: 16px;
            color: #475569;
            font-weight: 500;
        }
        .list-check li::before {
            content: "\F26A"; /* check-circle-fill */
            font-family: "bootstrap-icons";
            position: absolute; left: 0; top: 1px;
            color: #10b981;
            font-size: 1.2rem;
        }

        /* --- Accordion --- */
        .accordion-item { border: none; border-bottom: 1px solid #f1f5f9 !important; transition: 0.2s; }
        .accordion-button { font-weight: 600; padding: 1.25rem; color: #1e293b; background: white; transition: 0.2s; }
        .accordion-button:hover { background-color: #f8fafc; }
        .accordion-button:not(.collapsed) { background-color: #eff6ff; color: var(--sc-navy); box-shadow: none; border-radius: 8px; }
        .accordion-button:focus { box-shadow: none; }
        .accordion-body { color: #475569; line-height: 1.6; }
    </style>
</head>
<body>

<section class="account-hero">
    <div class="container">
        <span class="badge bg-white text-dark bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 mb-3 shadow-sm">
            <i class="bi bi-shield-lock me-1"></i> Security & Settings
        </span>
        <h1 class="display-4 fw-bold mb-3">Account Support</h1>
        <p class="lead text-white-50" style="max-width: 600px; margin: 0 auto;">
            Manage your ShopCorrect identity, secure your data, and troubleshoot login issues.
        </p>
    </div>
</section>

<div class="container pb-5">
    
    <div class="row g-4">
        
        <div class="col-md-4">
            <div class="action-card shadow-sm">
                <div class="icon-circle">
                    <i class="bi bi-person-plus"></i>
                </div>
                <h5 class="fw-bold text-dark">New Customer?</h5>
                <p class="text-secondary small mb-4">Create an account to track orders, save items, and speed up checkout.</p>
                <a href="register.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm fw-bold">Create Account</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="action-card shadow-sm">
                <div class="icon-circle">
                    <i class="bi bi-key"></i>
                </div>
                <h5 class="fw-bold text-dark">Login Issues?</h5>
                <p class="text-secondary small mb-4">Forgot your password or locked out? Recover access to your account securely.</p>
                <a href="forgot-password.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm fw-bold">Reset Password</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="action-card shadow-sm">
                <div class="icon-circle">
                    <i class="bi bi-shop"></i>
                </div>
                <h5 class="fw-bold text-dark">Vendor Account</h5>
                <p class="text-secondary small mb-4">Manage your shop settings, verify your identity, or apply to sell.</p>
                <a href="becomevendor.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm fw-bold">Vendor Portal</a>
            </div>
        </div>

    </div>

    <div class="security-section">
        <div class="row align-items-center">
            <div class="col-lg-7 mb-4 mb-lg-0 pe-lg-5">
                <h3 class="fw-bold text-dark mb-3">Keep your account safe</h3>
                <p class="text-secondary mb-4" style="line-height: 1.7;">
                    Security is a top priority at ShopCorrect. We recommend taking the following steps to ensure your personal and payment data remains secure at all times.
                </p>
                <ul class="list-check">
                    <li>Use a unique, strong password (min. 8 characters).</li>
                    <li>Never share your OTP (One-Time Password) with anyone.</li>
                    <li>Always log out when using public or shared computers.</li>
                    <li>Update your password every 3-6 months.</li>
                </ul>
            </div>
            <div class="col-lg-5 text-center">
                <i class="bi bi-shield-check text-success opacity-75" style="font-size: 12rem;"></i>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mt-5">
        <div class="col-lg-9">
            <h4 class="fw-bold text-center mb-4 text-dark">Common Account Questions</h4>
            
            <div class="accordion shadow-sm rounded-4 overflow-hidden border" id="accountFAQ">
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How do I update my email address?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#accountFAQ">
                        <div class="accordion-body">
                            For security reasons, vendors cannot change their email address manually from your dashboard. Please contact our support team at <strong>support@shopcorrect.com</strong> to begin the verification and update process.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            How do I delete my account?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#accountFAQ">
                        <div class="accordion-body">
                            To request account deletion, please go to your Profile Settings > Privacy > Delete Account. Please note that some transaction data may be retained for legal and tax compliance purposes.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Why is my account suspended?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#accountFAQ">
                        <div class="accordion-body">
                            Accounts may be suspended for violating our Terms of Service (e.g., fraudulent activity, abusive behavior, or suspicious payment methods). Please check your email for a notification from our trust and safety team, or contact support for an appeal.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

</body>
</html>