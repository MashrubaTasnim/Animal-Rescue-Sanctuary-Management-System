<!-- navbar.php -->
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Cinzel+Decorative:wght@400;700&family=Cormorant+Garamond:ital,wght@0,400;1,300;1,400&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">

<?php
$current_page = basename($_SERVER['PHP_SELF']);

$portal_pages = ['user.php','animals.php','vets.php','donate.php','events.php','vacancies.php','guidelines.php','process_sos.php','surrender.php'];
$admin_pages  = ['admin.php','manage_rescues.php','manage_vacancies.php','manage_animals.php','manage_clinics.php','manage_users.php','manage_events.php','manage_donations.php','manage_contents.php','admin_settings.php'];

$role         = $_SESSION['role'] ?? '';
$is_logged_in = isset($_SESSION['user_id']);

// Helper: renders a dropdown <li> item
function nb_item(string $href, string $icon, string $label, string $extra_class = '', string $onclick = ''): string {
    $oc = $onclick ? " onclick=\"{$onclick}\"" : '';
    return "<li><a class='dropdown-item nb-item {$extra_class}' href='{$href}'{$oc}><i class='fas fa-{$icon}'></i> {$label}</a></li>";
}
?>

<div id="pt-navbar-root">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-lg-5">

            <!-- BRAND -->
            <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
                <div class="nb-logo-wrap">
                    <img src="logo.png" alt="Logo" class="nb-logo-img">
                </div>
                <div class="brand-text-block">
                    <span class="nb-brand-title"><?= htmlspecialchars(setting('site_name', 'Heartbeat Heaven')) ?></span>
                    <span class="nb-brand-tagline"><?= htmlspecialchars(setting('site_tagline', 'Animal Rescue & Sanctuary')) ?></span>
                </div>
            </a>

            <!-- TOGGLER -->
            <button class="navbar-toggler nb-toggler" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navMain"
                    aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- NAV LINKS -->
            <div class="collapse navbar-collapse" id="navMain">
                <?php if ($current_page !== 'index.php'): ?>
                <a href="javascript:history.back()" class="nb-back-btn nb-back-desktop d-none d-lg-flex" title="Go Back">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <?php endif; ?>

                <ul class="navbar-nav ms-auto align-items-center gap-1">

                    <!-- PORTAL (all users) -->
                    <li class="nav-item dropdown hover-dropdown">
                        <a class="nav-link nb-link nb-info dropdown-toggle clean-link main-click-link <?= in_array($current_page, $portal_pages) ? 'nb-active' : '' ?>"
                           href="<?= $is_logged_in ? 'user.php' : '#' ?>">
                            <i class="fas fa-th-large nb-icon"></i> Portal
                        </a>
                        <ul class="dropdown-menu nb-dropdown shadow">
                            <li class="nb-drop-header">Explore</li>
                            <?= nb_item('animals.php',   'paw',               'View Animals') ?>
                            <?= nb_item('#', 'ambulance', 'Ask for Rescue', 'text-danger-soft', "nbOpenModal('rescueModal'); return false;") ?>
                            <?= nb_item('#', 'home',      'Surrender a Pet', '',               "nbOpenModal('surrenderModal'); return false;") ?>
                            <?= nb_item('vets.php',      'briefcase-medical', 'Nearby Clinics') ?>
                            <?= nb_item('events.php',    'calendar-alt',      'View Events') ?>
                            <?= nb_item('guidelines.php','heart',             'Pet Guidelines') ?>
                            <?= nb_item('vacancies.php', 'file-signature',    'Join Our Team') ?>
                            <li class="nb-divider"></li>
                            <?php if ($is_logged_in): ?>
                                <?= nb_item('donate.php',  'hand-holding-heart', 'Donate') ?>
                                <?= nb_item('notices.php', 'bell',               'Notices') ?>
                            <?php else: ?>
                                <?= nb_item('login.php', 'lock', 'Donate <small>(login)</small>', 'opacity-50') ?>
                                <?= nb_item('login.php', 'lock', 'Notices <small>(login)</small>', 'opacity-50') ?>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <?php if ($is_logged_in): ?>

                        <!-- ADMIN -->
                        <?php if ($role === 'admin'): ?>
                        <li class="nav-item dropdown hover-dropdown">
                            <a class="nav-link nb-link nb-admin dropdown-toggle clean-link main-click-link <?= in_array($current_page, $admin_pages) ? 'nb-active' : '' ?>" href="admin.php">
                                <i class="fas fa-shield-alt nb-icon"></i> Admin
                            </a>
                            <ul class="dropdown-menu nb-dropdown nb-dropdown-wide shadow">
                                <li class="nb-drop-header">Rescue &amp; HR</li>
                                <?= nb_item('manage_rescues.php',   'heartbeat', 'Mission Control') ?>
                                <?= nb_item('manage_vacancies.php', 'user-tie',  'Hiring &amp; Recruitment') ?>
                                <li class="nb-divider"></li>
                                <li class="nb-drop-header">Assets &amp; Users</li>
                                <?= nb_item('manage_animals.php',   'paw',              'Animal &amp; Adoption Records') ?>
                                <?= nb_item('manage_clinics.php',   'clinic-medical',   'Vet Clinic Partners') ?>
                                <?= nb_item('manage_users.php',     'user-shield',      'User Directory') ?>
                                <?= nb_item('manage_events.php',    'calendar-check',   'Event Control') ?>
                                <?= nb_item('manage_donations.php', 'hand-holding-usd', 'Donations') ?>
                                <li class="nb-divider"></li>
                                <li class="nb-drop-header">Content &amp; System</li>
                                <?= nb_item('manage_contents.php', 'layer-group', 'Content Manager') ?>
                                <?= nb_item('admin_settings.php',  'cog',         'Settings') ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <!-- RESCUER HQ -->
                        <?php if ($role === 'rescuer' || $role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link nb-link nb-info clean-link <?= $current_page === 'rescuer.php' ? 'nb-active' : '' ?>" href="rescuer.php">
                                <i class="fas fa-first-aid nb-icon"></i> Rescuer HQ
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- MEDICAL PORTAL -->
                        <?php if ($role === 'vet' || $role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link nb-link nb-info clean-link <?= $current_page === 'vet_dashboard.php' ? 'nb-active' : '' ?>" href="vet_dashboard.php">
                                <i class="fas fa-stethoscope nb-icon"></i> Medical Portal
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- MAINTENANCE BADGE -->
                        <?php if ($role === 'admin' && setting('maintenance_mode') === '1'): ?>
                        <li class="nav-item">
                            <a href="admin_settings.php#sec-general" class="nb-maintenance-badge">
                                <i class="fas fa-tools"></i> Maintenance ON
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- PROFILE -->
                        <li class="nav-item dropdown hover-dropdown ms-2">
                            <a class="nb-profile-btn dropdown-toggle clean-link main-click-link" href="my_profile.php">
                               <div class="nb-avatar"><?= strtoupper(substr($_SESSION['name'] ?? $_SESSION['full_name'] ?? 'G', 0, 1)) ?></div>
<span class="nb-profile-name"><?= htmlspecialchars(explode(' ', $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Guest')[0]) ?></span>
                                <i class="fas fa-chevron-down nb-caret"></i>
                            </a>
                            <ul class="dropdown-menu nb-dropdown nb-profile-dropdown dropdown-menu-end shadow" style="min-width:200px;right:0 !important;left:auto !important;">
                                <li class="nb-drop-header">My Account</li>
                                <?= nb_item('my_profile.php',      'id-badge',   'My Profile') ?>
                                <?= nb_item('my_applications.php', 'history',    'My Activity Hub') ?>
                                <li class="nb-divider"></li>
                                <?= nb_item('logout.php', 'sign-out-alt', 'Logout', 'nb-logout') ?>
                            </ul>
                        </li>

                    <?php else: ?>
                        <!-- GUEST -->
                        <li class="nav-item"><a href="login.php"    class="nb-ghost-btn">Login</a></li>
                        <li class="nav-item"><a href="register.php" class="nb-solid-btn">Join Us <i class="fas fa-paw ms-1"></i></a></li>
                    <?php endif; ?>

                </ul>
            </div>
        </div>
    </nav>

    <style>
    #pt-navbar-root .navbar {
        background: rgba(8,14,32,0.97) !important;
        backdrop-filter: blur(24px) !important;
        -webkit-backdrop-filter: blur(24px) !important;
        position: fixed !important;
        top: 0; left: 0; width: 100%;
        z-index: 9999 !important;
        padding: 0 !important;
        min-height: 80px;
        border-bottom: none !important;
        box-shadow: 0 4px 40px rgba(0,0,0,0.5), 0 1px 0 rgba(255,239,194,0.08) !important;
        transition: all 0.4s ease;
    }
    #pt-navbar-root .navbar::after {
        content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px;
        background: linear-gradient(90deg, transparent 0%, rgba(255,239,194,0.12) 15%, rgba(255,193,7,0.5) 50%, rgba(255,239,194,0.12) 85%, transparent 100%);
        transition: opacity 0.4s ease; opacity: 0.6;
    }
    #pt-navbar-root .navbar.nb-scrolled { background: rgba(6,10,24,0.99) !important; box-shadow: 0 6px 50px rgba(0,0,0,0.7), 0 1px 0 rgba(255,239,194,0.1) !important; }
    #pt-navbar-root .navbar.nb-scrolled::after { opacity: 1; background: linear-gradient(90deg, transparent 0%, rgba(255,239,194,0.18) 15%, rgba(255,193,7,0.7) 50%, rgba(255,239,194,0.18) 85%, transparent 100%); }
    body { padding-top: 80px !important; }

    /* BRAND */
    #pt-navbar-root .navbar-brand { text-decoration: none !important; }
    #pt-navbar-root .nb-logo-wrap { position: relative; width: 60px; height: 60px; flex-shrink: 0; overflow: visible; }
    #pt-navbar-root .nb-logo-wrap::before { content: ''; position: absolute; inset: -5px; border-radius: 50%; border: 1px solid rgba(201,168,76,0.55); opacity: 0.5; animation: nb-halo 3s ease-in-out infinite; pointer-events: none; }
    @keyframes nb-halo { 0%,100% { transform: scale(1); opacity: 0.5; } 50% { transform: scale(1.08); opacity: 0.22; } }
    #pt-navbar-root .nb-logo-img { width: 60px; height: 60px; object-fit: cover; border-radius: 50% !important; border: 2px solid rgba(201,168,76,0.6) !important; display: block; filter: drop-shadow(0 0 8px rgba(201,168,76,0.2)); transition: transform 0.5s cubic-bezier(0.34,1.56,0.64,1), filter 0.3s ease; }
    #pt-navbar-root .navbar-brand:hover .nb-logo-img { transform: scale(1.06); filter: drop-shadow(0 0 14px rgba(201,168,76,0.45)); }
    #pt-navbar-root .brand-text-block { all: initial; display: flex !important; flex-direction: column !important; align-items: flex-start !important; }
    #pt-navbar-root .nb-brand-title { font-family: 'Cinzel Decorative','Cinzel',serif !important; font-weight: 700 !important; font-style: italic !important; font-size: 1.3rem !important; letter-spacing: 0.03em !important; background: linear-gradient(135deg,#f0d080 0%,#c9a84c 50%,#fdf0c8 100%) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; background-clip: text !important; margin: 0 !important; padding: 0 !important; line-height: 1.2 !important; display: block !important; white-space: nowrap !important; text-decoration: none !important; border: none !important; }
    #pt-navbar-root .nb-brand-tagline { font-family: 'Cormorant Garamond',serif !important; font-style: italic !important; font-size: 0.75rem !important; color: rgba(240,208,128,0.5) !important; margin: 2px 0 0 !important; padding: 0 !important; letter-spacing: 0.18em !important; text-transform: uppercase !important; font-weight: 400 !important; display: block !important; text-decoration: none !important; border: none !important; background: none !important; -webkit-text-fill-color: rgba(240,208,128,0.5) !important; line-height: 1.5 !important; }

    /* TOGGLER */
    #pt-navbar-root .nb-toggler { background: transparent; border: 1px solid rgba(255,239,194,0.25) !important; border-radius: 8px; padding: 8px 10px; display: flex; flex-direction: column; gap: 5px; cursor: pointer; }
    #pt-navbar-root .nb-toggler .navbar-toggler-icon { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255%2C239%2C194%2C0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e"); width: 22px; height: 22px; }

    /* BACK BUTTON */
    #pt-navbar-root .nb-back-btn { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; border: 1px solid rgba(255,239,194,0.25); color: rgba(255,255,255,0.75) !important; font-size: 0.85rem; text-decoration: none !important; transition: all 0.25s ease; flex-shrink: 0; margin-right: 8px; }
    #pt-navbar-root .nb-back-btn:hover { background: rgba(255,239,194,0.08); border-color: rgba(255,239,194,0.45); color: #ffefc2 !important; }
    @media (min-width:992px) { #pt-navbar-root .nb-toggler { display: none !important; } #pt-navbar-root .nb-back-desktop { margin-right: 12px; } }
    @media (max-width:991px) {
        #pt-navbar-root .nb-logo-wrap { width: 42px; height: 42px; }
        #pt-navbar-root .nb-logo-img  { width: 42px !important; height: 42px !important; }
        #pt-navbar-root .nb-brand-title   { font-size: 1.05rem !important; letter-spacing: 0.02em !important; white-space: nowrap !important; }
        #pt-navbar-root .nb-brand-tagline { font-size: 0.58rem !important; letter-spacing: 0.12em !important; }
    }

    /* NAV LINKS */
    #pt-navbar-root .navbar-nav .nav-link.nb-link,
    #pt-navbar-root .nb-link { font-family: 'Montserrat',sans-serif !important; font-size: 0.72rem !important; font-weight: 700 !important; letter-spacing: 1.5px !important; text-transform: uppercase !important; color: rgba(255,255,255,0.75) !important; padding: 10px 12px !important; border-radius: 8px; text-decoration: none !important; border: none !important; position: relative; transition: all 0.25s ease, text-shadow 0.25s ease !important; display: flex; align-items: center; gap: 6px; text-shadow: 0 0 0 rgba(255,239,194,0); }
    #pt-navbar-root .nb-link::after, #pt-navbar-root .nb-link.dropdown-toggle::after { display: none !important; content: none !important; }
    #pt-navbar-root .nb-link.nb-info, #pt-navbar-root .nb-link.nb-admin { color: #ffd863 !important; }
    #pt-navbar-root .nb-link:hover, #pt-navbar-root .nb-link:focus { color: #ffefc2 !important; background: rgba(255,239,194,0.07) !important; text-shadow: 0 0 10px rgba(255,239,194,0.55), 0 0 20px rgba(255,239,194,0.2) !important; }
    #pt-navbar-root .nb-link.nb-info:hover,  #pt-navbar-root .nb-link.nb-admin:hover,
    #pt-navbar-root .nb-link.nb-info:focus,  #pt-navbar-root .nb-link.nb-admin:focus,
    #pt-navbar-root .hover-dropdown:hover > .nb-link.nb-info,
    #pt-navbar-root .hover-dropdown:hover > .nb-link.nb-admin,
    #pt-navbar-root .hover-dropdown:focus-within > .nb-link.nb-info,
    #pt-navbar-root .hover-dropdown:focus-within > .nb-link.nb-admin,
    #pt-navbar-root .nb-link.nb-info.nb-active,
    #pt-navbar-root .nb-link.nb-admin.nb-active { color: #ffd54f !important; background: rgba(255,193,7,0.08) !important; text-shadow: 0 0 10px rgba(255,213,79,0.65), 0 0 22px rgba(255,193,7,0.25) !important; }
    #pt-navbar-root .nb-icon { font-size: 0.75rem; opacity: 0.7; }
    #pt-navbar-root .nb-link i.fa-chevron-down { font-size: 0.55rem; opacity: 0.5; transition: transform 0.3s ease; margin-left: 2px; }
    #pt-navbar-root .hover-dropdown:hover .nb-link i.fa-chevron-down { transform: rotate(180deg); }

    /* DROPDOWN */
    #pt-navbar-root .nb-dropdown { background: rgba(8,14,32,0.98) !important; border: 1px solid rgba(255,239,194,0.12) !important; border-radius: 14px !important; padding: 8px 6px !important; margin-top: 8px !important; min-width: 210px; backdrop-filter: blur(20px); box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.04) !important; z-index: 9999 !important; opacity: 0; visibility: hidden; transform: translateY(8px); transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s ease !important; display: block !important; }
    #pt-navbar-root .nb-dropdown-wide { min-width: 260px; }
    #pt-navbar-root .hover-dropdown:hover > .nb-dropdown,
    #pt-navbar-root .hover-dropdown:focus-within > .nb-dropdown { opacity: 1 !important; visibility: visible !important; transform: translateY(0) !important; }
    #pt-navbar-root .nb-drop-header { font-family: 'Montserrat',sans-serif; font-size: 0.58rem; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; color: rgba(255,239,194,0.35); padding: 6px 14px 8px; list-style: none; }
    #pt-navbar-root .nb-dropdown > li:first-child.nb-drop-header { padding-top: 4px; }
    #pt-navbar-root .nb-divider { display: block; height: 1px; background: rgba(255,239,194,0.08); margin: 5px 10px; list-style: none; }
    #pt-navbar-root .nb-item { font-family: 'Montserrat',sans-serif !important; font-size: 0.8rem !important; font-weight: 500 !important; color: rgba(255,255,255,0.7) !important; padding: 9px 14px !important; border-radius: 8px !important; display: flex !important; align-items: center; gap: 10px; text-decoration: none !important; transition: all 0.2s ease !important; background: transparent !important; }
    #pt-navbar-root .nb-item i { width: 16px; text-align: center; font-size: 0.8rem; opacity: 0.6; transition: opacity 0.2s ease; flex-shrink: 0; }
    #pt-navbar-root .nb-item:hover { background: rgba(255,239,194,0.08) !important; color: #ffefc2 !important; padding-left: 18px !important; }
    #pt-navbar-root .nb-item:hover i { opacity: 1; }
    #pt-navbar-root .nb-item.text-danger-soft { color: rgba(255,110,110,0.8) !important; }
    #pt-navbar-root .nb-item.text-danger-soft:hover { color: #ff6e6e !important; background: rgba(255,80,80,0.07) !important; }
    #pt-navbar-root .nb-logout { color: rgba(255,100,100,0.75) !important; }
    #pt-navbar-root .nb-logout:hover { color: #ff6e6e !important; background: rgba(255,80,80,0.08) !important; }

    /* PROFILE */
    #pt-navbar-root .nb-profile-btn { display: flex; align-items: center; gap: 8px; padding: 6px 12px 6px 6px; border-radius: 40px; border: 1px solid rgba(255,193,7,0.3) !important; background: rgba(255,193,7,0.05); text-decoration: none !important; transition: all 0.3s ease; cursor: pointer; }
    #pt-navbar-root .nb-profile-btn::after { display: none !important; }
    #pt-navbar-root .nb-profile-btn:hover,
    #pt-navbar-root .hover-dropdown:hover > .nb-profile-btn,
    #pt-navbar-root .hover-dropdown:focus-within > .nb-profile-btn { border-color: rgba(255,193,7,0.6) !important; background: rgba(255,193,7,0.1); box-shadow: 0 0 10px rgba(255,193,7,0.2) !important; }
    #pt-navbar-root .nb-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg,#ffc107,#ff8f00); color: #0a1329; font-family: 'Montserrat',sans-serif; font-weight: 700; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    #pt-navbar-root .nb-profile-name { font-family: 'Montserrat',sans-serif; font-size: 0.72rem; font-weight: 700; color: #ffffff; letter-spacing: 0.5px; }
    #pt-navbar-root .nb-caret { font-size: 0.5rem; color: rgba(255,193,7,0.5); transition: transform 0.3s ease; }
    #pt-navbar-root .hover-dropdown:hover .nb-caret { transform: rotate(180deg); }
    #pt-navbar-root .nb-profile-dropdown { right: 0 !important; left: auto !important; transform-origin: top right !important; }

    /* GUEST BUTTONS */
    #pt-navbar-root .nb-ghost-btn,
    #pt-navbar-root .nb-solid-btn { font-family: 'Montserrat',sans-serif; font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 8px 18px; border-radius: 8px; text-decoration: none !important; transition: all 0.25s ease; display: inline-block; }
    #pt-navbar-root .nb-ghost-btn { color: rgba(255,255,255,0.75) !important; border: 1px solid rgba(255,255,255,0.18); }
    #pt-navbar-root .nb-ghost-btn:hover { color: #fff !important; border-color: rgba(255,255,255,0.4); background: rgba(255,255,255,0.06); }
    #pt-navbar-root .nb-solid-btn { color: #0a1329 !important; padding: 8px 20px; background: #ffc107; border: none; box-shadow: 0 4px 14px rgba(255,193,7,0.25); }
    #pt-navbar-root .nb-solid-btn:hover { background: #ffd54f; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,193,7,0.4); color: #0a1329 !important; }

    /* MAINTENANCE */
    #pt-navbar-root .nb-maintenance-badge { font-family: 'Montserrat',sans-serif; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px; color: #fff !important; background: #dc3545; padding: 5px 10px; border-radius: 6px; text-decoration: none !important; display: inline-flex; align-items: center; gap: 5px; animation: nb-pulse 1.5s ease-in-out infinite; }
    @keyframes nb-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.65; } }

    /* MOBILE */
    @media (max-width:991px) {
        #pt-navbar-root .navbar-collapse { background: rgba(6,10,24,0.98); border-top: 1px solid rgba(255,239,194,0.08); padding: 16px 12px 20px; }
        #pt-navbar-root .nb-dropdown { background: rgba(255,255,255,0.04) !important; border: none !important; border-radius: 10px !important; padding: 4px 0 !important; box-shadow: none !important; opacity: 1 !important; visibility: visible !important; transform: none !important; display: none !important; }
        #pt-navbar-root .nb-dropdown.show { display: block !important; }
        #pt-navbar-root .nb-link { padding: 10px 8px !important; }
        #pt-navbar-root .nb-profile-btn { border-radius: 10px !important; }
        #pt-navbar-root .gap-1 { gap: 2px !important; }
        #pt-navbar-root .nb-solid-btn, #pt-navbar-root .nb-ghost-btn { display: block; text-align: center; margin-top: 6px; }
    }

    /* KILL UNDERLINES */
    #pt-navbar-root a, #pt-navbar-root a::after, #pt-navbar-root a::before { text-decoration: none !important; border-bottom: none !important; }
    #pt-navbar-root .dropdown-toggle::after { display: none !important; }
    </style>

    <script>
    (function() {
        const navbar = document.querySelector('#pt-navbar-root .navbar');
        window.addEventListener('scroll', function() {
            navbar.classList.toggle('nb-scrolled', window.scrollY > 20);
        }, { passive: true });

        document.querySelectorAll('#pt-navbar-root .main-click-link').forEach(function(link) {
            link.addEventListener('mousedown', function() {
                if (window.innerWidth >= 992) {
                    var url = this.getAttribute('href');
                    if (url && url !== '#') window.location.href = url;
                }
            });
        });
    })();

    function nbOpenModal(modalId) {
        setTimeout(function() {
            var el = document.getElementById(modalId);
            if (!el) return;
            bootstrap.Modal.getOrCreateInstance(el).show();
        }, 120);
    }
    </script>
</div>