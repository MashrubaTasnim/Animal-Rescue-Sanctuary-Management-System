<?php 
session_start(); 
include 'db_config.php'; 
include 'navbar.php'; 

// Dynamic Statistics
$res_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM rescues WHERE status='Resolved'");
$resolved_data = mysqli_fetch_assoc($res_query);
$resolved_count = $resolved_data['total'] + 300; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heartbeat Heaven | Home</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <style>
        :root {
            --midnight: #0a1329;
            --moonlight: #ffc107;
            --cloud-blue: #c8d8f0;
            --gold-glow: rgba(255, 193, 7, 0.18);
            --gold-bright: rgba(255, 193, 7, 0.45);
        }

        /* ===================== GLOBAL ===================== */
        * { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            overflow-x: hidden;
        }

        html { scroll-behavior: smooth; }

        /* ===================== PARTICLE CANVAS ===================== */
        #particle-canvas {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            opacity: 0.35;
        }

        /* ===================== HERO ===================== */
        .hero-wrap {
            position: relative;
            overflow: hidden;
        }

        .hero-wrap::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(ellipse 80% 60% at 20% 50%, rgba(255,193,7,0.04) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 20%, rgba(10,19,41,0.15) 0%, transparent 60%);
            z-index: 1;
            pointer-events: none;
        }

        .hero-wrap::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(255,193,7,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,193,7,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridShift 20s linear infinite;
            z-index: 1;
        }

        @keyframes gridShift {
            from { background-position: 0 0; }
            to   { background-position: 60px 60px; }
        }

        .hero-content { position: relative; z-index: 2; }

        .hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-weight: 700;
            font-size: clamp(2.8rem, 6vw, 5.5rem);
            line-height: 1.1;
            color: white;
            letter-spacing: -0.02em;
        }

        .hero-title .line-1 {
            display: block;
            opacity: 0;
            transform: translateY(40px);
            animation: slideUp 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.3s forwards;
        }

        .hero-title .line-2 {
            display: block;
            opacity: 0;
            transform: translateY(40px);
            animation: slideUp 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.55s forwards;
        }

        @keyframes slideUp {
            to { opacity: 1; transform: translateY(0); }
        }

        .typing-text { color: var(--moonlight); position: relative; }

        .typing-text::after {
            content: '|';
            display: inline-block;
            color: var(--moonlight);
            animation: blink 0.75s step-end infinite;
            margin-left: 2px;
        }

        .typing-text.done::after { display: none; }

        @keyframes blink {
            from, to { opacity: 1; }
            50%       { opacity: 0; }
        }

        .hero-lead {
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.85s forwards;
            color: var(--cloud-blue);
            border-left: 3px solid var(--moonlight);
            padding-left: 20px;
            font-size: 1.1rem;
            line-height: 1.7;
        }

        .hero-buttons {
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.9s cubic-bezier(0.16, 1, 0.3, 1) 1.1s forwards;
        }

        .btn-adopt {
            background: var(--moonlight);
            color: var(--midnight);
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            letter-spacing: 0.03em;
            border: none;
            padding: 16px 40px;
            border-radius: 4px;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-adopt::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.2);
            transform: translateX(-100%) skewX(-15deg);
            transition: transform 0.4s ease;
        }

        .btn-adopt:hover::before { transform: translateX(200%) skewX(-15deg); }
        .btn-adopt:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 12px 30px rgba(255,193,7,0.4);
            color: var(--midnight);
        }

        .btn-rescue {
            background: transparent;
            color: white;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            border: 1.5px solid rgba(255,255,255,0.35);
            padding: 16px 40px;
            border-radius: 4px;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(4px);
        }

        .btn-rescue:hover {
            border-color: var(--moonlight);
            color: var(--moonlight);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255,193,7,0.15);
        }

        .scroll-indicator {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            opacity: 0;
            animation: fadeIn 1s ease 2s forwards;
        }

        .scroll-indicator span {
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
        }

        .scroll-mouse {
            width: 22px; height: 36px;
            border: 1.5px solid rgba(255,255,255,0.2);
            border-radius: 11px;
            display: flex;
            justify-content: center;
            padding-top: 6px;
        }

        .scroll-mouse::before {
            content: '';
            width: 3px; height: 8px;
            background: var(--moonlight);
            border-radius: 2px;
            animation: scrollDown 1.8s ease infinite;
        }

        @keyframes scrollDown {
            0%   { transform: translateY(0);    opacity: 1; }
            100% { transform: translateY(10px); opacity: 0; }
        }

        /* ===================== ABOUT ===================== */
        #about-us { position: relative; overflow: hidden; }

        #about-us::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,193,7,0.06) 0%, transparent 70%);
        }

        .logo-container { position: relative; }

        .logo-container::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,193,7,0.08) 0%, transparent 65%);
            transform: scale(1);
            transition: transform 0.5s ease;
        }

        .logo-container:hover::before { transform: scale(1.1); }

        .logo-container img {
            transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            filter: drop-shadow(0 20px 40px rgba(255,193,7,0.1));
        }

        .logo-container:hover img { transform: scale(1.04) rotate(1deg); }

        .stat-card {
            padding: 20px 24px;
            border-left: 3px solid var(--moonlight);
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            right: -20px; top: 50%;
            transform: translateY(-50%);
            width: 80px; height: 80px;
            border-radius: 50%;
            background: rgba(255,193,7,0.06);
            transition: all 0.4s ease;
        }

        .stat-card:hover {
            box-shadow: 0 6px 24px rgba(255,193,7,0.12);
            transform: translateX(4px);
        }

        .stat-card:hover::after { transform: translateY(-50%) scale(1.5); }

        .stat-card h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--midnight);
            margin: 0;
        }

        /* ===================== VISION ===================== */
        .vision-section { background: #fafbfc; position: relative; overflow: hidden; }

        .vision-section::before {
            content: '✦';
            position: absolute;
            font-size: 18rem;
            color: rgba(255,193,7,0.025);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            line-height: 1;
        }

        .vision-box {
            padding: 40px 36px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 2px 20px rgba(0,0,0,0.04);
            transition: all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
            height: 100%;
            border: 1px solid rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }

        .vision-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--moonlight), rgba(255,193,7,0.3));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .vision-box:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(255, 193, 7, 0.12), 0 4px 16px rgba(0,0,0,0.06);
        }

        .vision-box:hover::before { transform: scaleX(1); }

        .vision-icon {
            width: 64px; height: 64px;
            background: rgba(255, 193, 7, 0.08);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            color: var(--midnight);
            font-size: 1.6rem;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }

        .vision-icon::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 18px;
            border: 1.5px solid rgba(255,193,7,0.15);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .vision-box:hover .vision-icon {
            background: rgba(255, 193, 7, 0.18);
            transform: scale(1.1) rotate(-5deg);
        }

        .vision-box:hover .vision-icon::after { opacity: 1; }

        .vision-box h4 {
            color: var(--midnight);
            font-weight: 700;
            margin-bottom: 14px;
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
        }

        /* ===================== GALLERY ===================== */
        .gallery-section { background: #fdfdfd; position: relative; }

        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            cursor: pointer;
            height: 280px;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
        }

        .gallery-item:hover {
            transform: translateY(-12px) scale(1.01);
            box-shadow: 0 24px 50px rgba(255, 193, 7, 0.2), 0 8px 20px rgba(0,0,0,0.1);
            z-index: 2;
        }

        .gallery-item img {
            transition: transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            object-fit: cover;
            width: 100%;
            height: 100%;
        }

        .gallery-item:hover img { transform: scale(1.1); }

        .gallery-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to top, rgba(10, 19, 41, 0.92) 0%, rgba(10,19,41,0.2) 60%, transparent 100%);
            display: flex; align-items: flex-end; justify-content: flex-start;
            opacity: 0;
            transition: opacity 0.4s ease;
            padding: 24px;
        }

        .gallery-item:hover .gallery-overlay { opacity: 1; }

        .gallery-overlay-inner {
            transform: translateY(10px);
            transition: transform 0.4s ease;
        }

        .gallery-item:hover .gallery-overlay-inner { transform: translateY(0); }

        .gallery-overlay i {
            display: block;
            color: var(--moonlight);
            font-size: 1.4rem;
            margin-bottom: 6px;
        }

        /* Gallery hidden extra items */
        .gallery-extra { display: none !important; }
        .gallery-extra.shown { display: block !important; }

        /* See More buttons */
        .btn-see-more, .btn-see-less {
            background: transparent;
            color: var(--midnight);
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            border: 2px solid var(--moonlight);
            padding: 12px 36px;
            border-radius: 4px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-block;
        }

        .btn-see-more:hover, .btn-see-less:hover {
            background: var(--moonlight);
            color: var(--midnight);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255,193,7,0.3);
        }

        .btn-see-less { display: none; }

        /* ===================== QUOTE ===================== */
        .quote-container {
            background: var(--midnight) !important;
            position: relative;
            overflow: hidden;
            padding: 120px 0;
            z-index: 1;
        }

        .quote-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
        }

        .quote-orb-1 {
            width: 400px; height: 400px;
            background: rgba(255,193,7,0.04);
            top: -100px; left: -100px;
            animation: orbFloat 8s ease-in-out infinite alternate;
        }

        .quote-orb-2 {
            width: 300px; height: 300px;
            background: rgba(255,193,7,0.03);
            bottom: -80px; right: -80px;
            animation: orbFloat 10s ease-in-out infinite alternate-reverse;
        }

        @keyframes orbFloat {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, 20px) scale(1.1); }
        }

        .quote-container::before {
            content: '\f10d';
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 22rem;
            color: rgba(255, 193, 7, 0.025);
            z-index: 0;
            transition: all 1s ease;
        }

        .quote-container:hover::before { color: rgba(255, 193, 7, 0.06); }

        .quote-text {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: clamp(1.4rem, 3vw, 2rem);
            line-height: 1.5;
        }

        .fade-quote { display: none; position: relative; z-index: 2; }
        .fade-quote.active { display: block; }

        /* ===================== TESTIMONIALS ===================== */
        .testimonial-card {
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.05) !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .testimonial-card::before {
            content: '\f10d';
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 16px; right: 20px;
            font-size: 4rem;
            color: rgba(255,193,7,0.06);
            line-height: 1;
        }

        .testimonial-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }

        /* Hidden extra testimonials */
        .testimonial-extra { display: none; }
        .testimonial-extra.shown { display: block; }

        .avatar-ring {
            width: 40px; height: 40px;
            background: var(--moonlight);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--midnight);
            position: relative;
            flex-shrink: 0;
        }

        .avatar-ring::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            border: 2px solid rgba(255,193,7,0.3);
            animation: ringPulse 2.5s ease-in-out infinite;
        }

        @keyframes ringPulse {
            0%, 100% { transform: scale(1);    opacity: 0.5; }
            50%       { transform: scale(1.15); opacity: 0;   }
        }

        .review-dark-card {
            background: var(--midnight);
            border-radius: 20px;
            padding: 28px;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .review-dark-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,193,7,0.04);
            transform: translate(50%, -50%);
        }

        .rev-input,
