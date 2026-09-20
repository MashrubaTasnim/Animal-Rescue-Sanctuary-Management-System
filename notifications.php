<?php
// notifications.php — All Email Notification Triggers
require_once __DIR__ . '/mailer.php';

// ============================================================
// 1. SOS RESCUE NOTIFICATION
// ============================================================
function notify_sos(?int $rescue_id, string $status): void {
    if (!$rescue_id || setting('notify_sos', '1') !== '1') return;

    global $conn;
    $stmt = $conn->prepare("
        SELECT r.species, r.location, r.description, u.full_name, u.email 
        FROM rescues r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $rescue_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || empty($data['email'])) return;

    $is_approved = strtolower($status) === 'approved';
    $status_label = $is_approved ? 'Approved ✅' : 'Rejected ❌';
    $status_class = $is_approved ? 'status-approved' : 'status-rejected';
    $site_name    = setting('site_name', 'Heartbeat Heaven');

    $subject = "$site_name — Your Rescue Request has been " . ($is_approved ? 'Approved' : 'Rejected');

    $body = "
        <h2>Hello, {$data['full_name']}!</h2>
        <p>Your rescue request has been reviewed by our team.</p>
        <div style='padding:15px; border-radius:10px; margin:10px 0; background:" . ($is_approved ? '#d4edda' : '#f8d7da') . "; color:" . ($is_approved ? '#155724' : '#721c24') . ";'>
            <strong>Status: $status_label</strong>
        </div>
        <p><strong>Animal:</strong> {$data['species']}</p>
        <p><strong>Location:</strong> {$data['location']}</p>
        " . ($data['description'] ? "<p><strong>Your Report:</strong> {$data['description']}</p>" : "") . "
        " . ($is_approved
            ? "<p>Our rescue team has been notified and will be on their way shortly. Thank you for helping save a life! 🐾</p>"
            : "<p>Unfortunately we were unable to process this request at this time.</p>"
        ) . "
        <br><a href='index.php' style='background:#ffefc2; color:#0a1329; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>Visit $site_name</a>";

    send_email($data['email'], $data['full_name'], $subject, $body);
}

// ============================================================
// 2. ADOPTION NOTIFICATION
// ============================================================
function notify_adoption(?int $adoption_id, string $status): void {
    if (!$adoption_id || setting('notify_adoption', '1') !== '1') return;

    global $conn;
    $stmt = $conn->prepare("
        SELECT ar.admin_note, a.name as animal_name, a.species, a.breed,
                u.full_name, u.email
        FROM adoption_requests ar
        LEFT JOIN animals a ON ar.animal_id = a.id
        LEFT JOIN users u ON ar.user_id = u.id
        WHERE ar.id = ?
    ");
    $stmt->bind_param("i", $adoption_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || empty($data['email'])) return;

    $is_approved  = strtolower($status) === 'approved';
    $status_label = $is_approved ? 'Approved ✅' : 'Rejected ❌';
    $site_name    = setting('site_name', 'Heartbeat Heaven');
    $animal       = $data['animal_name'] . ' (' . $data['species'] . ($data['breed'] ? ', ' . $data['breed'] : '') . ')';

    $subject = "$site_name — Adoption Update for {$data['animal_name']}";

    $body = "
        <h2>Hello, {$data['full_name']}!</h2>
        <p>Update for application: <strong>$animal</strong></p>
        <div style='padding:15px; border-radius:10px; margin:10px 0; background:" . ($is_approved ? '#d4edda' : '#f8d7da') . ";'>
            <strong>Status: $status_label</strong>
        </div>
        " . ($is_approved
            ? "<p>Congratulations! 🎉 Your adoption has been approved. Please visit us to bring your new family member home.</p>"
            : "<p>We're sorry, your request was not approved at this time.</p>"
        ) . "
        " . (!empty($data['admin_note']) ? "<p><strong>Note from Admin:</strong> {$data['admin_note']}</p>" : "") . "
        <br><a href='animals.php' style='background:#ffefc2; color:#0a1329; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>View More Animals</a>";

    send_email($data['email'], $data['full_name'], $subject, $body);
}

// ============================================================
// 3. JOB APPLICATION NOTIFICATION
// ============================================================
function notify_job_application(?int $application_id, string $status): void {
    if (!$application_id || setting('notify_jobs', '1') !== '1') return;

    global $conn;
    $stmt = $conn->prepare("
        SELECT ja.full_name, ja.email, v.title as job_title, v.category
        FROM job_applications ja
        LEFT JOIN vacancies v ON ja.job_id = v.id
        WHERE ja.id = ?
    ");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || empty($data['email'])) return;

    $is_accepted  = strtolower($status) === 'accepted';
    $status_label = $is_accepted ? 'Accepted ✅' : 'Rejected ❌';
    $site_name    = setting('site_name', 'Heartbeat Heaven');

    $subject = "$site_name — Job Application Update";

    $body = "
        <h2>Hello, {$data['full_name']}!</h2>
        <p>Position: <strong>{$data['job_title']}</strong></p>
        <div style='padding:15px; border-radius:10px; margin:10px 0; background:" . ($is_accepted ? '#d4edda' : '#f8d7da') . ";'>
            <strong>Status: $status_label</strong>
        </div>
        " . ($is_accepted
            ? "<p>Congratulations! 🎉 We're thrilled to have you join the team. We will be in touch shortly.</p>"
            : "<p>Thank you for your interest. Unfortunately, we won't be moving forward at this time.</p>"
        ) . "
        <br><a href='vacancies.php' style='background:#ffefc2; color:#0a1329; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>View Open Positions</a>";

    send_email($data['email'], $data['full_name'], $subject, $body);
}

