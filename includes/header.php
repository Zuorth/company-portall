<?php
/** @var string $pageTitle  set by the page before including this file */
$current = basename($_SERVER['SCRIPT_NAME']);
$nav = [
    'index.php'        => 'Dashboard',
    'add_employee.php' => 'Add Employee',
    'log_hours.php'    => 'Assign Hours',
    'employees.php'    => 'Directory',
];
$flash = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · <?= e(app_config('app_name', 'Company Staff Portal')) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><span class="logo">C</span><?= e(app_config('app_name', 'Company Staff Portal')) ?></a>
  <nav>
    <?php foreach ($nav as $file => $label): ?>
      <a href="<?= e($file) ?>" class="<?= $current === $file ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main class="container">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
  <?php endif; ?>
