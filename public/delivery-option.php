<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";
?>

<script>document.title = "Global Delivery Options | ShopCorrect";</script>

<style>
    :root { 
        --sc-navy: #0B2447; 
        --sc-accent: #19376D; 
        --sc-gold: #FFD700;
        --sc-bg: #F8FAFC; 
        --sc-text: #334155;
    }

    body { 
        background-color: var(--sc-bg); 
    }
    
    main { 
        min-height: calc(100vh - 250px); 
    }

    /* --- Hero Section --- */
    .page-hero {
        background: linear-gradient(135deg, #0B2447 0%, #19376D 100%);
        color: white;
        padding: 4rem 1rem 7rem 1rem; 
        text-align: center;
        position: relative;
    }
    
    .hero-badge {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: var(--sc-gold);
        font-weight: 600;
        letter-spacing: 0.5px;
        padding: 8px 16px;
        border-radius: 50px;
        display: inline-block;
        margin-bottom: 1.5rem;
        font-size: 0.85rem;
    }

    /* --- Delivery Cards --- */
    .cards-container {
        margin-top: -4rem;
        position: relative;
        z-index: 10;
    }

    .option-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 2.5rem 2rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
    }
    
    .option-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(11, 36, 71, 0.08);
        border-color: #cbd5e1;
    }

    .option-card.express-card {
        border: 2px solid var(--sc-navy);
        background: linear-gradient(180deg, #ffffff 0%, #f4f7fa 100%);
        transform: translateY(-4px); 
    }
    .option-card.express-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 20px 40px rgba(11, 36, 71, 0.15);
    }
    .express-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--sc-navy);
        color: white;
        font-size: 0.7rem;
        font-weight: 800;
        padding: 6px 16px;
        border-bottom-left-radius: 16px;
        letter-spacing: 1px;
    }

    /* ✅ Renamed to avoid targeting the navbar cart */
    .delivery-icon-wrapper {
        width: 64px;
        height: 64px;
        background-color: #f1f5f9;
        color: var(--sc-navy);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1.5rem;
        transition: 0.3s;
    }
    
    .express-card .delivery-icon-wrapper {
        background-color: var(--sc-navy);
        color: var(--sc-gold);
    }

    .option-card:hover .delivery-icon-wrapper:not(.express-icon) {
        background-color: var(--sc-navy);
        color: white;
    }

    .card-meta-box {
        margin-top: auto;
        background: #f8fafc;
        border-radius: 12px;
        padding: 1rem;
        border: 1px solid #f1f5f9;
    }

    .express-card .card-meta-box {
        background: white;
        border-color: #e2e8f0;
    }

    .meta-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        font-weight: 700;
        color: #64748b;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
        display: block;
    }

    /* --- FAQ Section --- */
    .faq-section {
        margin-top: 5rem;
        margin-bottom: 5rem;
    }
    
    .accordion-item {
        border: 1px solid #e2e8f0;
        margin-bottom: 1rem;
        border-radius: 16px !important;
        overflow: hidden;
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .accordion-button {
        background-color: white;
        font-weight: 700;
        color: var(--sc-navy);
        padding: 1.25rem 1.5rem;
        font-size: 1rem;
    }
    
    .accordion-button:not(.collapsed) {
        background-color: #f8fafc;
        color: var(--sc-navy);
        box-shadow: none;
    }
    
    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(0,0,0,0.05);
    }
    
    .accordion-body {
        padding: 0 1.5rem 1.5rem 1.5rem;
        color: #475569;
        font-size: 0.95rem;
        line-height: 1.6;
        background-color: #f8fafc;
    }
</style>

