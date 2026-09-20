<?php
// finance_auto_hooks.php
// Include this file wherever vet/rescuer save their records
// It provides helper functions to auto-log expenses and income

/**
 * Auto-log a medical expense when a vet saves a treatment.
 *
 * @param mysqli $conn
 * @param int    $animal_id   - The animal treated
 * @param float  $cost        - Treatment cost
 * @param string $clinic_name - Vet clinic or doctor name
 * @param string $description - Brief treatment description
 * @param int    $logged_by   - Vet's user ID
 */
function autoLogMedicalExpense($conn, $rescue_id, $cost, $clinic_name, $description = '', $logged_by = null) {
    if ($cost <= 0) return false;

    $rescue_id   = intval($rescue_id);
    $cost        = floatval($cost);
    $clinic_name = $conn->real_escape_string($clinic_name);
    $description = $conn->real_escape_string($description);
    $today       = date('Y-m-d');
    $created_by  = $logged_by ? intval($logged_by) : 'NULL';

    // ── Link to rescue, NOT animals table ──────────────────
    $sql = "INSERT INTO sanctuary_expenses
                (category, amount, date_paid, vendor_name_or_payee, rescue_id, notes, created_by)
            VALUES
                ('Medical', $cost, '$today', '$clinic_name', $rescue_id, '$description', $created_by)";

    return $conn->query($sql);
}

/**
 * Auto-log a rescue ops expense when a rescuer logs field costs.
 *
 * @param mysqli $conn
 * @param int    $rescue_id   - The rescue mission ID
 * @param float  $cost        - Operational cost (fuel, transport, etc.)
 * @param string $description - e.g. "Fuel for rescue van", "Trap rental"
 * @param int    $logged_by   - Rescuer's user ID
 */
function autoLogRescueExpense($conn, $rescue_id, $cost, $description = 'Field rescue costs', $logged_by = null) {
    if ($cost <= 0) return false;

    $rescue_id   = intval($rescue_id);
    $cost        = floatval($cost);
    $description = $conn->real_escape_string($description);
    $today       = date('Y-m-d');
    $created_by  = $logged_by ? intval($logged_by) : 'NULL';

    // Get rescuer name to use as payee
    $payee = 'Field Operations';
    if ($logged_by) {
        $r = $conn->query("SELECT full_name FROM users WHERE id=" . intval($logged_by));
        if ($r && $row = $r->fetch_assoc()) {
            $payee = $conn->real_escape_string('Rescuer: ' . $row['full_name']);
        }
    }

    $sql = "INSERT INTO sanctuary_expenses
                (category, amount, date_paid, vendor_name_or_payee, rescue_id, notes, created_by)
            VALUES
                ('Rescue Ops', $cost, '$today', '$payee', $rescue_id, '$description', $created_by)";

    return $conn->query($sql);
}

/**
 * Auto-log a General Donation from the donations table when approved.
 * Call this when admin approves a donation (status -> 'Approved').
 *
 * @param mysqli $conn
 * @param int    $donation_id
 */
function autoSyncDonation($conn, $donation_id) {
    $donation_id = intval($donation_id);

    // Prevent duplicate entries
    $check = $conn->query("SELECT id FROM funding_income WHERE donation_id = $donation_id");
    if ($check && $check->num_rows > 0) return false;

    $r = $conn->query("
        SELECT d.*, u.full_name
        FROM donations d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.id = $donation_id AND d.status = 'Approved'
    ");
    if (!$r || $r->num_rows === 0) return false;

    $d    = $r->fetch_assoc();
    $name = $conn->real_escape_string($d['full_name'] ?? 'Anonymous');
    $date = date('Y-m-d', strtotime($d['created_at']));
    $note = $conn->real_escape_string('Auto-synced on donation approval. Method: ' . $d['method']);

    $sql = "INSERT INTO funding_income
                (source_type, amount, date_received, source_name, donation_id, notes)
            VALUES
                ('General Donation', {$d['amount']}, '$date', '$name', $donation_id, '$note')";

    return $conn->query($sql);
}

/**
 * Auto-log a Surrender Contribution to funding_income when admin marks
 * a surrender as Received after the physical visit.
 *
 * IMPORTANT: Only call this AFTER the pet parent visits and pays.
 * Do NOT call this on form submission or appointment scheduling.
 *
 * @param mysqli $conn
 * @param int    $surrender_id        - surrender_requests.id
 * @param float  $contribution_amount - Actual amount collected on visit (NOT the pledged amount)
 * @param int    $admin_id            - Admin's user ID (from $_SESSION['user_id'])
 */
function autoSyncSurrenderContribution($conn, $surrender_id, $contribution_amount, $admin_id = null) {
    $surrender_id        = intval($surrender_id);
    $contribution_amount = floatval($contribution_amount);

    // Guard: nothing to log if amount is zero or negative
    if ($contribution_amount <= 0) return false;

    // Prevent duplicate entries for the same surrender
    $check = $conn->query("SELECT id FROM funding_income WHERE surrender_id = $surrender_id");
    if ($check && $check->num_rows > 0) return false;

    // Fetch surrender + pet parent name
    $r = $conn->query("
        SELECT sr.*, u.full_name
        FROM surrender_requests sr
        LEFT JOIN users u ON sr.user_id = u.id
        WHERE sr.id = $surrender_id AND sr.status = 'Received'
    ");
    if (!$r || $r->num_rows === 0) return false;

    $sr         = $r->fetch_assoc();
    $name       = $conn->real_escape_string($sr['full_name'] ?? 'Anonymous');
    $species    = ucfirst($sr['species'] ?? '');
    $breed      = !empty($sr['breed']) ? ' (' . $sr['breed'] . ')' : '';
    $today      = date('Y-m-d');
    $created_by = $admin_id ? intval($admin_id) : 'NULL';

    $note = $conn->real_escape_string(
        'Surrender contribution collected on physical visit. ' .
        'Surrender Request #' . $surrender_id . '. ' .
        'Pet: ' . $species . $breed . '. ' .
        'Submitted by: ' . ($sr['full_name'] ?? 'Unknown') . '.'
    );

    $sql = "INSERT INTO funding_income
                (source_type, amount, date_received, source_name, surrender_id, notes, created_by)
            VALUES
                ('Surrender Contribution', $contribution_amount, '$today', '$name',
                 $surrender_id, '$note', $created_by)";

    return $conn->query($sql);
}
?>