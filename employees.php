<?php
/**
 * Employee Directory: search + CSV export
 *
 * Adapted from textbook Fig. 11.7: a "dynamic query" whose WHERE clause
 * depends on what the user types (lines 8-13), and getAll()/foreach
 * looping over the result (lines 14-17). Uses GET so searches can be
 * bookmarked, and ? placeholders so user input never becomes SQL.
 */
require_once __DIR__ . '/includes/helpers.php';

$q    = trim((string) ($_GET['q'] ?? ''));
$dno  = (string) ($_GET['dno'] ?? '');
$sort = (string) ($_GET['sort'] ?? 'name');

// Whitelist of sort orders: column names can't be ? placeholders,
// so only these exact strings are ever put into the SQL.
$sorts = [
    'name'       => 'E.Lname, E.Fname',
    'salary'     => 'E.Salary DESC, E.Lname',
    'department' => 'D.Dname, E.Lname',
    'age'        => 'E.Bdate',
];
$orderBy = $sorts[$sort] ?? $sorts['name'];

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = "(CONCAT(E.Fname, ' ', E.Lname) LIKE ? OR E.Address LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($dno !== '' && ctype_digit($dno)) {
    $where[]  = 'E.Dno = ?';
    $params[] = (int) $dno;
}

$sql = "SELECT E.Fname, E.Minit, E.Lname, E.Ssn, E.Bdate, E.Address, E.Sex, E.Salary,
               D.Dname, CONCAT(S.Fname, ' ', S.Lname) AS Supervisor,
               COALESCE(SUM(W.Hours), 0) AS Hours, COUNT(W.Pno) AS Projects
          FROM EMPLOYEE E
          JOIN DEPARTMENT D    ON D.Dnumber = E.Dno
          LEFT JOIN EMPLOYEE S ON S.Ssn = E.Super_ssn
          LEFT JOIN WORKS_ON W ON W.Essn = E.Ssn"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " GROUP BY E.Ssn
         ORDER BY $orderBy";

$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// ---------- CSV export of exactly what's on screen ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['First', 'MI', 'Last', 'Birth date', 'Address', 'Sex', 'Salary', 'Department', 'Supervisor', 'Projects', 'Hours/week']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['Fname'], $r['Minit'], $r['Lname'], $r['Bdate'], $r['Address'], $r['Sex'],
                       $r['Salary'], $r['Dname'], $r['Supervisor'], $r['Projects'], $r['Hours']]);
    }
    exit;
}

$highlight   = (string) ($_GET['highlight'] ?? '');
$departments = departments();
$exportUrl   = 'employees.php?' . http_build_query(['q' => $q, 'dno' => $dno, 'sort' => $sort, 'export' => 'csv']);
$pageTitle   = 'Directory';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head row">
  <div>
    <h1>Employee directory</h1>
    <p><?= count($rows) ?> employee<?= count($rows) === 1 ? '' : 's' ?><?= ($q !== '' || $dno !== '') ? ' match your search' : '' ?>.</p>
  </div>
  <div class="head-actions">
    <a class="btn btn-ghost" href="<?= e($exportUrl) ?>">Export CSV</a>
    <a class="btn" href="add_employee.php">+ Add employee</a>
  </div>
</section>

<form method="get" action="employees.php" class="card filters">
  <label>Search name or address
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="e.g. Wong or Houston">
  </label>
  <label>Department
    <select name="dno">
      <option value="">All departments</option>
      <?php foreach ($departments as $d): ?>
        <option value="<?= e($d['Dnumber']) ?>" <?= $dno === (string) $d['Dnumber'] ? 'selected' : '' ?>><?= e($d['Dname']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Sort by
    <select name="sort">
      <?php foreach (['name' => 'Name', 'salary' => 'Salary (high→low)', 'department' => 'Department', 'age' => 'Age (oldest first)'] as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="actions">
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== '' || $dno !== '' || $sort !== 'name'): ?><a class="btn btn-ghost" href="employees.php">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card table-wrap">
  <?php if (!$rows): ?>
    <p class="muted empty">No employees match. Try a different search.</p>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr><th>Name</th><th>SSN</th><th>Department</th><th>Supervisor</th><th class="num">Salary</th><th class="num">Hours/wk</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= $highlight === $r['Ssn'] ? 'new-row' : '' ?>">
          <td>
            <strong><?= e($r['Fname'] . ' ' . ($r['Minit'] ? $r['Minit'] . '. ' : '') . $r['Lname']) ?></strong>
            <?php if ($highlight === $r['Ssn']): ?><span class="badge">new</span><?php endif; ?>
            <div class="muted small"><?= e($r['Address']) ?></div>
          </td>
          <td class="mono">***-**-<?= e(substr($r['Ssn'], -4)) ?></td>
          <td><?= e($r['Dname']) ?></td>
          <td><?= e($r['Supervisor'] ?? '-') ?></td>
          <td class="num"><?= e(money($r['Salary'])) ?></td>
          <td class="num"><?= e((float) $r['Hours']) ?> <span class="muted small">(<?= e($r['Projects']) ?>)</span></td>
          <td><a href="log_hours.php?essn=<?= e(urlencode($r['Ssn'])) ?>">Hours</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
