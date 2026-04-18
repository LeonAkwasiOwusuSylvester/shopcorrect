<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance | ShopCorrect</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        :root {
            --brand-primary: #0B2447;
            --brand-accent: #19376D;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-image: 
                linear-gradient(135deg, rgba(11, 36, 71, 0.95), rgba(25, 55, 109, 0.85)),
                url('https://images.unsplash.com/photo-1563013544-824ae1b704d3?q=80&w=1920&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
            overflow: hidden;
        }

        /* Background Watermark */
        body::before {
            content: "UPGRADING";
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-20deg);
            font-size: 15vw; font-weight: 900;
            color: rgba(255, 255, 255, 0.02);
            white-space: nowrap; pointer-events: none; letter-spacing: 2rem;
            z-index: 0;
        }

        .maintenance-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            z-index: 1;
            animation: fadeIn 0.6s ease-out;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 2rem;
        }

        .brand-logo img {
            height: 45px;
            border-radius: 8px;
        }

        .brand-logo span {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
        }

        .icon-container {
            font-size: 4rem;
            color: #FF6B00; 
            margin-bottom: 1.5rem;
            display: inline-block;
        }

        .spin-slow {
            animation: spin 4s linear infinite;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -0.5px;
        }

        p {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .support-box {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .support-box p {
            margin: 0;
            font-size: 0.85rem;
        }

        .support-box a {
            color: #ffffff;
            font-weight: 700;
            text-decoration: none;
            transition: 0.2s;
        }

        .support-box a:hover {
            color: #FF6B00;
        }

        .global-icon {
            font-size: 1.1rem;
            vertical-align: middle;
            margin-left: 4px;
            color: rgba(255, 255, 255, 0.6);
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="maintenance-card">
        
        <div class="brand-logo">
            <img src="assets/images/logo_w.png" alt="ShopCorrect Logo" onerror="this.src='../assets/images/logo_w.png'">
            <span>ShopCorrect</span>
        </div>

        <div class="icon-container spin-slow">
            <i class="bi bi-gear-wide-connected"></i>
        </div>

        <h1>System Optimization</h1>
        <p>We are currently upgrading our platform to provide you with an even better shopping experience. We'll be back online shortly.</p>

        <div class="support-box">
            <p>Need urgent assistance?</p>
            <p>Contact <a href="mailto:support@shopcorrect.com">support@shopcorrect.com</a></p>
            <p style="margin-top: 10px; color: rgba(255,255,255,0.4); font-size: 0.75rem;">
                Thank you for your patience <span class="global-icon"><i class="bi bi-globe"></i></span>
            </p>
        </div>

    </div>

</body>
</html>