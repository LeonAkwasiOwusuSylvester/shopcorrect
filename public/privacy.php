<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Data Protection | ShopCorrect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --sc-navy: #0B2447; 
            --sc-accent: #19376D; 
            --sc-bg: #F8FAFC; 
            --sc-text: #334155;
        }

        body { 
            background-color: var(--sc-bg); 
            font-family: 'Inter', sans-serif; 
            color: var(--sc-text); 
        }

        /* --- Header --- */
        .page-header {
            background: radial-gradient(circle at center, #19376D 0%, #0B2447 100%);
            color: white;
            padding: 80px 0 100px 0;
            text-align: center;
            margin-bottom: -60px; /* Overlap effect */
        }

        /* --- Content Card --- */
        .content-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 3rem 4rem;
            margin-bottom: 3rem;
            border: 1px solid #e2e8f0;
        }

        /* --- Typography --- */
        h1 { font-weight: 700; letter-spacing: -1px; }
        h2 { 
            color: var(--sc-navy); 
            font-weight: 700; 
            font-size: 1.4rem; 
            margin-top: 2.5rem; 
            margin-bottom: 1rem; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        h2 i { color: #ffc107; font-size: 1.2rem; }
        
        h3 { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-top: 1.5rem; }
        
        p { line-height: 1.7; color: #475569; margin-bottom: 1.2rem; }
        ul li { line-height: 1.7; color: #475569; margin-bottom: 0.5rem; }
        
        .last-updated {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .alert-info-custom {
            background-color: #eff6ff;
            border-left: 4px solid var(--sc-navy);
            color: var(--sc-text);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }

        @media (max-width: 768px) {
            .content-card { padding: 2rem; }
        }
    </style>
</head>
<body>

<section class="page-header">
    <div class="container">
        <div class="last-updated">Last Updated: February 1, 2026</div>
        <h1 class="display-4 mb-3">Privacy & Data Protection</h1>
        <p class="lead text-white-50" style="max-width: 700px; margin: 0 auto;">
            Transparency is key to trust. Learn how ShopCorrect collects, uses, and protects data for both our Buyers and Vendors.
        </p>
    </div>
</section>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="content-card">
                
                <p class="lead text-dark fw-medium border-bottom pb-4 mb-4">
                    ShopCorrect ("we", "our", or "us") is committed to protecting the privacy of all users. This policy outlines our practices regarding personal data collected from <strong>Buyers</strong> (customers) and <strong>Vendors</strong> (sellers) on our platform.
                </p>

                <h2><i class="bi bi-collection"></i> 1. Information We Collect</h2>
                
                <h3>A. For Buyers</h3>
                <ul>
                    <li><strong>Identity Data:</strong> Name, username, and password.</li>
                    <li><strong>Contact Data:</strong> Email address, phone number, and delivery address.</li>
                    <li><strong>Transaction Data:</strong> Details of products purchased, order history, and payment method details (masked).</li>
                </ul>

                <h3>B. For Vendors</h3>
                <ul>
                    <li><strong>Business Identity:</strong> Shop name, owner's legal name, and business registration documents.</li>
                    <li><strong>Financial Data:</strong> Bank account or mobile money details for payouts.</li>
                    <li><strong>Verification Data:</strong> Government-issued ID (e.g., Ghana Card) for fraud prevention and KYC (Know Your Customer) compliance.</li>
                </ul>

                <h2><i class="bi bi-shield-lock"></i> 2. How We Protect Your Data</h2>
                <p>We implement robust technical and organizational measures to safeguard your data against unauthorized access, alteration, or destruction:</p>
                <ul>
                    <li><strong>Encryption:</strong> All sensitive data (such as passwords and payment details) is encrypted using Secure Socket Layer (SSL) technology during transmission and stored using industry-standard hashing algorithms (e.g., bcrypt).</li>
                    <li><strong>Access Control:</strong> Access to personal data is strictly restricted to ShopCorrect employees and contractors who need it to perform specific job functions (e.g., customer support).</li>
                    <li><strong>Secure Servers:</strong> Our database is hosted in secure data centers with 24/7 monitoring and firewall protection.</li>
                    <li><strong>Vendor Isolation:</strong> Vendors can only see the buyer information necessary to fulfill a specific order (e.g., shipping address). They do not have access to a buyer's full history or payment details.</li>
                </ul>

                <div class="alert-info-custom">
                    <strong>Note on Payments:</strong> ShopCorrect does not store your full credit card numbers. All payments are processed through secure, PCIDSS-compliant third-party payment gateways (e.g., Paystack, Flutterwave).
                </div>

                <h2><i class="bi bi-sliders"></i> 3. How We Use Your Information</h2>
                <p>We use your data strictly to facilitate the marketplace experience:</p>
                <ul>
                    <li><strong>Order Fulfillment:</strong> Sharing necessary shipping details with Vendors and Logistics Partners to deliver goods.</li>
                    <li><strong>Payouts:</strong> Processing earnings for Vendors.</li>
                    <li><strong>Communication:</strong> Sending order updates, security alerts, and support messages.</li>
                    <li><strong>Fraud Prevention:</strong> Monitoring transactions for suspicious activity to protect both buyers and sellers.</li>
                </ul>

                <h2><i class="bi bi-share"></i> 4. Data Sharing & Third Parties</h2>
                <p>We do not sell your personal data. We only share data with:</p>
                <ul>
                    <li><strong>Logistics Partners:</strong> To physically deliver your items.</li>
                    <li><strong>Payment Processors:</strong> To handle secure financial transactions.</li>
                    <li><strong>Legal Authorities:</strong> If compelled by law or to protect the rights and safety of ShopCorrect and its users.</li>
                </ul>

                <h2><i class="bi bi-person-check"></i> 5. Your Rights</h2>
                <p>Both Buyers and Vendors have the right to:</p>
                <ul>
                    <li><strong>Access:</strong> View the personal data we hold about you via your dashboard.</li>
                    <li><strong>Correction:</strong> Update inaccurate information in your profile settings.</li>
                    <li><strong>Deletion:</strong> Request the deletion of your account and associated data (subject to legal retention requirements for transaction records).</li>
                </ul>

                <h2>6. Contact Us</h2>
                <p>If you have questions about how your data is handled, please contact our Data Protection Officer:</p>
                <div class="bg-light p-4 rounded-3 border mt-3">
                    <p class="mb-1"><strong>Email:</strong> privacy@shopcorrect.com</p>
                    <p class="mb-0"><strong>Address:</strong> 12 Airport City Road, Accra, Ghana</p>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>