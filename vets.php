<?php
session_start();
include 'db_config.php';

// No redirect — guests can access freely
$query = "SELECT * FROM vet_clinics WHERE is_verified = 1";
$result = $conn->query($query);
$db_clinics = [];
while($row = $result->fetch_assoc()) {
    $db_clinics[] = $row;
}

include 'navbar.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hybrid Rescue Map | Moonlight of Heaven</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="style.css">

    <style>
        body { background-color: #f6f8fb; font-family: 'Montserrat', sans-serif; }
        
        .hub-header {
            background: linear-gradient(rgba(10, 19, 41, 0.8), rgba(10, 19, 41, 0.8)), 
                        url('https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?auto=format&fit=crop&q=80&w=2000');
            background-size: cover; background-position: center;
            color: white; padding: 80px 0; margin-bottom: -50px;
        }

        #map { height: 450px; width: 100%; border-radius: 25px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); border: 6px solid white; z-index: 5; }
        
        .clinic-card { transition: 0.4s; border: none; border-radius: 20px; background: white; overflow: hidden; height: 100%; }
        .clinic-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        
        .clinic-img-wrapper { height: 170px; width: 100%; overflow: hidden; background: #eee; position: relative; }
        .clinic-img { width: 100%; height: 100%; object-fit: cover; }
        
        .dist-label { position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: bold; color: #198754; z-index: 2; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        .badge-verified { background: #198754; color: white; font-size: 0.7rem; }
        .badge-map { background: #6c757d; color: white; font-size: 0.7rem; }
        
        #loading-view { display: none; margin: 20px 0; }
        .btn-call { border: 2px solid #198754; color: #198754; background: white; }
        .btn-call:hover { background: #198754; color: white; }

        /* ── Modal polish ── */
        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, #198754, #0f5132);
            color: white;
            border: none;
            padding: 1.4rem 1.6rem;
        }
        .modal-header .btn-close { filter: invert(1) brightness(2); }
        .modal-body { padding: 1.8rem; }

        /* Clinic name pill inside modal */
        #modal-clinic-display {
            background: #e9f7ef;
            color: #0f5132;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 1.2rem;
        }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed #ced4da;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            background: #f8f9fa;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: #198754; background: #e9f7ef; }
        .upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer;
        }
        .upload-zone .preview-img {
            max-height: 140px;
            border-radius: 10px;
            object-fit: cover;
            display: none;
            margin: 8px auto 0;
        }

        /* Spinner overlay inside modal submit btn */
        #modal-submit-btn .spinner-border { width: 1rem; height: 1rem; border-width: 2px; }

        /* Success state */
        #modal-success { display: none; text-align: center; padding: 20px 0; }
        #modal-success .success-icon { font-size: 3rem; color: #198754; animation: popIn 0.4s ease; }
        @keyframes popIn { 0%{transform:scale(0)} 80%{transform:scale(1.2)} 100%{transform:scale(1)} }
    </style>
</head>
<body>

<header class="hub-header text-center">
    <div class="container">
        <h2 class="display-4 fw-bold mb-3">Hybrid Rescue Finder</h2>
        <p class="lead opacity-75">Combining our verified partners with real-time global map data.</p>
        <button class="btn btn-light rounded-pill px-5 fw-bold shadow-sm mt-2" onclick="startHybridSearch()">
            <i class="fas fa-satellite-dish me-2 text-success"></i>SCAN ALL CLINICS
        </button>
    </div>
</header>

<div class="container mb-5" style="position: relative; z-index: 10;">
    <div id="map"></div>
    <div id="loading-view" class="text-center">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 fw-bold text-muted">Locating and sorting nearest clinics...</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4" id="clinic-list"></div>
</div>

<!-- ══════════════════════════════════════════
     CLINIC REPORT MODAL  (replaces report_transport.php redirect)
     ══════════════════════════════════════════ -->
<div class="modal fade" id="clinicReportModal" tabindex="-1" aria-labelledby="clinicReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="clinicReportModalLabel">
                        <i class="fas fa-hospital-alt me-2"></i>Confirm Arrival at Clinic
                    </h5>
                    <small class="opacity-75">Help us track this rescue's journey</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- Success state (shown after submit) -->
                <div id="modal-success">
                    <div class="success-icon mb-3"><i class="fas fa-check-circle"></i></div>
                    <h5 class="fw-bold text-success">Report Submitted!</h5>
                    <p class="text-muted small">Status set to <strong>At Clinic</strong>. Admin will verify shortly.</p>
                    <button class="btn btn-outline-success rounded-pill px-4 mt-2" data-bs-dismiss="modal">Close</button>
                </div>

                <!-- Form state -->
                <div id="modal-form-body">
                    <div id="modal-alert-area"></div>

                    <div id="modal-clinic-display">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <span id="modal-clinic-name-label"></span>
                    </div>

                    <form id="clinicReportForm" enctype="multipart/form-data">
                        <!-- Hidden fields -->
                        <input type="hidden" name="clinic_name" id="modal-clinic-name-input">
                        <input type="hidden" name="rescue_id" id="modal-rescue-id" value="0">

                        <!-- Species (only for new direct reports, rescue_id == 0) -->
                        <div class="mb-3" id="species-row">
                            <label class="form-label fw-bold small">Animal Species</label>
                            <select name="species" class="form-select form-select-sm">
                                <option value="Dog">Dog</option>
                                <option value="Cat">Cat</option>
                                <option value="Bird">Bird</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <!-- Condition -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Animal Condition <span class="text-danger">*</span></label>
                            <textarea name="condition" id="modal-condition" class="form-control form-control-sm" rows="3" 
                                      required placeholder="Describe the health condition as observed at the clinic…"></textarea>
                        </div>

                        <!-- Photo upload -->
                        <div class="mb-4">
                            <label class="form-label fw-bold small">Clinic Arrival Photo <span class="text-danger">*</span></label>
                            <div class="upload-zone" id="upload-zone">
                                <input type="file" name="update_photo" id="modal-photo-input" accept="image/*" required>
                                <i class="fas fa-camera fa-2x text-muted mb-2"></i>
                                <p class="text-muted small mb-0">Click or drag a photo here</p>
                                <img id="photo-preview" class="preview-img" src="" alt="preview">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold rounded-pill" id="modal-submit-btn">
                            <span id="btn-label"><i class="fas fa-paper-plane me-2"></i>SUBMIT CLINIC REPORT</span>
                            <span id="btn-spinner" class="d-none">
                                <span class="spinner-border me-2" role="status"></span>Submitting…
                            </span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- ══ end modal ══ -->

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    var map = L.map('map').setView([23.8103, 90.4125], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    var markerGroup = L.layerGroup().addTo(map);

    const dbClinics = <?= json_encode($db_clinics) ?>;

    // ── Open modal helper ──────────────────────────────────────────────
    function openClinicModal(clinicName, rescueId = 0) {
        // Reset form & states
        $('#modal-alert-area').empty();
        $('#clinicReportForm')[0].reset();
        $('#photo-preview').hide().attr('src', '');
        $('#modal-success').hide();
        $('#modal-form-body').show();
        $('#btn-label').removeClass('d-none');
        $('#btn-spinner').addClass('d-none');

        // Populate
        $('#modal-clinic-name-label').text(clinicName);
        $('#modal-clinic-name-input').val(clinicName);
        $('#modal-rescue-id').val(rescueId);

        // Show/hide species row: only for new reports (no rescue_id)
        rescueId > 0 ? $('#species-row').hide() : $('#species-row').show();

        new bootstrap.Modal(document.getElementById('clinicReportModal')).show();
    }

    // ── Photo preview ──────────────────────────────────────────────────
    $(document).on('change', '#modal-photo-input', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            $('#photo-preview').attr('src', e.target.result).show();
            $('#upload-zone p').text(file.name);
        };
        reader.readAsDataURL(file);
    });

    // Drag-over style
    $('#upload-zone').on('dragover', function () { $(this).addClass('dragover'); })
                     .on('dragleave drop', function () { $(this).removeClass('dragover'); });

    // ── AJAX form submit ───────────────────────────────────────────────
    $('#clinicReportForm').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('submit_report', '1');   // mirrors the PHP check

        $('#btn-label').addClass('d-none');
        $('#btn-spinner').removeClass('d-none');
        $('#modal-alert-area').empty();

        $.ajax({
            url: 'report_transport.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $('#btn-label').removeClass('d-none');
                $('#btn-spinner').addClass('d-none');

                // Check for success alert in returned HTML
                if (response.includes('alert-success')) {
                    $('#modal-form-body').hide();
                    $('#modal-success').fadeIn(300);
                } else {
                    // Extract and show the error alert
                    const alertMatch = response.match(/<div class='alert[^']*alert-danger[^>]*>[\s\S]*?<\/div>/);
                    if (alertMatch) {
                        $('#modal-alert-area').html(alertMatch[0]);
                    } else {
                        $('#modal-alert-area').html("<div class='alert alert-danger'>Something went wrong. Please try again.</div>");
                    }
                }
            },
            error: function () {
                $('#btn-label').removeClass('d-none');
                $('#btn-spinner').addClass('d-none');
                $('#modal-alert-area').html("<div class='alert alert-danger'>Network error. Please try again.</div>");
            }
        });
    });

    // ── Map & clinic cards ─────────────────────────────────────────────
    function startHybridSearch() {
        if (!navigator.geolocation) return alert("Geolocation not supported");

        $('#loading-view').fadeIn();
        $('#clinic-list').empty();
        markerGroup.clearLayers();

        navigator.geolocation.getCurrentPosition(position => {
            const uLat = position.coords.latitude;
            const uLon = position.coords.longitude;

            map.setView([uLat, uLon], 14);
            L.circle([uLat, uLon], {color: '#198754', radius: 400, fillOpacity: 0.1}).addTo(markerGroup);

            let combinedResults = [];

            dbClinics.forEach((c) => {
                const d = L.latLng(uLat, uLon).distanceTo([c.latitude, c.longitude]) / 1000;
                if (d < 15) {
                    combinedResults.push({ ...c, distance: d, type: 'db' });
                }
            });

            const query = `[out:json];node["amenity"="veterinary"](around:6000,${uLat},${uLon});out;`;
            fetch("https://overpass-api.de/api/interpreter?data=" + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    data.elements.forEach(el => {
                        const d = L.latLng(uLat, uLon).distanceTo([el.lat, el.lon]) / 1000;
                        combinedResults.push({
                            name: el.tags.name || "Nearby Clinic",
                            address: el.tags["addr:street"] || "Found via Satellite",
                            latitude: el.lat,
                            longitude: el.lon,
                            phone: el.tags.phone || el.tags["contact:phone"] || "",
                            distance: d,
                            type: 'map'
                        });
                    });

                    combinedResults.sort((a, b) => a.distance - b.distance);

                    combinedResults.forEach((c, i) => {
                        const isDb   = c.type === 'db';
                        const badgeClass = isDb ? 'badge-verified' : 'badge-map';
                        const badgeLabel = isDb ? '<i class="fas fa-shield-alt me-1"></i> VERIFIED' : 'MAP DATA';
                        const btnColor   = isDb ? 'btn-success' : 'btn-dark';
                        const imgUrl     = `https://loremflickr.com/600/400/veterinary,clinic?lock=${i}`;

                        const iconColor = isDb ? 'green' : 'blue';
                        const markerIcon = L.icon({
                            iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${iconColor}.png`,
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                            iconSize: [25, 41], iconAnchor: [12, 41]
                        });
                        L.marker([c.latitude, c.longitude], {icon: markerIcon})
                         .addTo(markerGroup)
                         .bindPopup(c.name);

                        // ✅ Logged in → open modal | Guest → redirect to login
                        const clinicNameEscaped = c.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const goingBtn = isLoggedIn
                            ? `<button type="button"
                                    class="btn ${btnColor} btn-sm rounded-pill w-100 fw-bold"
                                    onclick="openClinicModal('${clinicNameEscaped}', 0)">
                                    <i class="fas fa-hospital-alt me-1"></i>I AM GOING HERE
                               </button>`
                            : `<a href="login.php" class="btn btn-outline-warning btn-sm rounded-pill w-100 fw-bold">
                                    <i class="fas fa-lock me-1"></i> Login to Report
                               </a>`;

                        const card = `
                            <div class="col-md-4">
                                <div class="card clinic-card shadow-sm">
                                    <div class="clinic-img-wrapper">
                                        <img src="${imgUrl}" class="clinic-img" alt="Clinic">
                                        <span class="dist-label">${c.distance.toFixed(1)} km</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <span class="badge ${badgeClass} mb-2">${badgeLabel}</span>
                                        <h5 class="fw-bold mb-1 text-truncate">${c.name}</h5>
                                        ${c.license ? `
<div class="mb-2">
    <span class="badge bg-success">
        <i class="fas fa-id-card me-1"></i>
        License: ${c.license}
    </span>
</div>` : ''}

                                        <p class="text-muted small mb-3 text-truncate">${c.address}</p>
                                        <div class="d-grid gap-2">
                                            <a href="tel:${c.phone}" class="btn btn-call btn-sm rounded-pill fw-bold">
                                                <i class="fas fa-phone-alt me-2"></i>CALL NOW
                                            </a>
                                            <a href="https://www.google.com/maps/dir/?api=1&destination=${c.latitude},${c.longitude}"
                                               target="_blank" class="btn btn-outline-dark btn-sm rounded-pill fw-bold">
                                                <i class="fas fa-directions me-2"></i>DIRECTIONS
                                            </a>
                                            ${goingBtn}
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        $('#clinic-list').append(card);
                    });

                    $('#loading-view').hide();
                });
        });
    }
</script>
</body>
</html>