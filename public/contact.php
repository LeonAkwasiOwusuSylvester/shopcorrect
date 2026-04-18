<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/partials/navbar.php";

// --- 1. BACKEND LOGIC TO HANDLE FORM SUBMISSION ---
$message_sent = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $msg     = trim($_POST['message'] ?? '');

    // Basic Validation
    if (!empty($name) && !empty($email) && !empty($msg)) {
        try {
            // Insert into Database
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $msg]);
            $message_sent = true;
        } catch (PDOException $e) {
            $error = "System error: Could not send message. Please try again.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Us | ShopCorrect</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --sc-navy: #0B2447;
            --sc-blue: #19376D;
            --sc-gold: #FFD700;
            --sc-bg: #F8FAFC;
        }
        
        body { 
            background-color: var(--sc-bg); 
            background-image: 
                radial-gradient(at 0% 0%, rgba(11, 36, 71, 0.06) 0px, transparent 40%),
                radial-gradient(at 100% 100%, rgba(255, 215, 0, 0.04) 0px, transparent 40%);
            background-attachment: fixed;
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #334155; 
        }

        /* --- Main Card Styling --- */
        .contact-card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.06); 
            overflow: hidden; 
            margin-top: 3rem;
            margin-bottom: 5rem;
        }

        /* --- Left Sidebar (Info) --- */
        .sidebar-navy { 
            background: linear-gradient(145deg, var(--sc-navy), var(--sc-blue)); 
            color: white; 
            padding: 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        
        /* Decorative circle in background */
        .sidebar-navy::before {
            content: '';
            position: absolute;
            top: -50px; left: -50px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .section-title { font-weight: 800; letter-spacing: -0.5px; }
        
        .info-list { margin-top: 2rem; }
        .info-item { margin-bottom: 2rem; display: flex; align-items: flex-start; gap: 1rem; }
        
        .icon-box { 
            width: 40px; height: 40px; 
            background: rgba(255,255,255,0.1); 
            border-radius: 10px; 
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            color: var(--sc-gold);
        }

        .info-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 2px; display: block; opacity: 0.9; }
        .info-text { font-size: 0.9rem; opacity: 0.7; line-height: 1.5; font-weight: 300; }

        /* --- Horizontal Social Media Section --- */
        .social-section {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 2rem;
            margin-top: 2rem;
        }
        .social-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; margin-bottom: 1rem; display: block; }
        
        .social-icons-wrapper {
            display: flex;
            flex-direction: row; /* Horizontal alignment */
            gap: 1rem;
        }

        .social-link { 
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.1);
            color: white; 
            border-radius: 50%;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .social-link:hover { 
            background: var(--sc-gold); 
            color: var(--sc-navy); 
            transform: translateY(-3px);
        }

        /* --- Right Side (Form) --- */
        .form-section { padding: 3.5rem; background-color: white; }
        
        .form-label { font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: #475569; }
        
        .form-control, .form-select { 
            background-color: #f8fafc; 
            border: 1px solid #e2e8f0; 
            padding: 0.9rem 1rem; 
            border-radius: 10px;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        
        .form-control:focus, .form-select:focus { 
            border-color: var(--sc-navy); 
            box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1); 
            background-color: #fff;
        }

        .btn-submit { 
            background-color: var(--sc-navy); 
            color: white; 
            padding: 14px; 
            border-radius: 10px; 
            font-weight: 600; 
            letter-spacing: 0.5px;
            width: 100%; 
            border: none;
            transition: all 0.2s;
        }
        .btn-submit:hover { 
            background-color: #163a6e; 
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11, 36, 71, 0.15);
        }

    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-11 col-xl-10">
            
            <?php if ($message_sent): ?>
                <div class="alert alert-success mt-4 border-0 shadow-sm rounded-3 d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                    <div>
                        <div class="fw-bold">Message Sent!</div>
                        <div class="small">We have received your inquiry and will respond shortly.</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger mt-4 border-0 shadow-sm rounded-3 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                    <div>
                        <div class="fw-bold">Failed to Send</div>
                        <div class="small"><?= htmlspecialchars($error) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="contact-card">
                <div class="row g-0 h-100">
                    
                    <div class="col-md-5 sidebar-navy">
                        <div>
                            <h2 class="section-title mb-3">Let's talk</h2>
                            <p class="mb-4" style="opacity: 0.8; font-size: 0.95rem; line-height: 1.6;">
                                Have questions about your order, account, or becoming a vendor? We're here to help.
                            </p>

                            <div class="info-list">
                                <div class="info-item">
                                    <div class="icon-box"><i class="bi bi-geo-alt-fill"></i></div>
                                    <div>
                                        <span class="info-title">Visit Us</span>
                                        <span class="info-text">12 Airport City Road,<br>Accra, Ghana</span>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="icon-box"><i class="bi bi-envelope-fill"></i></div>
                                    <div>
                                        <span class="info-title">Email Us</span>
                                        <span class="info-text">support@shopcorrect.com</span>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="icon-box"><i class="bi bi-telephone-fill"></i></div>
                                    <div>
                                        <span class="info-title">Call Us</span>
                                        <span class="info-text">+233 (0) 59 426 4517</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="social-section">
                            <span class="social-label">Connect with us</span>
                            <div class="social-icons-wrapper">
                                <a href="#" class="social-link" title="Facebook"><i class="bi bi-facebook"></i></a>
                                <a href="#" class="social-link" title="Twitter/X"><i class="bi bi-twitter-x"></i></a>
                                <a href="#" class="social-link" title="Instagram"><i class="bi bi-instagram"></i></a>
                                <a href="#" class="social-link" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-7 form-section">
                        <form action="contact.php" method="POST">
                            <div class="mb-4">
                                <h3 class="fw-bold text-dark">Send us a message</h3>
                                <p class="text-muted small">We usually respond within 24 hours.</p>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="John Doe" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Subject</label>
                                    <select name="subject" class="form-select">
                                        <option value="Order Inquiry">Order Inquiry</option>
                                        <option value="Vendor Application">Vendor Application</option>
                                        <option value="Technical Support">Technical Support</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="5" placeholder="Tell us how we can help..." required></textarea>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn-submit">
                                        Send Message <i class="bi bi-arrow-right-short fs-5 ms-1" style="vertical-align: middle;"></i>
                                    </button>
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