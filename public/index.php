<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$pageTitle = 'Панель';
$activeNav = 'dashboard';

$repairsCount = (int) db()->query("SELECT COUNT(*) c FROM repairs WHERE status NOT IN ('выдан','отказ')")->fetch()['c'];
$clientsCount = (int) db()->query('SELECT COUNT(*) c FROM clients')->fetch()['c'];

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Панель управления</h2>
  <p style="color:var(--muted);font-size:13px;margin:0;">Внутренние инструменты сервиса АВИОР.</p>
</div>

<div class="modules">
  <a class="module-card active" href="repairs.php">
    <div class="module-icon">🛠️</div>
    <div class="module-title">Ремонты</div>
    <div class="module-desc">В работе сейчас: <strong><?= $repairsCount ?></strong>. Приём, статусы, диагностика, выдача.</div>
    <div class="module-arrow">Открыть →</div>
  </a>

  <a class="module-card active" href="clients.php">
    <div class="module-icon">👥</div>
    <div class="module-title">Клиенты</div>
    <div class="module-desc">Всего в базе: <strong><?= $clientsCount ?></strong>. Контакты и история обращений.</div>
    <div class="module-arrow">Открыть →</div>
  </a>

  <a class="module-card active" href="smeta-sborka-pk.html" target="_blank" rel="noopener">
    <div class="module-icon">🖥️</div>
    <div class="module-title">Сборка ПК</div>
    <div class="module-desc">Смета на сборку: комплектующие, услуги, печать и отправка клиенту.</div>
    <div class="module-arrow">Открыть →</div>
  </a>

  <div class="module-card disabled">
    <span class="module-badge">скоро</span>
    <div class="module-icon">📦</div>
    <div class="module-title">Склад</div>
    <div class="module-desc">Остатки комплектующих, приход/расход, единая база с формой сметы.</div>
  </div>

  <div class="module-card disabled">
    <span class="module-badge">скоро</span>
    <div class="module-icon">💰</div>
    <div class="module-title">Финансы</div>
    <div class="module-desc">Выручка, расходы, отчёты по периодам.</div>
  </div>

  <div class="module-card disabled">
    <span class="module-badge">скоро</span>
    <div class="module-icon">📨</div>
    <div class="module-title">SMS-рассылки</div>
    <div class="module-desc">Массовые уведомления клиентам, шаблоны сообщений.</div>
  </div>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
