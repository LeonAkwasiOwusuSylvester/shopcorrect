<?php

/*
|--------------------------------------------------------------------------
| SAFE AUTOLOAD
|--------------------------------------------------------------------------
*/
$autoloadPath = dirname(__DIR__, 2) . "/vendor/autoload.php";
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found. Run: composer install");
}
require_once $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*
|--------------------------------------------------------------------------
| CORE MAIL FUNCTION (UPDATED WITH BRANDED WRAPPER)
|--------------------------------------------------------------------------
*/
function sendMail(
    string $to,
    string $subject,
    string $htmlContent,
    array $attachments = []
): bool {

    if (!class_exists(PHPMailer::class)) {
        error_log("PHPMailer not loaded.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shopcorrect.official@gmail.com';
        $mail->Password   = 'msfl xifc rqqz alid';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('shopcorrect.official@gmail.com', 'ShopCorrect Compliance');
        $mail->addAddress($to);

        // --- EMBED LOGO ---
        $logoPath = dirname(__DIR__, 2) . "/public/assets/images/shopcorrect-logo.png";
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'brand_logo');
        }

        // --- PROFESSIONAL BRANDED WRAPPER ---
        $brandedHtml = "
        <div style='background-color: #f8fafc; padding: 40px 0; font-family: sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0;'>
                
                <div style='background-color: #0B2447; padding: 30px; text-align: center;'>
                    <div style='display: inline-block; vertical-align: middle;'>
                        <img src='cid:brand_logo' alt='ShopCorrect Logo' style='height: 40px; margin-right: 10px; vertical-align: middle;'>
                        <h1 style='color: #ffffff; display: inline-block; margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -1px; vertical-align: middle;'>ShopCorrect</h1>
                    </div>
                </div>

                <div style='padding: 40px; line-height: 1.6; color: #1e293b;'>
                    $htmlContent
                </div>

                <div style='background-color: #f1f5f9; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;'>
                    <p style='margin: 0; font-size: 14px; font-weight: 700; color: #0B2447;'>ShopCorrect Inc.</p>
                    <p style='margin: 5px 0 0; font-size: 12px; color: #64748b;'>
                        Authentically Ghanaian <span style='color: #ef4444;'>❤️</span>
                    </p>
                    <p style='margin: 15px 0 0; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;'>
                        &copy; " . date("Y") . " ShopCorrect. All Rights Reserved.
                    </p>
                </div>
            </div>
        </div>";

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $brandedHtml;
        $mail->AltBody = strip_tags($htmlContent);

        foreach ($attachments as $filePath => $fileName) {
            if (file_exists($filePath)) {
                $mail->addAttachment($filePath, $fileName);
            }
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| OTP MAIL WRAPPER (UPDATED DESIGN)
|--------------------------------------------------------------------------
*/
function sendOtpMail(string $toEmail, string $otp): bool
{
    $html = "
        <div style='text-align: center;'>
            <h2 style='color: #0B2447; margin-bottom: 20px;'>Security Verification</h2>
            <p style='font-size: 16px; color: #475569;'>Use the 6-digit verification code below to secure your account:</p>
            <div style='background-color: #f8fafc; border: 2px dashed #0B2447; border-radius: 12px; padding: 20px; margin: 25px 0; display: inline-block;'>
                <span style='font-size: 32px; font-weight: 800; letter-spacing: 5px; color: #0B2447;'>{$otp}</span>
            </div>
            <p style='font-size: 14px; color: #64748b;'>This code expires in <strong>5 minutes</strong>. If you did not request this, please ignore this email.</p>
        </div>
    ";

    return sendMail($toEmail, "Your ShopCorrect Verification Code", $html);
}

/*
|--------------------------------------------------------------------------
| GENERATE COMPLIANCE PDF LETTER
|--------------------------------------------------------------------------
*/
function generateCompliancePdf(
    string $vendorName,
    string $vendorId,
    string $decision,
    string $reason,
    string $reference
): string {

    if (!class_exists('TCPDF')) {
        error_log("TCPDF not installed.");
        return "";
    }

    $storagePath = dirname(__DIR__, 2) . "/storage";
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0777, true);
    }

    $pdf = new \TCPDF();
    $pdf->SetCreator('ShopCorrect');
    $pdf->SetAuthor('ShopCorrect Compliance');
    $pdf->SetTitle('Vendor Compliance Decision');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    $html = "
        <h2 style='color:#0B2447;'>ShopCorrect Compliance Decision Letter</h2>
        <hr>
        <p><strong>Vendor Name:</strong> {$vendorName}</p>
        <p><strong>Vendor ID:</strong> {$vendorId}</p>
        <p><strong>Decision:</strong> {$decision}</p>
        <p><strong>Compliance Reference:</strong> {$reference}</p>
        <br>
        <p>{$reason}</p>
        <br><br>
        <p>This document is digitally issued by ShopCorrect Compliance.</p>
        <p>Date: " . date("F d, Y H:i") . "</p>
    ";

    $pdf->writeHTML($html, true, false, true, false, '');
    $filePath = $storagePath . "/compliance_" . uniqid() . ".pdf";
    $pdf->Output($filePath, 'F');

    return $filePath;
}

/*
|--------------------------------------------------------------------------
| BUILD PROFESSIONAL DECISION EMAIL (UPDATED DESIGN)
|--------------------------------------------------------------------------
*/
function buildVendorDecisionEmail(
    string $vendorName,
    string $vendorId,
    string $decision,
    string $reason,
    string $reference
): string {

    $badgeColor = $decision === 'APPROVED' ? '#28a745' : '#dc3545';
    $loginUrl   = "http://localhost/shopcorrect/public/login.php";
    $trackingCode = strtoupper(hash('sha256', $vendorId . $reference));

    return "
    <div>
        <p>Hello <strong>{$vendorName}</strong>,</p>

        <div style='display:inline-block;padding:6px 15px; background:{$badgeColor}; color:#fff; border-radius:20px; font-size:13px; font-weight:bold; margin-bottom: 20px;'>
            {$decision}
        </div>

        <p style='margin-top:20px;'>
            <strong>Vendor ID:</strong> {$vendorId}<br>
            <strong>Compliance Ref:</strong> {$reference}
        </p>

        <p>{$reason}</p>

        <div style='margin:30px 0;padding:15px;border:2px dashed #0B2447; text-align:center;color:#0B2447;font-weight:bold;'>
            ✔ Digitally Verified — ShopCorrect Compliance
        </div>

        <div style='text-align:center;margin:30px 0;'>
            <a href='{$loginUrl}' style='background:#0B2447; color:#ffffff; padding:12px 30px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;'>
                Login Now
            </a>
        </div>

        <p style='font-size:12px;color:#999;margin-top:30px;'>
            Anti-Fraud Tracking Code: {$trackingCode}
        </p>

        <p style='margin-top:30px;font-size:14px;'>
            — <strong>ShopCorrect Compliance Team</strong><br>
            Marketplace Integrity & Risk Management Unit
        </p>
    </div>
    ";
}
