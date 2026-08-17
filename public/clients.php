<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();

$pageTitle = 'Клиенты';
$activeNav = 'clients';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create') {
    $fullName = post('full_name');
    $phone = post('phone');
    $email = post('email');
    $address = post('address');
    $source = post('source');
    $clientType = post('client_type') === 'legal_entity' ? 'legal_entity' : 'individual';

    if ($fullName === '' || $phone === '') {
        flash_set('Укажите имя и телефон клиента.', 'error');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO clients (full_name, client_type, contact_person, phone, email, address,
                inn, kpp, ogrn, legal_address, bank_name, bank_account, bank_bik, bank_corr_account, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $fullName,
            $clientType,
            $clientType === 'legal_entity' ? (post('contact_person') ?: null) : null,
            format_phone_ru($phone),
            $email ?: null,
            $address ?: null,
            $clientType === 'legal_entity' ? (post('inn') ?: null) : null,
            $clientType === 'legal_entity' ? (post('kpp') ?: null) : null,
            $clientType === 'legal_entity' ? (post('ogrn') ?: null) : null,
            $clientType === 'legal_entity' ? (post('legal_address') ?: null) : null,
            $clientType === 'legal_entity' ? (post('bank_name') ?: null) : null,
            $clientType === 'legal_entity' ? (post('bank_account') ?: null) : null,
            $clientType === 'legal_entity' ? (post('bank_bik') ?: null) : null,
            $clientType === 'legal_entity' ? (post('bank_corr_account') ?: null) : null,
            array_key_exists($source, client_sources()) ? $source : null,
        ]);
        flash_set('Клиент добавлен.', 'success');
    }
    redirect('clients.php');
}

$search = get('q');
if ($search !== '') {
    $stmt = db()->prepare(
        'SELECT * FROM clients WHERE full_name LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT 200'
    );
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = db()->query('SELECT * FROM clients ORDER BY created_at DESC LIMIT 200');
}
$clients = $stmt->fetchAll();

require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>Клиенты</h2>
  <form method="get" style="display:flex;gap:8px;">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Поиск по имени или телефону" style="padding:8px 10px;border:1px solid var(--border);border-radius:6px;">
    <button type="submit" class="btn">Найти</button>
  </form>
</div>

<details style="margin-bottom:20px;">
  <summary class="btn btn-primary" style="display:inline-flex;cursor:pointer;">+ Новый клиент</summary>
  <form method="post" class="form-grid" style="margin-top:16px;max-width:640px;">
    <input type="hidden" name="action" value="create">
    <?= render_client_type_toggle() ?>
    <label class="field full">ФИО / Название компании
      <input type="text" name="full_name" required>
    </label>
    <label class="field">Телефон
      <input type="text" name="phone" placeholder="+7 ..." required>
    </label>
    <label class="field">Email
      <input type="email" name="email">
    </label>
    <label class="field full">Адрес
      <input type="text" name="address">
    </label>
    <?= render_legal_entity_fields() ?>
    <label class="field full">Источник
      <select name="source">
        <option value="">— не указан —</option>
        <?php foreach (client_sources() as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="field full">
      <button type="submit" class="btn btn-primary">Сохранить клиента</button>
    </div>
  </form>
</details>

<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>Клиент</th>
        <th>Телефон</th>
        <th>Источник</th>
        <th>Email</th>
        <th>В базе с</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$clients): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);">Клиентов пока нет.</td></tr>
      <?php endif; ?>
      <?php foreach ($clients as $c): ?>
        <tr>
          <td data-label="Клиент"><a href="client_view.php?id=<?= (int) $c['id'] ?>"><?= $c['client_type'] === 'legal_entity' ? '🏢 ' : '' ?><?= e($c['full_name']) ?></a></td>
          <td data-label="Телефон"><?= e($c['phone']) ?></td>
          <td data-label="Источник"><?= e(client_source_label($c['source'] ?? null)) ?></td>
          <td data-label="Email"><?= e($c['email'] ?? '—') ?></td>
          <td data-label="В базе с"><?= date('d.m.Y', strtotime($c['created_at'])) ?></td>
          <td data-label=""><a href="client_view.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm">Открыть</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
