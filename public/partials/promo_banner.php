<?php
/* ==========================================================================
   🎉 ADVANCED DYNAMIC PROMO BAR (B3 SECURED)
   ========================================================================== */
$showHelloBar   = false;
$helloPromoCode = '';
$promoEndDate   = '';
$promoMessages  = [];

try {
    // B3 SECURITY: Enforce strict limits and handle potential database injection points
    $pbStmt = $pdo->query("SELECT promo_active, promo_text, promo_code, promo_end_date FROM settings LIMIT 1");
    $pbData = $pbStmt->fetch(PDO::FETCH_ASSOC);

    if ($pbData && $pbData['promo_active'] == 1) {
        $showHelloBar   = true;
        
        // B3 SECURITY: Strict type casting and stripping of raw inputs
        $rawPromoText   = (string)($pbData['promo_text'] ?? 'Special Promotion!');
        $helloPromoCode = htmlspecialchars(trim((string)($pbData['promo_code'] ?? '')), ENT_QUOTES, 'UTF-8');
        $promoEndDate   = htmlspecialchars(trim((string)($pbData['promo_end_date'] ?? '')), ENT_QUOTES, 'UTF-8');
        
        // B3 SECURITY: Map through the array and lock down every single piece of text against XSS
        $promoMessages = array_values(array_filter(array_map(function($msg) {
            return htmlspecialchars(trim($msg), ENT_QUOTES, 'UTF-8');
        }, explode('|', $rawPromoText))));

        if (empty($promoMessages)) $promoMessages = ['Special Promotion!'];
    }
} catch (PDOException $e) { 
    // Fail silently to prevent path exposure on error
}
?>

<?php if ($showHelloBar): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">

