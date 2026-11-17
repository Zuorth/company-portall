<?php
/**
 * Assign Hours form -> INSERT ... ON DUPLICATE KEY UPDATE into WORKS_ON
 *
 * A second form that writes to a relationship table: it links an EMPLOYEE
 * to a PROJECT with a number of weekly hours. If the pair already exists,
 * the hours are updated instead of failing with "Duplicate entry".
 */
require_once __DIR__ . '/includes/helpers.php';

const MAX_WEEKLY_HOURS = 60;

$old    = ['essn' => $_GET['essn'] ?? '', 'pno' => '', 'hours' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        $old = ['essn' => post('essn'), 'pno' => post('pno'), 'hours' => post('hours')];

        $st = db()->prepare('SELECT Fname, Lname FROM EMPLOYEE WHERE Ssn = ?');
        $st->execute([$old['essn']]);
        $emp = $st->fetch();
        if (!$emp) {
            $errors['essn'] = 'Pick an employee from the list.';
        }

        $st = db()->prepare('SELECT Pname FROM PROJECT WHERE Pnumber = ?');
        $st->execute([$old['pno']]);
        $proj = ctype_digit($old['pno']) ? $st->fetch() : false;
        if (!$proj) {
            $errors['pno'] = 'Pick a project from the list.';
        }

        if (!is_numeric($old['hours']) || $old['hours'] <= 0 || $old['hours'] > 60
            || round((float) $old['hours'] * 2) != (float) $old['hours'] * 2) {
            $errors['hours'] = 'Hours must be between 0.5 and 60, in half-hour steps.';
        }

        // Business rule: total weekly hours across all projects can't exceed 60
        if (!$errors) {
            $st = db()->prepare('SELECT COALESCE(SUM(Hours), 0) FROM WORKS_ON WHERE Essn = ? AND Pno <> ?');
            $st->execute([$old['essn'], $old['pno']]);
            $other = (float) $st->fetchColumn();
            if ($other + (float) $old['hours'] > MAX_WEEKLY_HOURS) {
                $left = MAX_WEEKLY_HOURS - $other;
                $errors['hours'] = "This would put {$emp['Fname']} over " . MAX_WEEKLY_HOURS
                    . " hours a week. They already have $other hours on other projects, so the most you can assign here is $left.";
            }
        }

        if (!$errors) {
            $st = db()->prepare(
                'INSERT INTO WORKS_ON (Essn, Pno, Hours) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE Hours = VALUES(Hours)'
            );
            $st->execute([$old['essn'], (int) $old['pno'], $old['hours']]);
            // rowCount(): 1 = new row inserted, 2 = existing row updated, 0 = same value
            $outcome = $st->rowCount() === 1 ? 'saved' : 'updated';
            $who     = "{$emp['Fname']} {$emp['Lname']}";
            $msg     = "$who: {$old['hours']} hrs/week on {$proj['Pname']}";
            audit('log_hours', $outcome, ($outcome === 'saved' ? 'Assigned ' : 'Updated ') . $msg);
            flash('success', ($outcome === 'saved' ? 'Saved! Assigned ' : 'Updated! ') . $msg . '.');
            redirect('log_hours.php?essn=' . urlencode($old['essn']));
        }

        audit('log_hours', 'rejected', isset($other, $emp) && isset($errors['hours']) && count($errors) === 1
            ? "Rejected: {$emp['Fname']} {$emp['Lname']} would exceed " . MAX_WEEKLY_HOURS . ' hrs/week'
            : 'Rejected: ' . count($errors) . ' invalid field(s) (' . implode(', ', array_keys($errors)) . ')');
    }
}

// Current assignments for the selected employee (shown beside the form)
$current = [];
$total   = 0;
if ($old['essn'] !== '') {
    $st = db()->prepare(
        'SELECT P.Pname, P.Plocation, W.Hours
           FROM WORKS_ON W JOIN PROJECT P ON P.Pnumber = W.Pno
          WHERE W.Essn = ?
          ORDER BY P.Pname'
    );
    $st->execute([$old['essn']]);
    $current = $st->fetchAll();
    $total   = array_sum(array_column($current, 'Hours'));
}

$employees = employees_for_select();
$projects  = projects();
$pageTitle = 'Assign Hours';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <h1>Assign project hours</h1>
  <p>Put an employee on a project for a number of hours a week. Choosing a project they're already on updates their hours.</p>
</section>

<?php if (isset($errors['form'])): ?>
  <div class="alert alert-error"><?= e($errors['form']) ?></div>
<?php endif; ?>

<div class="two-col">
  <form method="post" action="log_hours.php" class="card form" novalidate>
    <?= csrf_field() ?>
    <label>Employee
      <select name="essn" required onchange="location='log_hours.php?essn='+encodeURIComponent(this.value)"<?= isset($errors['essn']) ? ' aria-invalid="true"' : '' ?>>
        <option value="">Choose…</option>
        <?php foreach ($employees as $emp): ?>
          <option value="<?= e($emp['Ssn']) ?>" <?= $old['essn'] === $emp['Ssn'] ? 'selected' : '' ?>><?= e($emp['Lname'] . ', ' . $emp['Fname']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['essn'])): ?><p class="field-error"><?= e($errors['essn']) ?></p><?php endif; ?>
    </label>
    <label>Project
      <select name="pno" required<?= isset($errors['pno']) ? ' aria-invalid="true"' : '' ?>>
        <option value="">Choose…</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= e($p['Pnumber']) ?>" <?= $old['pno'] === (string) $p['Pnumber'] ? 'selected' : '' ?>><?= e($p['Pname']) ?> · <?= e($p['Plocation']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['pno'])): ?><p class="field-error"><?= e($errors['pno']) ?></p><?php endif; ?>
    </label>
    <label>Hours per week
      <input type="number" name="hours" min="0.5" max="60" step="0.5" required value="<?= e($old['hours']) ?>"<?= isset($errors['hours']) ? ' aria-invalid="true"' : '' ?>>
      <?php if (isset($errors['hours'])): ?><p class="field-error"><?= e($errors['hours']) ?></p><?php endif; ?>
    </label>
    <div class="actions"><button type="submit" class="btn">Save hours</button></div>
  </form>

  <aside class="card">
    <h2>Current assignments</h2>
    <?php if ($old['essn'] === ''): ?>
      <p class="muted">Choose an employee to see their projects.</p>
    <?php elseif (!$current): ?>
      <p class="muted">Not on any projects yet.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Project</th><th>Location</th><th class="num">Hours</th></tr></thead>
        <tbody>
          <?php foreach ($current as $row): ?>
            <tr><td><?= e($row['Pname']) ?></td><td><?= e($row['Plocation']) ?></td><td class="num"><?= $row['Hours'] === null ? '-' : e($row['Hours']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="2">Total</th><th class="num"><?= e($total) ?> / <?= MAX_WEEKLY_HOURS ?></th></tr></tfoot>
      </table>
      <div class="meter" aria-hidden="true"><span style="width: <?= min(100, $total / MAX_WEEKLY_HOURS * 100) ?>%"></span></div>
    <?php endif; ?>
  </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
