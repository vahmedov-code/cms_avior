<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$clients = db()->query('SELECT id, full_name, phone FROM clients ORDER BY full_name')->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientMode = post('client_mode', 'existing');
    $comment = post('comment');

    $clientId = null;
    if ($clientMode === 'new') {
        $newName = post('new_client_name');
        $newPhone = post('new_client_phone');
        $newSource = post('new_client_source');
        if ($newName === '' || $newPhone === '') {
            $error = 'Укажите имя и телефон нового клиента.';
        } else {
            $stmt = db()->prepare('INSERT INTO clients (full_name, phone, source) VALUES (?, ?, ?)');
            $stmt->execute([$newName, $newPhone, array_key_exists($newSource, client_sources()) ? $newSource : null]);
            $clientId = (int) db()->lastInsertId();
        }
    } else {
        $clientId = (int) post('client_id');
        if ($clientId <= 0) {
            $error = 'Выберите клиента из списка или создайте нового.';
        }
    }

    if (!$error) {
        $orderNo = next_order_no();
        $stmt = db()->prepare(
            "INSERT INTO repairs (order_no, order_type, client_id, device_type, problem_description, status, price_estimate)
             VALUES (?, 'pc_build', ?, 'Сборка ПК', ?, 'принят', 0)"
        );
        $stmt->execute([$orderNo, $clientId, $comment ?: null]);
        $repairId = (int) db()->lastInsertId();

        $log = db()->prepare('INSERT INTO repair_status_log (repair_id, status, comment, changed_by) VALUES (?, ?, ?, ?)');
        $log->execute([$repairId, 'принят', 'Заказ на сборку ПК создан', current_user()['id']]);

        flash_set('Заказ на сборку ' . $orderNo . ' создан. Добавьте комплектующие и услуги ниже.', 'success');
        redirect('repair_view.php?id=' . $repairId);
    }
}

$pageTitle = 'Сборка ПК';
$activeNav = 'repairs';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>🖥️ Новая сборка ПК</h2>
  <a href="repairs.php" class="btn btn-sm">← К списку заказов</a>
</div>

<?php if ($error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:13px;max-width:600px;margin-top:-8px;">
  Создайте заказ, а на следующем экране добавите комплектующие и услуги —
  так же, как раньше в отдельной форме сметы, только сразу с попаданием
  в общий список заказов.
</p>

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
    <label class="field full">Источник
      <select name="new_client_source">
        <option value="">— не указан —</option>
        <?php foreach (client_sources() as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <label class="field full">Пожелания клиента (необязательно)
    <textarea name="comment" rows="3" placeholder="Например: тихая сборка, бюджет до 100 000 ₽..."></textarea>
  </label>

  <div class="field full">
    <button type="submit" class="btn btn-primary">Создать заказ и перейти к комплектующим</button>
  </div>
</form>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
