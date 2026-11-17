<?php
/**
 * Add Employee form -> INSERT INTO EMPLOYEE
 *
 * Adapted from textbook Fig. 11.1 (form + $_POST) and Fig. 11.6 lines 13-15
 * (INSERT with ? placeholders). Every field of the textbook EMPLOYEE table
 * is collected, validated, and saved.
 */
require_once __DIR__ . '/includes/helpers.php';

$fields = ['fname', 'minit', 'lname', 'ssn', 'bdate', 'address', 'sex', 'salary', 'super_ssn', 'dno'];
$old    = array_fill_keys($fields, '');   // values to refill the form after an error
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        foreach ($fields as $f) {
            $old[$f] = post($f);
        }
        // Accept SSNs typed as 123-45-6789 or 123 45 6789
        $old['ssn']       = preg_replace('/[\s-]/', '', $old['ssn']);
        $old['minit']     = strtoupper($old['minit']);

        // ---------- Validation (mirrors the column types in setup.sql) ----------
        if ($old['fname'] === '' || mb_strlen($old['fname']) > 15 || !preg_match("/^[\p{L} '\-]+$/u", $old['fname'])) {
            $errors['fname'] = 'First name is required: letters only, up to 15 characters.';
        }
        if ($old['minit'] !== '' && !preg_match('/^[A-Z]$/', $old['minit'])) {
            $errors['minit'] = 'Middle initial must be a single letter.';
        }
        if ($old['lname'] === '' || mb_strlen($old['lname']) > 15 || !preg_match("/^[\p{L} '\-]+$/u", $old['lname'])) {
            $errors['lname'] = 'Last name is required: letters only, up to 15 characters.';
        }
        if (!preg_match('/^\d{9}$/', $old['ssn'])) {
            $errors['ssn'] = 'SSN must be exactly 9 digits.';
        }
        $bdate = DateTime::createFromFormat('!Y-m-d', $old['bdate']);
        if (!$bdate || $bdate->format('Y-m-d') !== $old['bdate']) {
            $errors['bdate'] = 'Enter a valid birth date.';
        } else {
            $age = $bdate->diff(new DateTime('today'))->y;
            if ($bdate > new DateTime('today') || $age < 16 || $age > 100) {
                $errors['bdate'] = 'Employees must be between 16 and 100 years old.';
            }
        }
        if ($old['address'] === '' || mb_strlen($old['address']) > 30) {
            $errors['address'] = 'Address is required, up to 30 characters.';
        }
        if (!in_array($old['sex'], ['M', 'F'], true)) {
            $errors['sex'] = 'Choose M or F.';
        }
        $salary = str_replace([',', '$'], '', $old['salary']);
        if (!is_numeric($salary) || $salary < 0 || $salary > 9999999) {
            $errors['salary'] = 'Salary must be a number between 0 and 9,999,999.';
        }

        // Supervisor and department must exist (checked against the DB)
        if ($old['super_ssn'] !== '') {
            $st = db()->prepare('SELECT 1 FROM EMPLOYEE WHERE Ssn = ?');
            $st->execute([$old['super_ssn']]);
            if (!$st->fetchColumn()) {
                $errors['super_ssn'] = 'Pick a supervisor from the list.';
            }
        }
        $st = db()->prepare('SELECT 1 FROM DEPARTMENT WHERE Dnumber = ?');
        $st->execute([$old['dno']]);
        if (!ctype_digit($old['dno']) || !$st->fetchColumn()) {
            $errors['dno'] = 'Pick a department from the list.';
        }

        // ---------- Save ----------
        if (!$errors) {
            try {
                $sql = 'INSERT INTO EMPLOYEE
                          (Fname, Minit, Lname, Ssn, Bdate, Address, Sex, Salary, Super_ssn, Dno)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                db()->prepare($sql)->execute([
                    $old['fname'],
                    $old['minit'] === '' ? null : $old['minit'],
                    $old['lname'],
                    $old['ssn'],
                    $old['bdate'],
                    $old['address'],
                    $old['sex'],
                    $salary,
                    $old['super_ssn'] === '' ? null : $old['super_ssn'],
                    (int) $old['dno'],
                ]);
                $name = "{$old['fname']} {$old['lname']}";
                audit('add_employee', 'saved', "Added $name (SSN ***-**-" . substr($old['ssn'], -4) . ")");
                flash('success', "Saved! $name was added to the EMPLOYEE table.");
                redirect('employees.php?highlight=' . urlencode($old['ssn']));
            } catch (PDOException $e) {
                // 1062 = duplicate primary key (the SSN already exists)
                if (($e->errorInfo[1] ?? null) === 1062) {
                    $errors['ssn'] = 'An employee with this SSN already exists.';
                } else {
                    error_log($e->getMessage());
                    $errors['form'] = 'The database rejected this record. Please check the values and try again.';
                }
            }
        }

        if ($errors) {
            audit('add_employee', 'rejected', count($errors) === 1
                ? 'Rejected: ' . reset($errors)
                : 'Rejected: ' . count($errors) . ' invalid fields (' . implode(', ', array_keys($errors)) . ')');
        }
    }
}