function notify_sponsorship(?int $sponsorship_id, string $status): void {
    if (!$sponsorship_id) return;

    global $conn;

    $stmt = $conn->prepare("
        SELECT rs.monthly_amount,
               a.name AS animal_name,
               a.species,
               u.full_name,
               u.email
        FROM resident_sponsorships rs
        LEFT JOIN animals a ON rs.animal_id = a.id
        LEFT JOIN users u ON rs.user_id = u.id
        WHERE rs.id = ?
    ");

    $stmt->bind_param("i", $sponsorship_id);
    $stmt->execute();

    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || empty($data['email'])) return;

    $is_approved = strtolower($status) === 'active';

    $status_label = $is_approved
        ? 'Approved ✅'
        : 'Rejected ❌';

    $site_name = setting('site_name', 'Heartbeat Heaven');

    $subject = "$site_name — Sponsorship Request Update";

    $body = "
        <h2>Hello, {$data['full_name']}!</h2>

        <p>Your sponsorship request for
        <strong>{$data['animal_name']}</strong>
        ({$data['species']})
        has been reviewed.</p>

        <div style='padding:15px;
                    border-radius:10px;
                    margin:10px 0;
                    background:" . ($is_approved ? '#d4edda' : '#f8d7da') . ";'>
            <strong>Status: {$status_label}</strong>
        </div>

        <p><strong>Monthly Amount:</strong>
        BDT {$data['monthly_amount']}</p>

        " . (
            $is_approved
            ? "<p>Thank you for supporting our sanctuary residents. Your sponsorship is now active. 🐾</p>"
            : "<p>Unfortunately, we could not approve this sponsorship request at this time.</p>"
        ) . "
    ";

    send_email(
        $data['email'],
        $data['full_name'],
        $subject,
        $body
    );
}
function notify_surrender_appointment(?int $request_id): void {
    if (!$request_id) return;

    global $conn;

    $stmt = $conn->prepare("
        SELECT sr.species,
               sr.breed,
               sr.appointment_date,
               u.full_name,
               u.email
        FROM surrender_requests sr
        LEFT JOIN users u ON sr.user_id = u.id
        WHERE sr.id = ?
    ");

    $stmt->bind_param("i", $request_id);
    $stmt->execute();

    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || empty($data['email'])) return;

    $site_name = setting('site_name', 'Heartbeat Heaven');

    $subject = "$site_name — Surrender Appointment Scheduled";

    $body = "
        <h2>Hello, {$data['full_name']}!</h2>

        <p>Your surrender request has been reviewed.</p>

        <p>
            <strong>Animal:</strong>
            {$data['species']}
            " . (!empty($data['breed']) ? "({$data['breed']})" : "") . "
        </p>

        <div style='padding:15px;
                    background:#d4edda;
                    border-radius:10px;
                    margin:10px 0;'>
            <strong>
                Appointment Date:
                {$data['appointment_date']}
            </strong>
        </div>

        <p>
            Please bring any available medical records
            and relevant information about the animal.
        </p>

        <p>
            Thank you for helping us ensure the animal's
            continued care and wellbeing.
        </p>
    ";

    send_email(
        $data['email'],
        $data['full_name'],
        $subject,
        $body
    );
}
// ============================================================
// ADOPTION APPOINTMENT NOTIFICATION
// ============================================================
function notify_adoption_appointment(?int $adoption_id): void {
    if (!$adoption_id || setting('notify_adoption', '1') !== '1') return;

    global $conn;

    $stmt = $conn->prepare("
        SELECT ar.appointment_date,
               ar.admin_note,
               a.name AS animal_name,
               a.species,
               a.breed,
               u.full_name,
               u.email
        FROM adoption_requests ar
        LEFT JOIN animals a ON ar.animal_id = a.id
        LEFT JOIN users u ON ar.user_id = u.id
        WHERE ar.id = ?
    ");

    $stmt->bind_param("i", $adoption_id);
    $stmt->execute();

    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || empty($data['email'])) return;

    $site_name = setting('site_name', 'Heartbeat Heaven');

    $animal = $data['animal_name']
        . ' (' . $data['species']
        . (!empty($data['breed']) ? ', ' . $data['breed'] : '')
        . ')';

    $subject = "$site_name — Adoption Appointment Scheduled";

    $body = "
        <h2>Hello, {$data['full_name']}!</h2>

        <p>Your adoption application has progressed to the next step.</p>

        <p>
            <strong>Animal:</strong>
            {$animal}
        </p>

        <div style='padding:15px;
                    border-radius:10px;
                    margin:10px 0;
                    background:#d4edda;
                    color:#155724;'>
            <strong>
                Appointment Scheduled ✅<br>
                Date & Time: {$data['appointment_date']}
            </strong>
        </div>

        <p>
            Please arrive on time and bring any required
            identification or supporting documents.
        </p>

        " . (!empty($data['admin_note'])
            ? "<p><strong>Note from Admin:</strong> {$data['admin_note']}</p>"
            : ""
        ) . "

        <p>
            We look forward to meeting you and helping you
            complete the adoption process.
        </p>

        <br>
        <a href='animals.php'
           style='background:#ffefc2;
                  color:#0a1329;
                  padding:10px 20px;
                  text-decoration:none;
                  border-radius:5px;
                  font-weight:bold;'>
            View More Animals
        </a>
    ";

    send_email(
        $data['email'],
        $data['full_name'],
        $subject,
        $body
    );
}