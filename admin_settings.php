<?php
session_start();
include 'db_config.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$success = '';
$error = '';

// ── NOTICE CRUD ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_notice'])) {
        $title   = trim($_POST['notice_title'] ?? '');
        $body    = trim($_POST['notice_body']  ?? '');
        $type    = $_POST['notice_type']  ?? 'info';
        $active  = isset($_POST['notice_active']) ? 1 : 0;
        if ($title !== '') {
            $stmt = $conn->prepare("INSERT INTO notices (title, body, type, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssi", $title, $body, $type, $active);
            $stmt->execute();
            $success = 'Notice';
        }
    }

    elseif (isset($_POST['edit_notice'])) {
        $id     = (int)$_POST['notice_id'];
        $title  = trim($_POST['notice_title'] ?? '');
        $body   = trim($_POST['notice_body']  ?? '');
        $type   = $_POST['notice_type']  ?? 'info';
        $active = isset($_POST['notice_active']) ? 1 : 0;
        $stmt   = $conn->prepare("UPDATE notices SET title=?, body=?, type=?, is_active=? WHERE id=?");
        $stmt->bind_param("sssii", $title, $body, $type, $active, $id);
        $stmt->execute();
        $success = 'Notice';
    }

    elseif (isset($_POST['delete_notice'])) {
        $id   = (int)$_POST['notice_id'];
        $stmt = $conn->prepare("DELETE FROM notices WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = 'Notice';
    }

    elseif (isset($_POST['toggle_notice'])) {
        $id  = (int)$_POST['notice_id'];
        $cur = (int)$_POST['current_active'];
        $new = $cur ? 0 : 1;
        $stmt = $conn->prepare("UPDATE notices SET is_active=? WHERE id=?");
        $stmt->bind_param("ii", $new, $id);
        $stmt->execute();
        $success = 'Notice';
    }

    elseif (isset($_POST['section'])) {
        $section = $_POST['section'];
        unset($_POST['section']);

        $checkboxes = [
            'maintenance_mode', 'notify_sos', 'notify_adoption', 'notify_jobs',
            'chatbot_enabled', 'triage_enabled', 'pw_require_upper',
            'pw_require_number', 'pw_require_special'
        ];
        foreach ($checkboxes as $cb) {
            if (!isset($_POST[$cb])) $_POST[$cb] = '0';
        }

        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)");
        foreach ($_POST as $key => $val) {
            $stmt->bind_param("ss", $key, $val);
            $stmt->execute();
        }
        $success = $section;
    }
}

// Fetch settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_val FROM system_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_val'];
}

// Fetch notices
$notices = [];
$nResult = $conn->query("SELECT * FROM notices ORDER BY created_at DESC");
while ($row = $nResult->fetch_assoc()) {
    $notices[] = $row;
}

function s($key, $settings, $default = '') {
    return htmlspecialchars($settings[$key] ?? $default);
}

$editNotice = null;
if (isset($_GET['edit_id'])) {
    $eid = (int)$_GET['edit_id'];
    foreach ($notices as $n) {
        if ($n['id'] === $eid) { $editNotice = $n; break; }
    }
}

