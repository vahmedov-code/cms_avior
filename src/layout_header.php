<?php
/**
 * Общая шапка CRM. Подключается на защищённых страницах после require_login().
 * Ожидает переменные: $pageTitle (string), $activeNav (string: dashboard|repairs|clients).
 */
$pageTitle = $pageTitle ?? 'АВИОР CRM';
$activeNav = $activeNav ?? '';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — АВИОР CRM</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="sheet">
  <header>
    <a href="index.php" class="brand-link">
      <div class="brand">
        <h1>АВИОР</h1>
        <p>Внутренняя CRM сервиса</p>
      </div>
    </a>
    <nav class="topnav">
      <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Панель</a>
      <a href="repairs.php" class="<?= $activeNav === 'repairs' ? 'active' : '' ?>">Заказы</a>
      <a href="clients.php" class="<?= $activeNav === 'clients' ? 'active' : '' ?>">Клиенты</a>
      <?php if (is_admin()): ?>
        <!-- Порядок ниже — как в группах плиток на панели (index.php):
             сначала «Услуги» (тут — только SMS-рассылки, остальное в
             этой группе не выводится в шапку), потом «Управление» в
             том же порядке, что и там (Аналитика/Сотрудники/Финансы/
             Склад/КУДиР — Бухгалтерия в плитках, тут просто КУДиР). -->
        <a href="sms_campaign.php" class="<?= $activeNav === 'sms_campaign' ? 'active' : '' ?>">SMS-рассылки</a>
        <a href="analytics.php" class="<?= $activeNav === 'analytics' ? 'active' : '' ?>">Аналитика</a>
      <?php endif; ?>
      <?php if (is_owner()): ?>
        <a href="employees.php" class="<?= $activeNav === 'employees' ? 'active' : '' ?>">Сотрудники</a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
        <a href="finance.php" class="<?= $activeNav === 'finance' ? 'active' : '' ?>">Финансы</a>
        <a href="warehouse.php" class="<?= $activeNav === 'warehouse' ? 'active' : '' ?>">Склад</a>
        <a href="kudir_export.php" class="<?= $activeNav === 'kudir' ? 'active' : '' ?>">КУДиР</a>
      <?php endif; ?>
      <?php if (is_owner()): ?>
        <a href="settings.php" class="<?= $activeNav === 'settings' ? 'active' : '' ?>">Настройки</a>
      <?php endif; ?>
    </nav>
    <div class="header-user">
      <?php if ($user): ?>
        <div class="pay-dropdown" style="display:inline-block;">
          <button type="button" class="profile-menu-btn" onclick="toggleHeaderMenu(event)" style="background:none;border:none;color:inherit;font:inherit;cursor:pointer;padding:0;">
            <?= e($user['full_name']) ?> ▾
          </button>
          <div class="pay-dropdown-menu" id="headerProfileMenu">
            <a href="profile.php" class="pay-dropdown-item" style="text-decoration:none;">⚙️ Настройки профиля</a>
            <a href="logout.php" class="pay-dropdown-item" style="text-decoration:none;color:var(--danger);">🚪 Выйти</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </header>
  <main class="content">
    <?php $flash = flash_get(); if ($flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
