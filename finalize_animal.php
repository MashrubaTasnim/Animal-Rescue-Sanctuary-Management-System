<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$rescue_id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$rescue_data = [];

if ($rescue_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM rescues WHERE id = ?");
    $stmt->bind_param("i", $rescue_id);
    $stmt->execute();
    $rescue_data = $stmt->get_result()->fetch_assoc();
}

if (!$rescue_data || $rescue_data['status'] === 'Resolved') {
    die("Invalid Case ID or Case already finalized.");
}

// ── Clean vet_notes: don't pre-fill the placeholder default ──
$prefill_vet_notes = '';
$raw_notes = trim($rescue_data['vet_notes'] ?? '');
if (!empty($raw_notes) && $raw_notes !== 'Ready for forever home.') {
    $prefill_vet_notes = $raw_notes;
}
// ─────────────────────────────────────────────────────────────
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Resident Enrollment | Praner Tan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --navy: #0a1329;
            --gold: #B8860B;
        }
        body { background: #f4f7fa; font-family: 'Inter', sans-serif; }

        /* Hero */
        .hero-finalize {
            background: linear-gradient(rgba(10, 19, 41, 0.9), rgba(10, 19, 41, 0.9)),
                        url('https://images.unsplash.com/photo-1548191265-cc70d3d45ba1?auto=format&fit=crop&q=80');
            background-size: cover;
            background-position: center;
            padding: 80px 0;
            color: white;
            text-align: center;
            border-bottom: 5px solid var(--gold);
        }

        .finalize-card {
            background: white;
            border-radius: 40px;
            border: none;
            box-shadow: 0 30px 60px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: -50px;
            position: relative;
            z-index: 10;
        }

        .preview-box {
            background: #000;
            height: 350px;
            border-radius: 25px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border: 5px solid #f8f9fa;
        }
        .preview-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

        .form-label { font-weight: 800; color: var(--navy); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .form-control, .form-select {
            border-radius: 15px;
            padding: 14px;
            border: 1px solid #e2e8f0;
            background: #fcfdfe;
            font-size: 0.95rem;
        }
        .form-control:focus { box-shadow: none; border-color: var(--gold); background: white; }

        .section-title {
            border-left: 5px solid var(--gold);
            padding-left: 15px;
            margin-bottom: 30px;
            color: var(--navy);
            font-weight: 900;
            text-transform: uppercase;
            font-size: 1.1rem;
        }
        .badge-status { background: var(--gold); color: var(--navy); font-weight: 800; padding: 8px 15px; border-radius: 10px; font-size: 0.7rem; }

        /* ── Placement radio cards ── */
        .placement-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .placement-grid input[type="radio"] { display: none; }

        .placement-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 14px;
            cursor: pointer;
            border: 1.5px solid #e2e8f0;
            background: white;
            transition: border-color 0.2s, background 0.2s;
            user-select: none;
        }
        .placement-label .icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f7fa;
            flex-shrink: 0;
            font-size: 16px;
            color: #94a3b8;
            transition: background 0.2s, color 0.2s;
        }
        .placement-label .txt { display: flex; flex-direction: column; gap: 2px; }
        .placement-label .txt span { font-size: 13px; font-weight: 700; color: var(--navy); }
        .placement-label .txt small { font-size: 11px; color: #94a3b8; }

        #st_adopt:checked + .placement-label {
            border-color: #185FA5;
            background: #EBF3FC;
        }
        #st_adopt:checked + .placement-label .icon-wrap {
            background: #185FA5;
            color: white;
        }
        #st_sanctuary:checked + .placement-label {
            border-color: var(--navy);
            background: #f0f2f8;
        }
        #st_sanctuary:checked + .placement-label .icon-wrap {
            background: var(--navy);
            color: var(--gold);
        }

        /* ── Submit button ── */
        .btn-finalize {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--navy);
            color: var(--gold);
            border: none;
            border-radius: 14px;
            padding: 16px 36px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s, color 0.2s;
        }
        .btn-finalize i { font-size: 17px; }
        .btn-finalize:hover {
            background: #162447;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(10, 19, 41, 0.28);
        }
        .btn-finalize:active {
            transform: translateY(0);
            box-shadow: none;
        }

        /* ── Abort link ── */
        .abort-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid transparent;
            transition: color 0.2s, border-color 0.2s, background 0.2s;
        }
        .abort-link:hover {
            color: #c0392b;
            border-color: #f5c0bb;
            background: #fff5f5;
            text-decoration: none;
        }
        .abort-link i { font-size: 14px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<section class="hero-finalize">
    <div class="container">
        <span class="badge-status mb-3">ADMINISTRATION PANEL</span>
        <h1 class="fw-bold display-5">Enroll New Resident</h1>
        <p class="text-white-50">Transforming a rescue case into a permanent sanctuary identity.</p>
    </div>
</section>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="finalize-card">
                <form action="process_finalization.php" method="POST" enctype="multipart/form-data" class="p-4 p-md-5">
                    <input type="hidden" name="rescue_id" value="<?= $rescue_id; ?>">

                    <div class="row g-5">

                        <!-- Left: Visual Identity -->
                        <div class="col-md-5">
                            <h5 class="section-title">Visual Identity</h5>
                            <div class="preview-box mb-4" id="photoPreview">
                                <img src="<?= $rescue_data['media_path']; ?>" id="output_image">
                                <div class="position-absolute top-0 end-0 m-3">
                                    <span class="badge bg-danger">ORIGINAL CASE PHOTO</span>
                                </div>
                            </div>
                            <div class="info-card p-3 bg-light rounded-4 mb-4 border">
                                <label class="form-label">Update Profile Photo</label>
                                <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(event)">
                                <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">
                                    <i class="fas fa-info-circle me-1"></i> Upload a clear, high-quality portrait for the sanctuary directory.
                                </small>
                            </div>

                            <!-- Image Focus Picker -->
                            <div class="info-card p-3 bg-light rounded-4 mb-4 border">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-crop-alt me-1" style="color:var(--gold);"></i>
                                    Image Focus Point
                                </label>
                                <small class="text-muted d-block mb-3" style="font-size:0.75rem;">
                                    Choose which part of the photo stays visible in the card view on the adoption page.
                                </small>

                                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:6px; margin-bottom:14px;">
                                    <?php
                                    $focus_options = [
                                        'top left'    => '↖ Top Left',
                                        'top center'  => '↑ Top',
                                        'top right'   => '↗ Top Right',
                                        'center left' => '← Left',
                                        'center'      => '⊕ Center',
                                        'center right'=> '→ Right',
                                        'bottom left' => '↙ Bot Left',
                                        'bottom center'=> '↓ Bottom',
                                        'bottom right'=> '↘ Bot Right',
                                    ];
                                    foreach ($focus_options as $val => $label): ?>
                                    <button type="button"
                                        onclick="setFocus('<?= $val ?>')"
                                        id="focus_btn_<?= str_replace(' ','_',$val) ?>"
                                        style="padding:7px 4px;font-size:0.72rem;border:1.5px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;transition:all .15s;font-weight:600;color:#555;">
                                        <?= $label ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>

                                <input type="hidden" name="image_focus" id="image_focus_input" value="center">

                                <label class="form-label" style="font-size:0.78rem;color:#666;">Live preview (how it looks on adoption page):</label>
                                <div id="focus_preview_box" style="width:100%;height:160px;overflow:hidden;border-radius:10px;border:2px solid var(--gold);position:relative;background:#eee;">
                                    <img id="focus_preview_img"
                                         src="<?= $rescue_data['media_path']; ?>"
                                         style="width:100%;height:100%;object-fit:cover;object-position:center;transition:object-position .3s;">
                                    <div style="position:absolute;bottom:6px;right:8px;background:rgba(0,0,0,0.55);color:#fff;font-size:0.68rem;padding:2px 8px;border-radius:20px;" id="focus_label_display">Focus: Center</div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Biological Dossier -->
                        <div class="col-md-7">
                            <h5 class="section-title">Biological Dossier</h5>
                            <div class="row g-4">

                                <div class="col-md-12">
                                    <label class="form-label">Sanctuary Name (Official)</label>
                                    <input type="text" name="animal_name" class="form-control" placeholder="e.g. Bagher, Luna, Max" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Species</label>
                                    <input type="text" name="species" class="form-control" value="<?= htmlspecialchars($rescue_data['species']); ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Breed (Identified by Vet)</label>
                                    <input type="text" name="breed" class="form-control" value="<?= htmlspecialchars($rescue_data['breed'] ?? ''); ?>" placeholder="e.g. Local Mix, Persian">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="Male"    <?= ($rescue_data['gender'] == 'Male')                                  ? 'selected' : '' ?>>Male</option>
                                        <option value="Female"  <?= ($rescue_data['gender'] == 'Female')                                ? 'selected' : '' ?>>Female</option>
                                        <option value="Unknown" <?= ($rescue_data['gender'] == 'Unknown' || empty($rescue_data['gender'])) ? 'selected' : '' ?>>Unknown</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Estimated Age</label>
                                    <input type="text" name="age" class="form-control" value="<?= htmlspecialchars($rescue_data['age'] ?? ''); ?>" placeholder="e.g. 2 Years">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Vaccination Status</label>
                                    <select name="vaccination" class="form-select">
                                        <option value="0" <?= ($rescue_data['is_vaccinated'] == 0) ? 'selected' : '' ?>>Not Vaccinated</option>
                                        <option value="1" <?= ($rescue_data['is_vaccinated'] == 1) ? 'selected' : '' ?>>Fully Vaccinated</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Spay/Neuter Status</label>
                                    <select name="spay_neuter" class="form-select">
                                        <option value="0" <?= ($rescue_data['is_spayed_neutered'] == 0) ? 'selected' : '' ?>>Intact (No)</option>
                                        <option value="1" <?= ($rescue_data['is_spayed_neutered'] == 1) ? 'selected' : '' ?>>Fixed (Yes)</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Permanent Medical Notes</label>
                                    <!-- Only pre-fills if vet entered real notes, not the placeholder default -->
                                    <textarea name="vet_notes" class="form-control" rows="3"
                                              placeholder="Lifetime care instructions..."><?= htmlspecialchars($prefill_vet_notes) ?></textarea>
                                </div>

                                <!-- Operational Placement -->
                                <div class="col-md-12">
                                    <label class="form-label">Operational Placement</label>
                                    <div class="placement-grid">
                                        <input type="radio" name="status" id="st_adopt" value="Available for Adoption" checked>
                                        <label class="placement-label" for="st_adopt">
                                            <span class="icon-wrap"><i class="fas fa-home"></i></span>
                                            <span class="txt">
                                                <span>Adoption Hub</span>
                                                <small>Open for adoption</small>
                                            </span>
                                        </label>

                                        <input type="radio" name="status" id="st_sanctuary" value="Resident of Sanctuary">
                                        <label class="placement-label" for="st_sanctuary">
                                            <span class="icon-wrap"><i class="fas fa-shield-cat"></i></span>
                                            <span class="txt">
                                                <span>Permanent Resident</span>
                                                <small>Long-term sanctuary</small>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="mt-5 pt-4 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <a href="admin_dashboard.php" class="abort-link">
                            <i class="fas fa-chevron-left"></i> Abort Enrollment
                        </a>
                        <button type="submit" name="submit_final" class="btn-finalize">
                            <i class="fas fa-circle-check"></i> Finalize and Move to Inventory
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    // Preview uploaded image in both boxes
    function previewImage(event) {
        var reader = new FileReader();
        reader.onload = function() {
            document.getElementById('output_image').src = reader.result;
            document.getElementById('focus_preview_img').src = reader.result;
        }
        reader.readAsDataURL(event.target.files[0]);
    }

    // Set image focus point
    function setFocus(val) {
        // Update hidden input
        document.getElementById('image_focus_input').value = val;

        // Update preview image position
        document.getElementById('focus_preview_img').style.objectPosition = val;

        // Update label
        document.getElementById('focus_label_display').textContent = 'Focus: ' + val.replace(/\b\w/g, l => l.toUpperCase());

        // Highlight active button, reset others
        document.querySelectorAll('[id^="focus_btn_"]').forEach(function(btn) {
            btn.style.background   = '#fff';
            btn.style.borderColor  = '#ddd';
            btn.style.color        = '#555';
        });
        var activeId = 'focus_btn_' + val.replace(/ /g, '_');
        var activeBtn = document.getElementById(activeId);
        if (activeBtn) {
            activeBtn.style.background  = '#0a1329';
            activeBtn.style.borderColor = 'var(--gold)';
            activeBtn.style.color       = '#fff';
        }
    }

    // Highlight Center by default on load
    document.addEventListener('DOMContentLoaded', function() {
        setFocus('center');
    });
</script>
</body>
</html>