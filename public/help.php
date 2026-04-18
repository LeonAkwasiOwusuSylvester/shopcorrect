<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Help Center | ShopCorrect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --sc-navy: #0B2447; }
        body { background-color: #F8FAFC; font-family: 'Inter', sans-serif; color: #334155; }
        
        .help-header { 
            background: linear-gradient(135deg, #0B2447 0%, #051329 100%); 
            padding: 80px 0 100px 0; 
            color: white; 
            text-align: center; 
            margin-bottom: -50px; 
            position: relative;
            z-index: 1;
        }
        
        .search-help { 
            max-width: 600px; 
            margin: 0 auto 40px; 
            position: relative; 
            z-index: 20; 
        }
        .search-help input { 
            border-radius: 50px; 
            padding: 18px 30px 18px 55px; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.1); 
            border: 1px solid rgba(0,0,0,0.05); 
            font-size: 1rem;
        }
        .search-help i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.2rem;
            z-index: 25;
        }
        .search-help input:focus {
            box-shadow: 0 15px 40px rgba(11, 36, 71, 0.15);
            border-color: var(--sc-navy);
        }

        /* --- Tabs Styling --- */
        .nav-pills-container {
            position: relative;
            z-index: 10; 
        }

        .nav-pills { gap: 10px; }
        
        .nav-pills .nav-link { 
            color: #64748b; 
            font-weight: 600; 
            padding: 12px 30px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 50px;
            transition: all 0.2s ease-in-out;
            cursor: pointer !important; 
        }
        
        .nav-pills .nav-link:hover { 
            background-color: #f1f5f9; 
            color: var(--sc-navy); 
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .nav-pills .nav-link.active { 
            background-color: var(--sc-navy) !important; 
            color: white !important; 
            border-color: var(--sc-navy);
            box-shadow: 0 4px 12px rgba(11, 36, 71, 0.25);
            transform: translateY(0);
        }

        .tab-content { position: relative; z-index: 5; }

        /* Accordion Styles */
        .accordion-item { border: none; border-bottom: 1px solid #f1f5f9 !important; transition: 0.3s; }
        .accordion-button { font-weight: 600; padding: 1.25rem; color: #1e293b; }
        .accordion-button:not(.collapsed) { background-color: #f0fdf4; color: var(--sc-navy); box-shadow: none; border-radius: 8px;}
        .accordion-button:focus { box-shadow: none; }
        .accordion-body { color: #475569; line-height: 1.6; font-size: 0.95rem; }

        .support-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin-top: 3rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

<div class="help-header">
    <h1 class="fw-bold mb-2">How can we help you?</h1>
    <p class="opacity-75">Find answers regarding delivery, returns, and account management.</p>
</div>

<div class="container pb-5">
    <div class="search-help">
        <i class="bi bi-search"></i>
        <input type="text" id="helpSearchInput" class="form-control" placeholder="Type keywords like 'refund', 'password', or 'tracking'...">
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <div class="nav-pills-container">
                <ul class="nav nav-pills mb-5 justify-content-center" id="helpTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-delivery" data-bs-toggle="pill" data-bs-target="#content-delivery" type="button" role="tab">
                            <i class="bi bi-truck me-2"></i> Delivery
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-returns" data-bs-toggle="pill" data-bs-target="#content-returns" type="button" role="tab">
                            <i class="bi bi-arrow-repeat me-2"></i> Returns & Refunds
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-account" data-bs-toggle="pill" data-bs-target="#content-account" type="button" role="tab">
                            <i class="bi bi-person-gear me-2"></i> Account
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content bg-white p-4 rounded-4 shadow-sm border" id="helpTabContent" style="min-height: 400px;">
                
                <div class="tab-pane fade show active" id="content-delivery" role="tabpanel">
                    <h4 class="fw-bold mb-4 text-dark border-bottom pb-3">Delivery Information</h4>
                    <div class="accordion" id="accDelivery">
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#del1">How long does shipping take?</button></h2>
                            <div id="del1" class="accordion-collapse collapse" data-bs-parent="#accDelivery">
                                <div class="accordion-body">
                                    Standard shipping within Accra takes <strong>2-3 business days</strong>. For other regions (Kumasi, Takoradi, Tamale), delivery takes <strong>3-5 business days</strong>. Express delivery is available for same-day or next-day service in select areas.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#del2">Can I pick up my order?</button></h2>
                            <div id="del2" class="accordion-collapse collapse" data-bs-parent="#accDelivery">
                                <div class="accordion-body">
                                    Yes! At checkout, select "Pickup Station" as your delivery method. We have over 50 secure pickup locations across the country. Pickup fees are significantly lower than doorstep delivery.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#del3">How do I track my order?</button></h2>
                            <div id="del3" class="accordion-collapse collapse" data-bs-parent="#accDelivery">
                                <div class="accordion-body">
                                    Go to <strong>My Orders</strong> in your account dashboard. Click on the specific order ID to see real-time status updates (e.g., Processing, Shipped, Out for Delivery).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="content-returns" role="tabpanel">
                    <h4 class="fw-bold mb-4 text-dark border-bottom pb-3">Returns & Refunds</h4>
                    <div class="accordion" id="accReturns">
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ret1">What is the return policy?</button></h2>
                            <div id="ret1" class="accordion-collapse collapse" data-bs-parent="#accReturns">
                                <div class="accordion-body">
                                    You can return items within <strong>7 days</strong> of delivery if they are defective, damaged, or incorrect. The item must be unused, in its original packaging, and include all accessories/tags. Personal hygiene items and digital goods are non-returnable.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ret2">How do I initiate a return?</button></h2>
                            <div id="ret2" class="accordion-collapse collapse" data-bs-parent="#accReturns">
                                <div class="accordion-body">
                                    1. Log in and go to "My Orders".<br>
                                    2. Select the delivered order and click "Request Refund".<br>
                                    3. Fill out the form with the reason.<br>
                                    Our support team will review your request shortly.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ret3">When will I get my refund?</button></h2>
                            <div id="ret3" class="accordion-collapse collapse" data-bs-parent="#accReturns">
                                <div class="accordion-body">
                                    Once we approve the refund request:<br>
                                    - <strong>Mobile Money:</strong> 24-48 hours.<br>
                                    - <strong>Bank Cards:</strong> 3-10 business days (depending on your bank).<br>
                                    - <strong>ShopCorrect Wallet:</strong> Instant credit.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="content-account" role="tabpanel">
                    <h4 class="fw-bold mb-4 text-dark border-bottom pb-3">Account Management</h4>
                    <div class="accordion" id="accAccount">
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#acc1">How do I reset my password?</button></h2>
                            <div id="acc1" class="accordion-collapse collapse" data-bs-parent="#accAccount">
                                <div class="accordion-body">
                                    If you are logged out, click "Login" > "Forgot Password?" and enter your email. We will send you a secure link to reset it. If you are logged in, go to "My Profile" > "Security" to change it.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#acc2">Can I change my email address?</button></h2>
                            <div id="acc2" class="accordion-collapse collapse" data-bs-parent="#accAccount">
                                <div class="accordion-body">
                                    For security reasons, email addresses cannot be changed manually. Please contact our support team at <strong>support@shopcorrect.com</strong> if you need to update your primary contact email.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#acc3">How do I become a vendor?</button></h2>
                            <div id="acc3" class="accordion-collapse collapse" data-bs-parent="#accAccount">
                                <div class="accordion-body">
                                    If you want to sell on ShopCorrect, scroll to the bottom of the page and click <strong>"Become a Vendor"</strong>. You will need to provide your business details and valid ID for verification.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="noResultsMsg" class="text-center py-5 d-none">
                    <i class="bi bi-search text-muted opacity-25" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark mt-3">No matching results found</h5>
                    <p class="text-muted">Try adjusting your search terms.</p>
                </div>

            </div>

            <div class="support-card">
                <div class="mb-3">
                    <span class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block">
                        <i class="bi bi-headset fs-3"></i>
                    </span>
                </div>
                <h4 class="fw-bold text-dark mb-2">Still need help?</h4>
                <p class="text-muted mb-4">Can't find the answer you're looking for? Our support team is here to help you.</p>
                <a href="contact.php" class="btn btn-dark rounded-pill px-5 py-2 fw-semibold">Contact Support</a>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 1. Ensure tabs work safely
    const triggerTabList = document.querySelectorAll('#helpTabs button');
    triggerTabList.forEach(triggerEl => {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        triggerEl.addEventListener('click', event => {
            event.preventDefault();
            tabTrigger.show();
            // Clear search when changing tabs
            document.getElementById('helpSearchInput').value = '';
            filterFAQs(); 
        });
    });

    // 2. Handle Deep Links (e.g. help.php#content-returns)
    document.addEventListener("DOMContentLoaded", function() {
        var hash = window.location.hash;
        if (hash) {
            var targetID = "";
            if(hash.includes("return")) targetID = "#tab-returns";
            if(hash.includes("account")) targetID = "#tab-account";
            if(hash.includes("delivery")) targetID = "#tab-delivery";

            if (targetID) {
                var triggerEl = document.querySelector(targetID);
                if (triggerEl) {
                    var tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                    triggerEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }
    });

    // 3. LIVE SEARCH LOGIC
    document.getElementById('helpSearchInput').addEventListener('keyup', filterFAQs);

    function filterFAQs() {
        let filter = document.getElementById('helpSearchInput').value.toLowerCase();
        
        // Only search within the currently active tab
        let activeTab = document.querySelector('.tab-pane.active');
        let items = activeTab.querySelectorAll('.faq-item');
        let hasVisibleItems = false;

        items.forEach(function(item) {
            let text = item.innerText.toLowerCase();
            if (text.includes(filter)) {
                item.style.display = '';
                hasVisibleItems = true;
            } else {
                item.style.display = 'none';
            }
        });

        // Show "No Results" message if everything is hidden
        let noResultsDiv = document.getElementById('noResultsMsg');
        if (!hasVisibleItems && filter !== '') {
            noResultsDiv.classList.remove('d-none');
        } else {
            noResultsDiv.classList.add('d-none');
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>