<style>
    /* ══════════════════════════════
       PROMO BAR — Premium Navy Core
    ══════════════════════════════ */
    .top-promo-bar {
        /* Sleek, professional moving gradient */
        background: linear-gradient(90deg, #0B2447, #1e293b, #0f172a, #0B2447);
        background-size: 300% 300%;
        animation: promoGradientShift 12s ease infinite;
        border-bottom: 1px solid rgba(255, 215, 0, 0.3); 
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        padding: 0;
        display: flex;
        align-items: stretch;
        justify-content: center;
        width: 100%;
        position: relative;
        z-index: 1060;
        min-height: 54px;
    }

    @keyframes promoGradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .promo-bar-inner {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 12px 24px;
        padding: 8px 20px;
        width: 100%;
        max-width: 1400px;
    }

    /* ══════════════════════════════
       OFFICIAL BADGE
    ══════════════════════════════ */
    .promo-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #FFD700, #F59E0B);
        color: #0B2447;
        font-weight: 800;
        font-size: 0.90rem;
        padding: 5px 14px;
        border-radius: 50px; /* Modern pill shape */
        letter-spacing: 1px;
        text-transform: uppercase;
        white-space: nowrap;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(255, 215, 0, 0.25);
    }

    /* ══════════════════════════════
       TEXT SLIDER
    ══════════════════════════════ */
    .text-slider-wrap {
        flex: 1 1 auto;
        min-width: 0;
        max-width: 600px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 28px;
        position: relative;
    }

    .promo-text-content {
        color: #F8FAFC;
        font-weight: 500;
        font-size: 1.05rem;
        letter-spacing: 0.5px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        position: absolute;
        width: 100%;
        transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .promo-text-content i {
        color: #FFD700;
        margin-right: 8px;
    }

    .promo-text-content.exiting  { opacity: 0; transform: translateY(-15px); }
    .promo-text-content.entering { opacity: 0; transform: translateY(15px); transition: none; }
    .promo-text-content.visible  { opacity: 1; transform: translateY(0); transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.4, 0, 0.2, 1); }

    /* ══════════════════════════════
       DIVIDER
    ══════════════════════════════ */
    .promo-divider {
        width: 1px;
        height: 24px;
        background: rgba(255,255,255,0.15);
        flex-shrink: 0;
    }

    /* ══════════════════════════════
       COUNTDOWN TIMER (Glassmorphism)
    ══════════════════════════════ */
    .promo-countdown {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .promo-countdown .cd-label {
        color: #FFD700;
        font-size: 0.90rem;
        font-weight: 700;
        margin-right: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .time-block {
        display: flex;
        align-items: baseline;
        gap: 3px;
        background: rgba(255, 255, 255, 0.08); /* Glass effect */
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255, 215, 0, 0.2);
        border-radius: 6px;
        padding: 4px 10px;
        font-variant-numeric: tabular-nums;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.1);
    }

    .time-block .t-num {
        color: #ffffff;
        font-size: 1.1rem;
        font-weight: 800;
    }

    .time-block .t-lbl {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .time-colon {
        color: rgba(255, 215, 0, 0.5);
        font-size: 1.1rem;
        font-weight: 700;
    }

    /* ══════════════════════════════
       PROMO CODE BUTTON
    ══════════════════════════════ */
    .promo-code-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 215, 0, 0.1);
        color: #FFD700;
        font-weight: 700;
        font-size: 0.90rem;
        padding: 6px 18px;
        border-radius: 50px;
        border: 1px dashed rgba(255, 215, 0, 0.5);
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .promo-code-btn:hover {
        background: #FFD700;
        color: #0B2447;
        border-style: solid;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 215, 0, 0.2);
    }

    .promo-code-btn.copied {
        background: #10b981;
        color: #ffffff;
        border-color: #10b981;
        border-style: solid;
        pointer-events: none;
    }

    /* ══════════════════════════════
       RESPONSIVE MOBILE TWEAKS
    ══════════════════════════════ */
    @media (max-width: 768px) {
        .promo-bar-inner { 
            padding: 10px 12px; 
            gap: 8px 10px; 
        }
        
        .promo-badge, 
        .promo-divider,
        .promo-countdown .cd-label { 
            display: none; 
        }
        
        .text-slider-wrap { 
            max-width: 100%; 
            width: 100%; 
            height: 24px;
        }
        
        .promo-text-content { 
            font-size: 0.95rem; 
        }
        
        .time-block { 
            padding: 3px 8px; 
            background: rgba(255, 255, 255, 0.1);
        }
        
        .time-block .t-num { 
            font-size: 0.95rem; 
        }

        .time-block .t-lbl {
            font-size: 0.7rem;
        }
        
        .promo-code-btn { 
            font-size: 0.85rem; 
            padding: 5px 14px;
        }

        .promo-countdown { order: 2; }
        .promo-code-btn { order: 3; }
    }
</style>

<div class="top-promo-bar" id="helloBar" role="banner" aria-label="Promotional offer">
    <div class="promo-bar-inner">

        <span class="promo-badge">
            <i class="fa-solid fa-bolt"></i> FLASH OFFER
        </span>

        <div class="promo-divider"></div>

        <div class="text-slider-wrap" aria-live="polite" aria-atomic="true">
            <span class="promo-text-content visible" id="promoTextSlider">
                <i class="fa-solid fa-gem"></i> <?= $promoMessages[0] ?>
            </span>
        </div>

        <?php if (!empty($promoEndDate)): ?>
            <div class="promo-divider"></div>
            <div id="promoCountdown" class="promo-countdown"
                 data-end="<?= $promoEndDate ?>"
                 aria-label="Countdown timer">
                <span class="cd-label"><i class="fa-solid fa-stopwatch"></i> Ends in</span>
                <div class="time-block"><span class="t-num" id="cd-days">00</span><span class="t-lbl">d</span></div>
                <span class="time-colon">:</span>
                <div class="time-block"><span class="t-num" id="cd-hrs">00</span><span class="t-lbl">h</span></div>
                <span class="time-colon">:</span>
                <div class="time-block"><span class="t-num" id="cd-mins">00</span><span class="t-lbl">m</span></div>
                <span class="time-colon">:</span>
                <div class="time-block"><span class="t-num" id="cd-secs">00</span><span class="t-lbl">s</span></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($helloPromoCode)): ?>
            <div class="promo-divider"></div>
            <button class="promo-code-btn"
                    data-code="<?= $helloPromoCode ?>"
                    id="promoBtn"
                    type="button"
                    aria-label="Copy promo code <?= $helloPromoCode ?>">
                <i class="fa-solid fa-ticket btn-icon" aria-hidden="true"></i>
                <span class="btn-code-text">Code: <?= $helloPromoCode ?></span>
            </button>
        <?php endif; ?>

    </div>
