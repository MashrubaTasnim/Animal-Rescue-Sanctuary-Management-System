<?php
ini_set('session.cookie_samesite', 'Lax'); 
session_start();
require_once 'auth_helper.php';

// The payment gateway sends people back here after paying (or failing).
// Show the result even if their login cookie was dropped on the way back.
$paymentDone = isset($_GET['payment']) && in_array($_GET['payment'], ['success', 'failed'], true);

if (!$paymentDone) {
    require_login('Please login or register to donate and support our mission.');
}

if (!$paymentDone && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_config.php';
include 'navbar.php'; 

$paymentStatus = isset($_GET['payment']) ? $_GET['payment'] : '';
$amount        = isset($_GET['amt'])     ? $_GET['amt']     : '';
$method        = isset($_GET['method'])  ? htmlspecialchars($_GET['method']) : '';
$user_name     = isset($_SESSION['name']) ? $_SESSION['name'] : "Guardian";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donate | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --midnight-blue: #0a1329;
            --gold-accent: #d4af37;
        }

        .hub-header {
            background: linear-gradient(rgba(10, 19, 41, 0.85), rgba(10, 19, 41, 0.85)), 
                        url('https://images.unsplash.com/photo-1532629345422-7515f3d16bb6?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            margin-bottom: -60px;
        }

        .donation-card {
            border: none;
            border-radius: 25px;
            background: #ffffff;
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            border-bottom: 3px solid transparent;
        }

        .donation-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(10, 19, 41, 0.15) !important;
            border-bottom: 5px solid var(--midnight-blue);
        }

        .icon-circle { 
            width: 80px; 
            height: 80px; 
            border-radius: 20px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 25px; 
            font-size: 2rem;
            background: #eefdf8;
            color: #198754;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
            color: var(--midnight-blue);
            font-weight: bold;
        }

        .form-control-lg {
            border-left: none;
            background-color: #f8f9fa;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .btn-pay {
            background-color: var(--midnight-blue);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 15px;
            font-size: 1.1rem;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .btn-pay:hover {
            background-color: #142142;
            color: var(--gold-accent);
            box-shadow: 0 5px 15px rgba(10, 19, 41, 0.3);
        }

        .ssl-banner {
            max-height: 30px;
            opacity: 0.7;
            filter: grayscale(1);
            transition: 0.3s;
        }

        .ssl-banner:hover {
            filter: grayscale(0);
            opacity: 1;
        }
    </style>
</head>
<body style="background-color: #f6f8fb;">

<header class="hub-header text-center">
    <div class="container">
        <span class="badge bg-success mb-3 px-3 py-2" style="border-radius: 50px;">Secure Payment Portal</span>
        <h2 class="display-4 fw-bold mb-3">Support Our Mission</h2>
        <p class="lead opacity-75">Your contribution provides food, medicine, and shelter for those in need.</p>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card donation-card p-5 shadow-sm">
                <div class="text-center">
                    <div class="icon-circle shadow-sm">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h3 class="fw-bold mb-2">Donate to Heartbeat Heaven</h3>
                    <p class="text-muted small mb-4">Fast, secure, and automated via SSLCommerz</p>
                </div>

                <form action="ssl_pay.php" method="POST" class="mt-2">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted" style="letter-spacing: 1px;">Amount in BDT</label>
                        <div class="input-group input-group-lg shadow-sm" style="border-radius: 15px; overflow: hidden;">
                            <span class="input-group-text">৳</span>
                            <input type="number" name="amount" class="form-control form-control-lg" placeholder="500" required min="10">
                        </div>
                        <div class="form-text text-center mt-2 italic small">Minimum donation amount is 10 BDT</div>
                    </div>
                    <button type="submit" class="btn btn-pay w-100 fw-bold">
                        <i class="fas fa-shield-alt me-2"></i> PROCEED TO PAYMENT
                    </button>
                </form>

                <div class="text-center mt-4 pt-4 border-top">
                    <p class="small text-muted mb-3">Supported Payment Methods</p>
                    <div class="d-flex justify-content-center align-items-center gap-3 flex-wrap">
                        <img src="https://securepay.sslcommerz.com/gw/asset/img/sslcommerz-banner.png" class="ssl-banner img-fluid" alt="SSLCommerz">
                    </div>
                    <div class="mt-3 text-muted small">
                        <i class="fas fa-lock me-1"></i> 256-bit SSL Encrypted Connection
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <p class="text-muted fst-italic">"Helping one animal might not change the world, but for that one animal, the world will change forever."</p>
            </div>
        </div>
    </div>
</div>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- 
  PASTE THIS INTO donate.php REPLACING YOUR EXISTING <script> BLOCK
  (the one with the Swal.fire success handler)
-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const status  = "<?php echo $paymentStatus; ?>";
    const amount  = "<?php echo $amount; ?>";
    const method  = "<?php echo $method; ?>";
    const receipt = "<?php echo isset($_GET['receipt']) ? '1' : ''; ?>";
    const trx     = "<?php echo isset($_GET['trx']) ? htmlspecialchars($_GET['trx']) : ''; ?>";

    if (status === 'success') {
        setTimeout(() => {

            // Build the receipt button HTML only if a receipt was generated
            const receiptBtn = receipt === '1' && trx
                ? `<br><br><a href="generate_receipt.php?trx_id=${encodeURIComponent(trx)}"
                        class="btn btn-sm mt-2"
                        style="background:#0a1329;color:#d4af37;border-radius:10px;padding:8px 20px;text-decoration:none;">
                        ⬇ Download Receipt (PDF)
                   </a>`
                : '';

            Swal.fire({
                title: '<span style="color:#0a1329">Donation Successful!</span>',
                html: `<b>Thank you!</b><br>We received your contribution of <b>৳${amount}</b> via <b>${method}</b>.<br>The animals of Heartbeat Heaven are grateful.${receiptBtn}`,
                icon: 'success',
                confirmButtonColor: '#0a1329',
                confirmButtonText: 'You are welcome!'
            }).then(() => {
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({path: cleanUrl}, '', cleanUrl);
            });

        }, 500);

    } else if (status === 'failed') {
        Swal.fire({
            title: 'Payment Failed',
            text: 'The transaction could not be completed. Please try again.',
            icon: 'error',
            confirmButtonColor: '#d33'
        });
    }
});
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>