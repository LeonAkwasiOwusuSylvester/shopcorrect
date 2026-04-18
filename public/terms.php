<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions | ShopCorrect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { 
            --sc-navy: #0B2447; 
            --sc-accent: #19376D; 
            --sc-bg: #F8FAFC; 
            --sc-text: #334155;
            --sc-gold: #ffc107;
        }

        body { 
            background-color: var(--sc-bg); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: var(--sc-text); 
            line-height: 1.6;
        }

        /* --- Page Header --- */
        .page-header {
            background: radial-gradient(circle at center, #19376D 0%, #0B2447 100%);
            color: white;
            padding: 100px 0 120px 0;
            text-align: center;
            margin-bottom: -60px;
        }

        /* --- Legal Content Card --- */
        .legal-content { 
            background: white; 
            padding: 4rem; 
            border-radius: 24px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 10px 30px rgba(11, 36, 71, 0.05);
            margin-bottom: 3rem;
        }

        /* --- Typography --- */
        h1 { font-weight: 800; letter-spacing: -1.5px; }
        
        h2 { 
            color: var(--sc-navy); 
            font-weight: 800; 
            font-size: 1.4rem; 
            margin-top: 3rem; 
            margin-bottom: 1.2rem; 
            display: flex;
            align-items: center;
            gap: 12px;
        }

        h2::before {
            content: "";
            display: inline-block;
            width: 4px;
            height: 24px;
            background: var(--sc-gold);
            border-radius: 10px;
        }

        p { 
            line-height: 1.8; 
            margin-bottom: 1.2rem; 
            color: #475569; 
            font-size: 1rem;
        }

        .highlight-box {
            background-color: #F0F9FF;
            border-left: 4px solid var(--sc-accent);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .last-updated {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .policy-link {
            color: var(--sc-accent);
            text-decoration: none;
            font-weight: 700;
            border-bottom: 2px solid transparent;
            transition: 0.2s;
        }
        .policy-link:hover {
            color: var(--sc-navy);
            border-bottom-color: var(--sc-gold);
        }

        @media (max-width: 768px) {
            .legal-content { padding: 2rem; }
            .page-header { padding: 60px 0 80px 0; }
        }
    </style>
</head>
<body>

<section class="page-header">
    <div class="container">
        <div class="last-updated">Effective Date: February 13, 2026</div>
        <h1 class="display-4 fw-bold">Terms & Conditions</h1>
        <p class="lead text-white-50" style="max-width: 650px; margin: 15px auto 0;">
            Review our service agreement for buyers and merchants.
        </p>
    </div>
</section>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="legal-content">
                
                <p class="lead text-dark fw-bold mb-4">
                    Welcome to ShopCorrect. By accessing our platform, you agree to comply with these terms of service. This ensures a transparent and secure environment for all community members.
                </p>

                <h2>1. Service Fees & Commissions</h2>
                <p>
                    ShopCorrect operates on a success-based model for all registered merchants. By listing products on our platform, vendors agree to the following financial structure:
                </p>
                <div class="highlight-box">
                    <p class="mb-0 text-dark fw-bold">
                        <i class="bi bi-percent me-2 text-primary"></i>
                        Commission Rates: Commission fees are determined by ShopCorrect management. New registered vendors receive a free commission waiver for their first few months after approval.
                    </p>
                </div>
                <ul style="color: #475569; margin-bottom: 1.5rem; line-height: 1.8;">
                    <li>Commission is automatically calculated and deducted at the time of sale based on your active rate.</li>
                    <li>Fees apply only to completed orders. No commission is charged on cancelled or fully refunded items.</li>
                    <li>ShopCorrect reserves the right to adjust commission rates. We will provide a minimum 30-day notice to all vendors before any changes.</li>
                </ul>

                <h2>2. Vendor Fulfillment & Conduct</h2>
                <p>
                    To maintain the ShopCorrect standard, all merchants are required to follow these rules:
                </p>
                <ul style="color: #475569; margin-bottom: 1.5rem; line-height: 1.8;">
                    <li>Ensure products are authentic and match the provided description exactly.</li>
                    <li>Process and package orders within 24 to 48 hours of receipt.</li>
                    <li>Maintain accurate inventory levels to prevent order cancellations due to out of stock items.</li>
                </ul>

                <h2>3. Payout Policy</h2>
                <p>
                    Vendor earnings consist of the total sales price minus your applicable platform commission and plus any valid shipping refunds. Disbursements are processed on a weekly cycle to your verified Mobile Money wallet or bank account.
                </p>

                <h2>4. Intellectual Property</h2>
                <p>
                    Users may not modify, copy, or attempt to reverse engineer any software or proprietary systems contained on ShopCorrect. All brand assets and platform technologies are the exclusive property of ShopCorrect.
                </p>

                <h2>5. Privacy & Data</h2>
                <p>
                    We prioritize your data security. Detailed information on how we handle personal and business data can be found in our 
                    <a href="privacy.php" class="policy-link">Privacy Policy</a>.
                </p>

                <h2>6. Termination of Service</h2>
                <p>
                    ShopCorrect reserves the right to suspend or permanently terminate any account. We will do this if a buyer or vendor is found to be engaging in fraudulent activity, selling prohibited items, or repeatedly violating platform quality standards.
                </p>

                <div class="mt-5 p-4 rounded-4 bg-light text-center">
                    <p class="small mb-0">
                        Questions regarding these terms? Contact our Compliance Team at 
                        <a href="mailto:support@shopcorrect.com" class="policy-link">support@shopcorrect.com</a>
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>