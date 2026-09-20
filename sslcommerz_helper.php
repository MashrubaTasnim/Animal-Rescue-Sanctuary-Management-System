<?php
// sslcommerz_helper.php — shared SSLCommerz settings + payment validation
//
// Runs in SANDBOX (test) mode by default. For real payments:
//   1. set SSLC_SANDBOX to false
//   2. put your live store id / password below
//   3. change SSLC_BASE_URL to your public HTTPS address

define('SSLC_SANDBOX',    true);
define('SSLC_STORE_ID',   'testbox');   // sandbox test store
define('SSLC_STORE_PASS', 'qwerty');    // sandbox test password
define('SSLC_BASE_URL',   'http://localhost/Moonlight_of_Heaven/');
define('SSLC_HOST', SSLC_SANDBOX ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com');

/**
 * Ask SSLCommerz directly whether a payment is real.
 * Returns SSLCommerz's answer as an array when the payment is valid AND the
 * transaction id and amount match our own record, otherwise null.
 */
function sslc_validate(string $val_id, string $expected_tran_id, float $expected_amount): ?array
{
    if ($val_id === '' || $expected_tran_id === '') {
        return null;
    }

    $url = SSLC_HOST . '/validator/api/validationserverAPI.php?' . http_build_query([
        'val_id'       => $val_id,
        'store_id'     => SSLC_STORE_ID,
        'store_passwd' => SSLC_STORE_PASS,
        'format'       => 'json',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        // XAMPP often has no CA bundle, so sandbox skips the check; live mode keeps it strict.
        CURLOPT_SSL_VERIFYPEER => !SSLC_SANDBOX,
        CURLOPT_SSL_VERIFYHOST => SSLC_SANDBOX ? 0 : 2,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    if ($body === false) {
        return null;
    }
    $r = json_decode($body, true);
    if (!is_array($r)) {
        return null;
    }

    if (!in_array($r['status'] ?? '', ['VALID', 'VALIDATED'], true)) return null;
    if (($r['tran_id'] ?? '') !== $expected_tran_id)                  return null;
    if (abs((float)($r['amount'] ?? 0) - $expected_amount) > 0.01)    return null;

    return $r;
}

/**
 * The gateway redirect drops the login cookie, so after a payment has been
 * CONFIRMED by sslc_validate(), log the payer back in.
 * Only call this with a user id taken from our own database record.
 */
function sslc_restore_session(mysqli $conn, int $user_id): void
{
    if (isset($_SESSION['user_id'])) {
        return; // already logged in
    }
    $stmt = $conn->prepare("SELECT id, full_name, email, role, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || ($row['status'] ?? '') === 'restricted') {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['user_id']   = $row['id'];
    $_SESSION['full_name'] = $row['full_name'];
    $_SESSION['name']      = $row['full_name'];
    $_SESSION['email']     = $row['email'];
    $_SESSION['role']      = $row['role'];
}
