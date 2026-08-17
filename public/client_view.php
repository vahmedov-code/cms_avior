<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$id = (int) get('id');
$stmt = db()->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    flash_set('Клиент не найден.', 'error');
    redirect('clients.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update') {
    $source = post('source');
    $clientType = post('client_type') === 'legal_entity' ? 'legal_entity' : 'individual';
    $stmt = db()->prepare(
        'UPDATE clients SET full_name = ?, client_type = ?, contact_person = ?, phone = ?, email = ?,
            address = ?, inn = ?, kpp = ?, ogrn = ?, legal_address = ?, bank_name = ?, bank_account = ?,
            bank_bik = ?, bank_corr_account = ?, notes = ?, source = ? WHERE id = ?'
    );
    $stmt->execute([
        post('full_name'),
        $clientType,
        $clientType === 'legal_entity' ? (post('contact_person') ?: null) : null,
        format_phone_ru(post('phone')),
        post('email') ?: null,
        post('address') ?: null,
        $clientType === 'legal_entity' ? (post('inn') ?: null) : null,
        $clientType === 'legal_entity' ? (post('kpp') ?: null) : null,
        $clientType === 'legal_entity' ? (post('ogrn') ?: null) : null,
        $clientType === 'legal_entity' ? (post('legal_address') ?: null) : null,
        $clientType === 'legal_entity' ? (post('bank_name') ?: null) : null,
        $clientType === 'legal_entity' ? (post('bank_account') ?: null) : null,
        $clientType === 'legal_entity' ? (post('bank_bik') ?: null) : null,
        $clientType === 'legal_entity' ? (post('bank_corr_account') ?: null) : null,
        post('notes') ?: null,
        array_key_exists($source, client_sources()) ? $source : null,
        $id,
    ]);
    flash_set('Данные клиента обновлены.', 'success');
    redirect('client_view.php?id=' . $id);
}

$stmt = db()->prepare('SELECT * FROM repairs WHERE client_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$repairs = $stmt->fetchAll();

$pageTitle = $client['full_name'];
$activeNav = 'clients';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2><?= $client['client_type'] === 'legal_entity' ? '🏢 ' : '' ?><?= e($client['full_name']) ?></h2>
  <a href="clients.php" class="btn btn-sm">← К списку клиентов</a>
</div>

<form method="post" class="form-grid" style="max-width:640px;margin-bottom:28px;">
  <input type="hidden" name="action" value="update">
  <?= render_client_type_toggle($client['client_type'] ?? 'individual') ?>
  <label class="field full">ФИО / Название компании
    <input type="text" name="full_name" value="<?= e($client['full_name']) ?>" required>
  </label>
  <label class="field">Телефон
    <input type="text" name="phone" value="<?= e($client['phone']) ?>" required>
  </label>
  <label class="field">Email
    <input type="email" name="email" value="<?= e($client['email'] ?? '') ?>">
  </label>
  <label class="field full">Адрес
    <input type="text" name="address" value="<?= e($client['address'] ?? '') ?>">
  </label>
  <?= render_legal_entity_fields($client) ?>
  <label class="field">Источник
    <select name="source">
      <option value="">— не указан —</option>
      <?php foreach (client_sources() as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= ($client['source'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field full">Заметки
    <textarea name="notes" rows="3"><?= e($client['notes'] ?? '') ?></textarea>
  </label>
  <div class="field full">
    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
  </div>
</form>

<h3 style="color:var(--navy);font-size:16px;">История заказов</h3>
<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>№ заказа</th>
        <th>Устройство</th>
        <th>Статус</th>
        <th>Сумма</th>
        <th>Создан</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$repairs): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);">Заказов пока нет.</td></tr>
      <?php endif; ?>
      <?php foreach ($repairs as $r): ?>
        <tr>
          <td data-label="№ заказа"><a href="repair_view.php?id=<?= (int) $r['id'] ?>"><?= e($r['order_no']) ?></a></td>
          <td data-label="Устройство"><?= e($r['device_type']) ?> <?= e($r['device_model'] ?? '') ?></td>
          <td data-label="Статус"><span class="status-badge" data-status="<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td data-label="Сумма"><?= money((float) ($r['price_final'] ?? $r['price_estimate'])) ?></td>
          <td data-label="Создан"><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
          <td data-label=""><a href="repair_view.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm">Открыть</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