</div>

<script>
(function() {
    'use strict';

    /* ── 1. Copy Code ───────────────────────────────────────── */
    var promoBtn = document.getElementById('promoBtn');
    if (promoBtn) {
        promoBtn.addEventListener('click', function() {
            var btn          = this;
            var code         = btn.getAttribute('data-code');
            var originalHTML = btn.innerHTML;

            var onSuccess = function() {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> Copied!';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = originalHTML;
                }, 2000);
            };

            var onFail = function() {
                console.warn('ShopCorrect: clipboard copy failed.');
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(code).then(onSuccess).catch(onFail);
            } else {
                var tmp;
                try {
                    tmp = document.createElement('textarea');
                    tmp.value = code;
                    tmp.setAttribute('readonly', '');
                    tmp.style.cssText = 'position:absolute;left:-9999px;top:-9999px;';
                    document.body.appendChild(tmp);
                    tmp.select();
                    document.execCommand('copy');
                    document.body.removeChild(tmp);
                    onSuccess();
                } catch (e) {
                    if (tmp && document.body.contains(tmp)) document.body.removeChild(tmp);
                    onFail();
                }
            }
        });
    }

    /* ── 2. Text Slider ─────────────────────────────────────── */
    // B3 SECURITY: JSON Encoding an already sanitized PHP array locks out JS injection
    var messages    = <?= json_encode($promoMessages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var msgIndex    = 0;
    var slider      = document.getElementById('promoTextSlider');
    var isAnimating = false;

    if (slider && messages.length > 1) {
        setInterval(function() {
            if (isAnimating) return;
            isAnimating = true;

            slider.classList.remove('visible');
            slider.classList.add('exiting');

            setTimeout(function() {
                var next = msgIndex;
                while (next === msgIndex) {
                    next = Math.floor(Math.random() * messages.length);
                }
                msgIndex = next;

                // XSS Defense: Since HTML was stripped in PHP, this is safe to inject
                slider.innerHTML = '<i class="fa-solid fa-gem"></i> ' + messages[msgIndex];
                slider.classList.remove('exiting');
                slider.classList.add('entering');

                void slider.offsetWidth; // Force reflow

                slider.classList.remove('entering');
                slider.classList.add('visible');

                setTimeout(function() { isAnimating = false; }, 500);
            }, 500);

        }, 4500);
    }

    /* ── 3. Countdown Timer ─────────────────────────────────── */
    var cdEl = document.getElementById('promoCountdown');
    if (cdEl) {
        var rawEnd  = cdEl.getAttribute('data-end').replace(/-/g, '/');
        var endTime = new Date(rawEnd).getTime();

        if (isNaN(endTime)) {
            cdEl.style.display = 'none';
        } else {
            var pad = function(n) { return String(n).padStart(2, '0'); };

            var tick = function() {
                var distance = endTime - Date.now();

                if (distance <= 0) {
                    clearInterval(timer);
                    cdEl.style.display = 'none';
                    return;
                }

                document.getElementById('cd-days').textContent = pad(Math.floor(distance / 86400000));
                document.getElementById('cd-hrs').textContent  = pad(Math.floor((distance % 86400000) / 3600000));
                document.getElementById('cd-mins').textContent = pad(Math.floor((distance % 3600000)  / 60000));
                document.getElementById('cd-secs').textContent = pad(Math.floor((distance % 60000)    / 1000));
            };

            tick();
            var timer = setInterval(tick, 1000);
        }
    }

})();
</script>
<?php endif; ?>