.rev-input:focus,
.rev-input:active,
.rev-input:not(:placeholder-shown) {
    background: rgba(255, 255, 255, 0.07) !important;
    background-color: rgba(255, 255, 255, 0.07) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 8px !important;
    transition: all 0.3s ease !important;
    -webkit-text-fill-color: #ffffff !important;
    caret-color: #ffffff !important;
}

/* Fix browser autofill background override */
.rev-input:-webkit-autofill,
.rev-input:-webkit-autofill:hover,
.rev-input:-webkit-autofill:focus,
.rev-input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 40px #1a2540 inset !important;
    -webkit-text-fill-color: #ffffff !important;
    caret-color: #ffffff !important;
}
        .rev-input::placeholder { color: rgba(255, 255, 255, 0.35) !important; }

        .rev-input:focus {
            background: rgba(255, 255, 255, 0.12) !important;
            border-color: rgba(255,193,7,0.5) !important;
            box-shadow: 0 0 0 3px rgba(255,193,7,0.08) !important;
            color: white !important;
        }

        /* ===================== LIGHTBOX ===================== */
        .lb-overlay {
            display: none;
            position: fixed; z-index: 9999; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(5, 10, 25, 0.97);
            align-items: center; justify-content: center; flex-direction: column;
            backdrop-filter: blur(8px);
        }

        .lb-img {
            max-width: 85%; max-height: 75vh;
            border-radius: 12px;
            border: 1px solid rgba(255,193,7,0.2);
            box-shadow: 0 40px 80px rgba(0,0,0,0.6);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        .lb-overlay.show .lb-img { transform: scale(1); }

        .lb-close {
            position: absolute; top: 30px; right: 40px;
            color: rgba(255,255,255,0.5);
            font-size: 36px; cursor: pointer;
            transition: all 0.2s ease;
            width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .lb-close:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: rotate(90deg);
        }

        .lb-caption {
            color: rgba(255,255,255,0.7);
            margin-top: 20px;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 0.75rem;
        }

        /* ===================== SECTION HEADINGS ===================== */
        .section-eyebrow {
            font-size: 0.7rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--moonlight);
            display: block;
            margin-bottom: 10px;
        }

        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1.8rem, 3.5vw, 2.8rem);
            font-weight: 700;
            color: var(--midnight);
            line-height: 1.15;
        }

        .section-divider {
            width: 40px; height: 2px;
            background: var(--moonlight);
            margin: 16px auto 0;
            border-radius: 2px;
            position: relative;
        }

        .section-divider::after {
            content: '';
            position: absolute;
            left: 44px; top: 0;
            width: 8px; height: 2px;
            background: rgba(255,193,7,0.4);
            border-radius: 2px;
        }

        /* ===================== SCROLL REVEAL ===================== */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .reveal.visible { opacity: 1; transform: translateY(0); }

        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }

        /* ===================== RESCUE MODAL ===================== */
        .modal-content { border-radius: 20px !important; overflow: hidden; }
        .modal-header.bg-danger { background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%) !important; }

        .form-control {
            border-radius: 10px !important;
            border: 1px solid rgba(0,0,0,0.1) !important;
            transition: all 0.3s ease !important;
        }

        .form-control:focus {
            border-color: rgba(255,193,7,0.5) !important;
            box-shadow: 0 0 0 3px rgba(255,193,7,0.1) !important;
        }

        /* ===================== STAR RATING ===================== */
        .star-btn {
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-block;
        }

        .star-btn:hover { transform: scale(1.3); }

        /* ===================== ADDRESS ===================== */
        .address-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 18px 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 3px solid var(--moonlight);
            transition: all 0.3s ease;
        }

        .address-card:hover {
            background: #fff;
            box-shadow: 0 4px 16px rgba(255,193,7,0.1);
        }

        /* ===================== ANIMATIONS ===================== */
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        /* ===================== MISC ===================== */
        .border-warning { border-color: var(--moonlight) !important; }
    </style>
