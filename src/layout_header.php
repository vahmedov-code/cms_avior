<?php
/**
 * Общая шапка CMS. Подключается на защищённых страницах после require_login().
 * Ожидает переменные: $pageTitle (string), $activeNav (string: dashboard|repairs|clients).
 */
$pageTitle = $pageTitle ?? 'АВИОР CMS';
$activeNav = $activeNav ?? '';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — АВИОР CMS</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="sheet">
  <header>
    <a href="index.php" class="brand-link">
      <div class="brand">
        <h1>АВИОР</h1>
        <p>Внутренняя CMS сервиса</p>
      </div>
    </a>
    <nav class="topnav">
      <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Панель</a>
      <a href="repairs.php" class="<?= $activeNav === 'repairs' ? 'active' : '' ?>">Заказы</a>
      <a href="clients.php" class="<?= $activeNav === 'clients' ? 'active' : '' ?>">Клиенты</a>
      <a href="finance.php" class="<?= $activeNav === 'finance' ? 'active' : '' ?>">Финансы</a>
      <a href="analytics.php" class="<?= $activeNav === 'analytics' ? 'active' : '' ?>">Аналитика</a>
      <?php if (is_admin()): ?>
        <a href="employees.php" class="<?= $activeNav === 'employees' ? 'active' : '' ?>">Сотрудники</a>
        <a href="settings.php" class="<?= $activeNav === 'settings' ? 'active' : '' ?>">Настройки</a>
      <?php endif; ?>
    </nav>
    <div class="header-user">
      <?php if ($user): ?>
        <a href="profile.php" class="<?= $activeNav === 'profile' ? 'active' : '' ?>" style="margin-right:10px;"><?= e($user['full_name']) ?></a>
        <a href="logout.php" class="logout-link">Выйти</a>
      <?php endif; ?>
    </div>
  </header>
  <main class="content">
    <?php $flash = flash_get(); if ($flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
