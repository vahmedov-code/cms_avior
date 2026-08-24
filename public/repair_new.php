<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

// Сколько устройств можно принять за один раз от одного клиента —
// первый блок виден сразу, остальные раскрываются кнопкой
// «+ Добавить ещё устройство» (см. JS ниже). Каждое устройство —
// отдельный заказ (repairs), т.к. у каждого свой статус/цена/сроки,
// но все они привязаны к одному client_id.
const MAX_DEVICES = 5;

$clients = db()->query('SELECT id, full_name, phone FROM clients ORDER BY full_name')->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientMode = post('client_mode', 'existing');

    $deviceTypes = array_map('trim', (array) ($_POST['device_type'] ?? []));
    $deviceModels = array_map('trim', (array) ($_POST['device_model'] ?? []));
    $problems = array_map('trim', (array) ($_POST['problem_description'] ?? []));
    $prices = (array) ($_POST['price_estimate'] ?? []);

    // Индексы блоков, где реально заполнен тип устройства — пустые
    // (нераскрытые) блоки просто игнорируются.
    $deviceIndexes = [];
    foreach ($deviceTypes as $i => $dt) {
        if ($dt !== '') {
            $deviceIndexes[] = $i;
        }
    }

    if (!$deviceIndexes) {
        $error = 'Укажите тип устройства хотя бы для одного заказа.';
    }

    $clientId = null;
    if (!$error && $clientMode === 'new') {
        $newName = post('new_client_name');
        $newPhone = post('new_client_phone');
        $newSource = post('new_client_source');
        if ($newName === '' || $newPhone === '') {
            $error = 'Укажите имя и телефон нового клиента.';
        } else {
            // Та же защита от дублей, что теперь в Mobile API (обсуждали
            // 19.08) — если клиент с таким телефоном уже есть, используем
            // его вместо создания дубля.
            $clientId = find_or_create_client(
                $newName,
                $newPhone,
                array_key_exists($newSource, client_sources()) ? $newSource : null
            );
        }
    } elseif (!$error) {
        $clientId = (int) post('client_id');
        if ($clientId <= 0) {
            $error = 'Выберите клиента из списка или создайте нового.';
        }
    }

    if (!$error) {
        $stmt = db()->prepare(
            'INSERT INTO repairs (order_no, client_id, device_type, device_model, problem_description, status, price_estimate, public_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $log = db()->prepare('INSERT INTO repair_status_log (repair_id, status, comment, changed_by) VALUES (?, ?, ?, ?)');

        $createdIds = [];
        $createdOrderNos = [];
        foreach ($deviceIndexes as $i) {
            $priceEstimate = (float) str_replace(',', '.', (string) ($prices[$i] ?? '0'));
            $orderNo = next_order_no();
            $stmt->execute([
                $orderNo,
                $clientId,
                $deviceTypes[$i],
                $deviceModels[$i] !== '' ? $deviceModels[$i] : null,
                $problems[$i] !== '' ? $problems[$i] : null,
                'принят',
                $priceEstimate,
                generate_public_token(),
            ]);
            $repairId = (int) db()->lastInsertId();
            $log->execute([$repairId, 'принят', 'Заказ создан', current_user()['id']]);
            $createdIds[] = $repairId;
            $createdOrderNos[] = $orderNo;
        }

        if (count($createdIds) === 1) {
            flash_set('Заказ ' . $createdOrderNos[0] . ' создан.', 'success');
            redirect('repair_view.php?id=' . $createdIds[0]);
        }

        flash_set('Создано заказов: ' . count($createdIds) . ' (' . implode(', ', $createdOrderNos) . ').', 'success');
        redirect('client_view.php?id=' . $clientId);
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
    <label class="field full">Источник
      <select name="new_client_source">
        <option value="">— не указан —</option>
        <?php foreach (client_sources() as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <div class="field full" style="border-top:1px solid var(--border, #333);margin-top:8px;padding-top:12px;">
    <strong>Устройства в этом приёме</strong>
  </div>

  <?php for ($i = 0; $i < MAX_DEVICES; $i++): ?>
    <div class="field full device-block" id="deviceBlock_<?= $i ?>" style="<?= $i === 0 ? '' : 'display:none;' ?>border:1px solid var(--border, #333);border-radius:8px;padding:12px;margin-bottom:8px;">
      <?php if ($i > 0): ?>
        <div style="font-size:13px;color:var(--muted);margin-bottom:6px;">Устройство №<?= $i + 1 ?></div>
      <?php endif; ?>
      <div class="form-grid">
        <label class="field">Тип устройства
          <?= render_device_type_picker('deviceTypeField_' . $i) ?>
          <input type="text" name="device_type[<?= $i ?>]" id="deviceTypeField_<?= $i ?>" list="deviceTypeList" placeholder="Или выберите вариант выше / впишите свой">
        </label>
        <label class="field">Модель
          <input type="text" name="device_model[<?= $i ?>]" list="deviceModelList">
        </label>
        <label class="field full">Описание проблемы
          <?= render_suggestion_chips('problemField_' . $i, suggest_problem_descriptions()) ?>
          <textarea name="problem_description[<?= $i ?>]" id="problemField_<?= $i ?>" rows="3"></textarea>
        </label>
        <label class="field">Оценка стоимости, ₽
          <input type="number" name="price_estimate[<?= $i ?>]" min="0" step="1" value="0">
        </label>
      </div>
    </div>
  <?php endfor; ?>

  <div class="field full">
    <button type="button" class="btn btn-sm" id="addDeviceBtn" onclick="addDeviceBlock()">+ Добавить ещё устройство</button>
  </div>

  <div class="field full">
    <button type="submit" class="btn btn-primary">Создать заказ</button>
  </div>
</form>

<?= render_datalist('deviceTypeList', suggest_device_types()) ?>
<?= render_datalist('deviceModelList', suggest_device_models()) ?>

<script>
(function () {
  var maxDevices = <?= MAX_DEVICES ?>;
  var nextIndex = 1; // блок 0 уже виден по умолчанию
  window.addDeviceBlock = function () {
    if (nextIndex >= maxDevices) { return; }
    var block = document.getElementById('deviceBlock_' + nextIndex);
    if (block) { block.style.display = 'block'; }
    nextIndex++;
    if (nextIndex >= maxDevices) {
      document.getElementById('addDeviceBtn').style.display = 'none';
    }
  };
})();
</script>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
