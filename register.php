<?php
session_start();
include 'db_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

$msg = "";
$msg_type = "";

if(isset($_POST['reg_btn'])) {
    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $pass  = $_POST['pass'];

    $site_name   = setting('site_name', 'Heartbeat Heaven');
    $tagline     = setting('site_tagline', 'Animal Rescue & Sanctuary');
    $sender_name = setting('smtp_sender_name', 'Heartbeat Heaven');
    $address     = setting('sanctuary_address');

    if (!str_ends_with($email, '@gmail.com')) {
        $msg = "Registration restricted to @gmail.com accounts only.";
        $msg_type = "danger";
    } else {
        $pw_check = validate_password($pass);
        if (!$pw_check['valid']) {
            $msg = implode(' ', $pw_check['errors']);
            $msg_type = "danger";
        } else {
            $checkUser = $conn->query("SELECT email, phone FROM users WHERE email = '$email' OR phone = '$phone'");
            if($checkUser->num_rows > 0) {
                $msg = "Email or Phone already registered.";
                $msg_type = "danger";
            } else {
                $otp = rand(100000, 999999);
                $_SESSION['temp_reg'] = [
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'pass'  => password_hash($pass, PASSWORD_DEFAULT),
                    'otp'   => $otp
                ];

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = setting('smtp_host');
                    $mail->SMTPAuth   = true;
                    $mail->Username   = setting('smtp_username');
                    $mail->Password   = setting('smtp_password');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = (int)setting('smtp_port', '587');
                    $mail->setFrom(setting('smtp_username'), $sender_name);
                    $mail->addReplyTo(setting('smtp_username'), $sender_name);
                    $mail->addAddress($email, $name);
                    $mail->isHTML(true);
                    $mail->Subject = "Verification Code for " . $site_name;
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                            <h2 style='color: #0a1329;'>Welcome to $site_name</h2>
                            <p>Hello $name, use the code below to verify your account for our $tagline:</p>
                            <div style='background: #ffefc2; padding: 15px; font-size: 24px; font-weight: bold; text-align: center; border-radius: 8px; color: #0a1329; margin: 20px 0; border: 1px solid #0a1329;'>
                                $otp
                            </div>
                            <p style='font-size: 11px; color: #777;'>
                                &copy; " . date('Y') . " $site_name | $address
                            </p>
                        </div>";
                    if($mail->send()) {
                        $msg = "Verification code sent! Check your Gmail.";
                        $msg_type = "success";
                        header("refresh:2;url=verify_otp.php");
                    }
                } catch (Exception $e) {
                    $msg = "Mailer Error: {$mail->ErrorInfo}";
                    $msg_type = "danger";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?= setting('site_name', 'Heartbeat Heaven') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cinzel+Decorative:wght@400;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --gold: #c9a84c;
            --gold-light: #f0d080;
            --gold-pale: #fdf0c8;
            --cream: #e8eef8;
            --midnight: #060d1f;
            --midnight-mid: #0b1530;
            --midnight-card: #0d1a38;
            --blue-border: rgba(100,140,220,0.15);
        }

        body {
            height: 100vh;
            background-color: var(--midnight);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cormorant Garamond', Georgia, serif;
            position: relative;
            overflow: hidden;
        }

        /* ── Layered atmospheric background ── */
        .bg-layer { position: fixed; inset: 0; z-index: 0; }
        .bg-photo {
            background: url('https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&q=80&w=2000') center/cover no-repeat;
            filter: brightness(0.18) saturate(0.6) hue-rotate(180deg);
        }
        .bg-vignette {
            background: radial-gradient(ellipse at center, rgba(6,13,31,0.4) 10%, rgba(4,9,22,0.92) 80%);
        }
        .bg-grain {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            opacity: 0.5;
        }

        /* ── Gold dust particles ── */
        .particles { position: fixed; inset: 0; z-index: 1; pointer-events: none; }
        .particle {
            position: absolute;
            width: 2px; height: 2px;
            background: var(--gold-light);
            border-radius: 50%;
            opacity: 0;
            animation: drift var(--dur, 8s) var(--delay, 0s) infinite ease-in-out;
        }
        @keyframes drift {
            0%   { transform: translateY(100vh) translateX(0); opacity: 0; }
            10%  { opacity: 0.6; }
            90%  { opacity: 0.2; }
            100% { transform: translateY(-10vh) translateX(var(--x, 30px)); opacity: 0; }
        }

        /* ── Decorative corner ornaments ── */
        .ornament { position: fixed; width: 120px; height: 120px; opacity: 0.18; z-index: 2; }
        .ornament svg { width: 100%; height: 100%; fill: none; stroke: var(--gold); stroke-width: 0.8; }
        .ornament-tl { top: 24px; left: 24px; }
        .ornament-tr { top: 24px; right: 24px; transform: scaleX(-1); }
        .ornament-bl { bottom: 24px; left: 24px; transform: scaleY(-1); }
        .ornament-br { bottom: 24px; right: 24px; transform: scale(-1); }

        /* ── Main wrapper ── */
        .page-center {
            position: relative; z-index: 10;
            width: 100%; max-width: 440px;
            padding: 12px 16px 0;
            display: flex; flex-direction: column; align-items: center;
            animation: fadeUp 1s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Branding ── */
        .brand-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 10px;
            animation: fadeUp 1s 0.1s cubic-bezier(.16,1,.3,1) both;
        }
        .logo-wrap {
            width: 54px; height: 54px;
            flex-shrink: 0;
            position: relative;
            overflow: visible;
        }
        .logo-wrap::before {
            content: '';
            position: absolute; inset: -5px;
            border-radius: 50%;
            border: 1px solid var(--gold);
            opacity: 0.5;
            animation: halo 3s ease-in-out infinite;
        }
        @keyframes halo {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50%       { transform: scale(1.08); opacity: 0.25; }
        }
        .logo-wrap img {
            width: 54px; height: 54px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid rgba(201,168,76,0.6);
            display: block;
        }
        .logo-fallback {
            display: none;
            width: 54px; height: 54px;
            border-radius: 50%;
            border: 2px solid rgba(201,168,76,0.6);
            background: rgba(10,25,60,0.5);
            align-items: center; justify-content: center;
            font-size: 24px;
            color: var(--gold);
        }
        .brand-text { text-align: left; }
        .brand-name {
            font-family: 'Cinzel Decorative', 'Cinzel', serif;
            font-style: italic;
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: 0.03em;
            background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 50%, var(--gold-pale) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        .brand-tagline {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 0.75rem;
            color: rgba(240, 208, 128, 0.5);
            letter-spacing: 0.12em;
            margin-top: 3px;
            text-transform: uppercase;
        }

        /* ── Divider ── */
        .gold-rule {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 12px; width: 100%;
        }
        .gold-rule::before, .gold-rule::after {
            content: ''; flex: 1; height: 1px;
            background: linear-gradient(to right, transparent, rgba(201,168,76,0.4), transparent);
        }
        .gold-rule span {
            color: var(--gold); font-size: 0.75rem; opacity: 0.7;
            letter-spacing: 0.2em; font-family: 'Cinzel', serif;
        }

        /* ── Card ── */
        .card {
            width: 100%;
            background: linear-gradient(160deg, rgba(13,26,56,0.95) 0%, rgba(8,15,35,0.97) 100%);
            border: 1px solid rgba(201,168,76,0.22);
            border-radius: 4px;
            padding: 22px 30px 20px;
            position: relative;
            box-shadow:
                0 2px 0 rgba(201,168,76,0.1) inset,
                0 40px 80px rgba(0,0,0,0.7),
                0 0 60px rgba(10,30,80,0.4),
                0 0 0 1px rgba(0,0,0,0.5);
            animation: fadeUp 1s 0.2s cubic-bezier(.16,1,.3,1) both;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 10%; right: 10%; height: 1px;
            background: linear-gradient(to right, transparent, var(--gold-light), transparent);
            opacity: 0.5;
        }
        .card-corner {
            position: absolute;
            width: 18px; height: 18px;
            border-color: rgba(201,168,76,0.5);
            border-style: solid;
        }
        .card-corner-tl { top: -1px; left: -1px; border-width: 2px 0 0 2px; }
        .card-corner-tr { top: -1px; right: -1px; border-width: 2px 2px 0 0; }
        .card-corner-bl { bottom: -1px; left: -1px; border-width: 0 0 2px 2px; }
        .card-corner-br { bottom: -1px; right: -1px; border-width: 0 2px 2px 0; }

        /* ── Heading ── */
        .card-heading {
            font-family: 'Cinzel', serif;
            font-size: 0.7rem;
            letter-spacing: 0.3em;
            color: var(--gold);
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 14px;
            opacity: 0.75;
        }

        /* ── Alerts ── */
        .alert {
            border-radius: 3px;
            font-size: 0.85rem;
            padding: 7px 12px;
            margin-bottom: 12px;
            display: flex; align-items: flex-start; gap: 10px;
            font-family: 'Cormorant Garamond', serif;
            line-height: 1.4;
        }
        .alert-success {
            background: rgba(80,120,60,0.18);
            border: 1px solid rgba(100,170,80,0.3);
            color: #9dca7a;
        }
        .alert-danger {
            background: rgba(130,40,40,0.2);
            border: 1px solid rgba(180,60,60,0.3);
            color: #e09090;
        }
        .alert-warning {
            background: rgba(140,110,20,0.2);
            border: 1px solid rgba(201,168,76,0.3);
            color: var(--gold-light);
        }

        /* ── Form ── */
        .form-group { margin-bottom: 11px; }

        .form-label {
            display: block;
            font-family: 'Cinzel', serif;
            font-size: 0.62rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(240,208,128,0.6);
            margin-bottom: 5px;
        }

        .input-wrap { position: relative; }

        .form-control {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(201,168,76,0.18);
            border-bottom-color: rgba(201,168,76,0.35);
            color: var(--cream);
            font-family: 'Cormorant Garamond', serif;
            font-size: 1rem;
            padding: 9px 40px 9px 12px;
            border-radius: 2px;
            outline: none;
            transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
            -webkit-appearance: none;
        }
        .form-control:focus {
            border-color: rgba(201,168,76,0.6);
            background: rgba(100,140,220,0.06);
            box-shadow: 0 0 0 3px rgba(201,168,76,0.07), 0 1px 0 rgba(201,168,76,0.4);
        }
        .form-control::placeholder { color: rgba(180,195,230,0.25); }

        /* no icon padding for plain fields */
        .form-control.no-icon { padding-right: 14px; }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--cream) !important;
            -webkit-box-shadow: 0 0 0px 1000px #0c1632 inset !important;
        }

        .toggle-pass {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: rgba(201,168,76,0.4); font-size: 0.85rem;
            transition: color 0.2s; padding: 4px;
        }
        .toggle-pass:hover { color: var(--gold); }

        /* ── Password hints ── */
        .pw-hints {
            margin-top: 7px;
            display: flex; flex-direction: column; gap: 2px;
        }
        .pw-hint {
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.8rem;
            color: rgba(180,195,230,0.35);
            display: flex; align-items: center; gap: 7px;
            transition: color 0.25s;
        }
        .pw-hint i {
            font-size: 0.6rem;
            color: rgba(201,168,76,0.25);
            transition: color 0.25s;
            width: 10px; text-align: center;
        }
        .pw-hint.met { color: rgba(157,202,122,0.85); }
        .pw-hint.met i { color: #9dca7a; }

        /* ── Submit button ── */
        .btn-login {
            width: 100%; margin-top: 6px;
            padding: 11px;
            font-family: 'Cinzel', serif;
            font-size: 0.75rem;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: #060d1f;
            background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 60%, #a8782a 100%);
            border: none; border-radius: 2px;
            cursor: pointer;
            position: relative; overflow: hidden;
            transition: filter 0.25s, transform 0.15s, box-shadow 0.25s;
            box-shadow: 0 4px 20px rgba(201,168,76,0.25);
        }
        .btn-login::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.25), transparent 60%);
            opacity: 0; transition: opacity 0.25s;
        }
        .btn-login:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 8px 30px rgba(201,168,76,0.35); }
        .btn-login:hover::before { opacity: 1; }
        .btn-login:active { transform: translateY(0); filter: brightness(0.95); }

        /* ── Login link ── */
        .register-line {
            text-align: center;
            margin-top: 14px;
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 0.9rem;
            color: rgba(232,238,248,0.35);
        }
        .register-line a {
            color: var(--gold);
            text-decoration: none;
            border-bottom: 1px solid rgba(201,168,76,0.3);
            padding-bottom: 1px;
            transition: color 0.2s, border-color 0.2s;
        }
        .register-line a:hover { color: var(--gold-light); border-color: var(--gold-light); }

        /* ── Paw footer ── */
        
        .paw-icon {
            font-size: 1rem;
            color: var(--gold);
            opacity: 0.25;
            display: inline-block;
            animation: pawPulse 3.5s ease-in-out infinite;
            filter: drop-shadow(0 0 4px rgba(201,168,76,0.3));
        }
        @keyframes pawPulse {
            0%, 100% { opacity: 0.25; transform: scale(1); }
            50%       { opacity: 0.55; transform: scale(1.12); }
        }
        .paw-text { display: none; }
    </style>
