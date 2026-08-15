<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$clients = db()->query('SELECT id, full_name, phone FROM clients ORDER BY full_name')->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientMode = post('client_mode', 'existing');
    $deviceType = post('device_type');
    $deviceModel = post('device_model');
    $problem = post('problem_description');
    $priceEstimate = (float) str_replace(',', '.', post('price_estimate', '0'));

    if ($deviceType === '') {
        $error = 'Укажите тип устройства.';
    }

    $clientId = null;
    if (!$error && $clientMode === 'new') {
        $newName = post('new_client_name');
        $newPhone = post('new_client_phone');
        if ($newName === '' || $newPhone === '') {
            $error = 'Укажите имя и телефон нового клиента.';
        } else {
            $stmt = db()->prepare('INSERT INTO clients (full_name, phone) VALUES (?, ?)');
            $stmt->execute([$newName, $newPhone]);
            $clientId = (int) db()->lastInsertId();
        }
    } elseif (!$error) {
        $clientId = (int) post('client_id');
        if ($clientId <= 0) {
            $error = 'Выберите клиента из списка или создайте нового.';
        }
    }

    if (!$error) {
        $orderNo = next_order_no();
        $stmt = db()->prepare(
            'INSERT INTO repairs (order_no, client_id, device_type, device_model, problem_description, status, price_estimate)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderNo, $clientId, $deviceType, $deviceModel ?: null, $problem ?: null, 'принят', $priceEstimate]);
        $repairId = (int) db()->lastInsertId();

        $log = db()->prepare('INSERT INTO repair_status_log (repair_id, status, comment, changed_by) VALUES (?, ?, ?, ?)');
        $log->execute([$repairId, 'принят', 'Заказ создан', current_user()['id']]);

        flash_set('Заказ ' . $orderNo . ' создан.', 'success');
        redirect('repair_view.php?id=' . $repairId);
    }
}

$pageTitle = 'Новый заказ';
$activeNav = 'repairs';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Новый заказ на ремонт</h2>
  <a href="repairs.php" class="btn btn-sm">← К списку заказов</a>
</div>

<?php if ($error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="form-grid" style="max-width:640px;">
  <div class="field full">
    <label style="display:inline-flex;align-items:center;gap:6px;margin-right:16px;">
      <input type="radio" name="client_mode" value="existing" checked onclick="document.getElementById('existingClientBlock').style.display='block';document.getElementById('newClientBlock').style.display='none';">
      Существующий клиент
    </label>
    <label style="display:inline-flex;align-items:center;gap:6px;">
      <input type="radio" name="client_mode" value="new" onclick="document.getElementById('existingClientBlock').style.display='none';document.getElementById('newClientBlock').style.display='block';">
      Новый клиент
    </label>
  </div>

  <div class="field full" id="existingClientBlock">
    <label>Клиент
      <select name="client_id">
        <option value="">— выберите —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int) $c['id'] ?>"><?= e($c['full_name']) ?> (<?= e($c['phone']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <div id="newClientBlock" style="display:none;" class="form-grid full">
    <label class="field">Имя нового клиента
      <input type="text" name="new_client_name">
    </label>
    <label class="field">Телефон
      <input type="text" name="new_client_phone" placeholder="+7 ...">
    </label>
  </div>

  <label class="field">Тип устройства
    <input type="text" name="device_type" placeholder="Ноутбук / смартфон / планшет / ПК" required>
  </label>
  <label class="field">Модель
    <input type="text" name="device_model">
  </label>
  <label class="field full">Описание проблемы
    <textarea name="problem_description" rows="3"></textarea>
  </label>
  <label class="field">Оценка стоимости, ₽
    <input type="number" name="price_estimate" min="0" step="1" value="0">
  </label>

  <div class="field full">
    <button type="submit" class="btn btn-primary">Создать заказ</button>
  </div>
</form>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