$departments = departments();
$supervisors = employees_for_select();
$pageTitle   = 'Add Employee';
require __DIR__ . '/includes/header.php';

/** Prints an error message under a field, if there is one. */
function field_error(array $errors, string $f): string
{
    return isset($errors[$f]) ? '<p class="field-error">' . e($errors[$f]) . '</p>' : '';
}
function invalid(array $errors, string $f): string
{
    return isset($errors[$f]) ? ' aria-invalid="true"' : '';
}
?>
<section class="page-head">
  <h1>Add an employee</h1>
  <p>Fill in every field and press <strong>Save employee</strong>. The record goes straight into the <code>EMPLOYEE</code> table.</p>
</section>

<?php if (isset($errors['form'])): ?>
  <div class="alert alert-error"><?= e($errors['form']) ?></div>
<?php elseif ($errors): ?>
  <div class="alert alert-error">Please fix the <?= count($errors) ?> highlighted field<?= count($errors) > 1 ? 's' : '' ?> below.</div>
<?php endif; ?>

<form method="post" action="add_employee.php" class="card form" novalidate>
  <?= csrf_field() ?>

  <fieldset>
    <legend>Personal details</legend>
    <div class="grid grid-3">
      <label>First name
        <input name="fname" maxlength="15" required value="<?= e($old['fname']) ?>"<?= invalid($errors, 'fname') ?>>
        <?= field_error($errors, 'fname') ?>
      </label>
      <label>Middle initial <span class="opt">optional</span>
        <input name="minit" maxlength="1" value="<?= e($old['minit']) ?>"<?= invalid($errors, 'minit') ?>>
        <?= field_error($errors, 'minit') ?>
      </label>
      <label>Last name
        <input name="lname" maxlength="15" required value="<?= e($old['lname']) ?>"<?= invalid($errors, 'lname') ?>>
        <?= field_error($errors, 'lname') ?>
      </label>
      <label>SSN
        <input name="ssn" inputmode="numeric" maxlength="11" placeholder="123-45-6789" required value="<?= e($old['ssn']) ?>"<?= invalid($errors, 'ssn') ?>>
        <?= field_error($errors, 'ssn') ?>
      </label>
      <label>Birth date
        <input type="date" name="bdate" required value="<?= e($old['bdate']) ?>"<?= invalid($errors, 'bdate') ?>>
        <?= field_error($errors, 'bdate') ?>
      </label>
      <div class="radio-group">
        <span class="label">Sex</span>
        <label class="inline"><input type="radio" name="sex" value="M" <?= $old['sex'] === 'M' ? 'checked' : '' ?>> M</label>
        <label class="inline"><input type="radio" name="sex" value="F" <?= $old['sex'] === 'F' ? 'checked' : '' ?>> F</label>
        <?= field_error($errors, 'sex') ?>
      </div>
    </div>
    <label>Address
      <input name="address" maxlength="30" placeholder="731 Fondren, Houston, TX" required value="<?= e($old['address']) ?>"<?= invalid($errors, 'address') ?>>
      <?= field_error($errors, 'address') ?>
    </label>
  </fieldset>

  <fieldset>
    <legend>Job details</legend>
    <div class="grid grid-3">
      <label>Salary (USD)
        <input name="salary" inputmode="decimal" placeholder="35000" required value="<?= e($old['salary']) ?>"<?= invalid($errors, 'salary') ?>>
        <?= field_error($errors, 'salary') ?>
      </label>
      <label>Department
        <select name="dno" required<?= invalid($errors, 'dno') ?>>
          <option value="">Choose…</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= e($d['Dnumber']) ?>" <?= $old['dno'] === (string) $d['Dnumber'] ? 'selected' : '' ?>>
              <?= e($d['Dname']) ?> (#<?= e($d['Dnumber']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'dno') ?>
      </label>
      <label>Supervisor <span class="opt">optional</span>
        <select name="super_ssn"<?= invalid($errors, 'super_ssn') ?>>
          <option value="">No supervisor</option>
          <?php foreach ($supervisors as $s): ?>
            <option value="<?= e($s['Ssn']) ?>" <?= $old['super_ssn'] === $s['Ssn'] ? 'selected' : '' ?>>
              <?= e($s['Lname'] . ', ' . $s['Fname']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'super_ssn') ?>
      </label>
    </div>
  </fieldset>

  <div class="actions">
    <button type="submit" class="btn">Save employee</button>
    <a href="employees.php" class="btn btn-ghost">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
