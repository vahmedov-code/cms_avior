<?php
/**
 * B2B-партнёрства с магазинами электроники — аутсорс сложного ремонта
 * (BGA-пайка, восстановление данных). Не путать с клиентами сервиса —
 * это другие бизнесы, цель контакта другая: не отремонтировать им
 * устройство, а договориться, чтобы направляли к нам своих клиентов
 * с ремонтом, за который сами не берутся.
 *
 * Только admin/owner — это бизнес-развитие, не повседневная работа
 * инженера-приёмщика.
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_admin();

$pageTitle = 'B2B-партнёры';
$activeNav = 'b2b_partners';

$statusLabels = [
    'not_contacted' => 'Ещё не связались',
    'contacted'     => 'Написали/позвонили',
    'interested'    => 'Заинтересованы',
    'partner'       => 'Партнёр',
    'declined'      => 'Отказались',
];
$channelLabels = [
    'telegram_whatsapp' => 'Telegram/WhatsApp',
    'call'               => 'Звонок',
    'in_person'          => 'Личный визит',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'add_partner') {
    $name = trim(post('name'));
    if ($name === '') {
        flash_set('Укажите название магазина.', 'error');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO b2b_partners (name, address, contact_person, phone, created_by) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            trim(post('address')) ?: null,
            trim(post('contact_person')) ?: null,
            trim(post('phone')) ?: null,
            current_user()['id'] ?? null,
        ]);
        flash_set('Партнёр добавлен.', 'success');
    }
    redirect('b2b_partners.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_status') {
    $id = (int) post('id');
    $status = post('status');
    $channel = post('channel') ?: null;
    if ($id > 0 && array_key_exists($status, $statusLabels)) {
        $stmt = db()->prepare(
            'UPDATE b2b_partners SET status = ?, channel = ?, last_contact_at = CURDATE() WHERE id = ?'
        );
        $stmt->execute([$status, $channel, $id]);
        flash_set('Статус обновлён.', 'success');
    }
    redirect('b2b_partners.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_partner') {
    $id = (int) post('id');
    db()->prepare('DELETE FROM b2b_partners WHERE id = ?')->execute([$id]);
    flash_set('Удалено.', 'success');
    redirect('b2b_partners.php');
}

$partners = db()->query('SELECT * FROM b2b_partners ORDER BY FIELD(status, "interested", "not_contacted", "contacted", "declined", "partner"), name')->fetchAll();

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>🤝 B2B-партнёры</h2>
</div>
<p style="color:var(--muted);font-size:13px;max-width:640px;margin-top:-8px;">
  Магазины электроники, которые не берутся за сложный ремонт (BGA-пайка,
  восстановление данных) — договариваемся, чтобы направляли таких
  клиентов к нам. Три канала на выбор: написать, позвонить, заехать
  лично — заготовки ниже под каждый.
</p>

<details style="margin:16px 0;background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px 18px;">
  <summary style="cursor:pointer;font-weight:600;color:var(--navy);">📋 Заготовки для контакта</summary>

  <h4 style="margin:16px 0 6px;color:var(--navy);">Telegram / WhatsApp — первое сообщение</h4>
  <p style="background:#f8f9fc;padding:10px 12px;border-radius:6px;font-size:13.5px;line-height:1.6;">
    Здравствуйте! Меня зовут Вейс, руковожу сервисом АВИОР (ремонт электроники,
    Можайское шоссе). Часто вижу, что магазины отказывают клиентам в сложном
    ремонте — перепайка BGA, восстановление данных с повреждённых накопителей,
    реболлинг. Мы как раз специализируемся именно на такой работе. Предлагаю:
    если к вам обращаются с тем, за что вы не беретесь — направляйте к нам,
    со своей стороны предложим процент с заказа или скидку вашим клиентам.
    Готовы обсудить условия?
  </p>

  <h4 style="margin:16px 0 6px;color:var(--navy);">Если не ответили через пару дней</h4>
  <p style="background:#f8f9fc;padding:10px 12px;border-radius:6px;font-size:13.5px;line-height:1.6;">
    Добрый день! Писал на днях по поводу сложного ремонта — если актуально,
    могу заехать или созвониться в удобное время, обсудим детали.
  </p>

  <h4 style="margin:16px 0 6px;color:var(--navy);">Звонок — тезисы</h4>
  <ul style="font-size:13.5px;line-height:1.7;padding-left:20px;">
    <li>Представиться, назвать сервис и специализацию (BGA-пайка, восстановление данных)</li>
    <li>Спросить: часто ли отказывают клиентам в сложном ремонте?</li>
    <li>Предложить направлять таких клиентов вам, обсудить процент/фиксированное вознаграждение</li>
    <li>Договориться о встрече или уточнить контакт для дальнейшего разговора</li>
  </ul>

  <h4 style="margin:16px 0 6px;color:var(--navy);">Личный визит — тезисы</h4>
  <ul style="font-size:13.5px;line-height:1.7;padding-left:20px;">
    <li>Взять визитки/буклет с контактами</li>
    <li>Если есть — показать примеры сложных работ (фото «до/после»)</li>
    <li>Обсудить схему передачи клиентов на месте, оставить контакт</li>
  </ul>
</details>

<h3 style="color:var(--navy);font-size:16px;margin-top:24px;">Добавить магазин</h3>
<form method="post" class="form-grid" style="max-width:560px;">
  <input type="hidden" name="action" value="add_partner">
  <label class="field full">Название
    <input type="text" name="name" required>
  </label>
  <label class="field">Адрес
    <input type="text" name="address">
  </label>
  <label class="field">Контактное лицо
    <input type="text" name="contact_person">
  </label>
  <label class="field full">Телефон
    <input type="text" name="phone">
  </label>
  <div class="field full">
    <button type="submit" class="btn btn-primary">Добавить</button>
  </div>
</form>

<h3 style="color:var(--navy);font-size:16px;margin-top:28px;">Список (<?= count($partners) ?>)</h3>
<div class="table-card">
  <table>
    <thead><tr><th>Магазин</th><th>Контакт</th><th>Статус</th><th>Канал</th><th>Последний контакт</th><th></th></tr></thead>
    <tbody>
      <?php if (!$partners): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);">Список пока пуст.</td></tr>
      <?php endif; ?>
      <?php foreach ($partners as $p): ?>
        <tr>
          <td data-label="Магазин">
            <strong><?= e($p['name']) ?></strong>
            <?php if ($p['address']): ?><br><span style="color:var(--muted);font-size:12px;"><?= e($p['address']) ?></span><?php endif; ?>
          </td>
          <td data-label="Контакт">
            <?= e($p['contact_person'] ?: '—') ?>
            <?php if ($p['phone']): ?><br><span style="color:var(--muted);font-size:12px;"><?= e($p['phone']) ?></span><?php endif; ?>
          </td>
          <td data-label="Статус">
            <form method="post" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <select name="status" onchange="this.form.submit()" style="font-size:12px;padding:3px 4px;">
                <?php foreach ($statusLabels as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= $p['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="channel" onchange="this.form.submit()" style="font-size:12px;padding:3px 4px;">
                <option value="">канал?</option>
                <?php foreach ($channelLabels as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= $p['channel'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td data-label="Канал"><?= e($p['channel'] ? ($channelLabels[$p['channel']] ?? $p['channel']) : '—') ?></td>
          <td data-label="Последний контакт"><?= $p['last_contact_at'] ? e(date('d.m.Y', strtotime($p['last_contact_at']))) : '—' ?></td>
          <td data-label="">
            <form method="post" onsubmit="return confirm('Удалить «<?= e($p['name']) ?>» из списка?');">
              <input type="hidden" name="action" value="delete_partner">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-warn">✕</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
