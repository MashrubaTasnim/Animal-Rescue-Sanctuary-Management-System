<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$view = $_GET['view'] ?? 'gallery';
$msg  = $_GET['msg']  ?? '';

// ═══ GALLERY LOGIC ═══
if ($view === 'gallery') {
    if (isset($_POST['upload_img'])) {
        $caption    = mysqli_real_escape_string($conn, $_POST['caption']);
        $target_dir = "uploads/gallery/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_ext  = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $file_name = "GAL_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
        $target_file = $target_dir . $file_name;
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $conn->query("INSERT INTO gallery (image_path, caption, status) VALUES ('$target_file', '$caption', 'active')");
                header("Location: manage_contents.php?view=gallery&msg=uploaded"); exit();
            } else { header("Location: manage_contents.php?view=gallery&msg=error"); exit(); }
        } else { header("Location: manage_contents.php?view=gallery&msg=invalid"); exit(); }
    }
    if (isset($_GET['toggle_id'])) {
        $tid        = intval($_GET['toggle_id']);
        $new_status = ($_GET['current'] == 'active') ? 'inactive' : 'active';
        $conn->query("UPDATE gallery SET status = '$new_status' WHERE id = $tid");
        header("Location: manage_contents.php?view=gallery&msg=updated"); exit();
    }
    if (isset($_GET['delete_id'])) {
        $id  = intval($_GET['delete_id']);
        $res = $conn->query("SELECT image_path FROM gallery WHERE id = $id");
        if ($img = $res->fetch_assoc()) {
            if (file_exists($img['image_path'])) { unlink($img['image_path']); }
        }
        $conn->query("DELETE FROM gallery WHERE id = $id");
        header("Location: manage_contents.php?view=gallery&msg=deleted"); exit();
    }
}

// ═══ QUOTES LOGIC ═══
if ($view === 'quotes') {
    if (isset($_POST['add_quote'])) {
        $text   = mysqli_real_escape_string($conn, $_POST['quote_text']);
        $author = mysqli_real_escape_string($conn, $_POST['author_name']);
        $conn->query("INSERT INTO site_quotes (quote_text, author_name, status) VALUES ('$text', '$author', 'active')");
        header("Location: manage_contents.php?view=quotes&msg=added"); exit();
    }
    if (isset($_GET['toggle_id'])) {
        $tid        = intval($_GET['toggle_id']);
        $new_status = ($_GET['current'] == 'active') ? 'inactive' : 'active';
        $conn->query("UPDATE site_quotes SET status = '$new_status' WHERE id = $tid");
        header("Location: manage_contents.php?view=quotes&msg=updated"); exit();
    }
    if (isset($_GET['delete_id'])) {
        $did = intval($_GET['delete_id']);
        $conn->query("DELETE FROM site_quotes WHERE id = $did");
        header("Location: manage_contents.php?view=quotes&msg=deleted"); exit();
    }
}

// ═══ REVIEWS LOGIC ═══
if ($view === 'reviews') {
    if (isset($_GET['approve_id'])) {
        $id = intval($_GET['approve_id']);
        $conn->query("UPDATE testimonials SET status = 'approved' WHERE id = $id");
        header("Location: manage_contents.php?view=reviews&msg=approved"); exit();
    }
    if (isset($_GET['delete_id'])) {
        $id = intval($_GET['delete_id']);
        $conn->query("DELETE FROM testimonials WHERE id = $id");
        header("Location: manage_contents.php?view=reviews&msg=deleted"); exit();
    }
}

// ═══ FETCH DATA ═══
$images  = $conn->query("SELECT * FROM gallery ORDER BY id DESC");
$quotes  = $conn->query("SELECT * FROM site_quotes ORDER BY id DESC");
$reviews = $conn->query("SELECT * FROM testimonials ORDER BY status DESC, created_at DESC");

