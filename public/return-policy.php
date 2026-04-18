<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Policy | ShopCorrect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --sc-navy: #0B2447; 
            --sc-accent: #19376D; 
            --sc-soft: #F8FAFC;
            --sc-gold: #ffc107;
        }

        body { 
            background-color: var(--sc-soft); 
            font-family: 'Inter', sans-serif; 
            color: #334155; 
        }

        /* --- Hero Section --- */
        .policy-hero {
            background: radial-gradient(circle at center, #19376D 0%, #0B2447 100%);
            color: white;
            padding: 80px 0 100px 0;
            text-align: center;
            margin-bottom: -50px;
        }

        /* --- Content Cards --- */
        .policy-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        /* --- Step Process --- */
        .step-circle {
            width: 50px; height: 50px;
            background-color: #eff6ff; color: var(--sc-navy);
            border-radius: 50%; font-weight: 700; font-size: 1.2rem;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem;
        }

        /* --- Check List --- */
        .check-list li {
            position: relative;
            padding-left: 30px;
            margin-bottom: 12px;
            list-style: none;
        }
        .check-list li::before {
            content: "\F26A"; /* Bootstrap check-circle icon */
            font-family: "bootstrap-icons";
            position: absolute; left: 0; top: 2px;
            color: #16a34a; font-size: 1.1rem;
        }

        /* --- Cross List --- */
        .cross-list li {
            position: relative;
            padding-left: 30px;
            margin-bottom: 12px;
            list-style: none;
        }
        .cross-list li::before {
            content: "\F62A"; /* Bootstrap x-circle icon */
            font-family: "bootstrap-icons";
            position: absolute; left: 0; top: 2px;
            color: #dc2626; font-size: 1.1rem;
        }
    </style>
</head>
<body>

<section class="policy-hero">
    <div class="container">
        <span class="badge bg-white text-dark bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 mb-3">
            <i class="bi bi-arrow-repeat me-1"></i> Easy Returns
        </span>
        <h1 class="display-4 fw-bold mb-3">Return Policy</h1>
        <p class="lead text-white-50" style="max-width: 600px; margin: 0 auto;">
            Not satisfied with your purchase? No problem. We have a simple and transparent return process to ensure your peace of mind.
        </p>
    </div>
</section>

<div class="container pb-5">
    
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="policy-card position-relative z-2">
                <h4 class="fw-bold text-dark mb-4 text-center">How to Return an Item</h4>
                
                <div class="row g-4 text-center">
                    <div class="col-md-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="step-circle">1</div>
                            <h6 class="fw-bold">Initiate Return</h6>
                            <p class="text-secondary small">Go to 'My Orders', select the item, and click 'Request Return' within 7 days of delivery.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="step-circle">2</div>
                            <h6 class="fw-bold">Pack & Ship</h6>
                            <p class="text-secondary small">Pack the item in its original box. Our courier will pick it up or you can drop it off.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="step-circle">3</div>
                            <h6 class="fw-bold">Get Refunded</h6>
                            <p class="text-secondary small">Once verified, your refund will be processed to your original payment method.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="policy-card h-100">
                <h5 class="fw-bold text-dark mb-4">✅ Eligible for Return</h5>
                <ul class="check-list text-secondary">
                    <li>Wrong item delivered (size, color, model).</li>
                    <li>Defective or damaged product upon arrival.</li>
                    <li>Missing parts or accessories.</li>
                    <li>Product is significantly different from description.</li>
                    <li>Fake or counterfeit product.</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="policy-card h-100">
                <h5 class="fw-bold text-dark mb-4">❌ Non-Returnable Items</h5>
                <ul class="cross-list text-secondary">
                    <li>Perishable goods (food, flowers, etc.).</li>
                    <li>Personal hygiene items (underwear, cosmetics).</li>
                    <li>Items damaged by the customer (misuse, drops).</li>
                    <li>Products missing original packaging or tags.</li>
                    <li>Digital goods (software, gift cards).</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="policy-card">
        <h5 class="fw-bold text-dark mb-3">Refund Timelines</h5>
        <p class="text-secondary mb-4">Refunds are initiated after the item passes our quality check at the warehouse (usually 1-2 days after pickup).</p>
        
        <div class="table-responsive">
            <table class="table table-bordered mb-0 rounded-3 overflow-hidden">
                <thead class="table-light">
                    <tr>
                        <th class="fw-bold text-secondary">Payment Method</th>
                        <th class="fw-bold text-secondary">Refund Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mobile Money (MOMO)</td>
                        <td>24 - 48 Hours</td>
                    </tr>
                    <tr>
                        <td>Bank Card (Visa / MasterCard)</td>
                        <td>3 - 7 Business Days</td>
                    </tr>
                    <tr>
                        <td>ShopCorrect Wallet</td>
                        <td>Instant</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center mt-5">
        <p class="text-muted">Still have questions about a return?</p>
        <a href="contact.php" class="btn btn-outline-dark rounded-pill px-4">Contact Support</a>
    </div>

</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>