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
      <a href="repairs.php" class="<?= $activeNav === 'repairs' ? 'active' : '' ?>">Ремонты</a>
      <a href="clients.php" class="<?= $activeNav === 'clients' ? 'active' : '' ?>">Клиенты</a>
      <a href="smeta-sborka-pk.html" target="_blank" rel="noopener">Смета ПК ↗</a>
    </nav>
    <div class="header-user">
      <?php if ($user): ?>
        <span><?= e($user['full_name']) ?></span>
        <a href="logout.php" class="logout-link">Выйти</a>
      <?php endif; ?>
    </div>
  </header>
  <main class="content">
    <?php $flash = flash_get(); if ($flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
