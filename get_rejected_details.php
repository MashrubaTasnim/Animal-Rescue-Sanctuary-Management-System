<?php
include 'db_config.php';

if (isset($_GET['rescue_id'])) {
    $rescue_id = $conn->real_escape_string($_GET['rescue_id']);

    // rescues টেবিল এবং ইউজারের তথ্য একসাথে আনা
    $query = "SELECT r.*, u.full_name, u.email, u.phone 
              FROM rescues r 
              LEFT JOIN users u ON r.user_id = u.id 
              WHERE r.id = '$rescue_id'";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        ?>
        <div class="row p-3">
            <div class="col-md-5 text-center">
                <img src="<?= $data['media_path']; ?>" style="width: 100%; border-radius: 15px; height: 220px; object-fit: cover; border: 3px solid #f8f9fa;">
                <h5 class="mt-3 fw-bold text-navy"><?= $data['species']; ?></h5>
                <span class="badge bg-danger rounded-pill px-3">Rejected Case Archive</span>
            </div>
            
            <div class="col-md-7 border-start">
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase d-block mb-1">Reporter Information</label>
                    <p class="mb-0"><strong>Name:</strong> <?= $data['full_name'] ?: 'Guest Reporter'; ?></p>
                    <p class="mb-0"><strong>Contact:</strong> <?= $data['phone'] ?: 'No contact provided'; ?></p>
                    <p class="mb-0"><strong>Email:</strong> <?= $data['email'] ?: 'N/A'; ?></p>
                </div>
                
                <hr class="my-3" style="opacity: 0.1;">
                
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase d-block mb-1">Case Details</label>
                    <p class="mb-1 text-dark"><strong>Location:</strong> <?= $data['location']; ?></p>
                    <p class="mb-0 small text-muted italic">"<?= $data['description']; ?>"</p>
                </div>

                <div class="bg-light p-3 rounded-3 mt-2">
                    <label class="text-muted small fw-bold text-uppercase d-block mb-1">Internal Log</label>
                    <p class="mb-0 small text-danger font-monospace">Case Status: REJECTED</p>
                    <p class="mb-0 small text-muted">Archived On: <?= date('d M, Y', strtotime($data['created_at'])); ?></p>
                </div>
            </div>
        </div>
        <?php
    } else {
        echo "<div class='text-center p-5'><i class='fa-solid fa-circle-exclamation text-danger fa-2xl mb-3'></i><p>Case data not found in archives.</p></div>";
    }
}
?>