<?php
session_start();
include 'db_config.php';
require_once 'finance_helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=unauthorized'); exit();
}

$animals_r = $conn->query("SELECT id, name, species FROM animals ORDER BY name ASC");
$animals   = [];
while ($a = $animals_r->fetch_assoc()) $animals[] = $a;

$events_r = $conn->query("SELECT id, title FROM events ORDER BY event_date DESC LIMIT 50");
$events   = [];
while ($ev = $events_r->fetch_assoc()) $events[] = $ev;

$rescues_r = $conn->query("SELECT id, species, location FROM rescues ORDER BY created_at DESC LIMIT 50");
$rescues   = [];
while ($re = $rescues_r->fetch_assoc()) $rescues[] = $re;

$staff = getStaffForSalary($conn);

$pre_cat = isset($_GET['cat']) ? htmlspecialchars($_GET['cat']) : '';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category  = $conn->real_escape_string($_POST['category']              ?? '');
    $amount    = floatval($_POST['amount']                                 ?? 0);
    $date_paid = $conn->real_escape_string($_POST['date_paid']             ?? date('Y-m-d'));
    $payee     = $conn->real_escape_string($_POST['vendor_name_or_payee']  ?? '');
    $animal_id = !empty($_POST['animal_id']) ? intval($_POST['animal_id']) : 'NULL';
    $event_id  = !empty($_POST['event_id'])  ? intval($_POST['event_id'])  : 'NULL';
    $rescue_id = !empty($_POST['rescue_id']) ? intval($_POST['rescue_id']) : 'NULL';
    $staff_id  = !empty($_POST['staff_id'])  ? intval($_POST['staff_id'])  : 'NULL';
    $notes     = $conn->real_escape_string($_POST['notes']                 ?? '');
    $created_by= intval($_SESSION['user_id']);

    if ($amount <= 0)        { $error = 'Amount must be greater than zero.'; }
    elseif (empty($category)){ $error = 'Please select an expense category.'; }
    elseif (empty($payee))   { $error = 'Vendor / Payee name is required.'; }
    else {
        $sql = "INSERT INTO sanctuary_expenses
                    (category, amount, date_paid, vendor_name_or_payee, animal_id, event_id, rescue_id, staff_id, notes, created_by)
                VALUES
                    ('$category', $amount, '$date_paid', '$payee', $animal_id, $event_id, $rescue_id, $staff_id, '$notes', $created_by)";
        if ($conn->query($sql)) {
            $success = "Expense logged successfully!";
            $pre_cat = '';
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

$cats = [
    'Medical'              => ['icon' => 'fa-stethoscope',   'color' => '#dc2626'],
    'Food & Supplies'      => ['icon' => 'fa-bowl-food',     'color' => '#16a34a'],
    'Facility Maintenance' => ['icon' => 'fa-wrench',        'color' => '#1d4ed8'],
    'Rescue Ops'           => ['icon' => 'fa-truck-medical', 'color' => '#d97706'],
    'Staff Salaries'       => ['icon' => 'fa-user-tie',      'color' => '#0891b2'],
    'Event Management'     => ['icon' => 'fa-calendar-star', 'color' => '#7c3aed'],
    'Admin'                => ['icon' => 'fa-folder-open',   'color' => '#475569'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log Expense | <?= htmlspecialchars(setting('site_name','Heartbeat Heaven')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
:root { --navy:#0a1329; --gold:#B8860B; --gold-lt:#ffc107; }
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
.ma-page-header { margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid #e2e8f0; }
.ma-page-header h5 { font-weight:800; font-size:1rem; color:var(--navy); margin:0 0 4px; }
.ma-page-header p  { font-size:0.75rem; color:#94a3b8; margin:0; }
.ma-accent-bar { width:40px; height:3px; border-radius:4px; margin-bottom:10px; background:#dc2626; }
.ma-alert { border-radius:12px; font-size:0.8rem; font-weight:600; }

/* FORM */
.form-section { background:#fff; border-radius:20px; border:1px solid #e2e8f0; box-shadow:0 4px 20px rgba(0,0,0,0.04); overflow:hidden; max-width:780px; }
.form-section-header { padding:20px 28px; border-bottom:1px solid #f1f5f9; background:linear-gradient(135deg,#7f1d1d 0%,#dc2626 100%); }
.form-section-header h6 { color:#fff; font-weight:800; font-size:0.85rem; margin:0 0 3px; letter-spacing:0.5px; }
.form-section-header p  { color:rgba(255,255,255,0.6); font-size:0.75rem; margin:0; }
.form-body { padding:28px; }
.field-label { font-size:0.68rem; font-weight:800; letter-spacing:1px; text-transform:uppercase; color:#475569; display:block; margin-bottom:7px; }
.field-input { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-family:'Inter',sans-serif; font-size:0.88rem; background:#fff; color:var(--navy); transition:border-color 0.2s; }
.field-input:focus { outline:none; border-color:#dc2626; box-shadow:0 0 0 3px rgba(220,38,38,0.08); }
.field-hint { font-size:0.7rem; color:#94a3b8; margin-top:5px; }
.form-row-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.conditional-field { display:none; }
.conditional-field.show { display:block; }
.form-divider { border:none; border-top:1px solid #f1f5f9; margin:24px 0; }

/* CATEGORY PILLS */
.cat-pill-wrap { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:6px; }
.cat-pill {
    display:inline-flex; align-items:center; gap:7px;
    padding:8px 14px; border-radius:10px; font-size:0.72rem; font-weight:700;
    cursor:pointer; border:1.5px solid #e2e8f0; background:#fff; color:#64748b;
    transition:all 0.2s;
}
.cat-pill:hover { border-color:#dc2626; color:#dc2626; background:#fef2f2; }
.cat-pill.active { background:#dc2626; color:#fff; border-color:#dc2626; }
.cat-pill i { font-size:0.8rem; }

/* STAFF PICKER */
.staff-picker-wrap { display:none; margin-top:8px; }
.staff-picker-wrap.show { display:block; }

/* SUBMIT */
.btn-submit { background:#dc2626; color:#fff; padding:12px 28px; border-radius:10px; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; border:none; cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:8px; }
.btn-submit:hover { background:#b91c1c; transform:translateY(-1px); box-shadow:0 6px 18px rgba(220,38,38,0.3); }
.btn-cancel { background:#f1f5f9; color:#475569; padding:12px 20px; border-radius:10px; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; border:none; cursor:pointer; transition:all 0.2s; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
.btn-cancel:hover { background:#e2e8f0; color:var(--navy); }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="ma-wrapper">

    <!-- SIDEBAR -->
    <aside class="ma-sidebar">
        <div class="sidebar-head">
            <span>Finance</span>
            <p>Financial Control</p>
        </div>
        <a href="finance_dashboard.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-chart-pie"></i></div>
            Overview
        </a>
        <a href="finance_log_income.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-arrow-down"></i></div>
            Log Funding
        </a>
        <a href="finance_log_expense.php" class="sidebar-item active">
            <div class="si-icon"><i class="fas fa-arrow-up"></i></div>
            Log Expense
        </a>
        <a href="finance_ledger.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-table"></i></div>
            Full Ledger
        </a>
        <a href="finance_sponsorships.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-star"></i></div>
            Sponsorships
        </a>
        <div style="margin:16px 20px;border-top:1px solid rgba(255,255,255,0.08);"></div>
        <a href="admin.php" class="sidebar-item">
            <div class="si-icon"><i class="fas fa-shield-alt"></i></div>
            Admin Panel
        </a>
    </aside>

    <!-- MAIN -->
    <main class="ma-content">

        <div class="ma-page-header">
            <div>
                <div class="ma-accent-bar"></div>
                <h5>Log Sanctuary Expense</h5>
                <p>Financial Control &rsaquo; Record Outbound Costs</p>
            </div>
        </div>

        <?php if($success): ?>
        <div class="alert alert-success ma-alert alert-dismissible fade show mb-4">
            <i class="fas fa-check-circle me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if($error): ?>
        <div class="alert alert-danger ma-alert alert-dismissible fade show mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <div class="form-section-header">
                <h6><i class="fas fa-arrow-up me-2"></i>Record Expense Entry</h6>
                <p>Log any outbound cost tied to animal care, operations, or staffing.</p>
            </div>
            <div class="form-body">
                <form method="POST">

                    <!-- CATEGORY PILLS -->
                    <div class="mb-4">
                        <label class="field-label">Expense Category *</label>
                        <div class="cat-pill-wrap">
                            <?php foreach($cats as $cat => $meta): ?>
                            <div class="cat-pill <?= $pre_cat === $cat ? 'active' : '' ?>" data-value="<?= $cat ?>">
                                <i class="fas <?= $meta['icon'] ?>"></i> <?= $cat ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="category" id="category_input" value="<?= htmlspecialchars($pre_cat) ?>" required>
                        <div class="field-hint">Select the category that best describes this expense.</div>
                    </div>

                    <hr class="form-divider">

                    <!-- AMOUNT + DATE -->
                    <div class="form-row-grid mb-4">
                        <div>
                            <label class="field-label">Amount (৳) *</label>

                            <input type="number" name="amount" class="field-input" min="1000" step="0.01" placeholder="e.g. 2500.00" required>
                        </div>
                        <div>
                            <label class="field-label">Date Paid *</label>
                            <input type="date" name="date_paid" class="field-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- VENDOR / PAYEE -->
                    <div class="mb-4">
                        <label class="field-label">Vendor / Payee *</label>
                        <input type="text" name="vendor_name_or_payee" id="payee_input" class="field-input"
                               placeholder="e.g. City Vet Clinic, Wholesale Pet Foods, Employee: John" required>
                        <!-- Staff quick-fill (salary only) -->
                        <div class="staff-picker-wrap" id="staff_picker_wrap">
                            <label class="field-label mt-2">Quick Fill — Pick Staff Member</label>
                            <select id="staff_picker" class="field-input" onchange="autoFillStaff(this)">
                                <option value="">— Select Staff Member —</option>
                                <?php foreach($staff as $s): ?>
                                    <option value="<?= $s['id'] ?>|<?= htmlspecialchars($s['full_name']) ?>|<?= $s['role'] ?>">
                                        <?= htmlspecialchars($s['full_name']) ?> (<?= ucfirst($s['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="staff_id" id="staff_id_input" value="">
                        <div class="field-hint">For salaries, use the staff picker above to auto-fill the payee name.</div>
                    </div>

                    <!-- CONDITIONAL: Animal (Medical) -->
                    <div class="mb-4 conditional-field" id="field_animal">
                        <label class="field-label">Linked Animal <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                        <select name="animal_id" class="field-input">
                            <option value="">— Select Animal —</option>
                            <?php foreach($animals as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> (<?= $a['species'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-hint">Link this medical expense to a specific animal's record.</div>
                    </div>

                    <!-- CONDITIONAL: Event (Event Management) -->
                    <div class="mb-4 conditional-field" id="field_event">
                        <label class="field-label">Linked Event <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                        <select name="event_id" class="field-input">
                            <option value="">— Select Event —</option>
                            <?php foreach($events as $ev): ?>
                                <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- CONDITIONAL: Rescue (Rescue Ops) -->
                    <div class="mb-4 conditional-field" id="field_rescue">
                        <label class="field-label">Linked Rescue Mission <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                        <select name="rescue_id" class="field-input">
                            <option value="">— Select Rescue —</option>
                            <?php foreach($rescues as $re): ?>
                                <option value="<?= $re['id'] ?>">#<?= $re['id'] ?> — <?= htmlspecialchars($re['species']) ?> @ <?= htmlspecialchars(substr($re['location'],0,40)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- NOTES -->
                    <div class="mb-4">
                        <label class="field-label">Notes / Remarks <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                        <textarea name="notes" class="field-input" rows="3" style="resize:vertical;" placeholder="Invoice reference, treatment details, event name, etc."></textarea>
                    </div>

                    <hr class="form-divider">

                    <div class="d-flex gap-3 align-items-center">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Save Expense Record
                        </button>
                        <a href="finance_dashboard.php" class="btn-cancel">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                    </div>

                </form>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const pills       = document.querySelectorAll('.cat-pill');
const catInput    = document.getElementById('category_input');
const fieldAnimal = document.getElementById('field_animal');
const fieldEvent  = document.getElementById('field_event');
const fieldRescue = document.getElementById('field_rescue');
const staffWrap   = document.getElementById('staff_picker_wrap');

function updateConditionals(val) {
    fieldAnimal.classList.toggle('show', val === 'Medical');
    fieldEvent.classList.toggle('show',  val === 'Event Management');
    fieldRescue.classList.toggle('show', val === 'Rescue Ops');
    staffWrap.classList.toggle('show',   val === 'Staff Salaries');
    if (val !== 'Staff Salaries') {
        document.getElementById('staff_id_input').value = '';
        document.getElementById('staff_picker').value   = '';
    }
}

pills.forEach(pill => {
    pill.addEventListener('click', () => {
        pills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        catInput.value = pill.dataset.value;
        updateConditionals(pill.dataset.value);
    });
});

function autoFillStaff(sel) {
    if (!sel.value) return;
    const [id, name, role] = sel.value.split('|');
    document.getElementById('payee_input').value   = 'Employee: ' + name + ' (' + role + ')';
    document.getElementById('staff_id_input').value = id;
}

updateConditionals(catInput.value);
</script>

<?php include 'chat_widget.php'; ?>
<?php include 'footer.php'; ?>
</body>
</html>