$cnt_gallery = $images->num_rows;
$cnt_quotes  = $quotes->num_rows;
$cnt_reviews = $conn->query("SELECT id FROM testimonials WHERE status = 'pending'")->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Content Management | Praner Tan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root { --navy:#0a1329; --gold:#B8860B; --midnight-blue:#191970; }
        body { background:#f4f7fa; font-family:'Inter',sans-serif; margin:0; }

        /* ── LAYOUT ── */
        .rcd-wrapper  { display:flex; min-height:calc(100vh - 70px); }
        .rcd-sidebar  {
            width:240px; flex-shrink:0; background:var(--navy);
            padding:24px 0; position:sticky; top:70px;
            height:calc(100vh - 70px); overflow-y:auto;
        }
        .rcd-content  { flex:1; padding:32px 28px 60px; min-width:0; }

        /* ── SIDEBAR ── */
        .sidebar-head {
            padding:0 20px 20px;
            border-bottom:1px solid rgba(255,255,255,0.08);
            margin-bottom:12px;
        }
        .sidebar-head span {
            font-size:0.58rem; font-weight:800; letter-spacing:2.5px;
            text-transform:uppercase; color:rgba(255,193,7,0.6); display:block; margin-bottom:4px;
        }
        .sidebar-head p { color:#fff; font-weight:700; font-size:0.88rem; margin:0; }

        .sidebar-item {
            display:flex; align-items:center; gap:12px;
            padding:11px 20px; text-decoration:none !important;
            color:rgba(255,255,255,0.55); font-size:0.78rem; font-weight:600;
            transition:all 0.2s ease; border-left:3px solid transparent;
        }
        .sidebar-item:hover { color:#fff; background:rgba(255,255,255,0.05); }
        .sidebar-item.active { color:#fff; background:rgba(255,255,255,0.07); border-left-color:var(--gold); }

        .si-icon {
            width:30px; height:30px; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            font-size:0.8rem; flex-shrink:0; background:rgba(255,255,255,0.06);
        }
        .sidebar-item.active .si-icon { background:rgba(184,134,11,0.2); color:var(--gold); }

        .sidebar-badge {
            margin-left:auto; color:#fff;
            font-size:0.6rem; font-weight:800; padding:2px 7px;
            border-radius:20px; line-height:1.6; flex-shrink:0;
        }

        /* ── PAGE HEADER ── */
        .rcd-page-header { margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0; }
        .rcd-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
        .rcd-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
        .rcd-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; }

        /* ── GLASS CARD ── */
        .glass-card {
            background:#fff; border-radius:20px;
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid #e2e8f0; overflow:hidden;
        }
        .glass-card-header {
            padding:16px 20px; border-bottom:1px solid #f1f5f9;
            display:flex; align-items:center; justify-content:space-between;
        }
        .glass-card-header-left { display:flex; align-items:center; gap:10px; }
        .glass-card-header .hdr-icon {
            width:36px; height:36px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:0.85rem; color:#fff; flex-shrink:0;
        }
        .glass-card-header h6 {
            font-weight:800; font-size:0.78rem;
            text-transform:uppercase; color:var(--navy); margin:0; letter-spacing:0.5px;
        }

        /* ── TABLE ── */
        .table thead th {
            font-size:0.72rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.8px; color:#94a3b8; background:#f8fafc;
            padding:14px 20px; border:none;
        }
        .table tbody td { padding:14px 20px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .table tbody tr:last-child td { border-bottom:none; }
        .table tbody tr:hover { background:#fafbfc; }

        .asset-img { width:70px; height:50px; object-fit:cover; border-radius:10px; border:2px solid #f1f5f9; }

        /* ── BUTTONS ── */
        .btn-cmd {
            border-radius:9px; font-weight:800; font-size:0.62rem;
            text-transform:uppercase; padding:8px 14px; border:none; cursor:pointer;
        }
        .btn-icon {
            width:32px; height:32px; border-radius:9px; border:none;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:0.78rem; transition:all 0.15s; cursor:pointer;
        }
        .btn-icon-approve { background:#dcfce7; color:#166534; }
        .btn-icon-approve:hover { background:#166534; color:#fff; }
        .btn-icon-toggle  { background:#f1f5f9; color:#475569; }
        .btn-icon-toggle:hover { background:#e0f2fe; color:#0369a1; }
        .btn-icon-del     { background:#fee2e2; color:#991b1b; }
        .btn-icon-del:hover { background:#991b1b; color:#fff; }

        /* ── BADGES ── */
        .status-pill {
            display:inline-flex; align-items:center; gap:4px;
            padding:3px 10px; border-radius:20px;
            font-size:0.63rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;
        }
        .pill-active   { background:#dcfce7; color:#166534; }
        .pill-inactive { background:#f1f5f9; color:#64748b; }
        .pill-pending  { background:#fef9c3; color:#92400e; }
        .pill-approved { background:#dcfce7; color:#166534; }

        /* ── STARS ── */
        .star-active   { color:#ffc107; font-size:0.75rem; }
        .star-inactive { color:#e2e8f0; font-size:0.75rem; }

        /* ── GOLD LABEL ── */
        .gold-label {
            font-size:0.6rem; letter-spacing:2px; font-weight:800;
            color:var(--gold); text-transform:uppercase; display:block; margin-bottom:6px;
        }

        /* ── MODAL ── */
        .modal-content { border-radius:24px !important; border:none; overflow:hidden; }
        .modal-header  { padding:20px 24px 0; border:none; }
        .modal-body    { padding:20px 24px; }
        .modal-footer  { padding:0 24px 24px; border:none; }
        .form-label {
            font-size:0.72rem; font-weight:800; color:#374151;
            text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;
        }
        .form-control, .form-select {
            border-radius:12px; border:1.5px solid #e2e8f0;
            padding:10px 14px; font-size:0.88rem; color:#1e293b;
        }
        .form-control:focus, .form-select:focus {
            border-color:var(--gold); box-shadow:0 0 0 3px rgba(184,134,11,0.12);
        }

        /* ── ALERT ── */
        .cmd-alert {
            border-radius:14px; border:none; padding:12px 18px;
            font-size:0.78rem; font-weight:700; margin-bottom:20px;
            display:flex; align-items:center; gap:10px;
        }
        .cmd-alert-success { background:#dcfce7; color:#166534; }
        .cmd-alert-danger  { background:#fee2e2; color:#991b1b; }

        .empty-msg { padding:48px; text-align:center; color:#94a3b8; font-weight:600; font-style:italic; }
    </style>
</head>
<body>
<?php include 'chat_widget.php'; ?>
<?php include 'navbar.php'; ?>

<div class="rcd-wrapper">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="rcd-sidebar">
        <div class="sidebar-head">
            <span>Admin Panel</span>
            <p>Content Management</p>
        </div>

        <a href="?view=gallery" class="sidebar-item <?= $view === 'gallery' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-images"></i></div>
            Gallery
            <?php if ($cnt_gallery > 0): ?>
                <span class="sidebar-badge" style="background:#6f42c1;"><?= $cnt_gallery ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=quotes" class="sidebar-item <?= $view === 'quotes' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-quote-left"></i></div>
            Quotes
            <?php if ($cnt_quotes > 0): ?>
                <span class="sidebar-badge" style="background:#0d6efd;"><?= $cnt_quotes ?></span>
            <?php endif; ?>
        </a>

        <a href="?view=reviews" class="sidebar-item <?= $view === 'reviews' ? 'active' : '' ?>">
            <div class="si-icon"><i class="fas fa-star"></i></div>
            Reviews
            <?php if ($cnt_reviews > 0): ?>
                <span class="sidebar-badge" style="background:#ff4d4d;"><?= $cnt_reviews ?></span>
            <?php endif; ?>
        </a>
    </aside>

    <!-- ═══════════ MAIN CONTENT ═══════════ -->
    <main class="rcd-content">

        <?php
        $view_meta = [
            'gallery' => ['color'=>'#6f42c1', 'icon'=>'fa-images',     'title'=>'Gallery Management',  'sub'=>'Upload and manage visual content shown on the public portal'],
            'quotes'  => ['color'=>'#0d6efd', 'icon'=>'fa-quote-left', 'title'=>'Quotes Management',   'sub'=>'Add and toggle inspirational quotes displayed on the site'],
            'reviews' => ['color'=>'#ff4d4d', 'icon'=>'fa-star',       'title'=>'Review Management',   'sub'=>'Moderate and approve user-submitted testimonials'],
        ];
        $meta = $view_meta[$view] ?? $view_meta['gallery'];
        ?>

        <div class="rcd-page-header">
            <div class="rcd-accent-bar" style="background:<?= $meta['color'] ?>;"></div>
            <h5><i class="fas <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>;"></i><?= $meta['title'] ?></h5>
            <p>Content Management &rsaquo; <?= $meta['title'] ?></p>
        </div>

        <?php if ($msg): ?>
        <div class="cmd-alert cmd-alert-success">
            <i class="fas fa-check-circle"></i>
            <?php
            $msgs = [
                'uploaded' => 'Image successfully added to gallery.',
                'deleted'  => 'Item permanently removed.',
                'added'    => 'Quote added successfully.',
                'updated'  => 'Status updated.',
                'approved' => 'Review published successfully.',
                'error'    => 'Upload failed. Please try again.',
                'invalid'  => 'Invalid file type. Please upload an image.',
            ];
            echo $msgs[$msg] ?? 'Action completed successfully.';
            ?>
        </div>
        <?php endif; ?>

        <?php /* ═══════════ GALLERY ═══════════ */ if ($view === 'gallery'): ?>

        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid #6f42c1;">
                <div class="glass-card-header-left">
                    <div class="hdr-icon" style="background:#6f42c1;"><i class="fas fa-images"></i></div>
                    <div>
                        <span class="gold-label">Visual Content</span>
                        <h6>Gallery Hub</h6>
                    </div>
                </div>
                <button class="btn-cmd text-white" style="background:#6f42c1;" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload me-1"></i> Upload Photo
                </button>
            </div>
            <div class="table-responsive">
                <?php $images->data_seek(0); if ($images->num_rows > 0): ?>
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Preview</th>
                            <th>Caption</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $images->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <a href="<?= htmlspecialchars($row['image_path']) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($row['image_path']) ?>" class="asset-img">
                                </a>
                            </td>
                            <td>
                                <div class="fw-bold text-dark" style="font-size:0.82rem;"><?= htmlspecialchars($row['caption'] ?: 'No Caption') ?></div>
                            </td>
                            <td>
                                <span style="font-size:0.72rem; color:#94a3b8;"><i class="fas fa-calendar me-1"></i><?= date('d M Y', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td>
                                <span class="status-pill <?= $row['status'] === 'active' ? 'pill-active' : 'pill-inactive' ?>">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="?view=gallery&toggle_id=<?= $row['id'] ?>&current=<?= $row['status'] ?>"
                                   class="btn-icon btn-icon-toggle me-1" title="Toggle Status">
                                    <i class="fas <?= $row['status'] === 'active' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                </a>
                                <a href="?view=gallery&delete_id=<?= $row['id'] ?>"
                                   onclick="return confirm('Delete this image permanently?')"
                                   class="btn-icon btn-icon-del">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-msg">No images in gallery. Upload one to get started.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upload Modal -->
        <div class="modal fade" id="uploadModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form action="" method="POST" enctype="multipart/form-data" class="modal-content shadow-lg">
                    <div class="modal-header">
                        <div>
                            <span class="gold-label">Visual Content</span>
                            <h5 class="fw-bold m-0 text-dark">Upload to Gallery</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Image File</label>
                            <input type="file" name="image" class="form-control" required>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Caption (Optional)</label>
                            <input type="text" name="caption" class="form-control" placeholder="e.g. Happy puppy after rescue">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="upload_img" class="btn-cmd text-white w-100 py-2" style="background:#6f42c1;">
                            <i class="fas fa-upload me-2"></i>Confirm Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php /* ═══════════ QUOTES ═══════════ */ elseif ($view === 'quotes'): ?>

        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid #0d6efd;">
                <div class="glass-card-header-left">
                    <div class="hdr-icon" style="background:#0d6efd;"><i class="fas fa-quote-left"></i></div>
                    <div>
                        <span class="gold-label">Website Content</span>
                        <h6>Daily Quotes</h6>
                    </div>
                </div>
                <button class="btn-cmd text-white" style="background:#0d6efd;" data-bs-toggle="modal" data-bs-target="#addQuoteModal">
                    <i class="fas fa-plus me-1"></i> Add Quote
                </button>
            </div>
            <div class="table-responsive">
                <?php $quotes->data_seek(0); if ($quotes->num_rows > 0): ?>
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Quote</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $quotes->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4" style="max-width:420px;">
                                <div style="font-size:0.82rem; color:#334155; font-style:italic;">"<?= htmlspecialchars($row['quote_text']) ?>"</div>
                            </td>
                            <td>
                                <div class="fw-bold" style="font-size:0.82rem; color:#0a1329;"><?= htmlspecialchars($row['author_name']) ?></div>
                            </td>
                            <td>
                                <span class="status-pill <?= $row['status'] === 'active' ? 'pill-active' : 'pill-inactive' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="?view=quotes&toggle_id=<?= $row['id'] ?>&current=<?= $row['status'] ?>"
                                   class="btn-icon btn-icon-toggle me-1" title="Toggle Status">
                                    <i class="fas <?= $row['status'] === 'active' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                </a>
                                <a href="?view=quotes&delete_id=<?= $row['id'] ?>"
                                   onclick="return confirm('Delete this quote permanently?')"
                                   class="btn-icon btn-icon-del">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-msg">No quotes yet. Add one to inspire your visitors.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Quote Modal -->
        <div class="modal fade" id="addQuoteModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form action="" method="POST" class="modal-content shadow-lg">
                    <div class="modal-header">
                        <div>
                            <span class="gold-label">Website Content</span>
                            <h5 class="fw-bold m-0 text-dark">Add Inspiration</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Quote Text</label>
                            <textarea name="quote_text" class="form-control" rows="4" required placeholder="Enter the quote..."></textarea>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Author Name</label>
                            <input type="text" name="author_name" class="form-control" placeholder="e.g. Mahatma Gandhi">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_quote" class="btn-cmd text-white w-100 py-2" style="background:#0d6efd;">
                            <i class="fas fa-save me-2"></i>Save Quote
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php /* ═══════════ REVIEWS ═══════════ */ elseif ($view === 'reviews'): ?>

        <div class="glass-card">
            <div class="glass-card-header" style="border-left:4px solid #ff4d4d;">
                <div class="glass-card-header-left">
                    <div class="hdr-icon" style="background:#ff4d4d;"><i class="fas fa-star"></i></div>
                    <div>
                        <span class="gold-label">Community Feedback</span>
                        <h6>Testimonial Manager</h6>
                    </div>
                </div>
                <?php if ($cnt_reviews > 0): ?>
                    <span class="status-pill pill-pending">
                        <i class="fas fa-clock me-1"></i><?= $cnt_reviews ?> Pending
                    </span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <?php $reviews->data_seek(0); if ($reviews->num_rows > 0): ?>
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Author</th>
                            <th>Testimonial</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $reviews->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold" style="font-size:0.82rem; color:#0a1329;"><?= htmlspecialchars($row['user_name']) ?></div>
                                <div style="font-size:0.7rem; color:#94a3b8;"><?= htmlspecialchars($row['user_role']) ?></div>
                            </td>
                            <td style="max-width:300px;">
                                <div style="font-size:0.78rem; color:#64748b; font-style:italic;">"<?= htmlspecialchars($row['message']) ?>"</div>
                                <div style="font-size:0.65rem; color:#cbd5e1; margin-top:4px;">
                                    <i class="fas fa-clock me-1"></i><?= date('d M Y', strtotime($row['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= ($i <= $row['rating']) ? 'star-active' : 'star-inactive' ?>"></i>
                                <?php endfor; ?>
                            </td>
                            <td>
                                <span class="status-pill <?= strtolower($row['status']) === 'pending' ? 'pill-pending' : 'pill-approved' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if (strtolower($row['status']) === 'pending'): ?>
                                    <a href="?view=reviews&approve_id=<?= $row['id'] ?>"
                                       class="btn-icon btn-icon-approve me-1" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?view=reviews&delete_id=<?= $row['id'] ?>"
                                   onclick="return confirm('Delete this testimonial permanently?')"
                                   class="btn-icon btn-icon-del" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-msg">No testimonials found.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>