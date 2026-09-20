<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Cinzel+Decorative:wght@400;700&family=Cormorant+Garamond:ital,wght@0,400;1,300;1,400&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

<footer class="ft-root">

    <!-- Top glow bar -->
    <div class="ft-topbar"></div>

    <div class="container pt-5 pb-2">
        <div class="row g-5 align-items-start">

            <!-- ===== COL 1: Emergency Contacts ===== -->
            <div class="col-md-4">
                <h6 class="ft-heading">
                    <i class="fas fa-phone-volume me-2 ft-heading-icon"></i>Emergency Contacts
                </h6>
                <div class="ft-contact-card">
                    <div class="ft-contact-item">
                        <span class="ft-contact-icon"><i class="fas fa-headset"></i></span>
                        <div>
                            <span class="ft-contact-label">Hotline</span>
                            <a href="tel:16222" class="ft-contact-value">16222</a>
                        </div>
                    </div>
                    <div class="ft-contact-item">
                        <span class="ft-contact-icon"><i class="fas fa-user-shield"></i></span>
                        <div>
                            <span class="ft-contact-label">Lead Rescuer</span>
                            <a href="tel:+880 1407893956" class="ft-contact-value">+880 1407893956</a>
                        </div>
                    </div>
                    <div class="ft-contact-item">
                        <span class="ft-contact-icon"><i class="fas fa-moon"></i></span>
                        <div>
                            <span class="ft-contact-label">Night Logistics</span>
                            <a href="tel:+8801938262081" class="ft-contact-value">+880 1938262081</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== COL 2: Branding ===== -->
            <div class="col-md-4 text-center">
                <div class="ft-brand-wrap">
                    <div class="ft-logo-wrap">
                        <img src="logo.png" alt="Footer Logo" class="ft-logo">
                    </div>
                    <h6 class="ft-brand-name">
                        <?= htmlspecialchars(setting('site_name', 'Heartbeat Heaven')) ?>
                    </h6>
                    <p class="ft-brand-tagline">
                        <?= htmlspecialchars(setting('site_tagline', 'Animal Rescue & Sanctuary')) ?>
                    </p>
                    <p class="ft-brand-desc">
                        <?= htmlspecialchars(setting('site_description', 'Dedicated to providing a safe haven for abandoned animals. Every life finds its light.')) ?>
                    </p>
                    <div class="ft-socials">
                        <?php if(setting('facebook_url')): ?>
                            <a href="<?= htmlspecialchars(setting('facebook_url')) ?>" target="_blank" class="ft-social-btn" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                        <?php else: ?>
                            <a href="#" class="ft-social-btn" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>

                        <?php if(setting('instagram_url')): ?>
                            <a href="<?= htmlspecialchars(setting('instagram_url')) ?>" target="_blank" class="ft-social-btn" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php else: ?>
                            <a href="#" class="ft-social-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>

                        <?php if(setting('contact_email')): ?>
                            <a href="mailto:<?= htmlspecialchars(setting('contact_email')) ?>" class="ft-social-btn" title="Email">
                                <i class="fas fa-envelope"></i>
                            </a>
                        <?php else: ?>
                            <a href="mailto:info@pranertan.org" class="ft-social-btn" title="Email"><i class="fas fa-envelope"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ===== COL 3: Address + Quick Links ===== -->
            <div class="col-md-4">
                <h6 class="ft-heading text-md-end">
                    <i class="fas fa-map-marker-alt me-2 ft-heading-icon"></i>Sanctuary Address
                </h6>
                <p class="ft-address text-md-end">
                    <?php if(setting('sanctuary_address')): ?>
                        <?= nl2br(htmlspecialchars(setting('sanctuary_address'))) ?>
                    <?php else: ?>
                        House 12, Road 05, Sector 04,<br>Uttara, Dhaka-1230, Bangladesh
                    <?php endif; ?>
                </p>

                <h6 class="ft-heading text-md-end mt-4">
                    <i class="fas fa-link me-2 ft-heading-icon"></i>Quick Links
                </h6>
                <ul class="ft-links list-unstyled mb-0 text-md-end">
                    <li><a href="index.php#about-us">About Us</a></li>
                    <li><a href="index.php#vision">Vision &amp; Mission</a></li>
                    <li><a href="index.php#gallery">Gallery of Hope</a></li>
                    <li><a href="index.php#quote">Our Quote</a></li>
                    <li><a href="index.php#testimonials">Testimonials</a></li>
                </ul>
            </div>

        </div>

        <!-- ===== DIVIDER + COPYRIGHT ===== -->
        <div class="ft-bottom">
            <div class="ft-paw-divider">
                <span></span>
                <i class="fas fa-paw ft-paw-icon"></i>
                <span></span>
            </div>
            <p class="ft-copyright">
                &copy; <?= date('Y') ?>
                <span class="ft-copyright-name"><?= htmlspecialchars(setting('site_name', 'Heartbeat Heaven')) ?></span>
                &mdash; Designed with 🐾 by <strong>Softdeft</strong>
            </p>
        </div>

    </div>


