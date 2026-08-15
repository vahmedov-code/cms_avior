<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$statuses = ['принят', 'диагностика', 'согласование', 'в ремонте', 'готов', 'выдан', 'отказ'];

$id = (int) get('id');

function load_repair(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone
         FROM repairs r JOIN clients c ON c.id = r.client_id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    $repair = $stmt->fetch();
    return $repair ?: null;
}

$repair = load_repair($id);
if (!$repair) {
    flash_set('Заказ не найден.', 'error');
    redirect('repairs.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'delete_repair') {
        require_admin();
        $stmt = db()->prepare('DELETE FROM repairs WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('Заказ ' . $repair['order_no'] . ' удалён.', 'success');
        redirect('repairs.php');
    }

    if ($action === 'update_status') {
        $newStatus = post('status');
        if (in_array($newStatus, $statuses, true) && $newStatus !== $repair['status']) {
            $stmt = db()->prepare('UPDATE repairs SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $id]);
            $log = db()->prepare('INSERT INTO repair_status_log (repair_id, status, comment, changed_by) VALUES (?, ?, ?, ?)');
            $log->execute([$id, $newStatus, post('status_comment') ?: null, current_user()['id']]);
            flash_set('Статус изменён на «' . $newStatus . '».', 'success');
        }
        redirect('repair_view.php?id=' . $id);
    }

    if ($action === 'update_details') {
        $stmt = db()->prepare(
            'UPDATE repairs SET device_type = ?, device_model = ?, problem_description = ?, price_final = ? WHERE id = ?'
        );
        $priceFinal = post('price_final');
        $stmt->execute([
            post('device_type'),
            post('device_model') ?: null,
            post('problem_description') ?: null,
            $priceFinal === '' ? null : (float) str_replace(',', '.', $priceFinal),
            $id,
        ]);
        flash_set('Данные заказа обновлены.', 'success');
        redirect('repair_view.php?id=' . $id);
    }

    if ($action === 'add_part') {
        $name = post('name');
        $qty = (float) str_replace(',', '.', post('qty', '1'));
        $price = (float) str_replace(',', '.', post('price', '0'));
        $cost = (float) str_replace(',', '.', post('cost', '0'));
        $warranty = post('warranty');
        $category = post('category') === 'service' ? 'service' : 'part';
        if ($name !== '') {
            $stmt = db()->prepare(
                'INSERT INTO repair_parts (repair_id, category, name, qty, price, cost, warranty) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$id, $category, $name, $qty ?: 1, $price, $cost, $warranty ?: null]);

            // подсказки на будущее — сохраняем в общий каталог комплектующих
            if ($category === 'part' && $price > 0) {
                $cat = db()->prepare(
                    'INSERT INTO parts_catalog (name, price) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE price = VALUES(price)'
                );
                $cat->execute([$name, $price]);
            }
        }
        redirect('repair_view.php?id=' . $id);
    }

    if ($action === 'delete_part') {
        $partId = (int) post('part_id');
        $stmt = db()->prepare('DELETE FROM repair_parts WHERE id = ? AND repair_id = ?');
        $stmt->execute([$partId, $id]);
        redirect('repair_view.php?id=' . $id);
    }

    if ($action === 'send_sms') {
        $message = post('message');
        if ($message !== '') {
            $ok = send_sms($repair['client_phone'], $message, $id);
            flash_set($ok ? 'SMS отправлено.' : 'SMS-провайдер не настроен — сообщение сохранено в журнал, но не отправлено. Настройте провайдера в config/config.php.', $ok ? 'success' : 'error');
        }
        redirect('repair_view.php?id=' . $id);
    }
}

$partsStmt = db()->prepare('SELECT * FROM repair_parts WHERE repair_id = ? ORDER BY id');
$partsStmt->execute([$id]);
$parts = $partsStmt->fetchAll();

$partsTotal = 0.0;
$servicesTotal = 0.0;
$marginTotal = 0.0;
foreach ($parts as $p) {
    $sum = (float) $p['qty'] * (float) $p['price'];
    $marginTotal += (float) $p['qty'] * ((float) $p['price'] - (float) ($p['cost'] ?? 0));
    if ($p['category'] === 'service') {
        $servicesTotal += $sum;
    } else {
        $partsTotal += $sum;
    }
}

$logStmt = db()->prepare(
    'SELECT l.*, u.full_name AS user_name FROM repair_status_log l
     LEFT JOIN users u ON u.id = l.changed_by
     WHERE l.repair_id = ? ORDER BY l.changed_at DESC'
);
$logStmt->execute([$id]);
$statusLog = $logStmt->fetchAll();

$pageTitle = 'Заказ ' . $repair['order_no'];
$activeNav = 'repairs';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Заказ <?= e($repair['order_no']) ?> <span style="font-size:13px;font-weight:400;color:var(--muted);">· <?= e(order_type_label($repair['order_type'] ?? 'repair')) ?></span></h2>
  <div style="display:flex;gap:8px;align-items:center;">
    <select class="btn no-print" style="cursor:pointer;" onchange="if(this.value){window.location.href=this.value;}this.selectedIndex=0;">
      <option value="">📄 Печатные документы...</option>
      <option value="repair_receipt.php?id=<?= (int) $id ?>">Квитанция о приёмке</option>
      <option value="repair_act.php?id=<?= (int) $id ?>">Акт выполненных работ</option>
    </select>
    <?php if (is_admin()): ?>
      <form method="post" class="no-print" style="display:inline;" onsubmit="return confirm('Удалить заказ «<?= e($repair['order_no']) ?>» безвозвратно? Все его позиции, история статусов и печатные документы удалятся вместе с ним. Отменить нельзя.');">
        <input type="hidden" name="action" value="delete_repair">
        <button type="submit" class="btn btn-sm btn-warn" title="Удалить заказ (только администратор)">🗑 Удалить заказ</button>
      </form>
    <?php endif; ?>
    <a href="repairs.php" class="btn btn-sm no-print">← К списку заказов</a>
  </div>
</div>

<div class="print-only">
  <div style="text-align:center;margin-bottom:18px;">
    <div style="font-size:22px;letter-spacing:3px;color:var(--gold);font-weight:800;">АВИОР</div>
    <div style="font-size:12px;color:var(--muted);">Можайское шоссе, 4к1, Москва · +7 (901) 222-81-11</div>
  </div>
  <h2 style="text-align:center;margin-bottom:4px;">Заказ <?= e($repair['order_no']) ?> — <?= e(order_type_label($repair['order_type'] ?? 'repair')) ?></h2>
  <p style="text-align:center;color:var(--muted);margin-top:0;">от <?= date('d.m.Y', strtotime($repair['created_at'])) ?> · статус: <?= e($repair['status']) ?></p>
  <p><strong>Клиент:</strong> <?= e($repair['client_name']) ?> · <?= e($repair['client_phone']) ?></p>
  <p><strong>Устройство:</strong> <?= e($repair['device_type']) ?> <?= e($repair['device_model'] ?? '') ?></p>
  <?php if ($repair['problem_description']): ?><p><strong>Описание:</strong> <?= nl2br(e($repair['problem_description'])) ?></p><?php endif; ?>
  <table style="width:100%;border-collapse:collapse;margin-top:12px;">
    <thead><tr><th style="text-align:left;border-bottom:2px solid #ccc;padding:6px;">Название</th><th style="border-bottom:2px solid #ccc;padding:6px;">Кол-во</th><th style="border-bottom:2px solid #ccc;padding:6px;">Цена</th><th style="border-bottom:2px solid #ccc;padding:6px;">Сумма</th></tr></thead>
    <tbody>
      <?php foreach ($parts as $p): ?>
        <tr>
          <td style="padding:6px;border-bottom:1px solid #e5e5e5;"><?= e($p['name']) ?></td>
          <td style="padding:6px;border-bottom:1px solid #e5e5e5;text-align:center;"><?= rtrim(rtrim((string) (float) $p['qty'], '0'), '.') ?></td>
          <td style="padding:6px;border-bottom:1px solid #e5e5e5;text-align:right;"><?= money((float) $p['price']) ?></td>
          <td style="padding:6px;border-bottom:1px solid #e5e5e5;text-align:right;"><?= money((float) $p['qty'] * (float) $p['price']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div style="max-width:300px;margin-left:auto;margin-top:12px;">
    <div style="display:flex;justify-content:space-between;"><span>Комплектующие:</span><strong><?= money($partsTotal) ?></strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Работа / услуги:</span><strong><?= money($servicesTotal) ?></strong></div>
    <div style="display:flex;justify-content:space-between;font-size:17px;border-top:2px solid #152a4e;margin-top:6px;padding-top:6px;"><span>Итого:</span><strong><?= money($partsTotal + $servicesTotal) ?></strong></div>
  </div>
</div>

<div class="no-print repair-detail-grid">
  <div>
    <div style="margin-bottom:20px;">
      <span class="status-badge" data-status="<?= e($repair['status']) ?>" style="font-size:14px;"><?= e($repair['status']) ?></span>
      <span style="color:var(--muted);font-size:13px;margin-left:10px;">Обновлён <?= date('d.m.Y H:i', strtotime($repair['updated_at'])) ?></span>
    </div>

    <form method="post" class="form-grid" style="margin-bottom:28px;">
      <input type="hidden" name="action" value="update_details">
      <label class="field">Тип устройства
        <?= render_device_type_picker('deviceTypeFieldEdit', $repair['device_type']) ?>
        <input type="text" name="device_type" id="deviceTypeFieldEdit" list="deviceTypeList" value="<?= e($repair['device_type']) ?>" required<?= array_key_exists($repair['device_type'], device_type_options()) ? ' readonly' : '' ?>>
      </label>
      <label class="field">Модель
        <input type="text" name="device_model" list="deviceModelList" value="<?= e($repair['device_model'] ?? '') ?>">
      </label>
      <label class="field full">Описание проблемы
        <?= render_suggestion_chips('problemFieldEdit', suggest_problem_descriptions()) ?>
        <textarea name="problem_description" id="problemFieldEdit" rows="3"><?= e($repair['problem_description'] ?? '') ?></textarea>
      </label>
      <label class="field">Итоговая цена, ₽ (необязательно)
        <input type="number" name="price_final" min="0" step="1" value="<?= e($repair['price_final'] !== null ? (string) (float) $repair['price_final'] : '') ?>">
      </label>
      <div class="field full">
        <button type="submit" class="btn">Сохранить изменения</button>
      </div>
    </form>
    <?= render_datalist('deviceTypeList', suggest_device_types()) ?>
    <?= render_datalist('deviceModelList', suggest_device_models()) ?>

    <h3 style="color:var(--navy);font-size:16px;">Комплектующие и услуги</h3>
    <div class="table-card" style="margin-bottom:14px;">
      <table>
        <thead>
          <tr><th>Название</th><th style="width:70px;">Кол-во</th><th style="width:100px;">Цена, ₽</th><th style="width:100px;">Себест., ₽</th><th style="width:90px;">Гарантия</th><th style="width:110px;">Сумма, ₽</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (!$parts): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);">Пока ничего не добавлено.</td></tr>
          <?php endif; ?>
          <?php foreach ($parts as $p): ?>
            <tr data-category="<?= e($p['category']) ?>">
              <td data-label="Название"><?= e($p['name']) ?></td>
              <td data-label="Кол-во"><?= rtrim(rtrim((string) (float) $p['qty'], '0'), '.') ?></td>
              <td data-label="Цена"><?= money((float) $p['price']) ?></td>
              <td data-label="Себестоимость" style="color:var(--muted);"><?= money((float) ($p['cost'] ?? 0)) ?></td>
              <td data-label="Гарантия"><?= $p['warranty'] ? e($p['warranty']) : '<span style="color:var(--muted);">нет</span>' ?></td>
              <td data-label="Сумма"><?= money((float) $p['qty'] * (float) $p['price']) ?></td>
              <td data-label="">
                <form method="post" onsubmit="return confirm('Удалить позицию?');">
                  <input type="hidden" name="action" value="delete_part">
                  <input type="hidden" name="part_id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-warn">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form method="post" class="form-grid" style="max-width:560px;margin-bottom:28px;">
      <input type="hidden" name="action" value="add_part">
      <label class="field full">Название
        <input type="text" name="name" list="partNamesList" required>
      </label>
      <label class="field">Категория
        <select name="category">
          <option value="part">Комплектующее</option>
          <option value="service">Услуга (работа)</option>
        </select>
      </label>
      <label class="field">Кол-во
        <input type="number" name="qty" value="1" min="0.01" step="0.01">
      </label>
      <label class="field">Цена, ₽
        <input type="number" name="price" value="0" min="0" step="1">
      </label>
      <label class="field">Себестоимость, ₽ (необязательно)
        <input type="number" name="cost" value="0" min="0" step="1">
      </label>
      <label class="field">Гарантия (необязательно)
        <input type="text" name="warranty" placeholder="нет / 30 дней / 6 мес.">
      </label>
      <div class="field full">
        <button type="submit" class="btn">+ Добавить</button>
      </div>
    </form>
    <?= render_datalist('partNamesList', suggest_part_names()) ?>

    <div style="max-width:320px;margin-left:auto;font-size:14px;">
      <div style="display:flex;justify-content:space-between;"><span>Комплектующие:</span><strong><?= money($partsTotal) ?></strong></div>
      <div style="display:flex;justify-content:space-between;"><span>Работа / услуги:</span><strong><?= money($servicesTotal) ?></strong></div>
      <div style="display:flex;justify-content:space-between;font-size:18px;color:var(--navy);border-top:2px solid var(--navy);margin-top:6px;padding-top:6px;">
        <span>Итого:</span><strong><?= money($partsTotal + $servicesTotal) ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;color:var(--good);margin-top:4px;">
        <span>Прибыль по заказу:</span><strong><?= money($marginTotal) ?></strong>
      </div>
    </div>
  </div>

  <div>
    <div class="table-card" style="padding:16px;margin-bottom:20px;">
      <h3 style="margin:0 0 10px;font-size:15px;color:var(--navy);">Клиент</h3>
      <p style="margin:0 0 4px;"><a href="client_view.php?id=<?= (int) $repair['client_id'] ?>"><?= e($repair['client_name']) ?></a></p>
      <p style="margin:0;color:var(--muted);"><?= e($repair['client_phone']) ?></p>
    </div>

    <div class="table-card" style="padding:16px;margin-bottom:20px;">
      <h3 style="margin:0 0 10px;font-size:15px;color:var(--navy);">Изменить статус</h3>
      <form method="post">
        <input type="hidden" name="action" value="update_status">
        <label class="field">Новый статус
          <select name="status">
            <?php foreach ($statuses as $s): ?>
              <option value="<?= e($s) ?>" <?= $s === $repair['status'] ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Комментарий (необязательно)
          <input type="text" name="status_comment">
        </label>
        <button type="submit" class="btn btn-primary">Обновить статус</button>
      </form>
    </div>

    <div class="table-card" style="padding:16px;margin-bottom:20px;">
      <h3 style="margin:0 0 10px;font-size:15px;color:var(--navy);">SMS клиенту</h3>
      <form method="post">
        <input type="hidden" name="action" value="send_sms">
        <label class="field">Текст сообщения
          <textarea name="message" rows="3" placeholder="Например: Ваш ноутбук готов, можно забрать."><?php
            $totalDue = max(0, $partsTotal + $servicesTotal - (float) $repair['prepayment']);
            echo e(default_sms_message($repair, $totalDue));
          ?></textarea>
        </label>
        <button type="submit" class="btn">Отправить SMS</button>
      </form>
      <p style="font-size:12px;color:var(--muted);margin-top:8px;">SMS-провайдер пока не подключён — сообщение уйдёт в журнал. Настраивается в <code>config/config.php</code>.</p>
    </div>

    <div class="table-card" style="padding:16px;">
      <h3 style="margin:0 0 10px;font-size:15px;color:var(--navy);">История статусов</h3>
      <?php if (!$statusLog): ?>
        <p style="color:var(--muted);font-size:13px;">Пока пусто.</p>
      <?php endif; ?>
      <ul style="list-style:none;padding:0;margin:0;font-size:13px;">
        <?php foreach ($statusLog as $l): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--border);">
            <strong><?= e($l['status']) ?></strong><br>
            <span style="color:var(--muted);"><?= date('d.m.Y H:i', strtotime($l['changed_at'])) ?><?= $l['user_name'] ? ' · ' . e($l['user_name']) : '' ?></span>
            <?php if ($l['comment']): ?><br><span><?= e($l['comment']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
