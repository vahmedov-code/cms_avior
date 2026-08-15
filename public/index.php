<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$pageTitle = 'Панель';
$activeNav = 'dashboard';

$repairsCount = (int) db()->query("SELECT COUNT(*) c FROM repairs WHERE status NOT IN ('выдан','отказ')")->fetch()['c'];
$clientsCount = (int) db()->query('SELECT COUNT(*) c FROM clients')->fetch()['c'];

// Счётчики заказов по статусным группам — для блока-конвейера на панели.
$statusCounts = array_fill_keys(
    ['принят', 'диагностика', 'согласование', 'в ремонте', 'готов', 'выдан', 'отказ'],
    0
);
foreach (db()->query('SELECT status, COUNT(*) c FROM repairs GROUP BY status') as $row) {
    $statusCounts[$row['status']] = (int) $row['c'];
}
$pipelineNew = $statusCounts['принят'];
$pipelineProgress = $statusCounts['диагностика'] + $statusCounts['в ремонте'];
$pipelineHold = $statusCounts['согласование'];
$pipelineReady = $statusCounts['готов'];
$pipelineIssued = $statusCounts['выдан'];

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Панель управления</h2>
  <p style="color:var(--muted);font-size:13px;margin:0;">Внутренние инструменты сервиса АВИОР.</p>
</div>

<div class="pipeline">
  <a class="pipeline-card pipeline-new" href="repairs.php?status=принят">
    <div class="pc-count"><?= $pipelineNew ?></div>
    <div class="pc-label">Новые</div>
  </a>
  <a class="pipeline-card pipeline-progress" href="repairs.php?status=<?= urlencode('диагностика,в ремонте') ?>">
    <div class="pc-count"><?= $pipelineProgress ?></div>
    <div class="pc-label">В работе</div>
  </a>
  <a class="pipeline-card pipeline-hold" href="repairs.php?status=согласование">
    <div class="pc-count"><?= $pipelineHold ?></div>
    <div class="pc-label">Отложенные</div>
  </a>
  <a class="pipeline-card pipeline-ready" href="repairs.php?status=готов">
    <div class="pc-count"><?= $pipelineReady ?></div>
    <div class="pc-label">Готовые</div>
  </a>
  <a class="pipeline-card pipeline-issued" href="repairs.php?status=выдан">
    <div class="pc-count"><?= $pipelineIssued ?></div>
    <div class="pc-label">Выданные</div>
  </a>
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

  <a class="module-card active" href="pc_build_new.php">
    <div class="module-icon">🖥️</div>
    <div class="module-title">Сборка ПК</div>
    <div class="module-desc">Новый заказ на сборку — комплектующие и услуги, сразу в общем списке заказов.</div>
    <div class="module-arrow">Открыть →</div>
  </a>

  <a class="module-card active" href="account_memo_new.php">
    <div class="module-icon">🔐</div>
    <div class="module-title">Памятка по аккаунту</div>
    <div class="module-desc">Печатная форма данных Apple ID / Google для клиента. Пароли не хранятся в базе.</div>
    <div class="module-arrow">Открыть →</div>
  </a>

  <a class="module-card active" href="finance.php">
    <div class="module-icon">💰</div>
    <div class="module-title">Финансы</div>
    <div class="module-desc">Выручка, расходы, чистая прибыль по периодам.</div>
    <div class="module-arrow">Открыть →</div>
  </a>

  <a class="module-card active" href="analytics.php">
    <div class="module-icon">📊</div>
    <div class="module-title">Аналитика</div>
    <div class="module-desc">Источники клиентов, популярные устройства, прибыльность.</div>
    <div class="module-arrow">Открыть →</div>
  </a>

  <?php if (is_admin()): ?>
  <a class="module-card active" href="employees.php">
    <div class="module-icon">👥</div>
    <div class="module-title">Сотрудники</div>
    <div class="module-desc">Добавление и удаление учётных записей, роли, сброс паролей.</div>
    <div class="module-arrow">Открыть →</div>
  </a>

  <a class="module-card active" href="settings.php">
    <div class="module-icon">🛠️</div>
    <div class="module-title">Настройки</div>
    <div class="module-desc">Реквизиты компании, site_url, SMS-провайдер — без правки файлов на сервере.</div>
    <div class="module-arrow">Открыть →</div>
  </a>
  <?php endif; ?>

  <a class="module-card active" href="profile.php">
    <div class="module-icon">⚙️</div>
    <div class="module-title">Настройки / профиль</div>
    <div class="module-desc">Сменить свой пароль и отображаемое имя.</div>
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
    <div class="module-icon">📨</div>
    <div class="module-title">SMS-рассылки</div>
    <div class="module-desc">Массовые уведомления клиентам, шаблоны сообщений.</div>
  </div>

  <a class="module-card" href="smeta-sborka-pk.html" target="_blank" rel="noopener" style="border-style:dashed;">
    <div class="module-icon" style="background:#eef1f8;color:var(--navy);">📄</div>
    <div class="module-title" style="color:var(--muted);">Смета ПК (офлайн-версия)</div>
    <div class="module-desc">Старая автономная форма — работает без входа в CMS, но не попадает в «Заказы».</div>
    <div class="module-arrow">Открыть →</div>
  </a>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
