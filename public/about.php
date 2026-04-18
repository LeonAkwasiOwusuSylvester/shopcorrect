<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";
?>

<script>document.title = "About Us | ShopCorrect";</script>

<style>
    :root { 
        --sc-navy: #0B2447; 
        --sc-soft: #F8FAFC; 
        --sc-accent: #19376D;
        --sc-gold: #FFD700;
        --sc-text: #334155;
    }
    
    body { 
        background-color: var(--sc-soft); 
        color: var(--sc-text); 
    }
    
    main { 
        min-height: calc(100vh - 250px); 
    }
    
    /* --- Hero Section --- */
    .about-hero {
        background: linear-gradient(135deg, #0B2447 0%, #19376D 100%);
        color: white; 
        padding: 6rem 1rem 8rem 1rem; 
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

    /* --- Image & Story Section --- */
    .story-container {
        margin-top: -5rem;
        position: relative;
        z-index: 10;
        background: white;
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(11, 36, 71, 0.08);
        padding: 3rem;
        border: 1px solid #e2e8f0;
    }

    .story-img-wrapper {
        border-radius: 16px;
        overflow: hidden;
        position: relative;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .story-img-wrapper img {
        transition: transform 0.5s ease;
    }

    .story-img-wrapper:hover img {
        transform: scale(1.03);
    }

    /* --- Stats Section --- */
    .stat-box {
        padding: 1.5rem;
        border-radius: 16px;
        background: var(--sc-soft);
        border: 1px solid #e2e8f0;
        transition: 0.3s;
    }
    .stat-box:hover {
        background: white;
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        border-color: #cbd5e1;
    }
    .stat-icon {
        width: 50px; height: 50px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    /* --- Feature Cards --- */
    .feature-card {
        background: white; 
        border: 1px solid #e2e8f0; 
        border-radius: 20px;
        padding: 2.5rem 2rem; 
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); 
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    .feature-card:hover { 
        transform: translateY(-8px); 
        box-shadow: 0 20px 40px rgba(11, 36, 71, 0.08); 
        border-color: var(--sc-navy);
    }
    
    .icon-box {
        width: 64px; height: 64px; 
        background: #f1f5f9; 
        color: var(--sc-navy);
        border-radius: 50%; 
        display: flex; align-items: center; justify-content: center;
        font-size: 1.75rem; 
        margin-bottom: 1.5rem;
        transition: 0.3s ease;
    }
    .feature-card:hover .icon-box {
        background: var(--sc-navy);
        color: var(--sc-gold);
    }

    /* --- Global Flags Section --- */
    .global-reach-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
        margin-top: 2rem;
        opacity: 0.8;
    }
    .global-reach-bar img {
        height: 24px;
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: 0.3s;
    }
    .global-reach-bar img:hover {
        transform: translateY(-2px);
        opacity: 1;
    }

    /* --- CTA Section --- */
    .cta-section {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border: 1px solid #cbd5e1;
        border-radius: 24px;
        padding: 5rem 2rem;
        text-align: center;
        margin-top: 4rem;
        margin-bottom: 4rem;
        position: relative;
        overflow: hidden;
    }
    .cta-section::before {
        content: "\F134"; /* Bootstrap icon cart */
        font-family: "bootstrap-icons";
        position: absolute;
        top: -20px;
        right: -20px;
        font-size: 15rem;
        color: rgba(11, 36, 71, 0.03);
        z-index: 0;
    }
    .cta-content {
        position: relative;
        z-index: 1;
    }

    @media (max-width: 991px) {
        .story-container { padding: 2rem 1.5rem; margin-top: -3rem; }
    }
</style>

<main>
    <section class="about-hero">
        <div class="container">
            <div class="hero-badge">
                <i class="bi bi-globe2 me-2"></i>Our Mission
            </div>
            <h1 class="fw-bold display-4 mb-3 text-white">Empowering Global Commerce</h1>
            <p class="lead text-white-50 mx-auto" style="max-width: 650px; font-size: 1.1rem;">
                ShopCorrect is a premier international marketplace, connecting millions of buyers and verified sellers across 12 countries with secure, seamless technology.
            </p>
        </div>
    </section>

    <div class="container pb-5">
        
        <div class="story-container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="story-img-wrapper">
                        <img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&q=80&w=800" 
                             alt="Team working together on global commerce" class="img-fluid w-100">
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="text-uppercase fw-bold mb-2" style="font-size: 0.8rem; letter-spacing: 1.5px; color: #3B82F6;">Our Story</h6>
                    <h2 class="fw-bold text-dark mb-4">Building the Future of Shopping</h2>
                    <p class="text-secondary mb-4" style="line-height: 1.8; font-size: 1.05rem;">
                        Launched with a vision to simplify trade, ShopCorrect provides a secure platform where vendors can scale their businesses globally, and customers can access premium products from Africa, Europe, North America, and Asia. We believe in absolute transparency, borderless reach, and unshakeable trust.
                    </p>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-sm-6">
                            <div class="stat-box d-flex align-items-center gap-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-shop"></i>
                                </div>
                                <div>
                                    <h3 class="fw-bold text-dark mb-0 fs-4">10k+</h3>
                                    <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.7rem;">Active Vendors</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="stat-box d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-globe-americas"></i>
                                </div>
                                <div>
                                    <h3 class="fw-bold text-dark mb-0 fs-4">12</h3>
                                    <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.7rem;">Countries Served</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5 pt-4 border-top">
                <p class="small text-muted fw-bold text-uppercase tracking-wide mb-3">Connecting Markets In</p>
                <div class="global-reach-bar">
                    <img src="https://flagcdn.com/w40/gh.png" alt="Ghana" title="Ghana">
                    <img src="https://flagcdn.com/w40/ng.png" alt="Nigeria" title="Nigeria">
                    <img src="https://flagcdn.com/w40/ke.png" alt="Kenya" title="Kenya">
                    <img src="https://flagcdn.com/w40/za.png" alt="South Africa" title="South Africa">
                    <img src="https://flagcdn.com/w40/ci.png" alt="Cote d'Ivoire" title="Cote d'Ivoire">
                    <img src="https://flagcdn.com/w40/us.png" alt="USA" title="USA">
                    <img src="https://flagcdn.com/w40/gb.png" alt="UK" title="UK">
                    <img src="https://flagcdn.com/w40/ca.png" alt="Canada" title="Canada">
                    <img src="https://flagcdn.com/w40/de.png" alt="Germany" title="Germany">
                    <img src="https://flagcdn.com/w40/cn.png" alt="China" title="China">
                </div>
            </div>
        </div>

        <div class="row g-4 mt-5">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="icon-box"><i class="bi bi-shield-lock"></i></div>
                    <h5 class="fw-bold text-dark">Secure Escrow Payments</h5>
                    <p class="text-secondary small mb-0" style="line-height: 1.6;">We hold payments securely until you successfully receive your item, ensuring 100% safety and peace of mind for every local and international transaction.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="icon-box"><i class="bi bi-airplane"></i></div>
                    <h5 class="fw-bold text-dark">Global Logistics</h5>
                    <p class="text-secondary small mb-0" style="line-height: 1.6;">Our integrated, cross-border logistics network ensures your products arrive on time, right to your doorstep or pickup station, regardless of where they ship from.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="icon-box"><i class="bi bi-patch-check"></i></div>
                    <h5 class="fw-bold text-dark">Verified Vendors</h5>
                    <p class="text-secondary small mb-0" style="line-height: 1.6;">Every seller on ShopCorrect goes through a rigorous identity and business verification process to ensure product authenticity and reliable service.</p>
                </div>
            </div>
        </div>

        <div class="cta-section">
            <div class="cta-content">
                <h2 class="fw-bold text-dark mb-3">Ready to experience ShopCorrect?</h2>
                <p class="text-secondary mb-4 mx-auto" style="max-width: 550px; font-size: 1.05rem;">
                    Join hundreds of thousands of shoppers worldwide who have already made the switch to smarter, safer online shopping.
                </p>
                <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
                    <a href="index.php" class="btn btn-dark rounded-pill px-5 py-3 fw-bold" style="background-color: var(--sc-navy); border-color: var(--sc-navy);">Start Shopping</a>
                    <a href="becomevendor.php" class="btn btn-outline-dark rounded-pill px-5 py-3 fw-bold bg-white">Become a Vendor</a>
                </div>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . "/partials/footer.php"; ?>