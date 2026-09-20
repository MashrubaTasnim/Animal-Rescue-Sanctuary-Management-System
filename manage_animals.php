<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}
// ── SMTP HELPER ───────────────────────────────────────────────────────────────
function getSmtpSettings($conn) {
    require_once __DIR__ . '/src/PHPMailer.php';
    require_once __DIR__ . '/src/SMTP.php';
    require_once __DIR__ . '/src/Exception.php';
    $keys = ['smtp_host','smtp_port','smtp_username','smtp_password','smtp_secure','smtp_sender_name'];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $s = $conn->prepare("SELECT setting_key, setting_val FROM system_settings WHERE setting_key IN ($placeholders)");
    $s->bind_param(str_repeat('s', count($keys)), ...$keys);
    $s->execute();
    $smtp = [];
    $res = $s->get_result();
    while ($r = $res->fetch_assoc()) $smtp[$r['setting_key']] = $r['setting_val'];
    $s->close();
    return $smtp;
}

function sendMail($conn, $to_email, $to_name, $subject, $body) {
    $smtp = getSmtpSettings($conn);
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['smtp_host']   ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['smtp_username'] ?? '';
        $mail->Password   = $smtp['smtp_password'] ?? '';
        $mail->SMTPSecure = $smtp['smtp_secure'] ?? 'tls';
        $mail->Port       = (int)($smtp['smtp_port'] ?? 587);
        $mail->setFrom($smtp['smtp_username'] ?? '', $smtp['smtp_sender_name'] ?? 'Heartbeat Heaven');
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function emailTemplate($heading_color, $heading, $body_html) {
    return '
    <div style="font-family:Inter,sans-serif;max-width:560px;margin:auto;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
        <div style="background:#0a1329;padding:28px 32px;">
            <h2 style="color:#B8860B;margin:0;">Heartbeat Heaven</h2>
        </div>
        <div style="padding:32px;">
            <h3 style="color:' . $heading_color . ';margin:0 0 16px;">' . $heading . '</h3>
            ' . $body_html . '
            <p style="color:#94a3b8;font-size:13px;margin-top:24px;">— Heartbeat Heaven Admin Team</p>
        </div>
    </div>';
}

// ── CONFIRM ADOPTION HANDOVER ─────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] == 'confirm_adoption' && isset($_GET['id']) && isset($_GET['request_id'])) {
    $animal_id  = $conn->real_escape_string($_GET['id']);
    $request_id = $conn->real_escape_string($_GET['request_id']);

    if ($conn->query("UPDATE animals SET status = 'Adopted' WHERE id = '$animal_id'")) {
        require_once 'notifications.php';
        $conn->query("UPDATE adoption_requests SET status = 'approved', updated_at = NOW() WHERE id = '$request_id'");
        require_once 'notifications.php';
        notify_adoption($request_id, 'approved');

        $ar = $conn->query("SELECT ar.*, u.email, u.full_name, a.name as animal_name
                            FROM adoption_requests ar
                            JOIN users u ON u.id = ar.user_id
                            JOIN animals a ON a.id = ar.animal_id
                            WHERE ar.id = '$request_id'");
        if ($ar && $ar->num_rows > 0) {
            $ar_row = $ar->fetch_assoc();
            sendMail($conn, $ar_row['email'], $ar_row['full_name'],
                'Adoption Confirmed – Heartbeat Heaven',
                emailTemplate('#16a34a', '🎉 Adoption Confirmed!', '
                    <p>Dear <strong>' . htmlspecialchars($ar_row['full_name']) . '</strong>,</p>
                    <p>Congratulations! Your adoption of <strong>' . htmlspecialchars($ar_row['animal_name']) . '</strong> has been officially confirmed.</p>
                    <p style="color:#64748b;font-size:14px;">Thank you for giving a loving home to one of our animals. Please remember to follow all adoption standards.</p>
                ')
            );
        }

        $others = $conn->query("SELECT ar.id, u.email, u.full_name, a.name as animal_name
                                FROM adoption_requests ar
                                JOIN users u ON u.id = ar.user_id
                                JOIN animals a ON a.id = ar.animal_id
                                WHERE ar.animal_id = '$animal_id'
                                AND ar.status IN ('pending','appointment_scheduled')
                                AND ar.id != '$request_id'");
        if ($others) {
            while ($o = $others->fetch_assoc()) {
                $conn->query("UPDATE adoption_requests SET status = 'rejected', updated_at = NOW() WHERE id = " . $o['id']);
                notify_adoption($o['id'], 'rejected');
                sendMail($conn, $o['email'], $o['full_name'],
                    'Adoption Request Update – Heartbeat Heaven',
                    emailTemplate('#ff4d4d', 'Application Update', '
                        <p>Dear <strong>' . htmlspecialchars($o['full_name']) . '</strong>,</p>
                        <p>Unfortunately your adoption request for <strong>' . htmlspecialchars($o['animal_name']) . '</strong> was <span style="color:#ff4d4d;font-weight:700;">not selected</span> as another applicant was chosen.</p>
                        <p style="color:#64748b;font-size:14px;">You are welcome to apply for other animals on our platform.</p>
                    ')
                );
            }
        }
        header("Location: manage_animals.php?view=confirmation&success=1");
        exit();
    }
}

// ── SCHEDULE ADOPTION APPOINTMENT ─────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'schedule_adoption_appointment') {
    $request_id       = (int)$_POST['request_id'];
    $appointment_date = $conn->real_escape_string($_POST['appointment_date']);
    $admin_note       = $conn->real_escape_string($_POST['admin_note'] ?? '');

    $conn->query("UPDATE adoption_requests
                  SET status = 'appointment_scheduled',
                      appointment_date = '$appointment_date',
                      admin_note = '$admin_note',
                      updated_at = NOW()
                  WHERE id = $request_id");
                          require_once 'notifications.php';
        require_once 'notifications.php';
notify_adoption_appointment($request_id);
    $ar = $conn->query("SELECT ar.*, u.email, u.full_name, a.name as animal_name
                        FROM adoption_requests ar
                        JOIN users u ON u.id = ar.user_id
                        JOIN animals a ON a.id = ar.animal_id
                        WHERE ar.id = $request_id");
    if ($ar && $ar->num_rows > 0) {
        $ar_row  = $ar->fetch_assoc();
        $apt_fmt = date('l, d F Y \a\t h:i A', strtotime($appointment_date));
        sendMail($conn, $ar_row['email'], $ar_row['full_name'],
            'Adoption Appointment Scheduled – Heartbeat Heaven',
            emailTemplate('#1d4ed8', '📅 Your Appointment is Confirmed', '
                <p>Dear <strong>' . htmlspecialchars($ar_row['full_name']) . '</strong>,</p>
                <p>Your adoption application for <strong>' . htmlspecialchars($ar_row['animal_name']) . '</strong> has been reviewed and we have scheduled an appointment.</p>
                <div style="background:#eff6ff;border-radius:12px;padding:16px;margin:16px 0;border-left:4px solid #1d4ed8;">
                    <div style="font-size:13px;color:#1d4ed8;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Appointment Date & Time</div>
                    <div style="font-size:18px;font-weight:800;color:#0a1329;">' . $apt_fmt . '</div>
                </div>
                ' . (!empty($admin_note) ? '<p style="color:#475569;font-size:14px;"><strong>Note from admin:</strong> ' . htmlspecialchars($admin_note) . '</p>' : '') . '
                <p style="color:#64748b;font-size:14px;">Please arrive on time to meet <strong>' . htmlspecialchars($ar_row['animal_name']) . '</strong> and complete the handover process.</p>
            ')
        );
    }
    header("Location: manage_animals.php?view=confirmation&apt_scheduled=1");
    exit();
}

// ── REJECT SINGLE APPLICANT ───────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] == 'reject_adoption' && isset($_GET['request_id'])) {
    $request_id = $conn->real_escape_string($_GET['request_id']);
    $ar = $conn->query("SELECT ar.id, ar.animal_id, u.email, u.full_name, a.name as animal_name
                        FROM adoption_requests ar
                        JOIN users u ON u.id = ar.user_id
                        JOIN animals a ON a.id = ar.animal_id
                        WHERE ar.id = '$request_id'");
    if ($ar && $ar->num_rows > 0) {
        $ar_row = $ar->fetch_assoc();
        $conn->query("UPDATE adoption_requests SET status = 'rejected', updated_at = NOW() WHERE id = '$request_id'");
        require_once 'notifications.php';
        notify_adoption($request_id, 'rejected');
        sendMail($conn, $ar_row['email'], $ar_row['full_name'],
            'Adoption Request Update – Heartbeat Heaven',
            emailTemplate('#ff4d4d', 'Application Update', '
                <p>Dear <strong>' . htmlspecialchars($ar_row['full_name']) . '</strong>,</p>
                <p>Your adoption request for <strong>' . htmlspecialchars($ar_row['animal_name']) . '</strong> has been <span style="color:#ff4d4d;font-weight:700;">rejected</span>.</p>
                <p style="color:#64748b;font-size:14px;">You are welcome to apply for other animals on our platform.</p>
            ')
        );
    }
    header("Location: manage_animals.php?view=confirmation&rejected=1");
    exit();
}

// ── SCHEDULE SURRENDER APPOINTMENT ───────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'schedule_appointment') {
    $sid              = (int)$_POST['surrender_id'];
    $appointment_date = $conn->real_escape_string($_POST['appointment_date']);
    $admin_notes      = $conn->real_escape_string($_POST['admin_notes'] ?? '');

    $conn->query("UPDATE surrender_requests
                  SET status = 'Appointment Scheduled',
                      appointment_date = '$appointment_date',
                      admin_notes = '$admin_notes',
                      updated_at = NOW()
                  WHERE id = $sid");
require_once 'notifications.php';
notify_surrender_appointment($sid);
    $sr = $conn->query("SELECT sr.*, u.email, u.full_name
                        FROM surrender_requests sr
                        JOIN users u ON u.id = sr.user_id
                        WHERE sr.id = $sid");
    if ($sr && $sr->num_rows > 0) {
        $s       = $sr->fetch_assoc();
        $apt_fmt = date('l, d F Y \a\t h:i A', strtotime($appointment_date));
        
    }
    header("Location: manage_animals.php?view=surrender&scheduled=1");
    exit();
}

// ── MARK SURRENDER AS RECEIVED ────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'mark_received') {
    $sid                    = (int)$_POST['surrender_id'];
    $animal_status          = $conn->real_escape_string($_POST['animal_status']);
    $contribution_collected = floatval($_POST['contribution_collected'] ?? 0);
    $contribution_status    = $contribution_collected > 0 ? 'collected' : 'waived';
    $admin_notes            = $conn->real_escape_string($_POST['admin_notes'] ?? '');

    $sr = $conn->query("SELECT * FROM surrender_requests WHERE id = $sid");
    if ($sr && $sr->num_rows > 0) {
        $s       = $sr->fetch_assoc();
        $name    = $conn->real_escape_string('Surrendered ' . ucfirst($s['species']));
        $species = $conn->real_escape_string($s['species']);
        $breed   = $conn->real_escape_string($s['breed'] ?? '');
        $age     = $conn->real_escape_string($s['age']);
        $gender  = $conn->real_escape_string($s['gender']);
        $vax     = is_null($s['is_vaccinated'])     ? 'NULL' : "'" . (int)$s['is_vaccinated'] . "'";
        $spay    = is_null($s['is_spayed_neutered']) ? 'NULL' : "'" . (int)$s['is_spayed_neutered'] . "'";
        $photo   = $conn->real_escape_string($s['photo_path'] ?? '');
        $history = $conn->real_escape_string('Surrendered. Reason: ' . ($s['reason'] ?? '') . '. ' . ($s['description'] ?? ''));

        $conn->query("INSERT INTO animals
            (name, species, breed, age, gender, is_vaccinated, is_spayed_neutered,
             medical_history, image_path, status, medical_clearance_status, created_at)
            VALUES
            ('$name','$species','$breed','$age','$gender',$vax,$spay,
             '$history','$photo','$animal_status','Pending',NOW())");

        $contrib_sql = $contribution_collected > 0 ? $contribution_collected : 'NULL';
        $conn->query("UPDATE surrender_requests
                      SET status = 'Received',
                          contribution_collected = $contrib_sql,
                          contribution_status    = '$contribution_status',
                          admin_notes            = '$admin_notes',
                          updated_at             = NOW()
                      WHERE id = $sid");

        if ($contribution_collected > 0) {
            require_once 'finance_auto_hooks.php';
            autoSyncSurrenderContribution($conn, $sid, $contribution_collected, $_SESSION['user_id']);
        }
    }
    header("Location: manage_animals.php?view=surrender&received=1");
    exit();
}

// ── DECLINE SURRENDER ─────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] == 'decline_surrender' && isset($_GET['id'])) {
    $sid = (int)$_GET['id'];
    $sr = $conn->query("SELECT sr.*, u.email, u.full_name FROM surrender_requests sr
                        JOIN users u ON u.id = sr.user_id WHERE sr.id = $sid");
    if ($sr && $sr->num_rows > 0) {
        $s = $sr->fetch_assoc();
        sendMail($conn, $s['email'], $s['full_name'],
            'Surrender Request Update – Heartbeat Heaven',
            emailTemplate('#ff4d4d', 'Surrender Request Update', '
                <p>Dear <strong>' . htmlspecialchars($s['full_name']) . '</strong>,</p>
                <p>Unfortunately we are unable to accept your pet surrender request at this time.</p>
                <p style="color:#64748b;font-size:14px;">If you need further assistance, please contact us directly.</p>
            ')
        );
    }
    $conn->query("UPDATE surrender_requests SET status = 'Cancelled', updated_at = NOW() WHERE id = '$sid'");
    header("Location: manage_animals.php?view=surrender&declined=1");
    exit();
}

// ── VIEW & FILTER PARAMETERS ──────────────────────────────────────────────────
$view           = $_GET['view']           ?? 'adoption';
$search         = trim($_GET['search']    ?? '');
$filter_status  = $_GET['filter_status']  ?? '';
$filter_species = $_GET['filter_species'] ?? '';

// ── STATS ─────────────────────────────────────────────────────────────────────
$count_adoption  = $conn->query("SELECT COUNT(*) c FROM animals WHERE status='Available for Adoption'")->fetch_assoc()['c'];
$count_sanctuary = $conn->query("SELECT COUNT(*) c FROM animals WHERE status='Resident of Sanctuary'")->fetch_assoc()['c'];
$count_confirm   = $conn->query("SELECT COUNT(*) c FROM adoption_requests WHERE status IN ('pending','appointment_scheduled')")->fetch_assoc()['c'];
$count_adopted   = $conn->query("SELECT COUNT(*) c FROM animals WHERE status='Adopted'")->fetch_assoc()['c'];
$count_rejected  = $conn->query("SELECT COUNT(*) c FROM adoption_requests WHERE status='rejected'")->fetch_assoc()['c'];
$count_surrender = $conn->query("SELECT COUNT(*) c FROM surrender_requests WHERE status IN ('Pending Review','Appointment Scheduled')")->fetch_assoc()['c'];

// ── DATA QUERIES ──────────────────────────────────────────────────────────────
$active_list       = null;
$rejected_cases    = null;
$surrender_list    = null;
$confirmation_rows = [];
$table_title       = '';
$border_color      = '#B8860B';
$result_count      = 0;

// ── helper: escape search for LIKE ───────────────────────────────────────────
function esc($conn, $v) { return $conn->real_escape_string($v); }

if ($view == 'adoption') {
    $where = "WHERE status='Available for Adoption'";
    if ($search)         $where .= " AND (name LIKE '%".esc($conn,$search)."%' OR species LIKE '%".esc($conn,$search)."%' OR breed LIKE '%".esc($conn,$search)."%')";
    if ($filter_species) $where .= " AND species='".esc($conn,$filter_species)."'";
    $active_list  = $conn->query("SELECT * FROM animals $where ORDER BY created_at DESC");
    $result_count = $active_list->num_rows;
    $table_title  = 'Active Adoption Inventory';
    $border_color = '#198754';

} elseif ($view == 'sanctuary') {
    $where = "WHERE status='Resident of Sanctuary'";
    if ($search)         $where .= " AND (name LIKE '%".esc($conn,$search)."%' OR species LIKE '%".esc($conn,$search)."%' OR breed LIKE '%".esc($conn,$search)."%')";
    if ($filter_species) $where .= " AND species='".esc($conn,$filter_species)."'";
    $active_list  = $conn->query("SELECT * FROM animals $where ORDER BY created_at DESC");
    $result_count = $active_list->num_rows;
    $table_title  = 'Sanctuary Resident Roster';
    $border_color = '#6f42c1';

} elseif ($view == 'confirmation') {
    $table_title  = 'Awaiting Placement Confirmation';
    $border_color = '#0d6efd';
    $where = "WHERE ar.status IN ('pending','appointment_scheduled')";
    if ($search)        $where .= " AND (u.full_name LIKE '%".esc($conn,$search)."%' OR a.name LIKE '%".esc($conn,$search)."%' OR u.email LIKE '%".esc($conn,$search)."%')";
    if ($filter_status) $where .= " AND ar.status='".esc($conn,$filter_status)."'";
    $dq = $conn->query("SELECT ar.id as request_id, ar.request_date, ar.updated_at, ar.status as ar_status,
               ar.appointment_date, ar.admin_note,
               ar.phone, ar.address, ar.housing_type, ar.has_outdoor,
               ar.has_other_pets, ar.other_pets_detail, ar.has_children, ar.children_ages,
               ar.pet_experience, ar.adoption_reason, ar.primary_caretaker, ar.alone_hours,
               u.full_name, u.email, u.phone as profile_phone, u.profile_image,
               a.id as animal_id, a.name as animal_name, a.species, a.image_path
        FROM adoption_requests ar
        JOIN users u ON u.id = ar.user_id
        JOIN animals a ON a.id = ar.animal_id
        $where ORDER BY a.name ASC, ar.request_date ASC");
    while ($d = $dq->fetch_assoc()) $confirmation_rows[] = $d;
    $result_count = count($confirmation_rows);

} elseif ($view == 'adopted') {
    $where = "WHERE a.status='Adopted'";
    if ($search)         $where .= " AND (a.name LIKE '%".esc($conn,$search)."%' OR u.full_name LIKE '%".esc($conn,$search)."%' OR u.email LIKE '%".esc($conn,$search)."%')";
    if ($filter_species) $where .= " AND a.species='".esc($conn,$filter_species)."'";
    $active_list = $conn->query("
        SELECT a.*, ar.request_date, ar.updated_at AS adopted_date,
               u.full_name AS adopter_name, u.email AS adopter_email,
               u.phone AS adopter_phone, u.address AS adopter_address,
               u.profile_image AS adopter_avatar
        FROM animals a
        LEFT JOIN adoption_requests ar ON ar.animal_id = a.id AND ar.status = 'approved'
        LEFT JOIN users u ON u.id = ar.user_id
        $where ORDER BY a.created_at DESC");
    $result_count = $active_list->num_rows;
    $table_title  = 'Successfully Adopted Records';
    $border_color = '#B8860B';

} elseif ($view == 'rejected') {
    $where = "WHERE ar.status='rejected'";
    if ($search)         $where .= " AND (u.full_name LIKE '%".esc($conn,$search)."%' OR a.name LIKE '%".esc($conn,$search)."%' OR u.email LIKE '%".esc($conn,$search)."%')";
    if ($filter_species) $where .= " AND a.species='".esc($conn,$filter_species)."'";
    $rejected_cases = $conn->query("SELECT ar.id as request_id, ar.request_date, ar.updated_at, ar.admin_note,
               u.full_name, u.email, u.phone, u.profile_image,
               a.name as animal_name, a.species, a.breed, a.gender, a.age, a.image_path
        FROM adoption_requests ar
        JOIN users u ON u.id = ar.user_id
        JOIN animals a ON a.id = ar.animal_id
        $where ORDER BY ar.request_date DESC");
    $result_count = $rejected_cases->num_rows;
    $table_title  = 'Rejected Adoption Applications';
    $border_color = '#ff4d4d';

} elseif ($view == 'surrender') {
    $where = "WHERE 1=1";
    if ($search)         $where .= " AND (sr.species LIKE '%".esc($conn,$search)."%' OR sr.breed LIKE '%".esc($conn,$search)."%' OR u.full_name LIKE '%".esc($conn,$search)."%' OR u.email LIKE '%".esc($conn,$search)."%')";
    if ($filter_status)  $where .= " AND sr.status='".esc($conn,$filter_status)."'";
    if ($filter_species) $where .= " AND sr.species='".esc($conn,$filter_species)."'";
    $surrender_list = $conn->query("SELECT sr.*, u.full_name, u.email, u.phone as user_phone
        FROM surrender_requests sr
        JOIN users u ON u.id = sr.user_id
        $where ORDER BY sr.created_at DESC");
    $result_count = $surrender_list->num_rows;
    $table_title  = 'Pet Surrender Requests';
    $border_color = '#f97316';
}

// ── build active filter label for print header ────────────────────────────────
$active_filters = [];
if ($search)         $active_filters[] = 'Search: "' . htmlspecialchars($search) . '"';
if ($filter_status)  $active_filters[] = 'Status: ' . htmlspecialchars($filter_status);
if ($filter_species) $active_filters[] = 'Species: ' . ucfirst(htmlspecialchars($filter_species));
$filter_label = implode(' · ', $active_filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Asset Control | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root { --navy:#0a1329; --gold:#B8860B; --sos:#ff4d4d; --shelter:#6f42c1; --vet:#198754; --orange:#f97316; }
        body { background:#f4f7fa; font-family:'Inter',sans-serif; margin:0; }
        .ma-wrapper  { display:flex; min-height:calc(100vh - 70px); }
        .ma-sidebar  { width:240px; flex-shrink:0; background:var(--navy); padding:24px 0; position:sticky; top:70px; height:calc(100vh - 70px); overflow-y:auto; }
        .ma-content  { flex:1; padding:32px 28px 60px; min-width:0; }
        .sidebar-head { padding:0 20px 20px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:12px; }
        .sidebar-head span { font-size:0.58rem; font-weight:800; letter-spacing:2.5px; text-transform:uppercase; color:rgba(255,193,7,0.6); display:block; margin-bottom:4px; }
        .sidebar-head p { color:#fff; font-weight:700; font-size:0.88rem; margin:0; }
        .sidebar-item { display:flex; align-items:center; gap:12px; padding:11px 20px; text-decoration:none !important; color:rgba(255,255,255,0.55); font-size:0.78rem; font-weight:600; transition:all 0.2s; border-left:3px solid transparent; }
        .sidebar-item:hover { color:#fff; background:rgba(255,255,255,0.05); }
        .sidebar-item.active { color:#fff; background:rgba(255,255,255,0.07); border-left-color:var(--gold); }
        .sidebar-item .si-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; flex-shrink:0; background:rgba(255,255,255,0.06); }
        .sidebar-item.active .si-icon { background:rgba(184,134,11,0.2); color:var(--gold); }
        .sidebar-badge { margin-left:auto; background:var(--sos); color:#fff; font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:20px; line-height:1.6; }
        .sidebar-badge.orange { background:var(--orange); }
        .ma-page-header { margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0; display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; }
        .ma-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
        .ma-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
        .ma-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; }
        .glass-card { background:#fff; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #e2e8f0; overflow:hidden; }
        .glass-card-header { padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .glass-card-header h6 { font-weight:800; font-size:0.78rem; text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px; }
        .asset-img { width:56px; height:56px; object-fit:cover; border-radius:12px; }
        .btn-cmd { border-radius:9px; font-weight:800; font-size:0.62rem; text-transform:uppercase; padding:8px 14px; border:none; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
        .animal-label { display:inline-block; background:#eef2ff; color:#3730a3; font-size:0.65rem; font-weight:800; padding:3px 10px; border-radius:20px; margin-bottom:4px; }
        .ma-alert { border-radius:12px; font-size:0.8rem; font-weight:600; }
        .date-badge { display:inline-flex; align-items:center; gap:4px; background:#f1f5f9; color:#64748b; font-size:0.63rem; font-weight:600; padding:3px 8px; border-radius:6px; margin-top:3px; }
        .date-badge i { font-size:0.6rem; }
        .btn-print { border-radius:9px; font-weight:800; font-size:0.62rem; text-transform:uppercase; padding:8px 14px; border:1px solid #e2e8f0; background:#f8fafc; color:#475569; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all 0.15s; }
        .btn-print:hover { background:#f1f5f9; border-color:#cbd5e1; color:var(--navy); }
        .s-pill { display:inline-flex; align-items:center; gap:5px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:4px 10px; font-size:0.67rem; font-weight:600; color:#475569; margin:2px; }

        /* ── FILTER BAR ── */
        .filter-bar { background:#fff; border-radius:14px; padding:14px 18px; border:1px solid #e2e8f0; display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,0.03); }
        .filter-bar input, .filter-bar select { padding:9px 13px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.82rem; font-family:'Inter',sans-serif; background:#fff; color:var(--navy); transition:border-color 0.2s; }
        .filter-bar input  { flex:1; min-width:200px; }
        .filter-bar input:focus, .filter-bar select:focus { outline:none; border-color:var(--gold); }
        .result-count { font-size:0.72rem; font-weight:700; color:#94a3b8; margin-left:auto; white-space:nowrap; }
        .result-count span { color:var(--navy); }

        /* ── CONFIRMATION VIEW ── */
        .app-row { border-bottom:1px solid #f1f5f9; }
        .app-row:last-child { border-bottom:none; }
        .app-details-panel { background:#f8fafc; border-top:1px solid #f1f5f9; padding:16px 20px; display:none; }
        .app-details-panel.open { display:block; }
        .app-detail-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:10px; }
        .app-detail-item { background:#fff; border-radius:10px; padding:10px 12px; border:1px solid #e2e8f0; }
        .app-detail-item .lbl { font-size:0.6rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin-bottom:3px; }
        .app-detail-item .val { font-size:0.78rem; font-weight:700; color:var(--navy); }
        .app-reason-box { background:#fff; border-radius:10px; padding:12px 14px; border:1px solid #e2e8f0; margin-top:10px; }
        .app-reason-box .lbl { font-size:0.6rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin-bottom:4px; }
        .app-reason-box .val { font-size:0.78rem; color:#334155; line-height:1.6; }
        .toggle-details-btn { background:none; border:1px solid #e2e8f0; border-radius:8px; font-size:0.62rem; font-weight:800; color:#64748b; padding:5px 12px; cursor:pointer; transition:all .2s; }
        .toggle-details-btn:hover { border-color:var(--gold); color:var(--gold); }
        .status-pill-ar { font-size:0.6rem; font-weight:800; padding:3px 9px; border-radius:20px; text-transform:uppercase; letter-spacing:0.4px; }
        .ar-pending   { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
        .ar-scheduled { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }

        /* ── SURRENDER CARDS ── */
        .surrender-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(310px,1fr)); gap:20px; }
        .surrender-card { background:#fff; border-radius:18px; border:1px solid #e2e8f0; overflow:hidden; transition:box-shadow 0.2s; }
        .surrender-card:hover { box-shadow:0 8px 24px rgba(0,0,0,0.08); }
        .surrender-card-img { width:100%; height:160px; object-fit:cover; }
        .surrender-card-body { padding:16px; }
        .surrender-card-body h6 { font-weight:800; font-size:0.85rem; color:var(--navy); margin:0 0 4px; }
        .surrender-card-body .s-meta { font-size:0.7rem; color:#64748b; margin-bottom:10px; }
        .urgency-urgent   { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
        .urgency-soon     { background:#fffbeb; border-color:#fde68a; color:#d97706; }
        .urgency-flexible { background:#f0fdf4; border-color:#bbf7d0; color:#16a34a; }
        .status-pill-sdr { font-size:0.62rem; font-weight:800; padding:3px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px; }
        .sdr-pending   { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
        .sdr-scheduled { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .sdr-received  { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
        .sdr-cancelled { background:#f8fafc; color:#64748b; border:1px solid #e2e8f0; }
        .contribution-badge { display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:8px; padding:5px 10px; font-size:0.72rem; font-weight:700; margin-top:8px; }
        .contribution-badge.waived { background:#f8fafc; border-color:#e2e8f0; color:#64748b; }

        /* ── MODALS ── */
        .sdr-modal-content { border-radius:22px !important; border:none !important; overflow:hidden; }
        .sdr-modal-header  { padding:18px 24px; }
        .sdr-modal-title   { color:#fff; font-weight:800; font-size:0.88rem; letter-spacing:1px; }
        .sdr-field-label   { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px; color:#475569; }
        .sdr-input         { width:100%; padding:10px 13px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.88rem; font-family:inherit; background:#fff; transition:border-color .2s; }
        .sdr-input:focus   { outline:none; border-color:#0891b2; }
        .place-opt { border:2px solid #e2e8f0; border-radius:14px; padding:16px; cursor:pointer; transition:all 0.2s; text-align:center; }
        .place-opt:hover, .place-opt.selected { border-color:var(--orange); background:#fff7ed; }
        .place-opt i { font-size:1.6rem; margin-bottom:8px; display:block; }
        .place-opt strong { font-size:0.8rem; display:block; color:var(--navy); }
        .place-opt span { font-size:0.68rem; color:#94a3b8; }

        /* ── PRINT ── */
        .print-only { display:none; }
        @media print {
            .ma-sidebar, nav, .navbar, footer, .btn-cmd, .btn-print, button,
            .btn-close, .alert, .modal, .chat-widget, [class*="chat"],
            .toggle-details-btn, .filter-bar { display:none !important; }
            body { background:#fff !important; font-size:11pt; }
            .ma-wrapper { display:block; }
            .ma-content { padding:0 !important; }
            .glass-card { box-shadow:none !important; border:1px solid #ccc !important; border-radius:0 !important; }
            .surrender-grid { display:block !important; }
            .surrender-card { border:1px solid #ccc !important; border-radius:0 !important; margin-bottom:16px !important; page-break-inside:avoid; break-inside:avoid; }
            .surrender-card-img { max-height:80px; }
            .app-details-panel { display:block !important; }
            tr { page-break-inside:avoid; }
            .print-only { display:block !important; }
            .print-only-header { display:flex !important; align-items:center; justify-content:space-between; padding-bottom:12px; margin-bottom:18px; border-bottom:2px solid #0a1329; }
            .print-only-header h3 { font-size:14pt; font-weight:800; color:#0a1329; margin:0; }
            .print-only-header .print-meta { font-size:9pt; color:#64748b; text-align:right; }
            .print-only-header .print-filters { font-size:8pt; color:#94a3b8; margin-top:3px; }
            .result-count { display:none !important; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="ma-wrapper">

    <!-- SIDEBAR -->
    <aside class="ma-sidebar">
        <div class="sidebar-head">
            <span>Operations</span>
            <p>Asset Control</p>
        </div>
        <a href="?view=adoption"     class="sidebar-item <?= $view=='adoption'     ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-paw"></i></div>
            Adoption Inventory
            <span style="margin-left:auto;font-size:0.68rem;color:rgba(255,255,255,0.35);"><?= $count_adoption ?></span>
        </a>
        <a href="?view=sanctuary"    class="sidebar-item <?= $view=='sanctuary'    ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-house-chimney-medical"></i></div>
            Sanctuary Roster
            <span style="margin-left:auto;font-size:0.68rem;color:rgba(255,255,255,0.35);"><?= $count_sanctuary ?></span>
        </a>
        <a href="?view=confirmation" class="sidebar-item <?= $view=='confirmation' ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-file-signature"></i></div>
            Awaiting Confirm
            <?php if($count_confirm > 0): ?>
                <span class="sidebar-badge"><?= $count_confirm ?></span>
            <?php endif; ?>
        </a>
        <a href="?view=adopted"      class="sidebar-item <?= $view=='adopted'      ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-award"></i></div>
            Placement Records
            <span style="margin-left:auto;font-size:0.68rem;color:rgba(255,255,255,0.35);"><?= $count_adopted ?></span>
        </a>
        <a href="?view=rejected"     class="sidebar-item <?= $view=='rejected'     ? 'active':'' ?>">
            <div class="si-icon"><i class="fas fa-ban"></i></div>
            Rejected Cases
            <span style="margin-left:auto;font-size:0.68rem;color:rgba(255,255,255,0.35);"><?= $count_rejected ?></span>
        </a>
        <div style="margin:16px 20px;border-top:1px solid rgba(255,255,255,0.08);"></div>
        <a href="?view=surrender"    class="sidebar-item <?= $view=='surrender'    ? 'active':'' ?>">
            <div class="si-icon" style="color:var(--orange);"><i class="fas fa-home"></i></div>
            Surrenders
            <?php if($count_surrender > 0): ?>
                <span class="sidebar-badge orange"><?= $count_surrender ?></span>
            <?php endif; ?>
        </a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="ma-content">

        <!-- PRINT-ONLY HEADER -->
        <div class="print-only">
            <div class="print-only-header">
                <h3>Heartbeat Heaven &mdash; <?= htmlspecialchars($table_title) ?></h3>
                <div class="print-meta">
                    <div style="font-weight:700;">Internal Operations &rsaquo; Asset Management</div>
                    <?php if($filter_label): ?>
                        <div class="print-filters">Filters: <?= $filter_label ?></div>
                    <?php endif; ?>
                    <div id="printDateStamp"></div>
                </div>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="ma-page-header">
            <div>
                <div class="ma-accent-bar" style="background:<?= $border_color ?>;"></div>
                <h5><?= $table_title ?></h5>
                <p>Internal Operations &rsaquo; Asset Management</p>
            </div>
            <button class="btn-print" onclick="printPage()"><i class="fas fa-print"></i> Print</button>
        </div>

        <!-- ALERTS -->
        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success ma-alert alert-dismissible fade show mb-4">
                <i class="fa-solid fa-circle-check me-2"></i> Adoption confirmed! Adopter notified, other applicants rejected.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['apt_scheduled'])): ?>
            <div class="alert alert-info ma-alert alert-dismissible fade show mb-4">
                <i class="fa-solid fa-calendar-check me-2"></i> Appointment scheduled and adopter notified via email.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['rejected'])): ?>
            <div class="alert alert-warning ma-alert alert-dismissible fade show mb-4">
                <i class="fa-solid fa-circle-xmark me-2"></i> Applicant rejected and notified via email.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['scheduled'])): ?>
            <div class="alert alert-info ma-alert alert-dismissible fade show mb-4">
                <i class="fa-solid fa-calendar-check me-2"></i> Surrender appointment scheduled and pet parent notified via email.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['received'])): ?>
            <div class="alert alert-success ma-alert alert-dismissible fade show mb-4">
                <i class="fa-solid fa-home me-2"></i> Pet received and added to roster. Contribution logged to financial records.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['declined'])): ?>
            <div class="alert alert-secondary ma-alert alert-dismissible fade show mb-4">
                <i class="fa-solid fa-xmark me-2"></i> Surrender request declined and pet parent notified.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- FILTER BAR (shown on all views)                        -->
        <!-- ══════════════════════════════════════════════════════ -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="<?= $view ?>">

            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="<?php
                    if($view === 'surrender')         echo 'Search species, breed, name...';
                    elseif($view === 'confirmation')  echo 'Search applicant name, animal, email...';
                    elseif($view === 'adopted')       echo 'Search animal name, adopter name, email...';
                    else                              echo 'Search name, species, breed...';
                ?>">

            <?php if($view === 'surrender'): ?>
                <select name="filter_status">
                    <option value="">All Statuses</option>
                    <option value="Pending Review"        <?= $filter_status==='Pending Review'        ?'selected':'' ?>>Pending Review</option>
                    <option value="Appointment Scheduled" <?= $filter_status==='Appointment Scheduled' ?'selected':'' ?>>Appointment Scheduled</option>
                    <option value="Received"              <?= $filter_status==='Received'              ?'selected':'' ?>>Received</option>
                    <option value="Cancelled"             <?= $filter_status==='Cancelled'             ?'selected':'' ?>>Cancelled</option>
                </select>
                <select name="filter_species">
                    <option value="">All Species</option>
                    <option value="cat"   <?= $filter_species==='cat'   ?'selected':'' ?>>Cat</option>
                    <option value="dog"   <?= $filter_species==='dog'   ?'selected':'' ?>>Dog</option>
                    <option value="bird"  <?= $filter_species==='bird'  ?'selected':'' ?>>Bird</option>
                    <option value="other" <?= $filter_species==='other' ?'selected':'' ?>>Other</option>
                </select>

            <?php elseif($view === 'confirmation'): ?>
                <select name="filter_status">
                    <option value="">All Statuses</option>
                    <option value="pending"               <?= $filter_status==='pending'               ?'selected':'' ?>>Pending Review</option>
                    <option value="appointment_scheduled" <?= $filter_status==='appointment_scheduled' ?'selected':'' ?>>Appointment Scheduled</option>
                </select>

            <?php elseif(in_array($view, ['adoption','sanctuary','adopted','rejected'])): ?>
                <select name="filter_species">
                    <option value="">All Species</option>
                    <option value="cat"   <?= $filter_species==='cat'   ?'selected':'' ?>>Cat</option>
                    <option value="dog"   <?= $filter_species==='dog'   ?'selected':'' ?>>Dog</option>
                    <option value="bird"  <?= $filter_species==='bird'  ?'selected':'' ?>>Bird</option>
                    <option value="other" <?= $filter_species==='other' ?'selected':'' ?>>Other</option>
                </select>
            <?php endif; ?>

            <button type="submit" class="btn-cmd text-white" style="background:var(--navy);">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="?view=<?= $view ?>" class="btn-cmd" style="background:#f1f5f9;color:var(--navy);">
                <i class="fas fa-times"></i> Clear
            </a>

            <div class="result-count">
                <span><?= $result_count ?></span> record<?= $result_count != 1 ? 's' : '' ?>
                <?= $filter_label ? ' &nbsp;<span style="color:#B8860B;">— filtered</span>' : '' ?>
            </div>
        </form>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- CONFIRMATION VIEW                                      -->
        <!-- ══════════════════════════════════════════════════════ -->
        <?php if($view == 'confirmation'): ?>
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid <?= $border_color ?>;">
                <i class="fas fa-file-signature" style="color:<?= $border_color ?>;"></i>
                <h6><?= $table_title ?> — <?= $result_count ?> Application<?= $result_count != 1 ? 's' : '' ?></h6>
            </div>

            <?php if(empty($confirmation_rows)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#e2e8f0;"></i>
                <?= ($search || $filter_status) ? 'No applications match your filter.' : 'No active adoption applications.' ?>
            </div>
            <?php else: ?>
            <?php foreach($confirmation_rows as $row):
                $avatar = !empty($row['profile_image']) ? $row['profile_image']
                    : 'https://ui-avatars.com/api/?name='.urlencode($row['full_name']).'&background=0a1329&color=B8860B&size=64&bold=true';
                $ar_status    = $row['ar_status'];
                $status_class = $ar_status === 'appointment_scheduled' ? 'ar-scheduled' : 'ar-pending';
                $status_label = $ar_status === 'appointment_scheduled' ? 'Appointment Scheduled' : 'Pending Review';
                $housing_labels = ['apartment'=>'Apartment','house'=>'House','rented_apartment'=>'Rented Apt','rented_house'=>'Rented House','other'=>'Other'];
                $exp_labels     = ['none'=>'First Timer','some'=>'Some Experience','experienced'=>'Experienced'];
            ?>
            <div class="app-row">
                <div class="d-flex align-items-center gap-3 px-4 py-3 flex-wrap">

                    <!-- Animal -->
                    <img src="<?= htmlspecialchars($row['image_path'] ?: 'assets/img/placeholder.png') ?>"
                         style="width:52px;height:52px;object-fit:cover;border-radius:12px;flex-shrink:0;">
                    <div style="min-width:120px;">
                        <span class="animal-label"><i class="fas fa-paw me-1"></i><?= htmlspecialchars($row['animal_name']) ?></span>
                        <div style="font-size:0.7rem;color:#94a3b8;"><?= htmlspecialchars($row['species']) ?></div>
                    </div>

                    <div style="width:1px;height:40px;background:#f1f5f9;flex-shrink:0;"></div>

                    <!-- Applicant -->
                    <img src="<?= $avatar ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;flex-shrink:0;">
                    <div style="flex:1;min-width:160px;">
                        <div class="fw-bold" style="font-size:0.82rem;"><?= htmlspecialchars($row['full_name']) ?></div>
                        <div style="font-size:0.68rem;color:#94a3b8;">
                            <?= htmlspecialchars($row['email']) ?> &nbsp;·&nbsp;
                            <?= htmlspecialchars($row['phone'] ?: $row['profile_phone'] ?: '—') ?>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                            <span class="date-badge"><i class="fas fa-calendar-alt"></i>Applied: <?= date('d M Y', strtotime($row['request_date'])) ?></span>
                            <span class="status-pill-ar <?= $status_class ?>"><?= $status_label ?></span>
                            <?php if($ar_status === 'appointment_scheduled' && !empty($row['appointment_date'])): ?>
                            <span class="date-badge" style="background:#eff6ff;color:#1d4ed8;">
                                <i class="fas fa-calendar-check"></i>
                                Apt: <?= date('d M Y, h:i A', strtotime($row['appointment_date'])) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 flex-wrap align-items-center ms-auto">
                        <button class="toggle-details-btn" onclick="toggleDetails(<?= $row['request_id'] ?>)">
                            <i class="fas fa-chevron-down me-1" id="chevron<?= $row['request_id'] ?>"></i>Application
                        </button>

                        <?php if($ar_status === 'pending'): ?>
                            <button class="btn-cmd text-white" style="background:#1d4ed8;"
                                onclick="openAdoptScheduleModal(<?= $row['request_id'] ?>)">
                                <i class="fas fa-calendar-plus me-1"></i>Schedule
                            </button>
                            <button class="btn-cmd bg-danger text-white"
                                onclick="if(confirm('Reject <?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>\'s application?')) location.href='manage_animals.php?view=confirmation&action=reject_adoption&request_id=<?= $row['request_id'] ?>'">
                                <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>

                        <?php elseif($ar_status === 'appointment_scheduled'): ?>
                            <button class="btn-cmd bg-success text-white"
                                onclick="if(confirm('Confirm handover for <?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>?\n\nAll other applicants for <?= htmlspecialchars($row['animal_name'], ENT_QUOTES) ?> will be rejected.')) location.href='manage_animals.php?view=confirmation&action=confirm_adoption&id=<?= $row['animal_id'] ?>&request_id=<?= $row['request_id'] ?>'">
                                <i class="fa-solid fa-handshake me-1"></i>Confirm Handover
                            </button>
                            <button class="btn-cmd bg-danger text-white"
                                onclick="if(confirm('Reject <?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>\'s application?')) location.href='manage_animals.php?view=confirmation&action=reject_adoption&request_id=<?= $row['request_id'] ?>'">
                                <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Expandable application details -->
                <div class="app-details-panel" id="details<?= $row['request_id'] ?>">
                    <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:10px;">Application Details</div>
                    <div class="app-detail-grid">
                        <div class="app-detail-item">
                            <div class="lbl"><i class="fas fa-home me-1"></i>Housing</div>
                            <div class="val"><?= htmlspecialchars($housing_labels[$row['housing_type']] ?? $row['housing_type'] ?? '—') ?></div>
                        </div>
                        <div class="app-detail-item">
                            <div class="lbl"><i class="fas fa-tree me-1"></i>Outdoor Space</div>
                            <div class="val"><?= is_null($row['has_outdoor']) ? '—' : ($row['has_outdoor'] ? 'Yes' : 'No') ?></div>
                        </div>
                        <div class="app-detail-item">
                            <div class="lbl"><i class="fas fa-clock me-1"></i>Alone Hours/Day</div>
                            <div class="val"><?= !is_null($row['alone_hours']) ? $row['alone_hours'].' hrs' : '—' ?></div>
                        </div>
                        <div class="app-detail-item">
                            <div class="lbl"><i class="fas fa-paw me-1"></i>Other Pets</div>
                            <div class="val">
                                <?= is_null($row['has_other_pets']) ? '—' : ($row['has_other_pets'] ? 'Yes' : 'No') ?>
                                <?= !empty($row['other_pets_detail']) ? '<br><span style="font-size:0.7rem;color:#64748b;font-weight:500;">'.htmlspecialchars($row['other_pets_detail']).'</span>' : '' ?>
                            </div>
                        </div>
                        <div class="app-detail-item">
                            <div class="lbl"><i class="fas fa-child me-1"></i>Children</div>
                            <div class="val">
                                <?= is_null($row['has_children']) ? '—' : ($row['has_children'] ? 'Yes' : 'No') ?>
                                <?= !empty($row['children_ages']) ? '<br><span style="font-size:0.7rem;color:#64748b;font-weight:500;">Ages: '.htmlspecialchars($row['children_ages']).'</span>' : '' ?>
                            </div>
                        </div>
                        <div class="app-detail-item">
                            <div class="lbl"><i class="fas fa-star me-1"></i>Experience</div>
                            <div class="val"><?= htmlspecialchars($exp_labels[$row['pet_experience']] ?? $row['pet_experience'] ?? '—') ?></div>
                        </div>
                        <div class="app-detail-item">
                            <div class="lbl"><i class="fas fa-user me-1"></i>Primary Caretaker</div>
                            <div class="val"><?= htmlspecialchars($row['primary_caretaker'] ?? '—') ?></div>
                        </div>
                        <div class="app-detail-item">
                            <div class="lbl"><i class="fas fa-map-marker-alt me-1"></i>Address</div>
                            <div class="val" style="font-size:0.72rem;"><?= htmlspecialchars($row['address'] ?? '—') ?></div>
                        </div>
                    </div>
                    <?php if(!empty($row['adoption_reason'])): ?>
                    <div class="app-reason-box">
                        <div class="lbl"><i class="fas fa-heart me-1"></i>Why they want to adopt</div>
                        <div class="val"><?= nl2br(htmlspecialchars($row['adoption_reason'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- SURRENDER VIEW                                         -->
        <!-- ══════════════════════════════════════════════════════ -->
        <?php elseif($view == 'surrender'): ?>
        <?php if($surrender_list && $surrender_list->num_rows > 0): ?>
        <div class="surrender-grid">
        <?php while($row = $surrender_list->fetch_assoc()):
            $urgency_class = 'urgency-' . ($row['urgency'] ?? 'flexible');
            $urgency_label = ['urgent'=>'⚡ Within a Week','soon'=>'📅 Within a Month','flexible'=>'🕊️ No Rush'][$row['urgency']] ?? 'Flexible';
            $status        = $row['status'];
            $status_class  = ['Pending Review'=>'sdr-pending','Appointment Scheduled'=>'sdr-scheduled','Received'=>'sdr-received','Cancelled'=>'sdr-cancelled'][$status] ?? 'sdr-pending';
            $vax  = is_null($row['is_vaccinated'])     ? 'Unknown' : ($row['is_vaccinated']     ? 'Vaccinated'     : 'Not Vaccinated');
            $spay = is_null($row['is_spayed_neutered']) ? 'Unknown' : ($row['is_spayed_neutered'] ? 'Spayed/Neutered' : 'Not Spayed');
        ?>
        <div class="surrender-card">
            <?php if(!empty($row['photo_path'])): ?>
                <img src="<?= htmlspecialchars($row['photo_path']) ?>" class="surrender-card-img">
            <?php else: ?>
                <div class="surrender-card-img d-flex align-items-center justify-content-center" style="background:#f1f5f9;">
                    <i class="fas fa-paw fa-2x" style="color:#cbd5e1;"></i>
                </div>
            <?php endif; ?>
            <div class="surrender-card-body">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <h6><?= htmlspecialchars(ucfirst($row['species'])) ?><?= $row['breed'] ? ' · '.htmlspecialchars($row['breed']) : '' ?></h6>
                    <span class="status-pill-sdr <?= $status_class ?>"><?= $status ?></span>
                </div>
                <p class="s-meta"><?= htmlspecialchars($row['age']) ?> &middot; <?= htmlspecialchars($row['gender']) ?></p>
                <div class="mb-2">
                    <span class="date-badge"><i class="fas fa-calendar-plus"></i>Submitted: <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></span>
                </div>
                <?php if($status === 'Appointment Scheduled' && !empty($row['appointment_date'])): ?>
                <div class="mb-2">
                    <span class="date-badge" style="background:#eff6ff;color:#1d4ed8;">
                        <i class="fas fa-calendar-check"></i>Appointment: <?= date('d M Y, h:i A', strtotime($row['appointment_date'])) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="mb-2">
                    <span class="s-pill <?= $urgency_class ?>"><i class="fas fa-clock"></i><?= $urgency_label ?></span>
                    <span class="s-pill"><i class="fas fa-syringe"></i><?= $vax ?></span>
                    <span class="s-pill"><i class="fas fa-scissors"></i><?= $spay ?></span>
                </div>
                <?php if(!is_null($row['contribution_pledged']) && floatval($row['contribution_pledged']) > 0): ?>
                <div class="mb-2">
                    <span class="s-pill" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">
                        <i class="fas fa-hand-holding-heart"></i>Pledged: ৳<?= number_format(floatval($row['contribution_pledged']),2) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="mb-2" style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
                    <div style="font-size:0.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Reason</div>
                    <div style="font-size:0.75rem;color:#334155;font-weight:600;"><?= htmlspecialchars($row['reason']) ?></div>
                    <?php if(!empty($row['description'])): ?>
                        <div style="font-size:0.7rem;color:#94a3b8;margin-top:3px;"><?= htmlspecialchars(substr($row['description'],0,80)) ?><?= strlen($row['description'])>80?'…':'' ?></div>
                    <?php endif; ?>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;margin-bottom:12px;">
                    <div style="font-size:0.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Submitted By</div>
                    <div style="font-size:0.75rem;font-weight:700;color:var(--navy);"><?= htmlspecialchars($row['full_name']) ?></div>
                    <div style="font-size:0.68rem;color:#64748b;"><?= htmlspecialchars($row['email']) ?></div>
                    <?php if(!empty($row['user_phone'])): ?>
                        <div style="font-size:0.68rem;color:#64748b;"><?= htmlspecialchars($row['user_phone']) ?></div>
                    <?php endif; ?>
                    <div style="font-size:0.65rem;color:#94a3b8;margin-top:2px;">Preferred contact: <?= htmlspecialchars($row['contact_time'] ?? 'Anytime') ?></div>
                </div>

                <?php if($status === 'Received'):
                    $pledged   = floatval($row['contribution_pledged'] ?? 0);
                    $collected = floatval($row['contribution_collected'] ?? 0);
                ?>
                <div class="d-flex gap-2 flex-wrap mt-1">
                    <div class="<?= $pledged > 0 ? 'contribution-badge' : 'contribution-badge waived' ?>">
                        <i class="fas fa-hand-holding-dollar"></i>Pledged: <?= $pledged > 0 ? '৳'.number_format($pledged,2) : 'None' ?>
                    </div>
                    <div class="<?= $collected > 0 ? 'contribution-badge' : 'contribution-badge waived' ?>">
                        <i class="fas fa-<?= $collected > 0 ? 'hand-holding-heart' : 'minus-circle' ?>"></i>
                        Collected: <?= $collected > 0 ? '৳'.number_format($collected,2) : 'Waived' ?>
                    </div>
                </div>
                <?php elseif($status === 'Pending Review'): ?>
                <div class="d-flex gap-2">
                    <button class="btn-cmd text-white flex-fill" style="background:#1d4ed8;" onclick="openScheduleModal(<?= $row['id'] ?>)">
                        <i class="fas fa-calendar-plus me-1"></i>Schedule
                    </button>
                    <button class="btn-cmd flex-fill" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;"
                        onclick="if(confirm('Decline this surrender request?')) location.href='manage_animals.php?view=surrender&action=decline_surrender&id=<?= $row['id'] ?>'">
                        <i class="fas fa-xmark me-1"></i>Decline
                    </button>
                </div>
                <?php elseif($status === 'Appointment Scheduled'): ?>
                <div class="d-flex gap-2">
                    <button class="btn-cmd text-white flex-fill" style="background:var(--orange);" onclick="openReceivedModal(<?= $row['id'] ?>)">
                        <i class="fas fa-check me-1"></i>Mark Received
                    </button>
                    <button class="btn-cmd flex-fill" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;"
                        onclick="if(confirm('Decline this surrender request?')) location.href='manage_animals.php?view=surrender&action=decline_surrender&id=<?= $row['id'] ?>'">
                        <i class="fas fa-xmark me-1"></i>Decline
                    </button>
                </div>
                <?php elseif($status === 'Cancelled'): ?>
                <div style="text-align:center;padding:8px 0;font-size:0.72rem;color:#94a3b8;font-weight:700;">
                    <i class="fas fa-ban me-1"></i>Request cancelled
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="glass-card p-5 text-center">
            <i class="fas fa-home fa-3x mb-3" style="color:#e2e8f0;"></i>
            <p class="text-muted mb-0"><?= ($search || $filter_status || $filter_species) ? 'No surrender requests match your filter.' : 'No surrender requests found.' ?></p>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- REJECTED VIEW                                          -->
        <!-- ══════════════════════════════════════════════════════ -->
        <?php elseif($view == 'rejected'): ?>
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid <?= $border_color ?>;">
                <i class="fas fa-ban" style="color:<?= $border_color ?>;"></i>
                <h6><?= $table_title ?></h6>
            </div>
            <?php if($rejected_cases->num_rows === 0): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#e2e8f0;"></i>
                <p class="mb-0"><?= ($search || $filter_species) ? 'No rejected cases match your filter.' : 'No rejected cases.' ?></p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle m-0 table-hover">
                    <tbody>
                    <?php while($row = $rejected_cases->fetch_assoc()):
                        $avatar = !empty($row['profile_image']) ? $row['profile_image']
                            : 'https://ui-avatars.com/api/?name='.urlencode($row['full_name']).'&background=0a1329&color=B8860B&size=64&bold=true';
                    ?>
                    <tr>
                        <td class="ps-4" style="width:80px;"><img src="<?= $avatar ?>" class="asset-img" style="border-radius:50%;border:2px solid #e2e8f0;"></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['full_name']) ?></div>
                            <small class="text-muted"><i class="fa-solid fa-envelope me-1"></i><?= htmlspecialchars($row['email']) ?></small><br>
                            <small class="text-muted">Applied for: <strong><?= htmlspecialchars($row['animal_name']) ?></strong> (<?= htmlspecialchars($row['species']) ?>)</small>
                        </td>
                        <td>
                            <span class="date-badge d-block mb-1"><i class="fas fa-calendar-alt"></i>Applied: <?= date('d M Y', strtotime($row['request_date'])) ?></span>
                            <?php if(!empty($row['updated_at'])): ?>
                            <span class="date-badge d-block" style="background:#fff0f0;color:#dc2626;"><i class="fas fa-calendar-xmark"></i>Rejected: <?= date('d M Y', strtotime($row['updated_at'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <span class="badge bg-danger px-3 py-2" style="border-radius:8px;font-size:0.7rem;font-weight:700;"><i class="fa-solid fa-xmark me-1"></i>Rejected</span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- ALL OTHER VIEWS (adoption, sanctuary, adopted)         -->
        <!-- ══════════════════════════════════════════════════════ -->
        <?php else: ?>
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid <?= $border_color ?>;">
                <i class="fas fa-database" style="color:<?= $border_color ?>;"></i>
                <h6><?= $table_title ?></h6>
            </div>
            <?php if($active_list->num_rows === 0): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#e2e8f0;"></i>
                <p class="mb-0"><?= ($search || $filter_species) ? 'No records match your filter.' : 'No records found.' ?></p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle m-0 table-hover">
                    <tbody>
                    <?php while($row = $active_list->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" style="width:90px;"><img src="<?= htmlspecialchars($row['image_path'] ?: 'assets/img/placeholder.png') ?>" class="asset-img"></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($row['species']) ?> | <strong><?= htmlspecialchars($row['status']) ?></strong></small><br>
                            <span class="date-badge"><i class="fas fa-calendar-plus"></i>Added: <?= date('d M Y', strtotime($row['created_at'])) ?></span>
                            <?php if ($view == 'adopted' && !empty($row['adopted_date'])): ?>
                                <span class="date-badge ms-1" style="background:#f0fdf4;color:#166534;"><i class="fas fa-heart"></i>Adopted: <?= date('d M Y', strtotime($row['adopted_date'])) ?></span>
                            <?php endif; ?>
                            <?php if ($view == 'adopted' && !empty($row['adopter_name'])): ?>
                                <?php $av = !empty($row['adopter_avatar']) ? $row['adopter_avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($row['adopter_name']).'&background=0a1329&color=B8860B&size=64&bold=true'; ?>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <img src="<?= htmlspecialchars($av) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;">
                                    <div>
                                        <div style="font-size:0.75rem;font-weight:700;color:var(--navy);"><?= htmlspecialchars($row['adopter_name']) ?></div>
                                        <div style="font-size:0.67rem;color:#64748b;"><?= htmlspecialchars($row['adopter_email']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if($view != 'adopted'): ?>
                            <button class="btn-cmd bg-dark text-white" onclick="location.href='view_animal_details.php?id=<?= $row['id'] ?>'">
                                <i class="fa-solid fa-pen-to-square me-1"></i>View & Edit
                            </button>
                            <button class="btn-cmd bg-danger text-white ms-1"
                                onclick="if(confirm('Delete this asset?')) location.href='delete_animal.php?id=<?= $row['id'] ?>'">
                                <i class="fa-solid fa-trash-can me-1"></i>Delete
                            </button>
                            <?php else:
                                $av_modal = !empty($row['adopter_avatar']) ? $row['adopter_avatar']
                                    : 'https://ui-avatars.com/api/?name='.urlencode($row['adopter_name'] ?? 'U').'&background=0a1329&color=B8860B&size=64&bold=true';
                            ?>
                            <button class="btn-cmd bg-dark text-white"
                                onclick="openAdoptedModal(
                                    '<?= htmlspecialchars($row['name'],           ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['species'],        ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['breed'] ?? '—',  ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['gender'] ?? '—', ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['age'] ?? '—',    ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['image_path'] ?: 'assets/img/placeholder.png', ENT_QUOTES) ?>',
                                    '<?= date('d M Y', strtotime($row['created_at'])) ?>',
                                    '<?= !empty($row['adopted_date']) ? date('d M Y', strtotime($row['adopted_date'])) : '—' ?>',
                                    '<?= htmlspecialchars($row['adopter_name']    ?? '—', ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['adopter_email']   ?? '—', ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['adopter_phone']   ?? '—', ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['adopter_address'] ?? '—', ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($av_modal,               ENT_QUOTES) ?>'
                                )">
                                <i class="fa-solid fa-eye me-1"></i>View Details
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- MODAL: SCHEDULE ADOPTION APPOINTMENT -->
<div class="modal fade" id="adoptScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sdr-modal-content">
            <div class="modal-header sdr-modal-header" style="background:#1d4ed8;">
                <h5 class="modal-title sdr-modal-title"><i class="fas fa-calendar-plus me-2"></i>Schedule Adoption Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="manage_animals.php?view=confirmation">
                <input type="hidden" name="action" value="schedule_adoption_appointment">
                <input type="hidden" name="request_id" id="adopt_schedule_rid">
                <div class="modal-body p-4">
                    <p style="font-size:0.8rem;color:#64748b;margin-bottom:20px;">Set a date for the adopter to visit and collect the animal.</p>
                    <div class="mb-3">
                        <label class="sdr-field-label"><i class="fas fa-calendar-check me-1"></i>Appointment Date & Time *</label>
                        <input type="datetime-local" name="appointment_date" class="sdr-input" required min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div>
                        <label class="sdr-field-label"><i class="fas fa-note-sticky me-1"></i>Note to Adopter (optional)</label>
                        <textarea name="admin_note" class="sdr-input" rows="3" placeholder="e.g. Please bring a carrier, arrive 15 mins early..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="submit" class="btn-cmd text-white w-100" style="background:#1d4ed8;padding:13px;font-size:0.75rem;">
                        <i class="fas fa-calendar-check me-2"></i>Confirm Appointment & Notify Adopter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: SCHEDULE SURRENDER APPOINTMENT -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sdr-modal-content">
            <div class="modal-header sdr-modal-header" style="background:#1d4ed8;">
                <h5 class="modal-title sdr-modal-title"><i class="fas fa-calendar-plus me-2"></i>Schedule Surrender Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="manage_animals.php?view=surrender">
                <input type="hidden" name="action" value="schedule_appointment">
                <input type="hidden" name="surrender_id" id="schedule_sid">
                <div class="modal-body p-4">
                    <p style="font-size:0.8rem;color:#64748b;margin-bottom:20px;">Pick a date and time for the owner to bring in their pet.</p>
                    <div class="mb-3">
                        <label class="sdr-field-label"><i class="fas fa-calendar-check me-1"></i>Appointment Date & Time *</label>
                        <input type="datetime-local" name="appointment_date" class="sdr-input" required min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div>
                        <label class="sdr-field-label"><i class="fas fa-note-sticky me-1"></i>Admin Notes (optional)</label>
                        <textarea name="admin_notes" class="sdr-input" rows="3" placeholder="e.g. Bring vaccination records, arrive 10 mins early..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="submit" class="btn-cmd text-white w-100" style="background:#1d4ed8;padding:13px;font-size:0.75rem;">
                        <i class="fas fa-calendar-check me-2"></i>Confirm Appointment & Notify Pet Parent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: MARK SURRENDER AS RECEIVED -->
<div class="modal fade" id="receivedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sdr-modal-content">
            <div class="modal-header sdr-modal-header" style="background:var(--orange);">
                <h5 class="modal-title sdr-modal-title"><i class="fas fa-home me-2"></i>Mark as Received</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="manage_animals.php?view=surrender">
                <input type="hidden" name="action" value="mark_received">
                <input type="hidden" name="surrender_id" id="received_sid">
                <div class="modal-body p-4">
                    <p style="font-size:0.8rem;color:#64748b;margin-bottom:20px;">Pet physically received. Place in roster and record any contribution collected.</p>
                    <label class="sdr-field-label mb-2"><i class="fas fa-map-pin me-1"></i>Place Animal In *</label>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="place-opt selected" onclick="selectPlaceOpt(this,'Available for Adoption')">
                                <i class="fas fa-paw" style="color:#198754;"></i>
                                <strong>Adoption List</strong>
                                <span>Ready to be adopted</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="place-opt" onclick="selectPlaceOpt(this,'Resident of Sanctuary')">
                                <i class="fas fa-house-chimney-medical" style="color:#6f42c1;"></i>
                                <strong>Sanctuary</strong>
                                <span>Long-term resident</span>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="animal_status" id="place_opt_val" value="Available for Adoption">
                    <div class="mb-3" style="background:#f0fdf4;border-radius:12px;padding:14px;border:1px solid #bbf7d0;">
                        <label class="sdr-field-label" style="color:#166534;"><i class="fas fa-hand-holding-heart me-1"></i>Contribution Collected (৳)</label>
                        <input type="number" name="contribution_collected" class="sdr-input" min="0" step="0.01" placeholder="0.00 — leave blank if waived">
                        <div style="font-size:0.7rem;color:#94a3b8;margin-top:5px;">Leave blank or 0 if nothing collected. Logged to funding income.</div>
                    </div>
                    <div>
                        <label class="sdr-field-label"><i class="fas fa-note-sticky me-1"></i>Admin Notes (optional)</label>
                        <textarea name="admin_notes" class="sdr-input" rows="2" placeholder="Any intake observations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="submit" class="btn-cmd text-white w-100" style="background:var(--orange);padding:13px;font-size:0.75rem;">
                        <i class="fas fa-check me-2"></i>Confirm Receipt & Register Animal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ADOPTED DETAIL -->
<div class="modal fade" id="adoptedDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:22px;border:none;overflow:hidden;">
            <div class="modal-header" style="background:var(--navy);padding:18px 24px;">
                <h5 class="modal-title" style="color:var(--gold);font-weight:800;font-size:0.88rem;letter-spacing:1px;">
                    <i class="fas fa-award me-2"></i>Adoption Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-5" style="border-right:1px solid #e2e8f0;">
                        <img id="amd-img" src="" alt="" style="width:100%;height:200px;object-fit:cover;">
                        <div class="p-4">
                            <div style="font-size:0.6rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#94a3b8;margin-bottom:6px;">Animal</div>
                            <div id="amd-name"    style="font-size:1.1rem;font-weight:800;color:var(--navy);"></div>
                            <div id="amd-species" style="font-size:0.75rem;color:#64748b;margin-bottom:12px;"></div>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="s-pill"><i class="fas fa-venus-mars"></i><span id="amd-gender"></span></span>
                                <span class="s-pill"><i class="fas fa-cake-candles"></i><span id="amd-age"></span></span>
                            </div>
                            <span class="date-badge d-block mb-1"><i class="fas fa-calendar-plus"></i>Added: <span id="amd-added"></span></span>
                            <span class="date-badge d-block" style="background:#f0fdf4;color:#166534;"><i class="fas fa-heart"></i>Adopted: <span id="amd-adopted"></span></span>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="p-4">
                            <div style="font-size:0.6rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#94a3b8;margin-bottom:16px;">Adopter Details</div>
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <img id="amd-avatar" src="" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:3px solid #e2e8f0;">
                                <div id="amd-adopter-name" style="font-size:1rem;font-weight:800;color:var(--navy);"></div>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                <div style="background:#f8fafc;border-radius:10px;padding:12px 14px;">
                                    <div style="font-size:0.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Email</div>
                                    <div id="amd-email" style="font-size:0.8rem;font-weight:600;color:var(--navy);"></div>
                                </div>
                                <div style="background:#f8fafc;border-radius:10px;padding:12px 14px;">
                                    <div style="font-size:0.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Phone</div>
                                    <div id="amd-phone" style="font-size:0.8rem;font-weight:600;color:var(--navy);"></div>
                                </div>
                                <div style="background:#f8fafc;border-radius:10px;padding:12px 14px;">
                                    <div style="font-size:0.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Address</div>
                                    <div id="amd-address" style="font-size:0.8rem;font-weight:600;color:var(--navy);"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleDetails(id) {
    const panel   = document.getElementById('details' + id);
    const chevron = document.getElementById('chevron' + id);
    const isOpen  = panel.classList.toggle('open');
    chevron.style.transform  = isOpen ? 'rotate(180deg)' : 'rotate(0deg)';
    chevron.style.transition = 'transform .2s ease';
}
function openAdoptScheduleModal(request_id) {
    document.getElementById('adopt_schedule_rid').value = request_id;
    new bootstrap.Modal(document.getElementById('adoptScheduleModal')).show();
}
function openScheduleModal(id) {
    document.getElementById('schedule_sid').value = id;
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}
function openReceivedModal(id) {
    document.getElementById('received_sid').value = id;
    document.querySelectorAll('.place-opt').forEach(o => o.classList.remove('selected'));
    document.querySelectorAll('.place-opt')[0].classList.add('selected');
    document.getElementById('place_opt_val').value = 'Available for Adoption';
    new bootstrap.Modal(document.getElementById('receivedModal')).show();
}
function selectPlaceOpt(el, val) {
    document.querySelectorAll('.place-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('place_opt_val').value = val;
}
function openAdoptedModal(name, species, breed, gender, age, img, added, adopted, aName, aEmail, aPhone, aAddress, aAvatar) {
    document.getElementById('amd-img').src               = img;
    document.getElementById('amd-name').textContent      = name;
    document.getElementById('amd-species').textContent   = species + (breed !== '—' ? ' · ' + breed : '');
    document.getElementById('amd-gender').textContent    = gender;
    document.getElementById('amd-age').textContent       = age;
    document.getElementById('amd-added').textContent     = added;
    document.getElementById('amd-adopted').textContent   = adopted;
    document.getElementById('amd-avatar').src            = aAvatar;
    document.getElementById('amd-adopter-name').textContent = aName;
    document.getElementById('amd-email').textContent     = aEmail;
    document.getElementById('amd-phone').textContent     = aPhone;
    document.getElementById('amd-address').textContent   = aAddress;
    new bootstrap.Modal(document.getElementById('adoptedDetailModal')).show();
}
function printPage() {
    var now  = new Date();
    var opts = { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' };
    var el   = document.getElementById('printDateStamp');
    if (el) el.textContent = 'Printed: ' + now.toLocaleDateString('en-GB', opts);
    window.print();
}
(function() {
    var now  = new Date();
    var opts = { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' };
    var el   = document.getElementById('printDateStamp');
    if (el) el.textContent = 'Printed: ' + now.toLocaleDateString('en-GB', opts);
})();
</script>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
</body>
</html>