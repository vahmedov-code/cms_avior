<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$statuses = ['принят', 'диагностика', 'согласование', 'в ремонте', 'готов', 'выдан', 'отказ'];

$id = (int) get('id');

function load_repair(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone, c.client_type AS client_type
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

    if ($action === 'save_manual_payment') {
        $amount = (float) str_replace(',', '.', post('amount', '0'));
        if ($amount > 0) {
            $stmt = db()->prepare(
                'INSERT INTO payments (repair_id, method, amount, receipt_printed, created_by) VALUES (?, ?, ?, 0, ?)'
            );
            $stmt->execute([$id, 'manual', $amount, current_user()['id'] ?? null]);
            flash_set('Оплата сохранена (' . money($amount) . '), без печати чека.', 'success');
        } else {
            flash_set('Укажите сумму больше нуля.', 'error');
        }
        redirect('repair_view.php?id=' . $id);
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

            // склад: списываем остаток, если позиция заведена на warehouse.php
            if ($category === 'part') {
                adjust_stock_for_part_usage($name, $qty ?: 1, $id, 'Заказ ' . $repair['order_no']);
            }
        }
        redirect('repair_view.php?id=' . $id);
    }

    if ($action === 'delete_part') {
        $partId = (int) post('part_id');
        $partStmt = db()->prepare('SELECT * FROM repair_parts WHERE id = ? AND repair_id = ?');
        $partStmt->execute([$partId, $id]);
        $deletedPart = $partStmt->fetch();

        $stmt = db()->prepare('DELETE FROM repair_parts WHERE id = ? AND repair_id = ?');
        $stmt->execute([$partId, $id]);

        // склад: возвращаем остаток обратно, если это была комплектующая
        if ($deletedPart && $deletedPart['category'] === 'part') {
            adjust_stock_for_part_usage($deletedPart['name'], -(float) $deletedPart['qty'], $id, 'Удалено из заказа ' . $repair['order_no']);
        }
        redirect('repair_view.php?id=' . $id);
    }

    if ($action === 'send_sms') {
        $message = post('message');
        if ($message !== '') {
            $smsError = null;
            $ok = send_sms($repair['client_phone'], $message, $id, $smsError);
            if ($ok) {
                flash_set('SMS отправлено.', 'success');
            } elseif ($smsError) {
                flash_set('SMS не отправлено: ' . $smsError, 'error');
            } else {
                flash_set('SMS-провайдер не настроен — сообщение сохранено в журнал, но не отправлено. Настройте провайдера в Настройках (settings.php) или config/config.php.', 'error');
            }
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
      <?php if (($repair['client_type'] ?? 'individual') === 'legal_entity'): ?>
        <option value="invoice.php?id=<?= (int) $id ?>">Счёт на оплату</option>
      <?php endif; ?>
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

    <div class="table-card no-print" style="padding:16px;margin-top:20px;">
      <h3 style="margin:0 0 10px;font-size:15px;color:var(--navy);">Оплата</h3>
      <p style="font-size:12px;color:var(--muted);margin-top:-4px;">
        Печать чека — напрямую из этого браузера через KkmServer.
        Работает только с того компьютера, где физически стоит касса.
      </p>
      <div id="kkmStatus" style="display:none;font-size:13px;margin-bottom:10px;padding:8px 10px;border-radius:6px;"></div>

      <div id="splitOverlay" style="display:none;position:fixed;inset:0;z-index:100;background:rgba(5,7,8,.7);align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:10px;max-width:340px;width:100%;padding:24px;text-align:center;">
          <h3 style="margin:0 0 4px;color:var(--navy);">Оплата в Яндекс Сплит</h3>
          <p style="font-size:13px;color:var(--muted);margin:0 0 16px;">Покажите QR-код покупателю для сканирования</p>
          <div id="splitQrHolder" style="display:flex;justify-content:center;margin-bottom:16px;"></div>
          <p id="splitStatusText" style="font-size:13px;color:var(--muted);margin:0 0 16px;">Ожидаем оплату…</p>
          <button type="button" class="btn" onclick="cancelYandexSplit()">Отменить</button>
        </div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
        <div class="pay-dropdown">
          <button type="button" class="btn btn-primary" onclick="togglePayMenu('offlineMenu')">🏦 Офлайн оплата ▾</button>
          <div class="pay-dropdown-menu" id="offlineMenu">
            <button type="button" class="pay-dropdown-item" id="payCashBtn" onclick="closePayMenus(); payAndPrint('cash', <?= (float) ($partsTotal + $servicesTotal) ?>, <?= (int) $id ?>)">💵 Наличные (с чеком)</button>
            <div class="pay-dropdown-item" style="cursor:default;">
              <form method="post" style="display:flex;gap:6px;align-items:center;">
                <input type="hidden" name="action" value="save_manual_payment">
                <span>📥 Без чека:</span>
                <input type="number" name="amount" min="0" step="1" value="<?= (float) ($partsTotal + $servicesTotal) ?>" style="width:90px;padding:4px 6px;">
                <button type="submit" class="btn btn-sm">Сохранить</button>
              </form>
            </div>
          </div>
        </div>

        <div class="pay-dropdown">
          <button type="button" class="btn btn-primary" onclick="togglePayMenu('cardMenu')">💳 Безналичный ▾</button>
          <div class="pay-dropdown-menu" id="cardMenu">
            <button type="button" class="pay-dropdown-item" id="payCardBtn" onclick="closePayMenus(); payAndPrint('card', <?= (float) ($partsTotal + $servicesTotal) ?>, <?= (int) $id ?>)">🏧 Эквайринг</button>
            <button type="button" class="pay-dropdown-item" id="paySplitBtn" onclick="closePayMenus(); startYandexSplit(<?= (int) $id ?>)">🟡 Яндекс Сплит</button>
          </div>
        </div>
      </div>
      <p style="font-size:11px;color:var(--muted);margin-top:-6px;margin-bottom:14px;">
        «Эквайринг» и «Яндекс Сплит» печатают чек с пометкой «электронный
        платёж» уже ПОСЛЕ того, как сама оплата прошла на терминале/в
        приложении — нажимайте кнопку следующим шагом, не вместо оплаты.
        «Без чека» — для случаев, когда деньги уже получены, а чек будет
        пробит отдельно.
      </p>
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
          <div class="chip-suggestions">
            <button type="button" class="btn btn-sm chip" onclick="<?= e('document.getElementById(\'smsMessageField\').value = ' . json_encode(review_request_sms_message($repair)) . ';') ?>">📝 Попросить отзыв (Яндекс Карты)</button>
          </div>
          <textarea name="message" id="smsMessageField" rows="3" placeholder="Например: Ваш ноутбук готов, можно забрать."><?php
            $totalDue = max(0, $partsTotal + $servicesTotal - (float) $repair['prepayment']);
            echo e(default_sms_message($repair, $totalDue));
          ?></textarea>
        </label>
        <button type="submit" class="btn">Отправить SMS</button>
      </form>
      <?php $activeSmsProvider = sms_active_provider(); ?>
      <?php if ($activeSmsProvider === null): ?>
        <p style="font-size:12px;color:var(--muted);margin-top:8px;">SMS-провайдер пока не подключён — сообщение уйдёт в журнал. Настроить можно в <a href="settings.php">Настройках</a>.</p>
      <?php else: ?>
        <p style="font-size:12px;color:var(--good);margin-top:8px;">SMS отправится через: <?= e(sms_provider_label($activeSmsProvider)) ?>.</p>
      <?php endif; ?>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
/**
 * Оплата и печать чека через KkmServer — запрос уходит прямо из этого
 * браузера на localhost (сервер CRM физически не может достучаться до
 * кассы в магазине). Работает только с того компьютера, где стоит АТОЛ
 * и запущен KkmServer с этими логином/паролем.
 */
var KKM_URL = <?= json_encode(rtrim(get_setting('kkm_server_url', 'http://localhost:5893'), '/')) ?>;
var KKM_LOGIN = <?= json_encode(get_setting('kkm_login', 'User')) ?>;
var KKM_PASSWORD = <?= json_encode(get_setting('kkm_password', '')) ?>;
var KKM_NUM_DEVICE = <?= json_encode((int) (get_setting('kkm_num_device', '1'))) ?>;

// Когда подключим Яндекс Сплит: перед вызовом KkmServer для этого способа
// оплаты нужно будет добавить в CheckStrings ещё одну позицию —
// «Расширенная гарантия» на EXTENDED_WARRANTY_PRICE (уже посчитана на
// сервере через extended_warranty_price() в functions.php, 15% от суммы
// позиций заказа). Готово, просто пока нигде не используется.
var EXTENDED_WARRANTY_PRICE = <?= json_encode(extended_warranty_price($parts)) ?>;

var ORDER_CHECK_STRINGS = <?= json_encode(array_map(function ($p) {
    return [
        'Register' => [
            'Name'     => $p['name'],
            'Quantity' => (float) $p['qty'],
            'Price'    => (float) $p['price'],
            'Amount'   => (float) $p['qty'] * (float) $p['price'],
            'Tax'      => 20,
        ],
    ];
}, $parts), JSON_UNESCAPED_UNICODE) ?>;

function togglePayMenu(id) {
  var menu = document.getElementById(id);
  var wasOpen = menu.classList.contains('open');
  closePayMenus();
  if (!wasOpen) { menu.classList.add('open'); }
}
function closePayMenus() {
  document.querySelectorAll('.pay-dropdown-menu.open').forEach(function (m) { m.classList.remove('open'); });
}
document.addEventListener('click', function (e) {
  if (!e.target.closest('.pay-dropdown')) { closePayMenus(); }
});

function showKkmStatus(text, isError) {
  var el = document.getElementById('kkmStatus');
  el.style.display = 'block';
  el.style.background = isError ? '#fbe9e7' : '#e6f4ea';
  el.style.color = isError ? 'var(--danger)' : 'var(--good)';
  el.textContent = text;
}

var SPLIT_POLL_TIMER = null;
var SPLIT_ORDER_ID = null;

function startYandexSplit(repairId) {
  var overlay = document.getElementById('splitOverlay');
  var holder = document.getElementById('splitQrHolder');
  var statusText = document.getElementById('splitStatusText');
  holder.innerHTML = '';
  statusText.textContent = 'Создаём заказ…';
  overlay.style.display = 'flex';

  fetch('api/yandex_split_create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ repair_id: repairId })
  })
    .then(function (r) { return r.json(); })
    .then(function (result) {
      if (!result.ok) {
        statusText.textContent = 'Ошибка: ' + (result.error || 'не удалось создать заказ');
        return;
      }
      SPLIT_ORDER_ID = result.orderId;
      new QRCode(holder, { text: result.paymentUrl, width: 200, height: 200 });
      statusText.textContent = 'Ожидаем оплату…';
      SPLIT_POLL_TIMER = setInterval(function () { pollSplitStatus(repairId); }, 2000);
    })
    .catch(function () {
      statusText.textContent = 'Не удалось связаться с сервером.';
    });
}

function pollSplitStatus(repairId) {
  if (!SPLIT_ORDER_ID) { return; }
  fetch('api/yandex_split_status.php?order_id=' + encodeURIComponent(SPLIT_ORDER_ID))
    .then(function (r) { return r.json(); })
    .then(function (result) {
      if (!result.ok) { return; }
      if (result.status === 'CAPTURED') {
        clearInterval(SPLIT_POLL_TIMER);
        document.getElementById('splitStatusText').textContent = 'Оплата прошла! Печатаем чек…';
        var total = <?= (float) ($partsTotal + $servicesTotal) ?> + EXTENDED_WARRANTY_PRICE;
        payAndPrint('card', total, repairId, true);
        setTimeout(function () { document.getElementById('splitOverlay').style.display = 'none'; }, 1500);
      } else if (result.status === 'FAILED') {
        clearInterval(SPLIT_POLL_TIMER);
        document.getElementById('splitStatusText').textContent = 'Оплата не прошла. Закройте окно и попробуйте снова.';
      }
      // PENDING — просто ждём следующего опроса
    });
}

function cancelYandexSplit() {
  clearInterval(SPLIT_POLL_TIMER);
  SPLIT_ORDER_ID = null;
  document.getElementById('splitOverlay').style.display = 'none';
}

function payAndPrint(method, amount, repairId, includeWarranty) {
  if (!ORDER_CHECK_STRINGS.length) {
    showKkmStatus('В заказе нет ни одной позиции — нечего вносить в чек.', true);
    return;
  }
  if (!KKM_URL) {
    showKkmStatus('Касса не настроена — заполните адрес KkmServer в Настройках.', true);
    return;
  }

  var btn = document.getElementById(method === 'cash' ? 'payCashBtn' : 'payCardBtn');
  if (btn) { btn.disabled = true; }
  showKkmStatus('Печатаем чек…', false);

  // Копия позиций чека — не трогаем ORDER_CHECK_STRINGS напрямую, чтобы
  // строка гарантии не осталась там для следующей оплаты без Сплита.
  var checkStrings = ORDER_CHECK_STRINGS.slice();
  if (includeWarranty && EXTENDED_WARRANTY_PRICE > 0) {
    checkStrings.push({
      Register: {
        Name: 'Расширенная гарантия',
        Quantity: 1,
        Price: EXTENDED_WARRANTY_PRICE,
        Amount: EXTENDED_WARRANTY_PRICE,
        Tax: 20
      }
    });
  }

  var payload = {
    Command: 'RegisterCheck',
    NumDevice: KKM_NUM_DEVICE,
    IsFiscalCheck: true,
    CheckStrings: checkStrings
  };
  if (method === 'cash') {
    payload.Cash = amount;
  } else {
    payload.ElectronicPayment = amount;
  }

  fetch(KKM_URL + '/Execute', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Basic ' + btoa(KKM_LOGIN + ':' + KKM_PASSWORD)
    },
    body: JSON.stringify(payload)
  })
    .then(function (r) { return r.json().catch(function () { return { Error: 'Пустой/нечитаемый ответ от KkmServer' }; }); })
    .then(function (kkmResponse) {
      var success = kkmResponse && kkmResponse.Error === 0;
      showKkmStatus(
        success ? 'Чек напечатан.' : ('Касса ответила ошибкой: ' + (kkmResponse.Error !== undefined ? kkmResponse.Error : '?') + (kkmResponse.Description ? ' — ' + kkmResponse.Description : '')),
        !success
      );
      return fetch('api/log_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          repair_id: repairId,
          method: method,
          amount: amount,
          receipt_printed: success,
          kkm_response: kkmResponse
        })
      });
    })
    .catch(function (err) {
      showKkmStatus('Не удалось связаться с кассой (' + err.message + '). Проверьте, что KkmServer запущен на этом компьютере и адрес в Настройках верный (попробуйте порт 5894, если 5893 не работает — вероятно, браузер блокирует http-запрос с https-страницы).', true);
    })
    .finally(function () {
      if (btn) { btn.disabled = false; }
    });
}
</script>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
