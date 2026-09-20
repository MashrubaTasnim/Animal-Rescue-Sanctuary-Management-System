<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ledger Print — <?= htmlspecialchars(setting('site_name','Heartbeat Heaven')) ?></title>
<style>
  body { font-family: 'Inter', Arial, sans-serif; font-size: 11pt; margin: 20mm; }
  h2 { font-size: 14pt; margin-bottom: 4px; }
  .meta { font-size: 9pt; color: #666; margin-bottom: 12px; }
  .strip { display: flex; gap: 30px; border: 1px solid #ddd; border-radius: 6px;
           padding: 10px 14px; margin-bottom: 14px; font-size: 9pt; }
  .strip strong { display: block; font-size: 7pt; text-transform: uppercase;
                  letter-spacing: 1px; color: #999; }
  table { width: 100%; border-collapse: collapse; font-size: 9pt; }
  thead th { background: #f4f7fa; border-bottom: 2px solid #ddd;
             padding: 7px 8px; text-align: left; font-size: 7.5pt;
             text-transform: uppercase; letter-spacing: 0.8px; color: #555; }
  tbody td { padding: 6px 8px; border-bottom: 1px solid #eee; }
  tbody tr:last-child td { border-bottom: none; }
  .amount { font-weight: 700; }
  .income  { color: #16a34a; }
  .expense { color: #dc2626; }
  .badge { display:inline-block; padding:1px 7px; border-radius:12px;
           font-size:7.5pt; font-weight:700; background:#f1f5f9; color:#333; }
  .footer-note { margin-top: 16px; font-size: 8pt; color: #999; text-align: right; }
  @media screen { body { max-width: 900px; margin: 30px auto; } }
</style>
</head>
<body>

<h2><?= $tab === 'income' ? '📥 Funding Income Ledger' : '📤 Sanctuary Expenses Ledger' ?></h2>
<div class="meta">
  <?= htmlspecialchars(setting('site_name','Heartbeat Heaven')) ?> &nbsp;|&nbsp;
  Generated: <?= date('d M Y, h:i A') ?>
  <?php if ($filter): ?>&nbsp;|&nbsp; Filter: <strong><?= htmlspecialchars($filter) ?></strong><?php endif; ?>
  <?php if ($search): ?>&nbsp;|&nbsp; Search: "<em><?= htmlspecialchars($search) ?></em>"<?php endif; ?>
</div>

<div class="strip">
  <div><strong>Total Records</strong><?= count($all) ?></div>
  <div><strong>Filtered Total</strong>
    <span class="amount <?= $tab==='income'?'income':'expense' ?>"><?= formatCurrency($filtered_total) ?></span>
  </div>
  <div><strong>Grand Total (All Time)</strong><?= formatCurrency($grand_total) ?></div>
</div>

<?php if ($tab === 'income'): ?>
<table>
  <thead>
    <tr>
      <th>#</th><th>Source Type</th><th>Source Name</th>
      <th>Amount</th><th>Date Received</th><th>Linked Animal</th>
      <th>Logged By</th><th>Notes</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($all as $row): ?>
  <tr>
    <td style="color:#aaa;">#<?= $row['id'] ?></td>
    <td><span class="badge"><?= htmlspecialchars($row['source_type']) ?></span></td>
    <td><?= htmlspecialchars($row['source_name']) ?></td>
    <td class="amount income"><?= formatCurrency($row['amount']) ?></td>
    <td><?= date('d M Y', strtotime($row['date_received'])) ?></td>
    <td><?= htmlspecialchars($row['animal_name'] ?: '—') ?></td>
    <td><?= htmlspecialchars($row['logged_by'] ?: 'System') ?></td>
    <td style="color:#666;max-width:140px;"><?= htmlspecialchars($row['notes'] ?: '—') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th><th>Category</th><th>Vendor / Payee</th>
      <th>Amount</th><th>Date Paid</th><th>Linked Animal</th>
      <th>Linked Event</th><th>Notes</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($all as $row): ?>
  <tr>
    <td style="color:#aaa;">#<?= $row['id'] ?></td>
    <td><span class="badge"><?= htmlspecialchars($row['category']) ?></span></td>
    <td><?= htmlspecialchars($row['vendor_name_or_payee']) ?></td>
    <td class="amount expense"><?= formatCurrency($row['amount']) ?></td>
    <td><?= date('d M Y', strtotime($row['date_paid'])) ?></td>
    <td><?= htmlspecialchars($row['animal_name'] ?: '—') ?></td>
    <td><?= htmlspecialchars($row['event_name']  ?: '—') ?></td>
    <td style="color:#666;max-width:140px;"><?= htmlspecialchars($row['notes'] ?: '—') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="footer-note">Printed from <?= htmlspecialchars(setting('site_name','Heartbeat Heaven')) ?> Financial System</div>

<script>window.onload = () => window.print();</script>
</body>
</html>