</footer>





<style>
/* ============================================================
   GOOGLE TRANSLATE — suppress the top toolbar & body shift
   ============================================================ */
body {
    top: 0 !important;          /* GT sets body { top: 40px } — override it */
    position: static !important;
}

/* Hide the GT iframe toolbar */
.goog-te-banner-frame,
iframe.goog-te-banner-frame {
    display: none !important;
}

/* Hide other GT injected UI we don't need */
.skiptranslate:not(#google_translate_element) {
    display: none !important;
}

#goog-gt-tt,
.goog-tooltip,
.goog-tooltip:hover {
    display: none !important;
}

a.ft-contact-value {
    color: #ffffff;
    text-decoration: none;
    transition: color 0.25s ease;
}

a.ft-contact-value:hover {
    color: #ffc107;
    text-shadow: 0 0 8px rgba(255,193,7,0.3);
}
/* ============================================================
   FOOTER ROOT
   ============================================================ */
.ft-root {
    background: linear-gradient(180deg, #080e20 0%, #0a1329 60%, #060b1a 100%);
    color: #ffffff;
    position: relative;
    overflow: hidden;
}

.ft-root::before {
    content: '';
    position: absolute;
    top: 0; left: 50%;
    transform: translateX(-50%);
    width: 700px;
    height: 400px;
    background: radial-gradient(ellipse at center, rgba(255,193,7,0.05) 0%, transparent 70%);
    pointer-events: none;
}

/* Top accent bar */
.ft-topbar {
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, transparent 0%, rgba(255,239,194,0.15) 20%, rgba(255,193,7,0.55) 50%, rgba(255,239,194,0.15) 80%, transparent 100%);
}

/* ============================================================
   HEADINGS
   ============================================================ */
.ft-heading {
    font-family: 'Cinzel', serif !important;
    font-size: 0.7rem !important;
    font-weight: 700 !important;
    letter-spacing: 2.5px !important;
    text-transform: uppercase !important;
    color: rgba(255,239,194,0.55) !important;
    margin-bottom: 20px !important;
}

.ft-heading-icon {
    color: rgba(255,193,7,0.6);
    font-size: 0.65rem;
}

/* ============================================================
   CONTACT CARD
   ============================================================ */
.ft-contact-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,239,194,0.08);
    border-radius: 14px;
    padding: 6px 10px;
    backdrop-filter: blur(10px);
}

.ft-contact-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 10px;
    border-radius: 10px;
    transition: background 0.25s ease, transform 0.25s ease;
    border-bottom: 1px solid rgba(255,239,194,0.05);
}

.ft-contact-item:last-child { border-bottom: none; }

.ft-contact-item:hover {
    background: rgba(255,239,194,0.05);
    transform: translateX(4px);
}