</head>
<body>

<!-- Particle canvas -->
<canvas id="particle-canvas"></canvas>

<!-- Lightbox -->
<div id="lbModal" class="lb-overlay">
    <span class="lb-close">&times;</span>
    <img class="lb-img" id="lbImg">
    <div class="lb-caption" id="lbCap"></div>
</div>

<!-- ==================== HERO ==================== -->
<section class="hero-wrap">
    <div class="container hero-content">
        <div class="col-lg-8">
            <h1 class="hero-title">
                <span class="line-1">Every Heartbeat</span>
                <span class="line-2"><span class="typing-text" id="typingTarget"></span></span>
            </h1>
            <p class="lead mt-4 mb-5 hero-lead">
                From Rescue to Forever Love. We are a sanctuary for those who have only known shadows.
            </p>
            <div class="d-flex flex-wrap gap-3 hero-buttons">
                <a href="animals.php" class="btn-adopt">Adopt Now 🐾</a>
                <button class="btn-rescue" data-bs-toggle="modal" data-bs-target="#rescueModal">
                    Ask for Rescue <i class="fas fa-ambulance ms-2"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="scroll-indicator">
        <div class="scroll-mouse"></div>
        <span>Scroll</span>
    </div>
</section>

<!-- ==================== ABOUT ==================== -->
<section class="py-5 bg-white" id="about-us">
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 text-center reveal">
                <div class="logo-container p-4">
                    <img src="logo.png" class="img-fluid" alt="Heartbeat Heaven" style="max-width: 350px;">
                </div>
            </div>
            <div class="col-lg-6 reveal reveal-delay-1">
                <span class="section-eyebrow">Who We Are</span>
                <h2 class="section-title mb-4">Nationwide Protection<br>for the Voiceless</h2>
                <p class="text-muted mb-4" style="line-height: 1.8; font-size: 0.95rem;">
                    <strong>Heartbeat Heaven</strong> is a nationwide ecosystem dedicated to animal welfare across Bangladesh. We specialize in critical midnight rescues and rehabilitation.
                </p>

                <div class="address-card mb-4">
                    <div style="color: var(--moonlight); margin-top: 2px; flex-shrink: 0;">
                        <i class="fas fa-map-marker-alt fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1" style="color: var(--midnight); font-size: 0.9rem;">Our Sanctuary Address</h6>
                        <p class="text-muted small mb-0">Embankment Drive Road, Sector-10, Utttara, Dhaka-1230</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6 reveal reveal-delay-2">
                        <div class="stat-card">
                            <h4 class="count-up" data-target="<?= $resolved_count ?>"><?= $resolved_count ?>+</h4>
                            <p class="small text-muted mb-0" style="font-weight: 500;">Lives Impacted</p>
                        </div>
                    </div>
                    <div class="col-md-6 reveal reveal-delay-3">
                        <div class="stat-card">
                            <h4>24/7</h4>
                            <p class="small text-muted mb-0" style="font-weight: 500;">Emergency Support</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== VISION ==================== -->
