<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- 1. update ---
if (isset($_POST['update_asset'])) {
    $id = $conn->real_escape_string($_POST['id']);
    $name = $conn->real_escape_string($_POST['name']);
    $species = $conn->real_escape_string($_POST['species']);
    $breed = $conn->real_escape_string($_POST['breed']);
    $age = $conn->real_escape_string($_POST['age']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $status = $conn->real_escape_string($_POST['status']);

    $is_vaccinated = ($_POST['is_vaccinated'] == 'Yes') ? 1 : 0;
    $is_spayed_neutered = ($_POST['is_spayed_neutered'] == 'Yes') ? 1 : 0;

    $vet_notes = $conn->real_escape_string($_POST['vet_notes']);
    $med_history = $conn->real_escape_string($_POST['medical_history']);
    $med_clearance = $conn->real_escape_string($_POST['medical_clearance_status']);

    // --- Image Upload Handling ---
    $image_sql = "";
    if (isset($_FILES['animal_image']) && $_FILES['animal_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $file_type = $_FILES['animal_image']['type'];

        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'uploads/animals/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext = pathinfo($_FILES['animal_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'animal_' . $id . '_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['animal_image']['tmp_name'], $upload_path)) {
                $image_sql = ", image_path='$upload_path'";
            }
        }
    }

    // Whitelist image_focus
    $allowed_focus = ['center','top center','bottom center','center left','center right',
                      'top left','top right','bottom left','bottom right'];
    $image_focus   = in_array($_POST['image_focus'] ?? 'center', $allowed_focus)
                     ? $_POST['image_focus'] : 'center';

    $update_sql = "UPDATE animals SET 
                    name='$name', species='$species', breed='$breed', 
                    age='$age', gender='$gender', status='$status', 
                    is_vaccinated='$is_vaccinated', 
                    is_spayed_neutered='$is_spayed_neutered',
                    vet_notes='$vet_notes', medical_history='$med_history',
                    medical_clearance_status='$med_clearance',
                    image_focus='$image_focus'
                    $image_sql
                   WHERE id='$id'";

    if ($conn->query($update_sql)) {
        header("Location: view_animal_details.php?id=$id&status=updated");
        exit();
    }
}

