<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Since this is in the root, we only go into the vendor folder
require_once __DIR__ . "/vendor/autoload.php";

/**
 * Universal ShopCorrect Mailer
 * Production Ready Version - Secured with .env
 */
function sendMail($to, $subject, $title, $message, $button = null, $otpCode = null, $statusType = null)
{
    // 1. Read the hidden .env file sitting in the root
    $env = parse_ini_file(__DIR__ . '/.env');

    // 2. Assign keys from .env
    $smtp_user = $env['GOOGLE_MAILER_USER'] ?? 'shopcorrect.official@gmail.com';
    $smtp_pass = $env['GOOGLE_MAILER_PASS'];

    // --- SAFETY FIX: Prevent Button/OTP Mix-up ---
    if (!is_array($button) && empty($otpCode) && (is_numeric($button) || is_string($button))) {
        $otpCode = $button;
        $button = null;
    }

    $mail = new PHPMailer(true);

    try {
        // SMTP SETTINGS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // 3. USE SECURE KEYS
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // RECIPIENT
        $mail->setFrom($smtp_user, 'ShopCorrect');
        $mail->addAddress($to);

        // HEADER: Cloudinary Logo + ShopCorrect Text Side-by-Side
        $logoUrl = "https://res.cloudinary.com/dtpx4mcnu/image/upload/v1772532296/logo_w_ufkid4.png";

        $logoHtml = "
        <table border='0' cellspacing='0' cellpadding='0' style='margin:0 auto;'>
            <tr>
                <td style='padding-right:12px; vertical-align:middle;'>
                    <img src='{$logoUrl}' alt='Logo' style='height:40px; display:block; border:none; outline:none; text-decoration:none;'>
                </td>
                <td style='color:#ffffff; font-size:26px; font-weight:bold; font-family:Arial, sans-serif; letter-spacing:-0.5px; vertical-align:middle;'>
                    ShopCorrect
                </td>
            </tr>
        </table>
        ";

        // DYNAMIC CENTER CONTENT
        $contentBoxHtml = '';

        if (!empty($otpCode) && is_numeric($otpCode)) {
            $contentBoxHtml = "
            <h1 style='margin:0; font-size:36px; color:#0B2447; letter-spacing:6px;'>$otpCode</h1>
            <p style='margin:8px 0 0 0; font-size:13px; color:#64748B;'>Expires in 5 minutes</p>
            ";
        } elseif (!empty($otpCode) && !is_numeric($otpCode)) {
            $contentBoxHtml = "
            <div style='font-size:12px; text-transform:uppercase; color:#64748B; font-weight:bold; margin-bottom:10px; letter-spacing:1px;'>Tracking Number</div>
            <div style='font-size:20px; color:#0B2447; font-weight:bold; border:2px dashed #CBD5E1; padding:14px 20px; border-radius:8px; background:#ffffff; word-break:break-all;'>
                $otpCode
            </div>
            ";
        } elseif ($statusType) {
            $badges = [
                'processing'      => ['icon' => '⚙️', 'label' => 'Processing Order'],
                'shipped'         => ['icon' => '🚚', 'label' => 'Order Shipped'],
                'delivered'       => ['icon' => '✅', 'label' => 'Delivered'],
                'review'          => ['icon' => '📋', 'label' => 'Application Under Review'],
                'approved'        => ['icon' => '🎉', 'label' => 'Account Active'],
                'correction'      => ['icon' => '⚠️', 'label' => 'Action Required'],
                'refund_approved' => ['icon' => '💸', 'label' => 'Refund Processed'],
                'refund_declined' => ['icon' => '❌', 'label' => 'Refund Declined']
            ];

            if (isset($badges[$statusType])) {
                $b = $badges[$statusType];
                $contentBoxHtml = "
                <div style='font-size:32px; margin-bottom:10px;'>{$b['icon']}</div>
                <div style='font-size:20px; color:#0B2447; font-weight:bold;'>{$b['label']}</div>
                ";
            }
        }

        $glassBoxHtml = '';
        if ($contentBoxHtml) {
            $glassBoxHtml = "
            <div style='background:#F8FAFC; padding:25px; border-radius:12px; margin:30px 0; text-align:center; border:1px solid #E2E8F0;'>
                $contentBoxHtml
            </div>
            ";
        }

        // BUTTON
        $buttonHtml = '';
        if (!empty($button) && is_array($button)) {
            $buttonHtml = "
            <div style='text-align:center; margin-top:30px;'>
                <a href='{$button['url']}'
                   style='background:#0B2447; color:#ffffff; padding:14px 26px; border-radius:8px; text-decoration:none; font-weight:bold; display:inline-block; font-size:15px;'>
                   {$button['text']}
                </a>
            </div>
            ";
        }

        $year = date("Y");

        // EMAIL TEMPLATE
        $fullBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin:0; padding:0; background:#F4F7FA; font-family:Arial, sans-serif;'>

            <table width='100%' cellpadding='0' cellspacing='0' style='padding:30px 0;'>
                <tr>
                    <td align='center'>

                        <table width='600' cellpadding='0' cellspacing='0'
                               style='width:100%; max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #E2E8F0;'>

                            <tr>
                                <td style='background:#0B2447; padding:35px 20px; text-align:center;'>
                                    $logoHtml
                                </td>
                            </tr>

                            <tr>
                                <td style='padding:40px; text-align:center; color:#1E293B;'>

                                    <h2 style='margin:0 0 15px 0; color:#0B2447; font-size:22px;'>
                                        $title
                                    </h2>

                                    <div style='font-size:15px; line-height:1.6; color:#64748B;'>
                                        $message
                                    </div>

                                    $glassBoxHtml
                                    $buttonHtml

                                    <div style='margin-top:40px; padding-top:20px; border-top:1px solid #E2E8F0;'>

                                        <p style='margin:0; font-size:12px; color:#64748B; line-height:1.8;'>
                                            support@shopcorrect.com | +233 (0) 59 426 4517
                                        </p>

                                        <p style='margin:8px 0 0 0; font-size:13px; font-weight:bold; color:#0B2447;'>
                                            <span style='color:#FF6B00; font-size:16px; margin-right:4px; vertical-align:middle;'>&#10004;</span>
                                            <span style='vertical-align:middle;'>Shop Smarter. Shop Correct</span>
                                        </p>

                                        <p style='margin-top:10px; font-size:11px; color:#94A3B8;'>
                                            &copy; $year ShopCorrect. All rights reserved.
                                        </p>

                                    </div>

                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>
            </table>

        </body>
        </html>
        ";

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $fullBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</div>', '</p>', '</tr>'], "\n", $message));

        return $mail->send();

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>