<section class="py-5 vision-section" id="vision">
    <div class="container py-5">
        <div class="text-center mb-5 reveal">
            <span class="section-eyebrow">The Future</span>
            <h2 class="section-title">Our Vision &amp; Mission</h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <div class="row g-4">
            <div class="col-md-4 reveal reveal-delay-1">
                <div class="vision-box">
                    <div class="vision-icon"><i class="fas fa-eye"></i></div>
                    <h4>The Vision</h4>
                    <p class="text-muted mb-0" style="line-height: 1.8; font-size: 0.92rem;">To build a society where every animal in Bangladesh coexists with dignity, safety, and the love they deserve.</p>
                </div>
            </div>
            <div class="col-md-4 reveal reveal-delay-2">
                <div class="vision-box">
                    <div class="vision-icon"><i class="fas fa-bullseye"></i></div>
                    <h4>The Mission</h4>
                    <p class="text-muted mb-0" style="line-height: 1.8; font-size: 0.92rem;">Saving lives through critical midnight rescues, providing high-quality medical care, and finding forever homes for the voiceless.</p>
                </div>
            </div>
            <div class="col-md-4 reveal reveal-delay-3">
                <div class="vision-box">
                    <div class="vision-icon"><i class="fas fa-heartbeat"></i></div>
                    <h4>Our Values</h4>
                    <p class="text-muted mb-0" style="line-height: 1.8; font-size: 0.92rem;">Compassion without borders, transparency in every rescue, and a commitment to animal rights advocacy across the nation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== GALLERY ==================== -->
