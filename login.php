<?php 
session_start(); 

if (
    isset($_SERVER['HTTP_REFERER']) && 
    !empty($_SERVER['HTTP_REFERER']) &&
    !isset($_SESSION['auth_redirect_back'])
) {
    $referer = $_SERVER['HTTP_REFERER'];
    $exclude = ['login.php', 'register.php', 'process_login.php', 'forgot_password.php'];
    $is_excluded = false;
    foreach ($exclude as $page) {
        if (str_contains($referer, $page)) { $is_excluded = true; break; }
    }
    if (!$is_excluded) {
        $_SESSION['auth_redirect_back'] = $referer;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Heartbeat Heaven</title>
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
        .bg-layer {
            position: fixed; inset: 0; z-index: 0;
        }
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
        .ornament {
            position: fixed;
            width: 120px; height: 120px;
            opacity: 0.18;
            z-index: 2;
        }
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
            margin-bottom: 16px;
            animation: fadeUp 1s 0.1s cubic-bezier(.16,1,.3,1) both;
        }
        .logo-wrap {
            width: 58px; height: 58px;
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
            width: 58px; height: 58px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid rgba(201,168,76,0.6);
            display: block;
        }
        .logo-fallback {
            display: none;
            width: 58px; height: 58px;
            border-radius: 50%;
            border: 2px solid rgba(201,168,76,0.6);
            background: rgba(10,25,60,0.5);
            align-items: center; justify-content: center;
            font-size: 26px;
            color: var(--gold);
        }
        .brand-text { text-align: left; }
        .brand-name {
            font-family: 'Cinzel Decorative', 'Cinzel', serif;
            font-style: italic;
            font-weight: 700;
            font-size: 1.25rem;
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
            font-size: 0.78rem;
            color: rgba(240, 208, 128, 0.5);
            letter-spacing: 0.12em;
            margin-top: 3px;
            text-transform: uppercase;
        }

        /* ── Divider ── */
        .gold-rule {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 16px; width: 100%;
        }
        .gold-rule::before, .gold-rule::after {
            content: ''; flex: 1;
            height: 1px;
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
            padding: 28px 32px 24px;
            position: relative;
            box-shadow:
                0 2px 0 rgba(201,168,76,0.1) inset,
                0 40px 80px rgba(0,0,0,0.7),
                0 0 60px rgba(10,30,80,0.4),
                0 0 0 1px rgba(0,0,0,0.5);
            animation: fadeUp 1s 0.2s cubic-bezier(.16,1,.3,1) both;
        }

        /* top shimmer line */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 10%; right: 10%; height: 1px;
            background: linear-gradient(to right, transparent, var(--gold-light), transparent);
            opacity: 0.5;
        }

        /* corner accents */
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
            margin-bottom: 18px;
            opacity: 0.75;
        }

        /* ── Alerts ── */
        .alert {
            border-radius: 3px;
            font-size: 0.875rem;
            padding: 8px 12px;
            margin-bottom: 14px;
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
        .form-group { margin-bottom: 16px; }

        .form-label {
            display: block;
            font-family: 'Cinzel', serif;
            font-size: 0.62rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(240,208,128,0.6);
            margin-bottom: 8px;
        }
        .label-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 8px;
        }
        .label-row .form-label { margin-bottom: 0; }

        .forgot-link {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 0.82rem;
            color: rgba(201,168,76,0.55);
            text-decoration: none;
            transition: color 0.25s;
        }
        .forgot-link:hover { color: var(--gold-light); }

        .input-wrap { position: relative; }

        .form-control {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(201,168,76,0.18);
            border-bottom-color: rgba(201,168,76,0.35);
            color: var(--cream);
            font-family: 'Cormorant Garamond', serif;
            font-size: 1rem;
            padding: 11px 44px 11px 14px;
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

        /* ── Submit button ── */
        .btn-login {
            width: 100%; margin-top: 8px;
            padding: 13px;
            font-family: 'Cinzel', serif;
            font-size: 0.75rem;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--ink);
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

        /* ── Register link ── */
        .register-line {
            text-align: center;
            margin-top: 18px;
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 0.9rem;
            color: rgba(253,246,227,0.35);
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

    <!-- Gold particles -->
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
                <div class="brand-tagline">Where Every Soul Finds Home</div>
            </div>
        </div>

        <div class="gold-rule"><span>✦</span></div>

        <!-- Card -->
        <div class="card">
            <div class="card-corner card-corner-tl"></div>
            <div class="card-corner card-corner-tr"></div>
            <div class="card-corner card-corner-bl"></div>
            <div class="card-corner card-corner-br"></div>

            <p class="card-heading">Member Access</p>

            <!-- Alerts -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'password_updated'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Password updated successfully. Please sign in.</span>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['error'])): ?>
                <?php if($_GET['error'] == 'restricted'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-user-slash"></i>
                        <div><strong>Access Denied.</strong> Your account is restricted. Contact admin for assistance.</div>
                    </div>
                <?php elseif($_GET['error'] == 'invalid'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Incorrect email or password. Please try again.</span>
                    </div>
                <?php elseif($_GET['error'] == 'session_expired'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        <span>Your session expired due to inactivity. Please sign in again.</span>
                    </div>
                <?php elseif($_GET['error'] == 'unauthorized'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-lock"></i>
                        <span>You are not authorized to access that page.</span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['auth_redirect_message'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-lock"></i>
                    <span><?= htmlspecialchars($_SESSION['auth_redirect_message']) ?></span>
                </div>
                <?php unset($_SESSION['auth_redirect_message']); ?>
            <?php endif; ?>

            <!-- Form -->
            <form action="process_login.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-wrap">
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-row">
                        <label class="form-label">Password</label>
                        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                    </div>
                    <div class="input-wrap">
                        <input type="password" name="password" id="loginPass" class="form-control" placeholder="••••••••" required>
                        <button type="button" class="toggle-pass" id="togglePass" aria-label="Toggle password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login_btn" class="btn-login">Sign In</button>
            </form>

            <div class="register-line">
                New here? <a href="register.php">Create an account</a>
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

        // Toggle password
        document.getElementById('togglePass').addEventListener('click', function() {
            const input = document.getElementById('loginPass');
            const icon = this.querySelector('i');
            input.type = input.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>