<main>
    <section class="page-hero">
        <div class="container">
            <div class="hero-badge">
                <i class="bi bi-globe-americas me-2"></i>Global Logistics & Shipping
            </div>
            <h1 class="display-5 fw-bold mb-3">How we get orders to you</h1>
            <p class="lead text-white-50 mx-auto" style="max-width: 650px; font-size: 1.1rem;">
                From local deliveries to international shipping across our 12 supported countries, we ensure your items arrive safely and on time.
            </p>
        </div>
    </section>

    <div class="container pb-5">
        
        <div class="row g-4 cards-container justify-content-center">
            
            <div class="col-lg-4 col-md-6">
                <div class="option-card">
                    <div class="delivery-icon-wrapper">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Standard Delivery</h4>
                    <p class="text-secondary small mb-4 line-height-base">
                        Reliable doorstep delivery locally and across borders via our trusted global logistics partners. Perfect for items you don't need immediately.
                    </p>
                    
                    <div class="card-meta-box d-flex justify-content-between align-items-center">
                        <div>
                            <span class="meta-label">Timeline</span>
                            <span class="fw-bold text-dark">3 - 7 Business Days</span>
                        </div>
                        <div class="text-end">
                            <span class="meta-label">Cost</span>
                            <span class="fw-bold text-dark">Varies by Region</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="option-card express-card">
                    <div class="express-badge">FASTEST</div>
                    <div class="delivery-icon-wrapper express-icon">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Express Delivery</h4>
                    <p class="text-secondary small mb-4 line-height-base">
                        In a hurry? Get your items delivered rapidly. Available in major metropolitan areas across our supported domestic and international regions.
                    </p>
                    
                    <div class="card-meta-box d-flex justify-content-between align-items-center">
                        <div>
                            <span class="meta-label">Timeline</span>
                            <span class="fw-bold text-dark">1 - 2 Business Days</span>
                        </div>
                        <div class="text-end">
                            <span class="meta-label">Cost</span>
                            <span class="fw-bold text-dark" style="font-size: 0.9rem;">Calculated at Checkout</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="option-card">
                    <div class="delivery-icon-wrapper">
                        <i class="bi bi-shop"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Pickup Station</h4>
                    <p class="text-secondary small mb-4 line-height-base">
                        Collect your items at your own convenience from hundreds of designated secure pickup points globally.
                    </p>
                    
                    <div class="card-meta-box d-flex justify-content-between align-items-center">
                        <div>
                            <span class="meta-label">Timeline</span>
                            <span class="fw-bold text-dark">2 - 5 Business Days</span>
                        </div>
                        <div class="text-end">
                            <span class="meta-label">Cost</span>
                            <span class="fw-bold text-success">Most Affordable</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row justify-content-center faq-section">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h3 class="fw-bold text-dark mb-2">Frequently Asked Questions</h3>
                    <p class="text-secondary">Everything you need to know about our global delivery process.</p>
                </div>

                <div class="accordion" id="deliveryFAQ">
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                <i class="bi bi-geo-alt me-3 text-muted"></i> Can I change my delivery address after ordering?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#deliveryFAQ">
                            <div class="accordion-body border-top">
                                We process orders rapidly to ensure fast delivery. If your order status is still marked as <strong>"Pending"</strong> in your account dashboard, you may contact customer support to update the address. Once the status changes to <strong>"Shipped"</strong>, the delivery address cannot be modified, especially for cross-border shipments.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                <i class="bi bi-globe me-3 text-muted"></i> Do you ship internationally?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#deliveryFAQ">
                            <div class="accordion-body border-top">
                                Yes! ShopCorrect currently supports shipping to 12 major countries including Ghana, Nigeria, Kenya, South Africa, the UK, USA, Canada, Germany, and China. Customs and import duties may apply depending on your region and are calculated at checkout.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                <i class="bi bi-telephone-x me-3 text-muted"></i> What happens if I miss my delivery?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#deliveryFAQ">
                            <div class="accordion-body border-top">
                                Our logistics partners will always attempt to contact you upon arrival. If you are unreachable or miss the delivery, we will safely hold your item and attempt delivery <strong>one more time</strong> the following business day before returning the item to the origin facility.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . "/partials/footer.php"; ?>