<section class="py-5 gallery-section" id="gallery">
    <div class="container py-5">
        <div class="text-center mb-5 reveal">
            <span class="section-eyebrow">Our Sanctuary</span>
            <h2 class="section-title">Gallery of Hope</h2>
            <div class="section-divider mx-auto"></div>
        </div>

        <div class="row g-4" id="gallery-row">
            <?php
            $gal_res = mysqli_query($conn, "SELECT * FROM gallery WHERE status='active' ORDER BY id DESC");
            $total_gallery = mysqli_num_rows($gal_res);

            if ($total_gallery > 0):
                $i = 0;
                while ($img = mysqli_fetch_assoc($gal_res)):
                    $clean_filename = basename($img['image_path']);
                    $display_path   = "uploads/gallery/" . $clean_filename;
                    $caption        = !empty($img['caption']) ? htmlspecialchars($img['caption']) : "Heartbeat Heaven Moment";
                    $delay          = ($i % 3) + 1;
                    // First 6 visible, rest hidden
                    $extra_class    = ($i >= 6) ? 'gallery-extra' : '';
            ?>
                <div class="col-md-4 reveal reveal-delay-<?= $delay ?> <?= $extra_class ?>">
                    <div class="gallery-item expand-trigger"
                         data-src="<?= $display_path ?>"
                         data-cap="<?= $caption ?>">
                        <img src="<?= $display_path ?>" alt="Gallery Image"
                             onerror="this.src='https://via.placeholder.com/400x280?text=Image+Not+Found'">
                        <div class="gallery-overlay">
                            <div class="gallery-overlay-inner">
                                <i class="fas fa-expand-alt"></i>
                                <p class="small m-0 fw-bold text-uppercase text-white" style="letter-spacing: 1.5px; font-size: 0.7rem;"><?= $caption ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php $i++; endwhile;
            else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted fst-italic">No moments captured yet. 🐾</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_gallery > 6): ?>
        <div class="text-center mt-5" id="gallery-btn-wrap">
            <button class="btn-see-more" id="btn-gallery-more">
                See More Photos <i class="fas fa-chevron-down ms-2"></i>
            </button>
            <button class="btn-see-less" id="btn-gallery-less">
                See Less <i class="fas fa-chevron-up ms-2"></i>
            </button>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- ==================== QUOTE ==================== -->
<section class="quote-container text-white" id="quote">
    <div class="quote-orb quote-orb-1"></div>
    <div class="quote-orb quote-orb-2"></div>
    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div id="quote-fader" class="w-100">
                    <?php
                    $q_res = mysqli_query($conn, "SELECT * FROM site_quotes WHERE status='active'");
                    $first = true;
                    if (mysqli_num_rows($q_res) > 0):
                        while ($q = mysqli_fetch_assoc($q_res)): ?>
                            <div class="fade-quote <?= $first ? 'active' : '' ?>">
                                <h2 class="quote-text mb-4">"<?= htmlspecialchars($q['quote_text']) ?>"</h2>
                                <hr class="w-25 mx-auto border-warning border-2 opacity-100 mb-4">
                                <h6 class="text-uppercase fw-bold text-warning" style="letter-spacing: 3px; font-size: 0.7rem;">
                                    — <?= htmlspecialchars($q['author_name']) ?>
                                </h6>
                            </div>
                        <?php $first = false; endwhile;
                    else: ?>
                        <div class="fade-quote active">
                            <h2 class="quote-text mb-4">"Kindness to animals is a virtue."</h2>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== TESTIMONIALS ==================== -->
