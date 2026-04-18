<?php
/* ==========================================================================
   🎉 ADVANCED DYNAMIC PROMO BAR (WITH COUNTDOWN)
   ========================================================================== */
$showHelloBar   = false;
$helloPromoText = '';
$helloPromoCode = '';
$promoEndDate   = '';
$promoMessages  = [];

try {
    $pbStmt = $pdo->query("SELECT promo_active, promo_text, promo_code, promo_end_date FROM settings LIMIT 1");
    $pbData = $pbStmt->fetch(PDO::FETCH_ASSOC);
    if ($pbData && $pbData['promo_active'] == 1) {
        $showHelloBar   = true;
        $helloPromoText = $pbData['promo_text']     ?? 'Special Promotion!';
        $helloPromoCode = $pbData['promo_code']     ?? '';
        $promoEndDate   = $pbData['promo_end_date'] ?? '';
        $promoMessages  = array_values(array_filter(array_map('trim', explode('|', $helloPromoText))));
        if (empty($promoMessages)) $promoMessages = ['Special Promotion!'];
    }
} catch (PDOException $e) { /* Fail silently */ }
?>

<?php if ($showHelloBar): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">

<style>
    /* ══════════════════════════════
       PROMO BAR — Premium Core
    ══════════════════════════════ */
    .top-promo-bar {
        /* Dynamic moving gradient background */
        background: linear-gradient(90deg, #6b0f1a, #d32f2f, #b21f2d, #6b0f1a);
        background-size: 300% 300%;
        animation: promoGradientShift 8s ease infinite;
        border-bottom: 2px solid #ffc107; 
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
        background: linear-gradient(135deg, #ffc107, #ff9800);
        color: #0B2447;
        font-weight: 800;
        font-size: 0.95rem;
        padding: 4px 12px;
        border-radius: 4px;
        letter-spacing: 1px;
        text-transform: uppercase;
        white-space: nowrap;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
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
        color: #ffffff;
        font-weight: 600;
        font-size: 1.1rem;
        letter-spacing: 0.3px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        position: absolute;
        width: 100%;
        transition: opacity 0.4s ease, transform 0.4s ease;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }

    .promo-text-content i {
        color: #ffc107;
        margin-right: 6px;
    }

    .promo-text-content.exiting  { opacity: 0; transform: translateY(-12px); }
    .promo-text-content.entering { opacity: 0; transform: translateY(12px); transition: none; }
    .promo-text-content.visible  { opacity: 1; transform: translateY(0); transition: opacity 0.4s ease, transform 0.4s ease; }

    /* ══════════════════════════════
       DIVIDER
    ══════════════════════════════ */
    .promo-divider {
        width: 1px;
        height: 24px;
        background: rgba(255,255,255,0.25);
        flex-shrink: 0;
    }

    /* ══════════════════════════════
       COUNTDOWN TIMER
    ══════════════════════════════ */
    .promo-countdown {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .promo-countdown .cd-label {
        color: #ffc107;
        font-size: 0.95rem;
        font-weight: 600;
        margin-right: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .time-block {
        display: flex;
        align-items: baseline;
        gap: 2px;
        background: rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 4px;
        padding: 3px 8px;
        font-variant-numeric: tabular-nums;
    }

    .time-block .t-num {
        color: #ffffff;
        font-size: 1.05rem;
        font-weight: 800;
    }

    .time-block .t-lbl {
        color: rgba(255,255,255,0.8);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: lowercase;
    }

    .time-colon {
        color: #ffc107;
        font-size: 1.05rem;
        font-weight: 700;
    }

    /* ══════════════════════════════
       PROMO CODE BUTTON
    ══════════════════════════════ */
    .promo-code-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(0,0,0,0.2);
        color: #ffffff;
        font-weight: 700;
        font-size: 0.95rem;
        padding: 6px 16px;
        border-radius: 4px;
        border: 1px dashed rgba(255,193,7,0.8);
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .promo-code-btn:hover {
        background: #ffc107;
        color: #0B2447;
        border-style: solid;
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
            padding: 8px 12px; 
            gap: 6px 10px; 
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
            padding: 2px 6px; 
            background: rgba(0,0,0,0.2);
            border: none;
        }
        
        .time-block .t-num { 
            font-size: 0.95rem; 
        }

        .time-block .t-lbl {
            font-size: 0.7rem;
        }
        
        .promo-code-btn { 
            font-size: 0.9rem; 
            padding: 5px 12px;
        }

        .promo-countdown {
            order: 2;
        }
        .promo-code-btn {
            order: 3;
        }
    }
</style>

<div class="top-promo-bar" id="helloBar" role="banner" aria-label="Promotional offer">
    <div class="promo-bar-inner">

        <span class="promo-badge">
            🔥 FLASH OFFER
        </span>

        <div class="promo-divider"></div>

        <div class="text-slider-wrap" aria-live="polite" aria-atomic="true">
            <span class="promo-text-content visible" id="promoTextSlider">
                <i class="fa-solid fa-cart-shopping"></i> <?= htmlspecialchars($promoMessages[0]) ?>
            </span>
        </div>

        <?php if (!empty($promoEndDate)): ?>
            <div class="promo-divider"></div>
            <div id="promoCountdown" class="promo-countdown"
                 data-end="<?= htmlspecialchars($promoEndDate) ?>"
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
                    data-code="<?= htmlspecialchars($helloPromoCode) ?>"
                    id="promoBtn"
                    type="button"
                    aria-label="Copy promo code <?= htmlspecialchars($helloPromoCode) ?>">
                <i class="fa-solid fa-ticket btn-icon" aria-hidden="true"></i>
                <span class="btn-code-text">Code: <?= htmlspecialchars($helloPromoCode) ?></span>
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
    var messages    = <?= json_encode($promoMessages) ?>;
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

                slider.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> ' + messages[msgIndex];
                slider.classList.remove('exiting');
                slider.classList.add('entering');

                void slider.offsetWidth; // Force reflow

                slider.classList.remove('entering');
                slider.classList.add('visible');

                setTimeout(function() { isAnimating = false; }, 450);
            }, 420);

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