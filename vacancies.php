<?php
session_start();
include 'db_config.php';
include 'navbar.php';

$u_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$u_name = $u_email = "";
if ($u_id) {
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, email FROM users WHERE id = $u_id"));
    if ($r) { $u_name = $r['full_name']; $u_email = $r['email']; }
}

$modal_message = $modal_type = "";
$open_modal_id = null;

if (isset($_POST['submit_application']) && $u_id) {
    $job_id     = intval($_POST['job_id']);
    $phone      = mysqli_real_escape_string($conn, $_POST['phone']);
    $experience = mysqli_real_escape_string($conn, $_POST['experience']);
    $cv_link    = mysqli_real_escape_string($conn, $_POST['cv_link']);
    $open_modal_id = $job_id;
    $check = $conn->query("SELECT id FROM job_applications WHERE job_id=$job_id AND user_id=$u_id");
    if ($check->num_rows > 0) {
        $modal_message = "You have already applied for this position.";
        $modal_type = "warning";
    } else {
        $ne = mysqli_real_escape_string($conn, $u_name);
        $ee = mysqli_real_escape_string($conn, $u_email);
        $sql = "INSERT INTO job_applications (job_id,user_id,full_name,email,phone,experience_details,cv_link)
                VALUES ('$job_id','$u_id','$ne','$ee','$phone','$experience','$cv_link')";
        if (mysqli_query($conn, $sql)) {
            $modal_message = "Application submitted successfully!";
            $modal_type    = "success";
        } else {
            $modal_message = "Something went wrong. Please try again.";
            $modal_type    = "danger";
        }
    }
}

$cat = isset($_GET['cat']) ? $_GET['cat'] : 'All Roles';
$q   = $cat == 'All Roles'
     ? "SELECT * FROM vacancies WHERE status='active' ORDER BY id DESC"
     : "SELECT * FROM vacancies WHERE status='active' AND category='".mysqli_real_escape_string($conn,$cat)."' ORDER BY id DESC";
