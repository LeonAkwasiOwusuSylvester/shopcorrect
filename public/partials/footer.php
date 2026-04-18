<?php
// public/partials/footer.php

// Ensure session is started so currency works
if (session_status() === PHP_SESSION_NONE) session_start();

// Newsletter Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['newsletter_error'] = "Invalid request.";
    } else {
        $email = filter_var($_POST['newsletter_email'], FILTER_VALIDATE_EMAIL);
        if ($email) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM newsletter_subscribers WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if (!$stmt->fetch()) {
                    $insert = $pdo->prepare("INSERT INTO newsletter_subscribers (email, created_at) VALUES (?, NOW())");
                    $insert->execute([$email]);
                }
                $_SESSION['newsletter_success'] = "Subscribed successfully.";
            } catch (Exception $e) {
                $_SESSION['newsletter_error'] = "Subscription failed.";
            }
        } else {
            $_SESSION['newsletter_error'] = "Enter a valid email.";
        }
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<style>
    /* ══════════════════════════════
       FOOTER STYLES
    ══════════════════════════════ */
    .site-footer { background-color: #0B2447; color: #ffffff; position: relative; z-index: 999; flex-shrink: 0; }
    .footer-link { color: rgba(255, 255, 255, 0.6); text-decoration: none; transition: all 0.2s ease; display: inline-block; font-size: 0.88rem; }
    .footer-link:hover { color: #ffffff; padding-left: 5px; }
    .social-link { color: rgba(255, 255, 255, 0.5); text-decoration: none; transition: 0.2s; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.07); flex-shrink: 0; }
    .social-link:hover { color: white; background: rgba(255,255,255,0.2); transform: translateY(-3px); }
    .footer-currency-btn { background: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255, 255, 255, 0.2); padding: 8px 14px; border-radius: 6px; font-size: 0.85rem; transition: 0.3s; display: flex; align-items: center; justify-content: space-between; gap: 6px; width: 100%; }
    .footer-currency-btn:hover { background: rgba(255,255,255,0.2); color: white; }
    .footer-currency-menu { border: none; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 8px !important; min-width: 240px; max-height: 280px; overflow-y: auto; -webkit-overflow-scrolling: touch; }
    .footer-currency-menu .dropdown-item { font-size: 0.87rem; padding: 8px 16px; display: flex; align-items: center; gap: 10px; color: #334155; font-weight: 500; }
    .footer-currency-menu .dropdown-item:hover { background-color: #f0f4ff; color: #0B2447; }
    .footer-nav-flag { width: 18px; height: auto; border-radius: 2px; object-fit: cover; }
    .newsletter-input-group .form-control { font-size: 0.9rem; padding: 10px 14px; border: none; border-radius: 6px 0 0 6px; }
    .newsletter-input-group .btn-subscribe { background-color: #eab308; border: none; color: white; font-weight: 700; padding: 10px 18px; border-radius: 0 6px 6px 0; white-space: nowrap; transition: background 0.2s; }
    .newsletter-input-group .btn-subscribe:hover { background-color: #ca9a04; }
    .footer-bottom { background-color: rgba(0, 0, 0, 0.25); border-top: 1px solid rgba(255,255,255,0.05); }
    @media (max-width: 576px) {
        .site-footer .container-fluid { padding-left: 1.2rem; padding-right: 1.2rem; }
        .footer-bottom .row > div { text-align: center !important; }
    }
</style>

<footer class="site-footer mt-auto">
    <div class="container-fluid px-4 px-lg-5 pt-5 pb-4">
        <div class="row g-4">

            <div class="col-lg-3 col-md-6 col-12">
                <a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none text-white mb-3">
                    <img src="assets/images/logo_w.png" alt="ShopCorrect" height="60" style="max-width:60px;object-fit:contain;">
                    <span class="fw-bold fs-5 notranslate" style="letter-spacing:-0.5px;">ShopCorrect</span>
                </a>
                <p class="small text-white-50 mb-4" style="max-width:300px;line-height:1.7;">
                    Your trusted online marketplace for authentic products. We connect buyers with verified local vendors for a secure and seamless shopping experience.
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="#" class="social-link" title="Facebook"><i class="bi bi-facebook fs-5"></i></a>
                    <a href="#" class="social-link" title="X / Twitter"><i class="bi bi-twitter-x fs-5"></i></a>
                    <a href="#" class="social-link" title="Instagram"><i class="bi bi-instagram fs-5"></i></a>
                    <a href="#" class="social-link" title="LinkedIn"><i class="bi bi-linkedin fs-5"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold text-uppercase mb-3 text-warning" style="letter-spacing:1px;font-size:0.72rem;">Help & Support</h6>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="help.php" class="footer-link">Help Center</a></li>
                    <li><a href="accounts.php" class="footer-link">Account</a></li>
                    <li><a href="delivery-option.php" class="footer-link">Delivery Options</a></li>
                    <li><a href="contact.php" class="footer-link">Contact Us</a></li>
                    <li><a href="return-policy.php" class="footer-link">Return Policy</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="fw-bold text-uppercase mb-3 text-warning" style="letter-spacing:1px;font-size:0.72rem;">Company</h6>
                <ul class="list-unstyled d-flex flex-column gap-2 mb-4">
                    <li><a href="about.php" class="footer-link">About Us</a></li>
                    <li><a href="terms.php" class="footer-link">Terms & Conditions</a></li>
                    <li><a href="privacy.php" class="footer-link">Privacy Policy</a></li>
                    <li><a href="becomevendor.php" class="footer-link">Become a Vendor</a></li>
                </ul>

                <h6 class="fw-bold text-uppercase mb-2 text-warning" style="letter-spacing:1px;font-size:0.72rem;">Currency</h6>
                <div class="dropup w-100">
                    <button class="footer-currency-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span><i class="bi bi-cash-coin me-1"></i> <?= $_SESSION['currency'] ?? 'GHS' ?></span>
                    </button>
                    <ul class="dropdown-menu footer-currency-menu">
                        <li><h6 class="dropdown-header fw-bold text-uppercase" style="font-size:0.68rem;">Africa</h6></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=GHS"><img src="https://flagcdn.com/w20/gh.png" class="footer-nav-flag"> GHS — Ghana Cedi</a></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=NGN"><img src="https://flagcdn.com/w20/ng.png" class="footer-nav-flag"> NGN — Nigerian Naira</a></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=XOF"><img src="https://flagcdn.com/w20/ci.png" class="footer-nav-flag"> XOF — CFA Franc (CI / Togo)</a></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=ZAR"><img src="https://flagcdn.com/w20/za.png" class="footer-nav-flag"> ZAR — South African Rand</a></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=KES"><img src="https://flagcdn.com/w20/ke.png" class="footer-nav-flag"> KES — Kenyan Shilling</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header fw-bold text-uppercase" style="font-size:0.68rem;">International</h6></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=GBP"><img src="https://flagcdn.com/w20/gb.png" class="footer-nav-flag"> GBP — British Pound</a></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=USD"><img src="https://flagcdn.com/w20/us.png" class="footer-nav-flag"> USD — US Dollar</a></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=CAD"><img src="https://flagcdn.com/w20/ca.png" class="footer-nav-flag"> CAD — Canadian Dollar</a></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=EUR"><img src="https://flagcdn.com/w20/de.png" class="footer-nav-flag"> EUR — Euro (Germany / Spain)</a></li>
                        <li><a class="dropdown-item" href="change-currency.php?cur=CNY"><img src="https://flagcdn.com/w20/cn.png" class="footer-nav-flag"> CNY — Chinese Yuan</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-12">
                <h6 class="fw-bold text-uppercase mb-3 text-warning" style="letter-spacing:1px;font-size:0.72rem;">Stay in the loop</h6>
                <p class="small text-white-50 mb-3" style="line-height:1.6;">
                    Subscribe to get special offers, free giveaways, and exclusive deals.
                </p>

                <?php if (isset($_SESSION['newsletter_success'])): ?>
                    <div class="alert alert-success py-2 small">
                        <?= $_SESSION['newsletter_success']; unset($_SESSION['newsletter_success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['newsletter_error'])): ?>
                    <div class="alert alert-danger py-2 small">
                        <?= $_SESSION['newsletter_error']; unset($_SESSION['newsletter_error']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="input-group newsletter-input-group">
                        <input type="email" name="newsletter_email" required class="form-control" placeholder="Enter your email">
                        <button class="btn btn-subscribe" type="submit">Subscribe</button>
                    </div>
                </form>

                <div class="small text-white-50">
                    <i class="bi bi-shield-lock-fill me-1 text-success"></i> Secure Payment:
                    <span class="ms-1 text-white opacity-75"><i class="bi bi-credit-card"></i> Visa</span>
                    <span class="ms-2 text-white opacity-75"><i class="bi bi-wallet2"></i> Mobile Money</span>
                </div>
            </div>

        </div>
    </div>

    <div class="footer-bottom">
        <div class="container-fluid px-4 px-lg-5 py-3">
            <div class="row align-items-center g-2">
                <div class="col-md-6 text-center text-md-start">
                    <small class="text-white-50">
                        &copy; <?= date("Y") ?> <strong class="notranslate">ShopCorrect</strong>. All rights reserved.
                    </small>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <small class="text-white-50">
                        Shop Smart. Shop Correct. <i class="bi bi-check-circle-fill text-success mx-1"></i>
                    </small>
                </div>
            </div>
        </div>
    </div>
</footer>

<?php if (basename($_SERVER['PHP_SELF']) !== 'index.php'): ?>
</main>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/69c25c194d7e6c1c3df7baa6/1jkfjfh6r';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->

</body>
</html>