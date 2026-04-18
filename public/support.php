<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Contact Us | ShopCorrect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #F8FAFC; font-family: 'Inter', sans-serif; color: #334155; }
        .contact-card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.03); overflow: hidden; }
        .bg-navy { background-color: #0B2447; color: white; }
        .form-control { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; }
        .form-control:focus { border-color: #0B2447; box-shadow: none; background: white; }
        .btn-navy { background-color: #0B2447; color: white; padding: 12px 30px; border-radius: 8px; }
        .btn-navy:hover { background-color: #19376D; color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="contact-card">
                <div class="row g-0">
                    <div class="col-md-5 bg-navy p-5 d-flex flex-column justify-content-between">
                        <div>
                            <h3 class="fw-bold mb-4">Let's talk</h3>
                            <p class="text-white-50 mb-5">Have questions about your order, account, or becoming a vendor? We're here to help.</p>
                            
                            <div class="d-flex align-items-start mb-4">
                                <i class="bi bi-geo-alt fs-5 me-3 text-warning"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Visit Us</h6>
                                    <small class="text-white-50">12 Airport City Road,<br>Accra, Ghana</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-4">
                                <i class="bi bi-envelope fs-5 me-3 text-warning"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Email Us</h6>
                                    <small class="text-white-50">support@shopcorrect.com</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <i class="bi bi-telephone fs-5 me-3 text-warning"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Call Us</h6>
                                    <small class="text-white-50">+233 (0) 555 123 456</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5">
                            <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                            <a href="#" class="text-white me-3"><i class="bi bi-twitter-x"></i></a>
                            <a href="#" class="text-white"><i class="bi bi-instagram"></i></a>
                        </div>
                    </div>

                    <div class="col-md-7 p-5 bg-white">
                        <h4 class="fw-bold text-dark mb-4">Send us a message</h4>
                        <form>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Name</label>
                                    <input type="text" class="form-control" placeholder="John Doe">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Email</label>
                                    <input type="email" class="form-control" placeholder="john@example.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Subject</label>
                                    <select class="form-select form-control">
                                        <option>Order Inquiry</option>
                                        <option>Vendor Support</option>
                                        <option>Technical Issue</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Message</label>
                                    <textarea class="form-control" rows="4" placeholder="How can we help?"></textarea>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-navy w-100 fw-bold">Send Message</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html> 