<section class="py-5" id="testimonials" style="background: #f8f9fa;">
    <div class="container py-5">
        <div class="text-center mb-5 reveal">
            <span class="section-eyebrow">Testimonials</span>
            <h2 class="section-title">Voice of the Community</h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <div class="row g-4" id="testimonials-row">
            <?php
            
$query = "SELECT * FROM testimonials WHERE status='approved' ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $total_test = mysqli_num_rows($result);
            $ti = 0;
            while ($row = mysqli_fetch_assoc($result)):
                $td          = ($ti % 2) + 1;
                $extra_class = ($ti >= 2) ? 'testimonial-extra' : '';
            ?>
                <div class="col-md-4 reveal reveal-delay-<?= $td ?> <?= $extra_class ?>">
                    <div class="card p-4 h-100 testimonial-card border-0">
                        <div class="text-warning mb-3" style="font-size: 0.85rem;">
                            <?php for ($s = 1; $s <= 5; $s++) echo ($s <= $row['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                        </div>
                        <p class="text-muted fst-italic mb-4" style="font-size: 0.9rem; line-height: 1.8;">"<?= htmlspecialchars($row['message']) ?>"</p>
                        <div class="d-flex align-items-center mt-auto">
                            <div class="avatar-ring me-3 text-dark fw-bold" style="font-size: 0.85rem;">
                                <?= strtoupper(substr($row['user_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h6 class="mb-0" style="color: var(--midnight); font-size: 0.88rem; font-weight: 600;"><?= htmlspecialchars($row['user_name']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($row['user_role']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php $ti++; endwhile; ?>

            <!-- Review form — always last -->
            <div class="col-md-4 reveal reveal-delay-3">
    <div class="review-dark-card">
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Logged-in: show form -->
            <h6 class="text-warning mb-3 fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;">Share Your Story</h6>
            <div class="star-rating mb-3 text-center" style="font-size: 1.4rem; color: #ffc107; cursor: pointer;">
                <i class="far fa-star star-btn" data-index="1"></i>
                <i class="far fa-star star-btn" data-index="2"></i>
                <i class="far fa-star star-btn" data-index="3"></i>
                <i class="far fa-star star-btn" data-index="4"></i>
                <i class="far fa-star star-btn" data-index="5"></i>
                <input type="hidden" id="selected-rating" value="0">
            </div>
            <input type="text" id="rev-name" class="form-control form-control-sm rev-input mb-2" placeholder="Your Name">
            <input type="text" id="rev-role" class="form-control form-control-sm rev-input mb-2" placeholder="Identity (e.g. Adopter)">
            <textarea id="rev-text" class="form-control form-control-sm rev-input mb-3" rows="3" placeholder="Tell us your story..."></textarea>
            <button class="btn btn-warning btn-sm w-100 fw-bold py-2" id="btn-post-review"
                    style="border-radius: 8px; letter-spacing: 0.5px;">
                Post Review 🐾
            </button>

        <?php else: ?>
            <!-- Guest: show login prompt -->
            <div class="text-center py-4">
                <i class="fas fa-lock fa-2x text-warning mb-3 d-block"></i>
                <h6 class="text-white fw-bold mb-2">Want to Share Your Story?</h6>
                <p class="text-muted small mb-4">You need to be logged in to leave a review.</p>
                <a href="login.php" class="btn btn-warning btn-sm fw-bold px-4 py-2" style="border-radius: 8px;">
                    Login to Review 🐾
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
        </div>

        <?php if ($total_test > 2): ?>
        <div class="text-center mt-5" id="test-btn-wrap">
            <button class="btn-see-more" id="btn-test-more">
                See More Reviews <i class="fas fa-chevron-down ms-2"></i>
            </button>
            <button class="btn-see-less" id="btn-test-less">
                See Less <i class="fas fa-chevron-up ms-2"></i>
            </button>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- ==================== RESCUE MODAL ==================== -->
<div class="modal fade" id="rescueModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white fw-bold py-3">
                <h5 class="modal-title"><i class="fas fa-bullhorn me-2"></i>Emergency Rescue SOS</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="rescueSOS" method="POST" action="process_sos.php" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold">Animal Species</label>
                            <input type="text" name="species" class="form-control" placeholder="Cat, Dog, Bird etc." required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold text-danger">
                                <i class="fas fa-notes-medical me-1"></i> Situation Description
                            </label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Describe the animal's condition (e.g., bleeding, unconscious, unable to walk)"
                                required></textarea>
                            <div class="form-text small" style="font-size: 0.7rem;">This info helps AI prioritize critical cases.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold">Detailed Location</label>
                            <textarea name="location" class="form-control" rows="2" placeholder="Enter landmark or street address" required></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold">Upload Photo/Video <span class="text-danger">*</span></label>
                            <input type="file" name="rescue_media" class="form-control" accept="image/*,video/*" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="contact_phone" class="form-control" placeholder="+880 1XXXXXXXXX" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold">Email <span class="text-optional text-muted">(optional)</span></label>
                            <input type="email" name="contact_email" class="form-control" placeholder="you@example.com">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 py-3 fw-bold shadow-sm" style="border-radius: 12px; letter-spacing: 1px;">
                        <i class="fas fa-paper-plane me-2"></i>SEND EMERGENCY SOS
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {

    // ==================== TYPING EFFECT ====================
    const phrases = ['Deserves Love.', 'Needs a Home.', 'Matters to Us.'];
    let phraseIdx = 0, charIdx = 0, isDeleting = false;
    const target = document.getElementById('typingTarget');

    function typeLoop() {
        const current = phrases[phraseIdx];
        if (!isDeleting) {
            target.textContent = current.slice(0, ++charIdx);
            if (charIdx === current.length) {
                target.classList.add('done');
                setTimeout(() => { isDeleting = true; target.classList.remove('done'); typeLoop(); }, 2200);
                return;
            }
        } else {
            target.textContent = current.slice(0, --charIdx);
            if (charIdx === 0) {
                isDeleting = false;
                phraseIdx = (phraseIdx + 1) % phrases.length;
            }
        }
        setTimeout(typeLoop, isDeleting ? 55 : 80);
    }
    setTimeout(typeLoop, 900);
// ==================== REVIEW FORM ENTER KEY NAV ====================
const reviewFields = ['#rev-name', '#rev-role', '#rev-text'];

reviewFields.forEach(function (selector, index) {
    $(selector).on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var next = reviewFields[index + 1];
            if (next) {
                $(next).focus();
            } else {
                // Last field (textarea) — trigger post review
                $('#btn-post-review').click();
            }
        }
    });
});
    // ==================== PARTICLE CANVAS ====================
    const canvas = document.getElementById('particle-canvas');
    const ctx    = canvas.getContext('2d');
    let W, H, particles = [];

    function resizeCanvas() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }
    resizeCanvas();
    $(window).on('resize', resizeCanvas);

    for (let i = 0; i < 55; i++) {
        particles.push({
            x: Math.random() * W, y: Math.random() * H,
            r: Math.random() * 1.5 + 0.3,
            vx: (Math.random() - 0.5) * 0.25,
            vy: (Math.random() - 0.5) * 0.25,
            a: Math.random() * 0.5 + 0.15
        });
    }

    function drawParticles() {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255,193,7,${p.a})`;
            ctx.fill();
            p.x += p.vx; p.y += p.vy;
            if (p.x < 0) p.x = W;
            if (p.x > W) p.x = 0;
            if (p.y < 0) p.y = H;
            if (p.y > H) p.y = 0;
        });
        requestAnimationFrame(drawParticles);
    }
    drawParticles();

    // ==================== SCROLL REVEAL ====================
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

    // ==================== COUNT-UP ====================
    const countObserver = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting && !e.target.dataset.counted) {
                e.target.dataset.counted = true;
                const tgt  = parseInt(e.target.dataset.target);
                let current = 0;
                const step  = Math.ceil(tgt / 60);
                const interval = setInterval(() => {
                    current = Math.min(current + step, tgt);
                    e.target.textContent = current.toLocaleString() + '+';
                    if (current >= tgt) clearInterval(interval);
                }, 25);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

    // ==================== LIGHTBOX ====================
    $('.expand-trigger').on('click', function () {
        $('#lbImg').attr('src', $(this).data('src'));
        var cap = $(this).data('cap');
        cap ? $('#lbCap').text(cap).show() : $('#lbCap').hide();
        $('#lbModal').css('display', 'flex').hide().fadeIn(300);
        setTimeout(() => $('#lbModal').addClass('show'), 50);
    });

    $('.lb-close, #lbModal').on('click', function () {
        $('#lbModal').removeClass('show').fadeOut(300);
    });

    $('.lb-img, .lb-caption').on('click', function (e) { e.stopPropagation(); });

    // ==================== GALLERY SEE MORE / SEE LESS ====================
    // ==================== GALLERY SEE MORE / SEE LESS ====================
    $('#btn-gallery-more').on('click', function () {
        // Add .shown to switch display:none!important -> display:block!important
        // Then animate opacity via jQuery
        $('.gallery-extra').addClass('shown').css('opacity', 0).animate({ opacity: 1 }, 400);
        $('.gallery-extra .reveal').addClass('visible');
        $('#btn-gallery-more').fadeOut(200, function () {
            $('#btn-gallery-less').fadeIn(200);
        });
    });

    $('#btn-gallery-less').on('click', function () {
        $('.gallery-extra').animate({ opacity: 0 }, 300, function () {
            $(this).removeClass('shown').css('opacity', '');
        });
        $('#btn-gallery-less').fadeOut(200, function () {
            $('#btn-gallery-more').fadeIn(200);
        });
        $('html, body').animate({ scrollTop: $('#gallery').offset().top - 80 }, 400);
    });

    // ==================== TESTIMONIALS SEE MORE / SEE LESS ====================
    $('#btn-test-more').on('click', function () {
        $('.testimonial-extra').each(function () {
            $(this).addClass('shown').hide().fadeIn(400);
            $(this).find('.reveal').addClass('visible');
        });
        $('#btn-test-more').fadeOut(200, function () {
            $('#btn-test-less').fadeIn(200);
        });
    });

    $('#btn-test-less').on('click', function () {
        $('.testimonial-extra').fadeOut(300, function () {
            $(this).removeClass('shown');
        });
        $('#btn-test-less').fadeOut(200, function () {
            $('#btn-test-more').fadeIn(200);
        });
        $('html, body').animate({ scrollTop: $('#testimonials').offset().top - 80 }, 400);
    });

    // ==================== QUOTE FADER ====================
    var quotes = $('.fade-quote');
    var currentQuote = 0;
    if (quotes.length > 1) {
        setInterval(function () {
            quotes.eq(currentQuote).fadeOut(500, function () {
                $(this).removeClass('active');
                currentQuote = (currentQuote + 1) % quotes.length;
                quotes.eq(currentQuote).addClass('active').fadeIn(500);
            });
        }, 6000);
    }

    // ==================== STAR RATING ====================
    $('.star-btn').on('click', function () {
        var rating = $(this).data('index');
        $('#selected-rating').val(rating);
        $('.star-btn').removeClass('fas').addClass('far');
        $('.star-btn').each(function () {
            if ($(this).data('index') <= rating) $(this).removeClass('far').addClass('fas');
        });
    });

    $('.star-btn').on('mouseenter', function () {
        const hov = $(this).data('index');
        $('.star-btn').each(function () {
            $(this).css('color', $(this).data('index') <= hov ? '#ffc107' : '');
        });
    }).on('mouseleave', function () {
        const sel = parseInt($('#selected-rating').val());
        $('.star-btn').each(function () {
            if (sel > 0) {
                $(this).data('index') <= sel
                    ? $(this).removeClass('far').addClass('fas')
                    : $(this).removeClass('fas').addClass('far');
            }
        });
    });

    // ==================== POST REVIEW ====================
    $('#btn-post-review').on('click', function () {
        var btn  = $(this);
        var data = {
            name:    $('#rev-name').val(),
            role:    $('#rev-role').val(),
            message: $('#rev-text').val(),
            rating:  $('#selected-rating').val()
        };
        if (data.name && data.message && data.rating > 0) {
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Posting...');
            $.ajax({
                url: 'save_review.php',
                method: 'POST',
                data: data,
               success: function (response) {
    response = response.trim();
    if (response === 'success') {
        alert('Thanks! Your review is pending approval.');
        location.reload();
    } else if (response === 'login_required') {
        alert('Please log in to post a review.');
        window.location.href = 'login.php';
    } else if (response === 'already_reviewed') {
        alert('You have already submitted a review. Thank you!');
        btn.prop('disabled', false).html('Post Review 🐾');
    } else {
        alert('Something went wrong. Please try again.');
        btn.prop('disabled', false).html('Post Review 🐾');
    }
}
            });
        } else {
            alert('Please fill in all fields and provide a star rating.');
        }
    });

    $('#btn-post-review').on('mouseenter', function () {
        $(this).css({ transform: 'translateY(-2px)', 'box-shadow': '0 8px 20px rgba(255,193,7,0.35)' });
    }).on('mouseleave', function () {
        $(this).css({ transform: '', 'box-shadow': '' });
    });

});
</script>

</body>
</html>