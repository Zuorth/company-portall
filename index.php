<?php
/**
 * Dashboard: live numbers from the database, department summary
 * (a GROUP BY join over EMPLOYEE and DEPARTMENT), and the audit trail
 * of recent form submissions.
 */
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();

$stats = $pdo->query(
    'SELECT (SELECT COUNT(*) FROM EMPLOYEE)   AS employees,
            (SELECT COUNT(*) FROM DEPARTMENT) AS departments,
            (SELECT COUNT(*) FROM PROJECT)    AS projects,
            (SELECT AVG(Salary) FROM EMPLOYEE) AS avg_salary'
)->fetch();

$byDept = $pdo->query(
    "SELECT D.Dnumber, D.Dname, CONCAT(M.Fname, ' ', M.Lname) AS Manager,
            COUNT(E.Ssn) AS Headcount, AVG(E.Salary) AS AvgSalary, SUM(E.Salary) AS Payroll
       FROM DEPARTMENT D
       LEFT JOIN EMPLOYEE M ON M.Ssn = D.Mgr_ssn
       LEFT JOIN EMPLOYEE E ON E.Dno = D.Dnumber
      GROUP BY D.Dnumber, D.Dname, Manager
      ORDER BY Headcount DESC, D.Dname"
)->fetchAll();
$maxHead = max(array_column($byDept, 'Headcount') ?: [1]);

$activity = $pdo->query(
    'SELECT Form_name, Outcome, Summary, Created_at
       FROM SUBMISSION_LOG ORDER BY Log_id DESC LIMIT 8'
)->fetchAll();

$formLabels = ['add_employee' => 'Add Employee', 'log_hours' => 'Assign Hours'];
$pageTitle  = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <h1>Dashboard</h1>
  <p>Everything on this page is read live from the <code>company_portal</code> database.</p>
</section>

<div class="stats">
  <div class="card stat"><span class="stat-label">Employees</span><span class="stat-value"><?= e($stats['employees']) ?></span></div>
  <div class="card stat"><span class="stat-label">Departments</span><span class="stat-value"><?= e($stats['departments']) ?></span></div>
  <div class="card stat"><span class="stat-label">Projects</span><span class="stat-value"><?= e($stats['projects']) ?></span></div>
  <div class="card stat"><span class="stat-label">Average salary</span><span class="stat-value"><?= e(money($stats['avg_salary'])) ?></span></div>
</div>

<div class="two-col">
  <section class="card">
    <h2>Departments</h2>
    <table class="table">
      <thead><tr><th>Department</th><th>Manager</th><th>Headcount</th><th class="num">Avg salary</th></tr></thead>
      <tbody>
        <?php foreach ($byDept as $d): ?>
          <tr>
            <td><a href="employees.php?dno=<?= e($d['Dnumber']) ?>"><?= e($d['Dname']) ?></a></td>
            <td><?= e($d['Manager'] ?? '-') ?></td>
            <td>
              <div class="bar"><span style="width: <?= $d['Headcount'] / $maxHead * 100 ?>%"></span></div>
              <span class="small"><?= e($d['Headcount']) ?></span>
            </td>
            <td class="num"><?= e(money($d['AvgSalary'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>Recent form activity</h2>
    <?php if (!$activity): ?>
      <p class="muted">No submissions yet. <a href="add_employee.php">Add an employee</a> to see it logged here.</p>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($activity as $a): ?>
          <li>
            <span class="pill pill-<?= e($a['Outcome']) ?>"><?= e($a['Outcome']) ?></span>
            <div>
              <div><?= e($a['Summary']) ?></div>
              <div class="muted small"><?= e($formLabels[$a['Form_name']] ?? $a['Form_name']) ?> · <?= e(date('M j, g:i a', strtotime($a['Created_at']))) ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
