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
        <!-- «Услуги» тут не группируем — в шапке из этой группы только
             один пункт (SMS-рассылки), группировать нечего. «Управление»
             — настоящая выпадающая группа, порядок пунктов внутри
             такой же, как в одноимённой группе плиток на панели. -->
        <a href="sms_campaign.php" class="<?= $activeNav === 'sms_campaign' ? 'active' : '' ?>">SMS-рассылки</a>
        <?php $managementActive = in_array($activeNav, ['analytics', 'employees', 'finance', 'warehouse', 'kudir'], true); ?>
        <div class="pay-dropdown">
          <button type="button" class="nav-dropdown-btn <?= $managementActive ? 'active' : '' ?>" onclick="toggleDropdownMenu(event, 'navManagementMenu')">Управление ▾</button>
          <div class="pay-dropdown-menu" id="navManagementMenu">
            <a href="analytics.php" class="pay-dropdown-item">Аналитика</a>
            <?php if (is_owner()): ?><a href="employees.php" class="pay-dropdown-item">Сотрудники</a><?php endif; ?>
            <a href="finance.php" class="pay-dropdown-item">Финансы</a>
            <a href="warehouse.php" class="pay-dropdown-item">Склад</a>
            <a href="kudir_export.php" class="pay-dropdown-item">КУДиР</a>
          </div>
        </div>
      <?php endif; ?>
      <?php if (is_owner()): ?>
        <a href="settings.php" class="<?= $activeNav === 'settings' ? 'active' : '' ?>">Настройки</a>
      <?php endif; ?>
    </nav>
    <div class="header-user">
      <?php if ($user): ?>
        <div class="pay-dropdown" style="display:inline-block;">
          <button type="button" class="profile-menu-btn" onclick="toggleDropdownMenu(event, 'headerProfileMenu')" style="background:none;border:none;color:inherit;font:inherit;cursor:pointer;padding:0;-webkit-tap-highlight-color:transparent;outline:none;">
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