$view = $_GET['view'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings | Heartbeat Heaven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --navy: #0a1329;
            --gold: #B8860B;
            --midnight-blue: #191970;
        }

        body { background: #f4f7fa; font-family: 'Inter', sans-serif; margin: 0; }

        /* ── LAYOUT ── */
        .rcd-wrapper  { display: flex; min-height: calc(100vh - 70px); }
        .rcd-sidebar  {
            width: 240px; flex-shrink: 0; background: var(--navy);
            padding: 24px 0; position: sticky; top: 70px;
            height: calc(100vh - 70px); overflow-y: auto;
        }
        .rcd-content  { flex: 1; padding: 32px 28px 60px; min-width: 0; }

        /* ── SIDEBAR ── */
        .sidebar-head {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 12px;
        }
        .sidebar-head span {
            font-size: 0.58rem; font-weight: 800; letter-spacing: 2.5px;
            text-transform: uppercase; color: rgba(255,193,7,0.6); display: block; margin-bottom: 4px;
        }
        .sidebar-head p { color: #fff; font-weight: 700; font-size: 0.88rem; margin: 0; }

        .sidebar-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 20px; text-decoration: none !important;
            color: rgba(255,255,255,0.55); font-size: 0.78rem; font-weight: 600;
            transition: all 0.2s ease; border-left: 3px solid transparent;
        }
        .sidebar-item:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .sidebar-item.active { color: #fff; background: rgba(255,255,255,0.07); border-left-color: var(--gold); }

        .si-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; flex-shrink: 0; background: rgba(255,255,255,0.06);
        }
        .sidebar-item.active .si-icon { background: rgba(184,134,11,0.2); color: var(--gold); }

        /* ── PAGE HEADER ── */
        .rcd-page-header { margin-bottom: 24px; padding-bottom: 18px; border-bottom: 1px solid #e2e8f0; }
        .rcd-page-header h5 { font-weight: 800; font-size: 1rem; color: var(--navy); margin: 0 0 4px; }
        .rcd-page-header p  { font-size: 0.75rem; color: #94a3b8; margin: 0; }
        .rcd-accent-bar { width: 40px; height: 3px; border-radius: 4px; margin-bottom: 10px; }

        /* ── GLASS CARD ── */
        .glass-card {
            background: #fff; border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 28px;
        }
        .glass-card-header {
            padding: 16px 20px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: 12px;
        }
        .glass-card-header .hdr-icon {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: #fff; flex-shrink: 0;
        }
        .glass-card-header h6 {
            font-weight: 800; font-size: 0.78rem;
            text-transform: uppercase; color: var(--navy); margin: 0; letter-spacing: 0.5px;
        }
        .glass-card-body   { padding: 24px 28px; }
        .glass-card-footer {
            padding: 16px 28px; background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: flex-end;
        }

        /* ── FORMS ── */
        .form-label {
            font-size: 0.72rem; font-weight: 800; color: #374151;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
        }
        .form-control, .form-select {
            border-radius: 12px; border: 1.5px solid #e2e8f0;
            padding: 10px 14px; font-size: 0.88rem; color: #1e293b; transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--gold); box-shadow: 0 0 0 3px rgba(184,134,11,0.12);
        }
        .form-hint { font-size: 0.7rem; color: #94a3b8; margin-top: 4px; }

        .input-group-text {
            background: #f8fafc; border: 1.5px solid #e2e8f0;
            border-right: none; border-radius: 12px 0 0 12px;
            color: #94a3b8; font-size: 0.85rem;
        }
        .input-group .form-control { border-left: none; border-radius: 0 12px 12px 0; }
        .input-group .form-control:focus { border-left: none; }

        /* ── TOGGLE ROWS ── */
        .toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 0; border-bottom: 1px solid #f1f5f9;
        }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-label { font-size: 0.85rem; font-weight: 600; color: #1e293b; }
        .toggle-desc  { font-size: 0.72rem; color: #94a3b8; margin-top: 2px; }

        .form-switch .form-check-input {
            width: 44px; height: 24px; border-radius: 50px;
            cursor: pointer; border: none; background-color: #cbd5e1;
        }
        .form-switch .form-check-input:checked { background-color: var(--navy); border-color: var(--navy); }

        /* ── SAVE BUTTON ── */
        .btn-cmd-save {
            background: var(--navy); color: white; border: none;
            border-radius: 10px; font-size: 0.65rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 1px; padding: 10px 22px;
            transition: all 0.2s; cursor: pointer;
        }
        .btn-cmd-save:hover { background: var(--midnight-blue); color: white; transform: translateY(-1px); }

        /* ── GOLD LABEL ── */
        .gold-label {
            font-size: 0.6rem; letter-spacing: 2px; font-weight: 800;
            color: var(--gold); text-transform: uppercase; display: block; margin-bottom: 6px;
        }

        /* ── SEVERITY DOTS ── */
        .sev-preview { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 700; }
        .sev-dot { width: 10px; height: 10px; border-radius: 50%; }

        /* ── NOTICES ── */
        .notice-row {
            background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px;
            padding: 14px 18px; margin-bottom: 10px; transition: box-shadow 0.2s;
        }
        .notice-row:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .notice-row.inactive { opacity: 0.5; }
        .notice-title { font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
        .notice-body-preview { font-size: 0.75rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 400px; }

        .notice-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 9px; border-radius: 20px;
            font-size: 0.63rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .notice-info    { background: #e0f2fe; color: #0369a1; }
        .notice-warning { background: #fef9c3; color: #92400e; }
        .notice-danger  { background: #fee2e2; color: #991b1b; }
        .notice-success { background: #dcfce7; color: #166534; }

        .btn-icon {
            width: 30px; height: 30px; border-radius: 9px; border: none;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.75rem; transition: all 0.15s; cursor: pointer;
        }
        .btn-icon-edit   { background: #f1f5f9; color: #475569; }
        .btn-icon-edit:hover { background: var(--navy); color: white; }
        .btn-icon-del    { background: #fee2e2; color: #991b1b; }
        .btn-icon-del:hover { background: #991b1b; color: white; }
        .btn-icon-toggle { background: #f1f5f9; color: #475569; }
        .btn-icon-toggle:hover { background: #e0f2fe; color: #0369a1; }

        .add-notice-panel {
            border: 2px dashed #e2e8f0; border-radius: 16px;
            padding: 22px; margin-bottom: 20px; background: #fafbfc; transition: border-color 0.2s;
        }
        .add-notice-panel:focus-within { border-color: var(--gold); }
        .panel-title { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; margin-bottom: 14px; }

        /* ── TOAST ── */
        .toast-container { z-index: 9999; }
        .toast-success {
            background: var(--navy); color: white;
            border-radius: 16px; border: 1px solid var(--gold);
        }

        .empty-msg { padding: 40px; text-align: center; color: #94a3b8; font-weight: 600; font-style: italic; }
    </style>
</head>
<body>

<?php include 'chat_widget.php'; ?>
<?php include 'navbar.php'; ?>

<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-4">
    <div id="saveToast" class="toast toast-success align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-bold">
                <i class="fas fa-check-circle me-2" style="color:var(--gold)"></i>
                <span id="toastMsg">Settings saved successfully.</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php if ($success): ?>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('toastMsg').innerText = '<?= htmlspecialchars($success) ?> settings saved successfully.';
        new bootstrap.Toast(document.getElementById('saveToast')).show();
    });
</script>
<?php endif; ?>

<div class="rcd-wrapper">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="rcd-sidebar">
        <div class="sidebar-head">
            <span>Control Panel</span>
            <p>System Settings</p>
        </div>

        <a href="?view=general" class="sidebar-item <?= $view === 'general' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-globe"></i></div>
            General
        </a>

        <a href="?view=smtp" class="sidebar-item <?= $view === 'smtp' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-envelope"></i></div>
            Email / SMTP
        </a>

        <a href="?view=notices" class="sidebar-item <?= $view === 'notices' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-bell"></i></div>
            Notices
            <?php if (count($notices) > 0): ?>
                <span style="margin-left:auto;background:#f59e0b;color:#fff;font-size:0.6rem;font-weight:800;padding:2px 7px;border-radius:20px;line-height:1.6;flex-shrink:0;"><?= count($notices) ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=ai" class="sidebar-item <?= $view === 'ai' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-robot"></i></div>
            AI & Chatbot
        </a>

        <a href="?view=security" class="sidebar-item <?= $view === 'security' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-shield-alt"></i></div>
            Security
        </a>
    </aside>

    <!-- ═══════════ MAIN CONTENT ═══════════ -->
    <main class="rcd-content">

        <?php
        $view_meta = [
            'general'  => ['color' => '#0a1329', 'icon' => 'fa-globe',      'title' => 'General Settings',       'sub' => 'Site identity, contact info, and global options'],
            'smtp'     => ['color' => '#0d6efd', 'icon' => 'fa-envelope',   'title' => 'Email / SMTP',            'sub' => 'Mail server configuration and notification triggers'],
            'notices'  => ['color' => '#f59e0b', 'icon' => 'fa-bell',       'title' => 'Notice Management',       'sub' => 'Create and manage announcements shown on the user portal'],
            'ai'       => ['color' => '#6f42c1', 'icon' => 'fa-robot',      'title' => 'AI & Chatbot',            'sub' => 'Chatbot behaviour, API keys, and triage thresholds'],
            'security' => ['color' => '#ff4d4d', 'icon' => 'fa-shield-alt', 'title' => 'Security Settings',      'sub' => 'Sessions, password policies, and access control'],
        ];
        $meta = $view_meta[$view] ?? $view_meta['general'];
        ?>

        <div class="rcd-page-header">
            <div class="rcd-accent-bar" style="background:<?= $meta['color'] ?>;"></div>
            <h5><i class="fas <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>;"></i><?= $meta['title'] ?></h5>
            <p>Control Panel &rsaquo; <?= $meta['title'] ?></p>
        </div>

        <?php /* ═══ GENERAL ═══ */ if ($view === 'general'): ?>
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid <?= $meta['color'] ?>;">
                <div class="hdr-icon" style="background:var(--navy);"><i class="fas fa-globe"></i></div>
                <div>
                    <span class="gold-label">Configuration</span>
                    <h6>General Settings</h6>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="section" value="General">
                <div class="glass-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Site Name</label>
                            <input type="text" name="site_name" class="form-control" value="<?= s('site_name', $settings, 'Heartbeat Heaven') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tagline</label>
                            <input type="text" name="site_tagline" class="form-control" value="<?= s('site_tagline', $settings, 'Animal Rescue & Sanctuary') ?>">
                            <div class="form-hint">Short subtitle shown below the site name in the navbar.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Site Description</label>
                            <textarea name="site_description" class="form-control" rows="2"><?= s('site_description', $settings, 'Dedicated to providing a safe haven for abandoned animals.') ?></textarea>
                            <div class="form-hint">Short mission statement shown in the footer branding section.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="contact_email" class="form-control" value="<?= s('contact_email', $settings) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="text" name="contact_phone" class="form-control" value="<?= s('contact_phone', $settings) ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Sanctuary Address</label>
                            <input type="text" name="sanctuary_address" class="form-control" value="<?= s('sanctuary_address', $settings) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook URL</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fab fa-facebook"></i></span>
                                <input type="url" name="facebook_url" class="form-control" value="<?= s('facebook_url', $settings) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Instagram URL</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                                <input type="url" name="instagram_url" class="form-control" value="<?= s('instagram_url', $settings) ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">Maintenance Mode</div>
                                    <div class="toggle-desc">Temporarily disable the site for the public while keeping admin access.</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="maintenance_mode" value="1"
                                        <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="glass-card-footer">
                    <button type="submit" class="btn-cmd-save"><i class="fas fa-save me-2"></i>Save General</button>
                </div>
            </form>
        </div>

        <?php /* ═══ SMTP ═══ */ elseif ($view === 'smtp'): ?>
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid #0d6efd;">
                <div class="hdr-icon" style="background:#0d6efd;"><i class="fas fa-envelope"></i></div>
                <div>
                    <span class="gold-label">Notifications</span>
                    <h6>Email / SMTP Settings</h6>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="section" value="Email / SMTP">
                <div class="glass-card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= s('smtp_host', $settings) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" placeholder="587" value="<?= s('smtp_port', $settings, '587') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" name="smtp_username" class="form-control" value="<?= s('smtp_username', $settings) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" name="smtp_password" class="form-control" placeholder="••••••••" value="<?= s('smtp_password', $settings) ?>">
                            <div class="form-hint">Leave blank to keep current password.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Sender Display Name</label>
                            <input type="text" name="smtp_sender_name" class="form-control" value="<?= s('smtp_sender_name', $settings, 'Heartbeat Heaven') ?>">
                            <div class="form-hint">This name appears in the "From" field of all system emails.</div>
                        </div>
                        <div class="col-12 mt-2">
                            <span class="gold-label mb-3">Notification Triggers</span>
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">SOS Status Updates</div>
                                    <div class="toggle-desc">Email user when their SOS is approved or rejected.</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="notify_sos" value="1" <?= ($settings['notify_sos'] ?? '1') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">Adoption Status Updates</div>
                                    <div class="toggle-desc">Email user when their adoption request is approved or rejected.</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="notify_adoption" value="1" <?= ($settings['notify_adoption'] ?? '1') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">Job Application Updates</div>
                                    <div class="toggle-desc">Email applicant when their vacancy application is processed.</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="notify_jobs" value="1" <?= ($settings['notify_jobs'] ?? '1') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="glass-card-footer">
                    <button type="submit" class="btn-cmd-save"><i class="fas fa-save me-2"></i>Save Email Settings</button>
                </div>
            </form>
        </div>

        <?php /* ═══ NOTICES ═══ */ elseif ($view === 'notices'): ?>
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid #f59e0b;">
                <div class="hdr-icon" style="background:#f59e0b;"><i class="fas fa-bell"></i></div>
                <div>
                    <span class="gold-label">User Portal</span>
                    <h6>Notice Management</h6>
                </div>
            </div>
            <div class="glass-card-body">

                <!-- Add / Edit Panel -->
                <div class="add-notice-panel">
                    <div class="panel-title">
                        <?= $editNotice ? '<i class="fas fa-pen me-1"></i> Edit Notice' : '<i class="fas fa-plus me-1"></i> Add New Notice' ?>
                    </div>
                    <form method="POST">
                        <?php if ($editNotice): ?>
                            <input type="hidden" name="notice_id" value="<?= (int)$editNotice['id'] ?>">
                            <input type="hidden" name="edit_notice" value="1">
                        <?php else: ?>
                            <input type="hidden" name="add_notice" value="1">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Notice Title</label>
                                <input type="text" name="notice_title" class="form-control" required
                                    placeholder="e.g. Scheduled Maintenance on Sunday"
                                    value="<?= htmlspecialchars($editNotice['title'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Type / Style</label>
                                <select name="notice_type" class="form-select">
                                    <?php foreach (['info'=>'ℹ Info','warning'=>'⚠ Warning','danger'=>'🚨 Danger','success'=>'✅ Success'] as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= ($editNotice['type'] ?? 'info') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message Body</label>
                                <textarea name="notice_body" class="form-control" rows="2"
                                    placeholder="Detailed notice text shown to users..."><?= htmlspecialchars($editNotice['body'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notice_active" value="1" id="noticeActive"
                                        <?= ($editNotice ? (int)$editNotice['is_active'] : 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label toggle-label" for="noticeActive">Show on User Portal immediately</label>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($editNotice): ?>
                                        <a href="?view=notices" class="btn btn-sm btn-outline-secondary rounded-3">Cancel</a>
                                    <?php endif; ?>
                                    <button type="submit" class="btn-cmd-save">
                                        <i class="fas fa-<?= $editNotice ? 'save' : 'plus' ?> me-2"></i>
                                        <?= $editNotice ? 'Update Notice' : 'Add Notice' ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Notice List -->
                <?php if (empty($notices)): ?>
                    <div class="empty-msg">
                        <i class="fas fa-bell-slash fa-2x mb-2 d-block" style="opacity:.3"></i>
                        No notices yet. Add one above.
                    </div>
                <?php else: ?>
                    <span class="gold-label mb-3">All Notices (<?= count($notices) ?>)</span>
                    <?php foreach ($notices as $n): ?>
                    <div class="notice-row <?= !$n['is_active'] ? 'inactive' : '' ?>">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div style="min-width:0">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <span class="notice-badge notice-<?= htmlspecialchars($n['type']) ?>">
                                        <?= ['info'=>'ℹ Info','warning'=>'⚠ Warning','danger'=>'🚨 Danger','success'=>'✅ Success'][$n['type']] ?? $n['type'] ?>
                                    </span>
                                    <?php if (!$n['is_active']): ?>
                                        <span class="notice-badge" style="background:#f1f5f9;color:#64748b;">Hidden</span>
                                    <?php else: ?>
                                        <span class="notice-badge" style="background:#dcfce7;color:#166534;">Live</span>
                                    <?php endif; ?>
                                    <span style="font-size:0.65rem;color:#cbd5e1;"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></span>
                                </div>
                                <div class="notice-title"><?= htmlspecialchars($n['title']) ?></div>
                                <?php if ($n['body']): ?>
                                    <div class="notice-body-preview"><?= htmlspecialchars($n['body']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="toggle_notice"  value="1">
                                    <input type="hidden" name="notice_id"      value="<?= (int)$n['id'] ?>">
                                    <input type="hidden" name="current_active" value="<?= (int)$n['is_active'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-toggle" title="<?= $n['is_active'] ? 'Hide' : 'Show' ?>">
                                        <i class="fas fa-<?= $n['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                </form>
                                <a href="?view=notices&edit_id=<?= (int)$n['id'] ?>" class="btn-icon btn-icon-edit" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this notice permanently?')">
                                    <input type="hidden" name="delete_notice" value="1">
                                    <input type="hidden" name="notice_id" value="<?= (int)$n['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-del" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>

        <?php /* ═══ AI ═══ */ elseif ($view === 'ai'): ?>
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid #6f42c1;">
                <div class="hdr-icon" style="background:#6f42c1;"><i class="fas fa-robot"></i></div>
                <div>
                    <span class="gold-label">Intelligence</span>
                    <h6>AI & Chatbot Settings</h6>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="section" value="AI & Chatbot">
                <div class="glass-card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">Enable AI Chatbot</div>
                                    <div class="toggle-desc">Show/hide the chat assistant widget on the user portal.</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="chatbot_enabled" value="1" <?= ($settings['chatbot_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">Enable SOS Triage System</div>
                                    <div class="toggle-desc">Automatically score and rank incoming SOS requests by severity.</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="triage_enabled" value="1" <?= ($settings['triage_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Groq API Key</label>
                            <input type="password" name="groq_api_key" class="form-control" placeholder="gsk_••••••••••••••••" value="<?= s('groq_api_key', $settings) ?>">
                            <div class="form-hint">Your Groq API key used to power the AI chatbot. Get one at console.groq.com</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Chatbot System Prompt</label>
                            <textarea name="chatbot_system_prompt" class="form-control" rows="3"><?= s('chatbot_system_prompt', $settings, "You are a helpful assistant for an animal welfare foundation. Answer concisely.") ?></textarea>
                            <div class="form-hint">Instructions that define how the AI chatbot behaves and responds to users.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Chatbot Fallback Message</label>
                            <textarea name="chatbot_fallback_message" class="form-control" rows="2"><?= s('chatbot_fallback_message', $settings, 'Sorry, I am unavailable right now. Please contact us directly.') ?></textarea>
                            <div class="form-hint">Shown when the AI model is unreachable or returns an error.</div>
                        </div>
                        <div class="col-12 mt-2">
                            <span class="gold-label mb-3">SOS Triage Severity Thresholds</span>
                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <span class="sev-preview"><span class="sev-dot" style="background:#198754;box-shadow:0 0 6px #198754"></span>Low Threshold</span>
                                    </label>
                                    <input type="number" name="triage_low_threshold" class="form-control" min="1" max="5" value="<?= s('triage_low_threshold', $settings, '2') ?>">
                                    <div class="form-hint">Scores at or below this = Low severity.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <span class="sev-preview"><span class="sev-dot" style="background:#ff9800;box-shadow:0 0 6px #ff9800"></span>Medium Threshold</span>
                                    </label>
                                    <input type="number" name="triage_medium_threshold" id="triage_medium_threshold" class="form-control" min="1" max="5" value="<?= s('triage_medium_threshold', $settings, '3') ?>">
                                    <div class="form-hint">Scores above Low but at or below this = Medium.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <span class="sev-preview"><span class="sev-dot" style="background:#ff4d4d;box-shadow:0 0 6px #ff4d4d"></span>Critical — Auto</span>
                                    </label>
                                    <input type="number" name="triage_critical_threshold" id="triage_critical_threshold" class="form-control" min="1" max="5" value="<?= s('triage_critical_threshold', $settings, '4') ?>" readonly>
                                    <div class="form-hint">Auto-calculated — scores above Medium = Critical.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="glass-card-footer">
                    <button type="submit" class="btn-cmd-save"><i class="fas fa-save me-2"></i>Save AI Settings</button>
                </div>
            </form>
        </div>

        <?php /* ═══ SECURITY ═══ */ elseif ($view === 'security'): ?>
        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid #ff4d4d;">
                <div class="hdr-icon" style="background:#ff4d4d;"><i class="fas fa-shield-alt"></i></div>
                <div>
                    <span class="gold-label">Access Control</span>
                    <h6>Security Settings</h6>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="section" value="Security">
                <div class="glass-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Session Timeout (minutes)</label>
                            <input type="number" name="session_timeout_minutes" class="form-control" min="5" max="1440" value="<?= s('session_timeout_minutes', $settings, '30') ?>">
                            <div class="form-hint">Users will be logged out after this period of inactivity.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Password Length</label>
                            <input type="number" name="password_min_length" class="form-control" min="6" max="32" value="<?= s('password_min_length', $settings, '8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Activity Log Retention (days)</label>
                            <input type="number" name="activity_log_retention_days" class="form-control" min="7" max="365" value="<?= s('activity_log_retention_days', $settings, '90') ?>">
                            <div class="form-hint">Logs older than this are automatically purged.</div>
                        </div>
                        <div class="col-12 mt-2">
                            <span class="gold-label mb-2">Password Policy</span>
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">Require Uppercase Letter</div>
                                    <div class="toggle-desc">Password must contain at least one uppercase character.</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="pw_require_upper" value="1" <?= ($settings['pw_require_upper'] ?? '1') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">Require Number</div>
                                    <div class="toggle-desc">Password must contain at least one numeric digit.</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="pw_require_number" value="1" <?= ($settings['pw_require_number'] ?? '1') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label">Require Special Character</div>
                                    <div class="toggle-desc">Password must contain at least one special character (e.g. @, #, !).</div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" name="pw_require_special" value="1" <?= ($settings['pw_require_special'] ?? '0') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="glass-card-footer">
                    <button type="submit" class="btn-cmd-save"><i class="fas fa-save me-2"></i>Save Security Settings</button>
                </div>
            </form>
        </div>

        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-calculate critical threshold
    const medInput  = document.getElementById('triage_medium_threshold');
    const critInput = document.getElementById('triage_critical_threshold');
    if (medInput && critInput) {
        medInput.addEventListener('input', () => {
            critInput.value = Math.min((parseInt(medInput.value) || 3) + 1, 5);
        });
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>