</head>
<body>

    <!-- Background layers -->
    <div class="bg-layer bg-photo"></div>
    <div class="bg-layer bg-vignette"></div>
    <div class="bg-layer bg-grain"></div>

    <!-- Particles -->
    <div class="particles" id="particles"></div>

    <!-- Corner ornaments -->
    <div class="ornament ornament-tl">
        <svg viewBox="0 0 100 100"><path d="M5 5 L5 40 M5 5 L40 5 M5 25 Q25 25 25 5"/></svg>
    </div>
    <div class="ornament ornament-tr">
        <svg viewBox="0 0 100 100"><path d="M5 5 L5 40 M5 5 L40 5 M5 25 Q25 25 25 5"/></svg>
    </div>
    <div class="ornament ornament-bl">
        <svg viewBox="0 0 100 100"><path d="M5 5 L5 40 M5 5 L40 5 M5 25 Q25 25 25 5"/></svg>
    </div>
    <div class="ornament ornament-br">
        <svg viewBox="0 0 100 100"><path d="M5 5 L5 40 M5 5 L40 5 M5 25 Q25 25 25 5"/></svg>
    </div>

    <!-- Main -->
    <div class="page-center">

        <!-- Branding -->
        <div class="brand-header">
            <div class="logo-wrap">
                <img src="logo.png" alt="Heartbeat Heaven" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="logo-fallback"><i class="fas fa-paw"></i></div>
            </div>
            <div class="brand-text">
                <div class="brand-name">Heartbeat Heaven</div>
                <div class="brand-tagline"><?= setting('site_tagline', 'Animal Rescue & Sanctuary') ?></div>
            </div>
        </div>

        <div class="gold-rule"><span>✦</span></div>

        <!-- Card -->
        <div class="card">
            <div class="card-corner card-corner-tl"></div>
            <div class="card-corner card-corner-tr"></div>
            <div class="card-corner card-corner-bl"></div>
            <div class="card-corner card-corner-br"></div>

            <p class="card-heading">Create Account</p>

            <!-- Alert -->
            <?php if($msg != ""): ?>
                <div class="alert alert-<?= $msg_type ?>">
                    <i class="fas <?= $msg_type == 'success' ? 'fa-paper-plane' : 'fa-exclamation-circle' ?>"></i>
                    <span><?= $msg ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST">

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <div class="input-wrap">
                        <input type="text" name="name" class="form-control no-icon" placeholder="Enter your full name" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Gmail Address</label>
                    <div class="input-wrap">
                        <input type="email" name="email" class="form-control no-icon" placeholder="example@gmail.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <div class="input-wrap">
                        <input type="text" name="phone" class="form-control no-icon" placeholder="01XXXXXXXXX" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 6px;">
                    <label class="form-label">Password</label>
                    <div class="input-wrap">
                        <input type="password" name="pass" id="passwordField" class="form-control"
                               placeholder="••••••••" required oninput="checkPolicy(this.value)">
                        <button type="button" class="toggle-pass" id="togglePass" aria-label="Toggle password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <!-- Live password hints -->
                    <div class="pw-hints">
                        <div class="pw-hint" id="hint-length">
                            <i class="fas fa-circle"></i>
                            At least <?= setting('password_min_length', '8') ?> characters
                        </div>
                        <?php if(setting('pw_require_upper', '1') === '1'): ?>
                        <div class="pw-hint" id="hint-upper">
                            <i class="fas fa-circle"></i>
                            One uppercase letter
                        </div>
                        <?php endif; ?>
                        <?php if(setting('pw_require_number', '1') === '1'): ?>
                        <div class="pw-hint" id="hint-number">
                            <i class="fas fa-circle"></i>
                            One number
                        </div>
                        <?php endif; ?>
                        <?php if(setting('pw_require_special', '0') === '1'): ?>
                        <div class="pw-hint" id="hint-special">
                            <i class="fas fa-circle"></i>
                            One special character (@, #, !)
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" name="reg_btn" class="btn-login">Create Account</button>
            </form>

            <div class="register-line">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>

        <!-- Paw footer -->
        
    </div>

    <script>
        // Gold particles
        const container = document.getElementById('particles');
        for (let i = 0; i < 28; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            const size = Math.random() * 2 + 1;
            p.style.cssText = `
                left: ${Math.random()*100}%;
                width: ${size}px; height: ${size}px;
                --dur: ${6 + Math.random()*10}s;
                --delay: ${Math.random()*10}s;
                --x: ${(Math.random()-0.5)*80}px;
                opacity: 0;
            `;
            container.appendChild(p);
        }

        // Password policy
        const minLen     = <?= (int)setting('password_min_length', '8') ?>;
        const needUpper  = <?= setting('pw_require_upper',   '1') === '1' ? 'true' : 'false' ?>;
        const needNumber = <?= setting('pw_require_number',  '1') === '1' ? 'true' : 'false' ?>;
        const needSpec   = <?= setting('pw_require_special', '0') === '1' ? 'true' : 'false' ?>;

        function checkPolicy(val) {
            toggle('hint-length', val.length >= minLen);
            if (needUpper)  toggle('hint-upper',   /[A-Z]/.test(val));
            if (needNumber) toggle('hint-number',  /[0-9]/.test(val));
            if (needSpec)   toggle('hint-special', /[\W_]/.test(val));
        }

        function toggle(id, met) {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.toggle('met', met);
            el.querySelector('i').className = met ? 'fas fa-check-circle' : 'fas fa-circle';
        }

        // Toggle password
        document.getElementById('togglePass').addEventListener('click', function() {
            const input = document.getElementById('passwordField');
            const icon = this.querySelector('i');
            input.type = input.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>