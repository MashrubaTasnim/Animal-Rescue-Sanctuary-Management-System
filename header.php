<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_start(); 
include 'db_config.php'; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Heartbeat Heaven</title>

    <!-- ✅ BOOTSTRAP (ONLY ONCE - MUST BE SAME EVERY PAGE) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- ✅ FONT AWESOME -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- ✅ GOOGLE FONTS (GLOBAL ONLY) -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- ✅ GLOBAL SAFETY FIX (IMPORTANT FOR YOUR ISSUE) -->
    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Montserrat', sans-serif;
            background: #0a1329;
            color: #fff;
        }

        /* Prevent font override between pages */
        * {
            box-sizing: border-box;
        }

        body, input, button, textarea, select {
            font-family: 'Montserrat', sans-serif !important;
        }

        /* Keep headings consistent */
        h1,h2,h3,h4,h5,h6 {
            font-family: 'Cinzel', serif;
        }

        /* Prevent navbar shrink / drift */
        .navbar {
            font-family: inherit !important;
        }

        /* FIX: page shifting under fixed navbar */
        .page-content {
            padding-top: 100px;
        }

        /* FIX: Bootstrap container inconsistency */
        .container, .container-fluid {
            max-width: 100%;
        }

        a {
            text-decoration: none;
        }
    </style>
</head>

<body>

<!-- ✅ IMPORTANT WRAPPER FOR ALL PAGES -->
<div class="page-content">