// --- 2. fetch ---
if (isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $query = "SELECT a.*, r.user_id as applicant_id, u.full_name, u.email, u.phone 
              FROM animals a 
              LEFT JOIN adoption_requests r ON a.id = r.animal_id AND r.status = 'Pending'
              LEFT JOIN users u ON r.user_id = u.id
              WHERE a.id = '$id'";
    $result = $conn->query($query);
    $animal = $result->fetch_assoc();

    if (!$animal) {
        die("<div class='container mt-5 alert alert-danger text-center'>Asset not found!</div>");
    }
} else {
    header("Location: manage_animals.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($animal['name']); ?> | Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --navy: #0a1329; --gold: #B8860B; }
        body { background-color: #f1f5f9; font-family: 'Montserrat', sans-serif; }
        .admin-hero {
            background: linear-gradient(rgba(10, 19, 41, 0.85), rgba(10, 19, 41, 0.85)), 
                        url('https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&q=80&w=2000');
            background-size: cover; background-position: center; color: white; padding: 100px 0 70px; text-align: center;
        }
        .modal { z-index: 9999 !important; }
        .modal-backdrop { z-index: 9998 !important; }
        .bg-navy { background-color: var(--navy) !important; }
        .main-content-wrapper { margin-top: -50px; padding-bottom: 80px; }
        .detail-card { background: white; border-radius: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); overflow: hidden; }
        .img-sidebar { width: 100%; height: 100%; min-height: 480px; object-fit: cover; border-radius: 20px; }
        .spec-pill { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 15px; padding: 15px; height: 100%; }
        .label-text { font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .value-text { font-size: 1rem; font-weight: 700; color: var(--navy); }
        .status-badge { position: absolute; top: 20px; left: 20px; background: rgba(10, 19, 41, 0.9); color: white; padding: 8px 20px; border-radius: 50px; font-size: 0.8rem; }
        .note-box { background: #f8fafc; border-left: 4px solid var(--navy); padding: 20px; border-radius: 12px; height: 100%; }
        .btn-update { background: var(--navy); color: white; border-radius: 50px; padding: 15px; font-weight: 700; width: 100%; border: none; transition: 0.3s; }
        .btn-update:hover { background: var(--gold); transform: translateY(-3px); }
        .img-preview-box { width: 100%; height: 160px; object-fit: cover; border-radius: 12px; border: 2px dashed #cbd5e1; }
        .upload-label { cursor: pointer; display: block; text-align: center; padding: 10px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; transition: 0.2s; }
        .upload-label:hover { border-color: var(--navy); background: #f1f5f9; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<section class="admin-hero">
    <div class="container">
        <h1 class="fw-800">Heartbeat Heaven Management</h1>
        <p class="opacity-75">Viewing: <?= htmlspecialchars($animal['name']); ?></p>
    </div>
</section>

<div class="main-content-wrapper">
    <div class="container">
        <?php if(isset($_GET['status']) && $_GET['status'] == 'updated'): ?>
            <div class="alert alert-success rounded-pill border-0 shadow-sm px-4 mb-4">
                <i class="fas fa-check-circle me-2"></i> Database updated successfully!
            </div>
        <?php endif; ?>

        <div class="detail-card p-4 p-lg-5">
            <div class="row g-5">
                <div class="col-lg-5">
                    <div class="position-relative h-100">
                        <img src="<?= $animal['image_path'] ?: 'default.jpg'; ?>" class="img-sidebar shadow-sm">
                        <div class="status-badge"><?= $animal['status']; ?></div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="fw-800 text-navy mb-0"><?= htmlspecialchars($animal['name']); ?></h2>
                        <a href="manage_animals.php" class="btn btn-outline-dark btn-sm rounded-pill px-3">Back</a>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-3"><div class="spec-pill"><span class="label-text">Species</span><span class="value-text"><?= $animal['species']; ?></span></div></div>
                        <div class="col-md-3"><div class="spec-pill"><span class="label-text">Breed</span><span class="value-text"><?= $animal['breed'] ?: 'Mixed'; ?></span></div></div>
                        <div class="col-md-3"><div class="spec-pill"><span class="label-text">Gender</span><span class="value-text"><?= $animal['gender']; ?></span></div></div>
                        <div class="col-md-3"><div class="spec-pill"><span class="label-text">Age Group</span><span class="value-text"><?= $animal['age']; ?></span></div></div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="spec-pill border-success bg-white d-flex align-items-center">
                                <i class="fas fa-syringe text-success me-3 fs-4"></i>
                                <div>
                                    <span class="label-text">Vaccination</span>
                                    <span class="value-text"><?= ($animal['is_vaccinated'] == 1) ? 'Up to Date (Yes)' : 'Pending (No)'; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="spec-pill border-primary bg-white d-flex align-items-center">
                                <i class="fas fa-cut text-primary me-3 fs-4"></i>
                                <div>
                                    <span class="label-text">Spay / Neuter</span>
                                    <span class="value-text"><?= ($animal['is_spayed_neutered'] == 1) ? 'Fixed (Yes)' : 'Not Fixed (No)'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-5">
                        <div class="col-md-6"><div class="note-box border-danger"><h6 class="fw-bold mb-2 text-danger small">MEDICAL HISTORY</h6><p class="small mb-0"><?= nl2br(htmlspecialchars($animal['medical_history'])); ?></p></div></div>
                        <div class="col-md-6"><div class="note-box border-success"><h6 class="fw-bold mb-2 text-success small">VET NOTES</h6><p class="small mb-0 italic"><?= nl2br(htmlspecialchars($animal['vet_notes'])); ?></p></div></div>
                    </div>

                    <button class="btn-update shadow-sm" data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header bg-navy text-white p-4">
                    <h5 class="modal-title fw-bold">Update Asset Information</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" value="<?= $animal['id']; ?>">
                    <div class="row g-3">

                        <!-- Image Upload -->
                        <div class="col-md-12">
                            <label class="small fw-bold mb-2">Animal Photo</label>
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <img id="imgPreview"
                                         src="<?= $animal['image_path'] ?: 'default.jpg'; ?>"
                                         class="img-preview-box">
                                </div>
                                <div class="col-md-8">
                                    <label for="animal_image" class="upload-label">
                                        <i class="fas fa-cloud-upload-alt fs-3 text-secondary mb-2 d-block"></i>
                                        <span class="small fw-bold text-secondary">Click to upload new image</span><br>
                                        <span class="text-muted" style="font-size:0.75rem">JPG, PNG, WEBP, GIF — leave blank to keep current</span>
                                    </label>
                                    <input type="file" name="animal_image" id="animal_image" class="d-none" accept="image/*">
                                </div>
                            </div>
                        </div>

                        <!-- Image Focus Picker -->
                        <div class="col-md-12">
                            <label class="small fw-bold mb-2">
                                <i class="fas fa-crop-alt me-1" style="color:var(--gold);"></i>
                                Image Focus Point
                            </label>
                            <small class="text-muted d-block mb-2" style="font-size:0.75rem;">
                                Choose which part of the photo stays visible in the card on the adoption page.
                            </small>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;margin-bottom:10px;">
                                <?php
                                $focus_options = [
                                    'top left'     => '↖ Top Left',
                                    'top center'   => '↑ Top',
                                    'top right'    => '↗ Top Right',
                                    'center left'  => '← Left',
                                    'center'       => '⊕ Center',
                                    'center right' => '→ Right',
                                    'bottom left'  => '↙ Bot Left',
                                    'bottom center'=> '↓ Bottom',
                                    'bottom right' => '↘ Bot Right',
                                ];
                                $current_focus = $animal['image_focus'] ?? 'center';
                                foreach ($focus_options as $val => $label): ?>
                                <button type="button"
                                    onclick="setFocus('<?= $val ?>')"
                                    id="focus_btn_<?= str_replace(' ','_',$val) ?>"
                                    style="padding:6px 4px;font-size:0.7rem;border:1.5px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;transition:all .15s;font-weight:600;color:#555;">
                                    <?= $label ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="image_focus" id="image_focus_input" value="<?= htmlspecialchars($current_focus) ?>">
                            <label class="small text-muted mb-1">Live preview (how it looks on adoption page):</label>
                            <div style="width:100%;height:140px;overflow:hidden;border-radius:10px;border:2px solid var(--gold);position:relative;background:#eee;">
                                <img id="focus_preview_img"
                                     src="<?= htmlspecialchars($animal['image_path'] ?: 'default.jpg') ?>"
                                     style="width:100%;height:100%;object-fit:cover;object-position:<?= htmlspecialchars($current_focus) ?>;transition:object-position .3s;">
                                <div style="position:absolute;bottom:6px;right:8px;background:rgba(0,0,0,0.55);color:#fff;font-size:0.65rem;padding:2px 8px;border-radius:20px;" id="focus_label_display">
                                    Focus: <?= ucwords($current_focus) ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6"><label class="small fw-bold">Name</label><input type="text" name="name" class="form-control rounded-pill" value="<?= $animal['name']; ?>" required></div>
                        <div class="col-md-6"><label class="small fw-bold">Species</label><input type="text" name="species" class="form-control rounded-pill" value="<?= $animal['species']; ?>" required></div>
                        <div class="col-md-4"><label class="small fw-bold">Breed</label><input type="text" name="breed" class="form-control rounded-pill" value="<?= $animal['breed']; ?>"></div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Gender</label>
                            <select name="gender" class="form-select rounded-pill">
                                <option value="Male" <?= $animal['gender']=='Male'?'selected':''; ?>>Male</option>
                                <option value="Female" <?= $animal['gender']=='Female'?'selected':''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="small fw-bold">Age Group</label><input type="text" name="age" class="form-control rounded-pill" value="<?= $animal['age']; ?>"></div>

                        <div class="col-md-6">
                            <label class="small fw-bold">Vaccination Status</label>
                            <select name="is_vaccinated" class="form-select rounded-pill">
                                <option value="Yes" <?= ($animal['is_vaccinated'] == 1) ? 'selected' : ''; ?>>Yes (Vaccinated)</option>
                                <option value="No" <?= ($animal['is_vaccinated'] == 0) ? 'selected' : ''; ?>>No (Pending)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Spay/Neuter Status</label>
                            <select name="is_spayed_neutered" class="form-select rounded-pill">
                                <option value="Yes" <?= ($animal['is_spayed_neutered'] == 1) ? 'selected' : ''; ?>>Yes (Fixed)</option>
                                <option value="No" <?= ($animal['is_spayed_neutered'] == 0) ? 'selected' : ''; ?>>No (Not Fixed)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">Medical Clearance</label>
                            <select name="medical_clearance_status" class="form-select rounded-pill">
                                <option value="Cleared" <?= $animal['medical_clearance_status']=='Cleared'?'selected':''; ?>>Cleared</option>
                                <option value="In Progress" <?= $animal['medical_clearance_status']=='In Progress'?'selected':''; ?>>In Progress</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Operational Status</label>
                            <select name="status" class="form-select rounded-pill">
                                <option value="Available for Adoption" <?= $animal['status']=='Available for Adoption'?'selected':''; ?>>Available for Adoption</option>
                                <option value="Resident of Sanctuary" <?= $animal['status']=='Resident of Sanctuary'?'selected':''; ?>>Resident of Sanctuary</option>
                            </select>
                        </div>
                        <div class="col-md-12"><label class="small fw-bold">Medical History</label><textarea name="medical_history" class="form-control rounded-4" rows="2"><?= $animal['medical_history']; ?></textarea></div>
                        <div class="col-md-12"><label class="small fw-bold">Vet Notes</label><textarea name="vet_notes" class="form-control rounded-4" rows="2"><?= $animal['vet_notes']; ?></textarea></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_asset" class="btn btn-dark rounded-pill px-5">Save Information</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php include 'chat_widget.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Live image preview before upload — also updates focus preview
    document.getElementById('animal_image').addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('imgPreview').src = e.target.result;
                document.getElementById('focus_preview_img').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    function setFocus(val) {
        document.getElementById('image_focus_input').value = val;
        document.getElementById('focus_preview_img').style.objectPosition = val;
        document.getElementById('focus_label_display').textContent = 'Focus: ' + val.replace(/\b\w/g, l => l.toUpperCase());
        document.querySelectorAll('[id^="focus_btn_"]').forEach(function(btn) {
            btn.style.background  = '#fff';
            btn.style.borderColor = '#ddd';
            btn.style.color       = '#555';
        });
        var activeBtn = document.getElementById('focus_btn_' + val.replace(/ /g, '_'));
        if (activeBtn) {
            activeBtn.style.background  = '#0a1329';
            activeBtn.style.borderColor = 'var(--gold)';
            activeBtn.style.color       = '#fff';
        }
    }

    // Highlight saved focus when modal opens
    document.getElementById('editModal').addEventListener('shown.bs.modal', function () {
        var saved = document.getElementById('image_focus_input').value || 'center';
        setFocus(saved);
    });
</script>
</body>
</html>