<?php
session_start();
include 'db_config.php';

$u_id    = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$u_name  = "";
$u_email = "";

if ($u_id) {
    $user_result = mysqli_query($conn, "SELECT full_name, email FROM users WHERE id = $u_id");
    if ($user_data = mysqli_fetch_assoc($user_result)) {
        $u_name  = $user_data['full_name'];
        $u_email = $user_data['email'];
    }
}

if (isset($_GET['id'])) {
    $id         = intval($_GET['id']);
    $job_result = mysqli_query($conn, "SELECT * FROM vacancies WHERE id = $id");
    $job        = mysqli_fetch_assoc($job_result);
    if (!$job) { header("Location: vacancies.php"); exit(); }
} else {
    header("Location: vacancies.php"); exit();
}

$message = "";
if (isset($_POST['submit_application']) && $u_id) {
    $phone      = mysqli_real_escape_string($conn, $_POST['phone']);
    $experience = mysqli_real_escape_string($conn, $_POST['experience']);
    $cv_link    = mysqli_real_escape_string($conn, $_POST['cv_link']);

    $check = $conn->query("SELECT id FROM job_applications WHERE job_id = $id AND user_id = $u_id");
    if ($check->num_rows > 0) {
        $message = "<div class='alert alert-warning border-0 shadow-sm mb-4 rounded-4'>You have already applied for this position.</div>";
    } else {
        $u_name_esc  = mysqli_real_escape_string($conn, $u_name);
        $u_email_esc = mysqli_real_escape_string($conn, $u_email);
        $sql = "INSERT INTO job_applications (job_id, user_id, full_name, email, phone, experience_details, cv_link)
                VALUES ('$id', '$u_id', '$u_name_esc', '$u_email_esc', '$phone', '$experience', '$cv_link')";
        if (mysqli_query($conn, $sql)) {
            $message = "<div class='alert alert-success border-0 shadow-sm mb-4 rounded-4'>Application submitted successfully!</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> | Praner Tan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f6f8fb; font-family: 'Montserrat', sans-serif; }
        .details-header {
            background: linear-gradient(rgba(10,19,41,0.8), rgba(10,19,41,0.8)),
                        url('https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&q=80&w=2000');
            background-size: cover; background-position: center; color: white; padding: 100px 0;
        }
        .glass-card {
            background: white; border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            border: none; padding: 40px;
            margin-top: -60px; position: relative; z-index: 2;
        }
        .job-meta-box { background: #f8f9fa; border-radius: 15px; padding: 20px; border-left: 4px solid #B8860B; }
        .meta-item i { color: #B8860B; width: 25px; }
        .description-content { line-height: 1.8; color: #444; white-space: pre-line; }
        .form-label { font-size: 0.75rem; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { border-radius: 12px; border: 1px solid #f1f1f1; padding: 12px; background: #f8f9fa; }
        .btn-apply { background: #0a1329; color: #B8860B; border-radius: 50px; padding: 15px 45px; font-weight: 700; border: 2px solid #0a1329; transition: 0.3s; }
        .btn-apply:hover { background: transparent; color: #0a1329; }
        nav { position: relative; z-index: 10; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="details-header text-center">
    <div class="container">
        <h2 class="display-5 fw-bold"><?php echo htmlspecialchars($job['title']); ?></h2>
        <p class="lead" style="font-family:'Playfair Display',serif;font-style:italic;">Praner Tan Animal Welfare Foundation</p>
    </div>
</header>

<div class="container mb-5">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <div class="glass-card">
                <a href="vacancies.php" class="text-muted text-decoration-none small fw-bold mb-4 d-inline-block">
                    <i class="fas fa-chevron-left me-1"></i> BACK TO OPPORTUNITIES
                </a>

                <?php echo $message; ?>

                <div class="row g-4 mb-5">
                    <div class="col-md-6">
                        <div class="job-meta-box h-100">
                            <div class="meta-item mb-2"><i class="fas fa-tag"></i> <strong>Category:</strong> <?php echo htmlspecialchars($job['category']); ?></div>
                            <div class="meta-item"><i class="fas fa-briefcase"></i> <strong>Job Type:</strong> <?php echo htmlspecialchars($job['job_type']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="job-meta-box h-100">
                            <div class="meta-item mb-2"><i class="fas fa-map-marker-alt"></i> <strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></div>
                            <div class="meta-item"><i class="fas fa-coins"></i> <strong>Salary:</strong> <?php echo htmlspecialchars($job['salary']); ?></div>
                        </div>
                    </div>
                </div>

                <h4 class="fw-bold mb-3">Job Responsibilities</h4>
                <div class="description-content mb-5"><?php echo htmlspecialchars($job['description']); ?></div>

                <hr class="my-5 opacity-10">

                <div id="apply-section">
                    <h4 class="fw-bold text-center mb-4">Submit Application</h4>
                    <?php if ($u_id): ?>
                        <form action="" method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($u_name); ?>" readonly disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($u_email); ?>" readonly disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="+880..." required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Cover Note / Experience</label>
                                <textarea name="experience" class="form-control" rows="4" placeholder="Briefly state why you're a good fit..." required></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">CV Link (Drive/Dropbox)</label>
                                <input type="url" name="cv_link" class="form-control" placeholder="https://drive.google.com/..." required>
                            </div>
                            <div class="col-12 text-center mt-4">
                                <button type="submit" name="submit_application" class="btn btn-apply">SUBMIT APPLICATION</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5 bg-light rounded-4 border border-dashed">
                            <p class="mb-0">Please <a href="login.php" class="text-dark fw-bold">Login</a> to apply for this vacancy.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>