<?php
// finance_helpers.php — Shared financial functions for Heartbeat Heaven
// Include this wherever financial data is needed

function getFinancialSummary($conn) {
    $summary = [
        'total_funding'      => 0,
        'total_expenditure'  => 0,
        'net_balance'        => 0,
        'funding_by_source'  => [],
        'expense_by_category'=> [],
        'recent_income'      => [],
        'recent_expenses'    => [],
        'monthly_trend'      => [],
    ];

    // Total funding
    $r = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM funding_income");
    $summary['total_funding'] = (float)$r->fetch_assoc()['total'];

    // Total expenditure
    $r = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM sanctuary_expenses");
    $summary['total_expenditure'] = (float)$r->fetch_assoc()['total'];

    // Net balance
    $summary['net_balance'] = $summary['total_funding'] - $summary['total_expenditure'];

    // Funding breakdown by source
    $r = $conn->query("
        SELECT source_type, COALESCE(SUM(amount),0) AS total
        FROM funding_income
        GROUP BY source_type
        ORDER BY total DESC
    ");
    while ($row = $r->fetch_assoc()) {
        $summary['funding_by_source'][$row['source_type']] = (float)$row['total'];
    }

    // Expense breakdown by category
    $r = $conn->query("
        SELECT category, COALESCE(SUM(amount),0) AS total
        FROM sanctuary_expenses
        GROUP BY category
        ORDER BY total DESC
    ");
    while ($row = $r->fetch_assoc()) {
        $summary['expense_by_category'][$row['category']] = (float)$row['total'];
    }

    // Recent income (last 8)
    $r = $conn->query("
        SELECT fi.*, u.full_name AS logged_by
        FROM funding_income fi
        LEFT JOIN users u ON fi.created_by = u.id
        ORDER BY fi.created_at DESC
        LIMIT 8
    ");
    while ($row = $r->fetch_assoc()) $summary['recent_income'][] = $row;

    // Recent expenses (last 8)
    $r = $conn->query("
        SELECT se.*, u.full_name AS logged_by
        FROM sanctuary_expenses se
        LEFT JOIN users u ON se.created_by = u.id
        ORDER BY se.created_at DESC
        LIMIT 8
    ");
    while ($row = $r->fetch_assoc()) $summary['recent_expenses'][] = $row;

    // Monthly trend (last 6 months) — income vs expense
    $r = $conn->query("
        SELECT
            DATE_FORMAT(date_received, '%Y-%m') AS month,
            SUM(amount) AS income
        FROM funding_income
        WHERE date_received >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $income_by_month = [];
    while ($row = $r->fetch_assoc()) $income_by_month[$row['month']] = (float)$row['income'];

    $r = $conn->query("
        SELECT
            DATE_FORMAT(date_paid, '%Y-%m') AS month,
            SUM(amount) AS expense
        FROM sanctuary_expenses
        WHERE date_paid >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $expense_by_month = [];
    while ($row = $r->fetch_assoc()) $expense_by_month[$row['month']] = (float)$row['expense'];

    // Merge into trend array
    $all_months = array_unique(array_merge(array_keys($income_by_month), array_keys($expense_by_month)));
    sort($all_months);
    foreach ($all_months as $m) {
        $summary['monthly_trend'][] = [
            'month'   => $m,
            'income'  => $income_by_month[$m] ?? 0,
            'expense' => $expense_by_month[$m] ?? 0,
        ];
    }

    return $summary;
}

function getActiveSponsors($conn) {
    $r = $conn->query("
        SELECT rs.*, u.full_name, u.email, u.profile_image,
               a.name AS animal_name, a.species, a.image_path AS animal_image
        FROM resident_sponsorships rs
        JOIN users u ON rs.user_id = u.id
        JOIN animals a ON rs.animal_id = a.id
        WHERE rs.status = 'Active'
        ORDER BY rs.start_date DESC
    ");
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function getSponsorableAnimals($conn) {
    $r = $conn->query("
        SELECT id, name, species, breed, age, image_path
        FROM animals
        WHERE status = 'Resident of Sanctuary'
        ORDER BY name ASC
    ");
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function getStaffForSalary($conn) {
    $r = $conn->query("
        SELECT id, full_name, role, email
        FROM users
        WHERE role IN ('rescuer','vet') AND status = 'active'
        ORDER BY role, full_name
    ");
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function getAllFundingPaginated($conn, $page = 1, $limit = 15, $filter_source = '', $search = '') {
    $offset = ($page - 1) * $limit;
    $where = "WHERE 1=1";
    if ($filter_source) $where .= " AND fi.source_type = '" . $conn->real_escape_string($filter_source) . "'";
    if ($search) $where .= " AND (fi.source_name LIKE '%" . $conn->real_escape_string($search) . "%' OR fi.notes LIKE '%" . $conn->real_escape_string($search) . "%')";

    $count_r = $conn->query("SELECT COUNT(*) AS total FROM funding_income fi $where");
    $total = (int)$count_r->fetch_assoc()['total'];

    $r = $conn->query("
        SELECT fi.*, a.name AS animal_name, u.full_name AS logged_by
        FROM funding_income fi
        LEFT JOIN animals a ON fi.animal_id = a.id
        LEFT JOIN users u ON fi.created_by = u.id
        $where
        ORDER BY fi.date_received DESC, fi.id DESC
        LIMIT $limit OFFSET $offset
    ");
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;

    return ['data' => $rows, 'total' => $total, 'pages' => ceil($total / $limit)];
}

function getAllExpensesPaginated($conn, $page = 1, $limit = 15, $filter_cat = '', $search = '') {
    $offset = ($page - 1) * $limit;
    $where = "WHERE 1=1";
    if ($filter_cat) $where .= " AND se.category = '" . $conn->real_escape_string($filter_cat) . "'";
    if ($search) $where .= " AND (se.vendor_name_or_payee LIKE '%" . $conn->real_escape_string($search) . "%' OR se.notes LIKE '%" . $conn->real_escape_string($search) . "%')";

    $count_r = $conn->query("SELECT COUNT(*) AS total FROM sanctuary_expenses se $where");
    $total = (int)$count_r->fetch_assoc()['total'];

    $r = $conn->query("
        SELECT se.*, a.name AS animal_name, e.title AS event_name, u.full_name AS logged_by
        FROM sanctuary_expenses se
        LEFT JOIN animals a ON se.animal_id = a.id
        LEFT JOIN events e ON se.event_id = e.id
        LEFT JOIN users u ON se.created_by = u.id
        $where
        ORDER BY se.date_paid DESC, se.id DESC
        LIMIT $limit OFFSET $offset
    ");
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;

    return ['data' => $rows, 'total' => $total, 'pages' => ceil($total / $limit)];
}

function formatCurrency($amount) {
    return '৳' . number_format((float)$amount, 2);
}

function getSourceBadgeClass($source) {
    $map = [
        'General Donation'       => 'badge-donation',
        'Sponsorship'            => 'badge-sponsor',
        'Grant'                  => 'badge-grant',
        'SOS Campaign'           => 'badge-sos',
        'Partner Clinic'         => 'badge-clinic',
        'Surrender Contribution' => 'badge-surrender',
        'Owner Capital'          => 'badge-owner',
    ];
    return $map[$source] ?? 'badge-default';
}

function getCategoryBadgeClass($cat) {
    $map = [
        'Medical'              => 'badge-medical',
        'Food & Supplies'      => 'badge-food',
        'Facility Maintenance' => 'badge-facility',
        'Rescue Ops'           => 'badge-rescue',
        'Staff Salaries'       => 'badge-salary',
        'Event Management'     => 'badge-event',
        'Admin'                => 'badge-admin',
    ];
    return $map[$cat] ?? 'badge-default';
}
?>