<?php
/**
 * Массовые SMS-рассылки — только через SMS.ru (отдельный ключ
 * bulk_sms_api_key в Настройках, независимый от обычных уведомлений
 * по заказу). Только admin/owner — не для инженера-приёмщика, как
 * финансы/аналитика/склад (маркетинговая рассылка — решение уровня
 * бизнеса, не повседневная операционка).
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_admin();

$pageTitle = 'SMS-рассылки';
$activeNav = 'sms_campaign';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'send_campaign') {
    $message = trim(post('message'));
    $clientIds = array_map('intval', $_POST['client_ids'] ?? []);
    $clientIds = array_unique(array_filter($clientIds));
    $consentConfirmed = post('consent_confirmed') === '1';

    $bulkApiKey = get_setting('bulk_sms_api_key') ?: '';

    if ($bulkApiKey === '') {
        flash_set('Сначала укажите api_id SMS.ru для массовых рассылок в Настройках.', 'error');
    } elseif ($message === '') {
        flash_set('Введите текст сообщения.', 'error');
    } elseif (!$clientIds) {
        flash_set('Выберите хотя бы одного получателя.', 'error');
    } elseif (!$consentConfirmed) {
        flash_set('Нужно подтвердить, что у получателей есть согласие на рассылку.', 'error');
    } else {
        set_time_limit(300); // большая рассылка может занять больше стандартных 30 секунд

        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = db()->prepare("SELECT id, phone FROM clients WHERE id IN ($placeholders)");
        $stmt->execute($clientIds);
        $recipients = $stmt->fetchAll();

        $campaignStmt = db()->prepare(
            'INSERT INTO sms_campaigns (message, recipients_count, created_by) VALUES (?, ?, ?)'
        );
        $campaignStmt->execute([$message, count($recipients), current_user()['id'] ?? null]);
        $campaignId = (int) db()->lastInsertId();

        $sentCount = 0;
        $failedCount = 0;
        foreach ($recipients as $r) {
            if (send_bulk_sms($r['phone'], $message, $campaignId)) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        db()->prepare('UPDATE sms_campaigns SET sent_count = ?, failed_count = ? WHERE id = ?')
            ->execute([$sentCount, $failedCount, $campaignId]);

        flash_set("Рассылка завершена: отправлено {$sentCount} из " . count($recipients) . ($failedCount ? ", не удалось: {$failedCount}" : '') . '.', $failedCount ? 'error' : 'success');
    }
    redirect('sms_campaign.php');
}

$bulkConfigured = (get_setting('bulk_sms_api_key') ?: '') !== '';
$clients = db()->query('SELECT id, full_name, phone, source FROM clients ORDER BY full_name')->fetchAll();
$campaigns = db()->query('SELECT * FROM sms_campaigns ORDER BY created_at DESC LIMIT 20')->fetchAll();

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>📨 SMS-рассылки</h2>
</div>

<?php if (!$bulkConfigured): ?>
  <div class="table-card" style="padding:14px 16px;margin-bottom:20px;border-color:#f0c4bd;background:#fbe9e7;">
    <strong>SMS.ru для рассылок не настроен.</strong> Зайдите в <a href="settings.php">Настройки</a> → раздел «Массовые SMS-рассылки» → впишите api_id. Без этого отправка работать не будет.
  </div>
<?php endif; ?>

<form method="post" id="campaignForm">
  <input type="hidden" name="action" value="send_campaign">

  <label class="field full">Текст сообщения
    <textarea name="message" rows="4" placeholder="Например: Здравствуйте! Сервис АВИОР напоминает..." required></textarea>
  </label>

  <div style="display:flex;justify-content:space-between;align-items:center;margin:12px 0 8px;">
    <strong>Получатели (<span id="selectedCount">0</span> выбрано из <?= count($clients) ?>)</strong>
    <div style="display:flex;gap:8px;">
      <button type="button" class="btn btn-sm" onclick="toggleAllClients(true)">Выбрать всех</button>
      <button type="button" class="btn btn-sm" onclick="toggleAllClients(false)">Снять всё</button>
    </div>
  </div>

  <div class="table-card" style="max-height:360px;overflow-y:auto;margin-bottom:16px;">
    <table>
      <tbody>
        <?php foreach ($clients as $c): ?>
          <tr>
            <td style="width:36px;">
              <input type="checkbox" name="client_ids[]" value="<?= (int) $c['id'] ?>" class="client-checkbox" onchange="updateSelectedCount()">
            </td>
            <td data-label="Клиент"><?= e($c['full_name']) ?></td>
            <td data-label="Телефон"><?= e($c['phone']) ?></td>
            <td data-label="Источник"><?= e(client_source_label($c['source'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$clients): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--muted);">Клиентов пока нет.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <label class="field full" style="flex-direction:row;align-items:flex-start;gap:8px;">
    <input type="checkbox" name="consent_confirmed" value="1" required style="margin-top:3px;">
    <span style="font-size:13px;color:var(--muted);">
      Подтверждаю, что у выбранных получателей есть согласие на получение таких сообщений
      (не просто согласие на связь по конкретному заказу — для маркетинговых рассылок нужно отдельное согласие).
    </span>
  </label>

  <div class="field full" style="margin-top:12px;">
    <button type="submit" class="btn btn-primary" onclick="return confirm('Отправить рассылку выбранным получателям? Действие нельзя отменить.');">Отправить рассылку</button>
  </div>
</form>

<h3 style="color:var(--navy);font-size:16px;margin-top:32px;">История рассылок</h3>
<div class="table-card">
  <table>
    <thead><tr><th>Дата</th><th>Текст</th><th>Получателей</th><th>Отправлено</th><th>Не удалось</th></tr></thead>
    <tbody>
      <?php if (!$campaigns): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--muted);">Рассылок пока не было.</td></tr>
      <?php endif; ?>
      <?php foreach ($campaigns as $camp): ?>
        <tr>
          <td data-label="Дата"><?= date('d.m.Y H:i', strtotime($camp['created_at'])) ?></td>
          <td data-label="Текст"><?= e(mb_strlen($camp['message']) > 60 ? mb_substr($camp['message'], 0, 60) . '…' : $camp['message']) ?></td>
          <td data-label="Получателей"><?= (int) $camp['recipients_count'] ?></td>
          <td data-label="Отправлено" style="color:var(--good);"><?= (int) $camp['sent_count'] ?></td>
          <td data-label="Не удалось" style="<?= $camp['failed_count'] > 0 ? 'color:var(--danger);' : '' ?>"><?= (int) $camp['failed_count'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function updateSelectedCount() {
  var checked = document.querySelectorAll('.client-checkbox:checked').length;
  document.getElementById('selectedCount').textContent = checked;
}
function toggleAllClients(state) {
  document.querySelectorAll('.client-checkbox').forEach(function (cb) { cb.checked = state; });
  updateSelectedCount();
}
</script>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
