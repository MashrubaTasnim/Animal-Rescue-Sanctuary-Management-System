<?php
// mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

function send_email(string $to_email, string $to_name, string $subject, string $body_html): bool {
    // Dynamic settings from your db table
    $host         = setting('smtp_host');         // Row 18
    $port         = (int) setting('smtp_port');    // Row 19
    $username     = setting('smtp_username');     // Row 20
    $password     = setting('smtp_password');     // Row 21
    $fromName     = setting('smtp_sender_name');  // Row 22
    $site_name    = setting('site_name');         // Row 1
    $address      = setting('sanctuary_address'); // Row 5

    if (empty($host) || empty($username) || empty($password)) {
        error_log("Mailer Error: SMTP settings are empty in database.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = $port;

        // Identity settings
        $mail->setFrom($username, $fromName);
        $mail->addReplyTo($username, $fromName);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = email_wrap($subject, $body_html, $site_name, $address);
        $mail->AltBody = strip_tags($body_html);

        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Exception: " . $mail->ErrorInfo);
        return false;
    }
}

function email_wrap(string $title, string $content, string $site_name, string $address): string {
    $year = date('Y');

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { margin: 0; padding: 0; background: #f4f7fa; font-family: 'Segoe UI', Arial, sans-serif; }
            .wrapper { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e1e8ed; }
            .header { background: #0a1329; padding: 30px; text-align: center; border-bottom: 3px solid #ffefc2; }
            .header h1 { color: #ffefc2; margin: 0; font-size: 20px; text-transform: uppercase; }
            .body { padding: 40px; color: #2c3e50; line-height: 1.6; }
            .otp-container { background: #fff9e6; border: 2px dashed #ffefc2; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
            .otp-code { font-size: 32px; font-weight: bold; color: #0a1329; letter-spacing: 5px; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #94a3b8; font-size: 11px; border-top: 1px solid #eee; }
        </style>
    </head>
    <body>
        <div class='wrapper'>
            <div class='header'><h1>$site_name</h1></div>
            <div class='body'>$content</div>
            <div class='footer'>
                &copy; $year $site_name | $address <br>
                This is an automated security message.
            </div>
        </div>
    </body>
    </html>";
}