.ft-contact-icon {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: rgba(255,193,7,0.1);
    border: 1px solid rgba(255,193,7,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffc107;
    font-size: 0.75rem;
    flex-shrink: 0;
    transition: all 0.25s ease;
}

.ft-contact-item:hover .ft-contact-icon {
    background: rgba(255,193,7,0.18);
    box-shadow: 0 0 10px rgba(255,193,7,0.2);
}

.ft-contact-label {
    display: block;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.62rem;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(255,239,194,0.4);
    margin-bottom: 2px;
}

.ft-contact-value {
    display: block;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.88rem;
    font-weight: 600;
    color: #ffffff;
    letter-spacing: 0.5px;
}

/* ============================================================
   BRAND
   ============================================================ */
.ft-brand-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.ft-logo-wrap {
    position: relative;
    display: inline-block;
    margin-bottom: 18px;
    width: 90px; height: 90px;
    flex-shrink: 0;
}

.ft-logo-wrap::before {
    content: '';
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    border: 1px solid rgba(201,168,76,0.55);
    opacity: 0.5;
    animation: ft-halo 3s ease-in-out infinite;
    pointer-events: none;
}

@keyframes ft-halo {
    0%, 100% { transform: scale(1);     opacity: 0.5;  }
    50%       { transform: scale(1.07); opacity: 0.22; }
}

.ft-logo {
    width: 90px; height: 90px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid rgba(201,168,76,0.6);
    display: block;
    filter: drop-shadow(0 0 10px rgba(201,168,76,0.2));
    transition: transform 0.4s ease, filter 0.4s ease;
}

.ft-logo:hover {
    transform: scale(1.06);
    filter: drop-shadow(0 0 18px rgba(201,168,76,0.45));
}

.ft-brand-name {
    font-family: 'Cinzel Decorative', 'Cinzel', serif !important;
    font-style: italic !important;
    font-weight: 700 !important;
    font-size: 1.1rem !important;
    letter-spacing: 0.04em !important;
    background: linear-gradient(135deg, #f0d080 0%, #c9a84c 50%, #fdf0c8 100%);
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
    background-clip: text !important;
    margin-bottom: 4px !important;
    line-height: 1.3 !important;
    text-transform: none !important;
}

.ft-brand-tagline {
    font-family: 'Cormorant Garamond', serif !important;
    font-style: italic !important;
    font-size: 0.75rem !important;
    color: rgba(240,208,128,0.5) !important;
    letter-spacing: 0.18em !important;
    text-transform: uppercase !important;
    margin-bottom: 14px !important;
    -webkit-text-fill-color: rgba(240,208,128,0.5) !important;
}

.ft-brand-desc {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    line-height: 1.7;
    max-width: 280px;
    margin: 0 auto 20px;
}

/* ============================================================
   SOCIAL BUTTONS
   ============================================================ */
.ft-socials {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.ft-social-btn {
    width: 38px; height: 38px;
    border-radius: 50%;
    border: 1px solid rgba(255,239,194,0.2);
    background: rgba(255,255,255,0.04);
    color: rgba(255,239,194,0.7) !important;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    text-decoration: none !important;
    transition: all 0.3s ease;
}

.ft-social-btn:hover {
    background: rgba(255,193,7,0.12);
    border-color: rgba(255,193,7,0.5);
    color: #ffd863 !important;
    transform: translateY(-3px);
    box-shadow: 0 6px 18px rgba(255,193,7,0.2);
}

/* ============================================================
   ADDRESS
   ============================================================ */
.ft-address {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.55);
    line-height: 1.8;
}

/* ============================================================
   QUICK LINKS
   ============================================================ */
.ft-links li { margin-bottom: 8px; }

.ft-links a {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.5);
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
    position: relative;
}

.ft-links a::after {
    content: '';
    display: inline-block;
    width: 12px; height: 1.5px;
    background: #ffc107;
    opacity: 0.3;
    flex-shrink: 0;
    transition: all 0.25s ease;
}

.ft-links a:hover {
    color: #ffefc2;
    padding-right: 4px;
    text-shadow: 0 0 8px rgba(255,239,194,0.3);
}

.ft-links a:hover::after { width: 18px; opacity: 1; }

@media (max-width: 767px) {
    .ft-links { text-align: left !important; }
    .ft-links a::after { display: none; }
    .ft-links a::before {
        content: '';
        display: inline-block;
        width: 12px; height: 1.5px;
        background: #ffc107;
        opacity: 0.3;
        flex-shrink: 0;
        transition: all 0.25s ease;
    }
    .ft-links a:hover { padding-right: 0; padding-left: 4px; }
    .ft-links a:hover::before { width: 18px; opacity: 1; }
}

/* ============================================================
   BOTTOM / COPYRIGHT
   ============================================================ */
.ft-bottom {
    margin-top: 50px;
    text-align: center;
}

.ft-paw-divider {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.ft-paw-divider span {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,239,194,0.15), transparent);
}

.ft-paw-icon {
    font-size: 0.85rem;
    color: rgba(255,193,7,0.4);
    animation: ft-paw-beat 3s ease-in-out infinite;
}

@keyframes ft-paw-beat {
    0%, 100% { opacity: 0.4; transform: scale(1);    }
    50%       { opacity: 0.8; transform: scale(1.15); }
}

.ft-copyright {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.75rem;
    color: rgba(255,255,255,0.3);
    letter-spacing: 0.5px;
    margin: 0;
    padding-bottom: 24px;
}

.ft-copyright-name {
    font-family: 'Cinzel Decorative', 'Cinzel', serif;
    font-style: italic;
    letter-spacing: 0.05em;
    background: linear-gradient(135deg, #f0d080, #c9a84c);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-transform: none;
}
</style>