$jobs = [];
$res  = mysqli_query($conn, $q);
while ($row = mysqli_fetch_assoc($res)) $jobs[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vacancies | Heartbeat Heaven</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Playfair+Display:ital,wght@1,700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
:root { --gold:#B8860B; --navy:#0a1329; --border:#e5e7eb; }
body { font-family:'DM Sans',sans-serif; background:#f4f5f7; }

/* Hero */
.vacancy-header {
    background: linear-gradient(rgba(10,19,41,.75),rgba(10,19,41,.75)),
                url('https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&q=80&w=2000') center/cover;
    color:#fff; padding:100px 0;
}
.hero-title { font-family:'Playfair Display',serif; font-weight:700; }

/* Filter bar */
.filter-bar { background:var(--navy); padding:12px 0; }
.filter-bar a {
    font-family:'Montserrat',sans-serif; font-size:.78rem; font-weight:600;
    color:rgba(255,255,255,.6); border:1px solid rgba(255,255,255,.15);
    padding:6px 18px; border-radius:50px; text-decoration:none; transition:.2s;
}
.filter-bar a.active, .filter-bar a:hover { background:var(--gold); border-color:var(--gold); color:#fff; }

/* Cards */
.job-card {
    background:#fff; border:1px solid var(--border); border-left:4px solid var(--border);
    border-radius:14px; padding:24px 26px; transition:.25s;
}
.job-card:hover { border-left-color:var(--gold); box-shadow:0 8px 28px rgba(0,0,0,.08); transform:translateY(-3px); }
.job-badge {
    font-family:'Montserrat',sans-serif; font-size:.65rem; font-weight:700;
    letter-spacing:.8px; text-transform:uppercase;
    background:#fef3c7; color:#78450a; padding:4px 12px; border-radius:50px;
}
.job-title { font-family:'Montserrat',sans-serif; font-size:1rem; font-weight:700; color:var(--navy); }
.job-meta { font-size:.8rem; color:#6b7280; }
.job-meta i { color:var(--gold); }
.btn-view {
    font-family:'Montserrat',sans-serif; font-size:.75rem; font-weight:700;
    padding:8px 20px; border-radius:50px; background:var(--navy);
    color:var(--gold); border:2px solid var(--navy); cursor:pointer; transition:.2s;
}
.btn-view:hover { background:transparent; color:var(--navy); }

/* Modal overlay */
.job-modal-wrap {
    position:fixed; inset:0; z-index:99999;
    display:none; overflow-y:auto;
    background:rgba(10,19,41,.6); padding:80px 16px 48px;
}
.job-modal-wrap.show { display:block; }
.job-modal-box { background:#fff; border-radius:18px; max-width:740px; margin:0 auto; overflow:hidden; box-shadow:0 25px 60px rgba(0,0,0,.2); }

/* Modal header */
.modal-hd { background:var(--navy); padding:22px 28px; display:flex; justify-content:space-between; align-items:flex-start; }
.modal-hd h5 { font-family:'Montserrat',sans-serif; font-size:.95rem; font-weight:700; color:#fff; margin:0 0 3px; }
.modal-hd small { color:rgba(255,255,255,.5); font-size:.75rem; }
.modal-close { background:rgba(255,255,255,.12); border:none; color:#fff; width:30px; height:30px; border-radius:50%; font-size:1rem; cursor:pointer; flex-shrink:0; margin-left:14px; transition:.2s; }
.modal-close:hover { background:rgba(255,255,255,.25); }

/* Modal body */
.modal-bd { padding:26px 28px; }
.chip { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:50px; background:#f3f4f6; border:1px solid var(--border); font-size:.75rem; font-weight:600; font-family:'Montserrat',sans-serif; }
.chip i { color:var(--gold); font-size:.7rem; }
.section-tag { font-family:'Montserrat',sans-serif; font-size:.65rem; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:var(--gold); }
.desc-text { font-size:.875rem; line-height:1.85; color:#444; white-space:pre-line; }

/* Form */
.mf-label { display:block; font-family:'Montserrat',sans-serif; font-size:.65rem; font-weight:700; letter-spacing:.8px; text-transform:uppercase; color:#6b7280; margin-bottom:5px; }
.mf-ctrl { width:100%; padding:10px 13px; border:1px solid var(--border); border-radius:9px; background:#f9fafb; font-family:'DM Sans',sans-serif; font-size:.875rem; transition:.2s; }
.mf-ctrl:focus { outline:none; border-color:var(--gold); background:#fff; }
.mf-ctrl:disabled { opacity:.55; }
textarea.mf-ctrl { resize:vertical; }
.btn-submit { font-family:'Montserrat',sans-serif; font-size:.78rem; font-weight:700; letter-spacing:.8px; text-transform:uppercase; padding:12px 40px; border-radius:50px; background:var(--gold); color:#fff; border:2px solid var(--gold); cursor:pointer; transition:.2s; }
.btn-submit:hover { background:transparent; color:var(--gold); }
</style>
</head>
<body>

<!-- Hero -->
<header class="vacancy-header text-center">
    <div class="container">
        <h2 class="display-4 hero-title">Opportunities</h2>
        <p class="lead mt-2" style="font-family:'Montserrat',sans-serif; opacity:.85;">Join us in making a difference for every life we touch.</p>
    </div>
</header>

<!-- Filter bar -->
<div class="filter-bar">
    <div class="container d-flex gap-2 flex-wrap">
        <?php foreach(['All Roles','Medical','Rescue','Management'] as $f): ?>
        <a href="vacancies.php?cat=<?= urlencode($f) ?>" class="<?= $cat==$f?'active':'' ?>"><?= $f ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Content -->
<div class="container py-5">
    <div class="row g-4">

        <!-- Sidebar -->
        <div class="col-lg-3">
            <div style="position:sticky; top:90px;">
                <p class="mb-1" style="font-family:'Montserrat',sans-serif; font-size:.65rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:var(--gold);">Open Positions</p>
                <h2 style="font-family:'Playfair Display',serif; font-size:1.9rem; color:var(--navy); line-height:1.2;">Join Our<br>Team</h2>
                <p class="text-muted mt-2" style="font-size:.88rem;">Passionate about animal welfare? We'd love to meet you.</p>
                <span class="badge bg-light text-secondary border mt-1" style="font-family:'Montserrat',sans-serif; font-size:.72rem;">
                    <?= count($jobs) ?> <?= count($jobs)==1?'role':'roles' ?> available
                </span>
            </div>
        </div>

        <!-- Job list -->
        <div class="col-lg-9">
            <?php if ($jobs): ?>
                <?php foreach ($jobs as $j): ?>
                <div class="job-card mb-3">
                    <span class="job-badge"><?= htmlspecialchars($j['job_type']) ?></span>
                    <h3 class="job-title mt-2 mb-1"><?= htmlspecialchars($j['title']) ?></h3>
                    <div class="job-meta d-flex flex-wrap gap-3 mb-2">
                        <span><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($j['location']) ?></span>
                        <span><i class="fas fa-tag me-1"></i><?= htmlspecialchars($j['category']) ?></span>
                        <span><i class="fas fa-calendar me-1"></i><?= date('M d, Y', strtotime($j['posted_date'])) ?></span>
                    </div>
                    <p class="text-muted mb-0" style="font-size:.85rem;"><?= substr(htmlspecialchars($j['description']),0,180) ?>...</p>
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <span style="font-family:'Montserrat',sans-serif; font-size:.78rem; font-weight:700; color:var(--navy);">
                            <i class="fas fa-coins me-1" style="color:var(--gold);"></i><?= htmlspecialchars($j['salary']) ?>
                        </span>
                        <button class="btn-view" onclick="openModal('m<?= $j['id'] ?>')">Apply Now &rarr;</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                    <h5 style="font-family:'Montserrat',sans-serif;">No openings under <em><?= htmlspecialchars($cat) ?></em></h5>
                    <p class="text-muted">Check back soon.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>

<!-- Modals — direct children of body, nothing can clip them -->
<?php foreach ($jobs as $j): ?>
<div class="job-modal-wrap <?= $open_modal_id==$j['id']?'show':'' ?>" id="m<?= $j['id'] ?>" onclick="if(event.target===this)closeModal('m<?= $j['id'] ?>')">
    <div class="job-modal-box">

        <div class="modal-hd">
            <div>
                <h5><?= htmlspecialchars($j['title']) ?></h5>
                <small>Heartbeat Heaven Animal Welfare Foundation</small>
            </div>
            <button class="modal-close" onclick="closeModal('m<?= $j['id'] ?>')">&times;</button>
        </div>

        <div class="modal-bd">

            <?php if ($open_modal_id==$j['id'] && $modal_message): ?>
            <div class="alert alert-<?= $modal_type ?> border-0 rounded-3 mb-4" style="font-size:.875rem;"><?= htmlspecialchars($modal_message) ?></div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="chip"><i class="fas fa-tag"></i><?= htmlspecialchars($j['category']) ?></span>
                <span class="chip"><i class="fas fa-briefcase"></i><?= htmlspecialchars($j['job_type']) ?></span>
                <span class="chip"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($j['location']) ?></span>
                <span class="chip"><i class="fas fa-coins"></i><?= htmlspecialchars($j['salary']) ?></span>
            </div>

            <p class="section-tag mb-2">Responsibilities</p>
            <div class="desc-text mb-4"><?= htmlspecialchars($j['description']) ?></div>

            <hr class="my-4">

            <h6 class="text-center mb-4" style="font-family:'Playfair Display',serif; font-size:1.1rem; color:var(--navy);">Submit Your Application</h6>

            <?php if ($u_id): ?>
            <form method="POST">
                <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="mf-label">Full Name</label>
                        <input class="mf-ctrl" type="text" value="<?= htmlspecialchars($u_name) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="mf-label">Email</label>
                        <input class="mf-ctrl" type="email" value="<?= htmlspecialchars($u_email) ?>" disabled>
                    </div>
                    <div class="col-12">
                        <label class="mf-label">Phone</label>
                        <input class="mf-ctrl" type="text" name="phone" placeholder="+880..." required>
                    </div>
                    <div class="col-12">
                        <label class="mf-label">Cover Note / Experience</label>
                        <textarea class="mf-ctrl" name="experience" rows="4" placeholder="Why are you a great fit?" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="mf-label">CV Link (Drive / Dropbox)</label>
                        <input class="mf-ctrl" type="url" name="cv_link" placeholder="https://drive.google.com/..." required>
                    </div>
                    <div class="col-12 text-center pt-2 pb-1">
                        <button type="submit" name="submit_application" class="btn-submit">Submit Application</button>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <div class="text-center py-4 bg-light rounded-3">
                <p class="mb-0 text-muted">Please <a href="login.php" class="fw-bold text-dark">log in</a> to apply.</p>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(id){ var e=document.getElementById(id); e.classList.add('show'); document.body.style.overflow='hidden'; e.scrollTop=0; }
function closeModal(id){ document.getElementById(id).classList.remove('show'); document.body.style.overflow=''; }
document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ document.querySelectorAll('.job-modal-wrap.show').forEach(function(m){ m.classList.remove('show'); }); document.body.style.overflow=''; }});
</script>
</body>
</html>