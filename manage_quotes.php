<?php
session_start();
include 'db_config.php';

// Strict Admin Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// Logic: Add New Quote
if (isset($_POST['add_quote'])) {
    $text = mysqli_real_escape_string($conn, $_POST['quote_text']);
    $author = mysqli_real_escape_string($conn, $_POST['author_name']);
    $conn->query("INSERT INTO site_quotes (quote_text, author_name, status) VALUES ('$text', '$author', 'active')");
    header("Location: manage_quotes.php?msg=added");
    exit();
}

// Logic: Toggle Status (Active/Inactive)
if (isset($_GET['toggle_id'])) {
    $tid = intval($_GET['toggle_id']);
    $current = $_GET['current'];
    $new_status = ($current == 'active') ? 'inactive' : 'active';
    $conn->query("UPDATE site_quotes SET status = '$new_status' WHERE id = $tid");
    header("Location: manage_quotes.php?msg=updated");
    exit();
}

// Logic: Delete Quote
if (isset($_GET['delete_id'])) {
    $did = intval($_GET['delete_id']);
    $conn->query("DELETE FROM site_quotes WHERE id = $did");
    header("Location: manage_quotes.php?msg=deleted");
    exit();
}

$quotes = $conn->query("SELECT * FROM site_quotes ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quote Manager | Praner Tan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f6f8fb; }
        .admin-header { background: #0a1329; color: white; padding: 60px 0; margin-bottom: -40px; }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; overflow: hidden; }
        .gold-label { font-size: 0.75rem; letter-spacing: 1.5px; font-weight: 700; color: #B8860B; text-transform: uppercase; }
        .btn-gold { background: #B8860B; color: white; border-radius: 10px; font-weight: 600; transition: 0.3s; border: none; }
        .btn-gold:hover { background: #966d08; color: white; transform: translateY(-2px); }
        .status-badge { font-size: 0.65rem; padding: 4px 10px; border-radius: 50px; text-transform: uppercase; font-weight: 700; }
        .status-active { background: #e7f9ed; color: #198754; }
        .status-inactive { background: #f8f9fa; color: #6c757d; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<header class="admin-header text-center">
    <div class="container">
        <span class="gold-label">Website Content</span>
        <h2 class="display-6 fw-bold mt-2">Daily Quotes Manager</h2>
        <button class="btn btn-gold mt-3 px-4" data-bs-toggle="modal" data-bs-target="#addQuoteModal">
            <i class="fas fa-plus me-2"></i>Add New Quote
        </button>
    </div>
</header>

<div class="container pb-5" style="position: relative; z-index: 5;">
    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 rounded-3">Action completed successfully!</div>
    <?php endif; ?>

    <div class="glass-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Quote Text</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $quotes->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" style="max-width: 400px;">
                            <div class="text-dark small">"<?php echo htmlspecialchars($row['quote_text']); ?>"</div>
                        </td>
                        <td class="fw-bold small"><?php echo htmlspecialchars($row['author_name']); ?></td>
                        <td>
                            <span class="status-badge <?php echo ($row['status'] == 'active') ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="?toggle_id=<?php echo $row['id']; ?>&current=<?php echo $row['status']; ?>" class="btn btn-sm btn-light border" title="Toggle Status">
                                <i class="fas <?php echo ($row['status'] == 'active') ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                            </a>
                            <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete permanently?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addQuoteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Add Inspiration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="small fw-bold mb-1">Quote Text</label>
                    <textarea name="quote_text" class="form-control" rows="4" required style="border-radius: 12px;"></textarea>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-1">Author Name</label>
                    <input type="text" name="author_name" class="form-control" placeholder="e.g. Mahatma Gandhi" style="border-radius: 10px;">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" name="add_quote" class="btn btn-gold w-100 py-2">Save Quote</button>
            </div>
        </form>
    </div>
</div>
<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>