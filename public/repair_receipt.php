<?php
/**
 * Квитанция о приёмке устройства в ремонт — печатная форма (2 экземпляра на листе:
 * для клиента и для сервиса), с условиями гарантии/хранения.
 *
 * Данные (серийный номер, комплектация, внешний вид, предоплата, срок, примечание,
 * ФИО мастера) хранятся в repairs — в отличие от «Памятки по аккаунту» здесь нет
 * секретных данных, поэтому сохранять их в базе безопасно и удобно для повторной печати.
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$id = (int) get('id');

function load_receipt_repair(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone,
                c.client_type AS client_type, c.contact_person AS client_contact_person,
                c.inn AS client_inn, c.kpp AS client_kpp
         FROM repairs r JOIN clients c ON c.id = r.client_id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$repair = load_receipt_repair($id);
if (!$repair) {
    flash_set('Заказ не найден.', 'error');
    redirect('repairs.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'save_receipt') {
    $deviceSerial = post('device_serial');
    $deviceComplete = post('device_complete');
    $deviceCondition = post('device_condition');
    $problem = post('problem_description');
    $priceEstimate = (float) str_replace(',', '.', post('price_estimate', '0'));
    $prepayment = (float) str_replace(',', '.', post('prepayment', '0'));
    $deadline = post('deadline_date');
    $note = post('receipt_note');
    $manager = post('manager_name') ?: (current_user()['full_name'] ?? '');

    $stmt = db()->prepare(
        'UPDATE repairs SET
            device_serial = ?, device_complete = ?, device_condition = ?,
            problem_description = ?, price_estimate = ?, prepayment = ?,
            deadline_date = ?, receipt_note = ?, manager_name = ?, receipt_ready = 1
         WHERE id = ?'
    );
    $stmt->execute([
        $deviceSerial ?: null,
        $deviceComplete ?: null,
        $deviceCondition ?: null,
        $problem ?: null,
        $priceEstimate,
        $prepayment,
        $deadline ?: null,
        $note ?: null,
        $manager ?: null,
        $id,
    ]);

    redirect('repair_receipt.php?id=' . $id);
}

$showForm = !((int) $repair['receipt_ready']) || get('edit') === '1';

if (!$showForm) {
    echo render_receipt_page($repair, false);
    exit;
}

$pageTitle = 'Квитанция о приёмке — ' . $repair['order_no'];
$activeNav = 'repairs';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>🧾 Квитанция о приёмке — заказ <?= e($repair['order_no']) ?></h2>
  <a href="repair_view.php?id=<?= (int) $id ?>" class="btn btn-sm">← К заказу</a>
</div>

<p style="color:var(--muted);font-size:13px;max-width:640px;">
  Заполните детали приёма устройства — они сохранятся в заказе, и квитанцию можно
  будет распечатать повторно в любой момент без повторного ввода.
</p>

<form method="post" class="form-grid" style="max-width:640px;">
  <input type="hidden" name="action" value="save_receipt">

  <label class="field">Тип устройства
    <input type="text" name="device_type" value="<?= e($repair['device_type']) ?>" disabled>
  </label>
  <label class="field">Модель
    <input type="text" name="device_model" value="<?= e($repair['device_model'] ?? '') ?>" disabled>
  </label>
  <p style="grid-column:1/-1;font-size:12px;color:var(--muted);margin:-6px 0 0;">
    Тип и модель редактируются на странице заказа. Здесь — только данные для квитанции.
  </p>

  <label class="field">Серийный номер
    <input type="text" name="device_serial" value="<?= e($repair['device_serial'] ?? '') ?>">
  </label>
  <label class="field">Ориентировочная дата готовности
    <input type="date" name="deadline_date" value="<?= e($repair['deadline_date'] ?? '') ?>">
  </label>

  <label class="field full">Комплектация (зарядка, чехол, аксессуары)
    <input type="text" name="device_complete" value="<?= e($repair['device_complete'] ?? '') ?>">
  </label>
  <label class="field full">Внешний вид (потёртости, сколы и т.п.)
    <input type="text" name="device_condition" value="<?= e($repair['device_condition'] ?? '') ?>">
  </label>
  <label class="field full">Причина ремонта со слов заказчика
    <textarea name="problem_description" rows="3"><?= e($repair['problem_description'] ?? '') ?></textarea>
  </label>

  <label class="field">Предоплата, ₽
    <input type="number" name="prepayment" min="0" step="1" value="<?= e((string) (float) $repair['prepayment']) ?>">
  </label>
  <label class="field">Ориентировочная стоимость ремонта, ₽
    <input type="number" name="price_estimate" min="0" step="1" value="<?= e((string) (float) $repair['price_estimate']) ?>">
  </label>

  <label class="field full">Примечание
    <input type="text" name="receipt_note" value="<?= e($repair['receipt_note'] ?? '') ?>">
  </label>
  <label class="field full">ФИО мастера/менеджера
    <input type="text" name="manager_name" value="<?= e($repair['manager_name'] ?: (current_user()['full_name'] ?? '')) ?>">
  </label>

  <div class="field full">
    <button type="submit" class="btn btn-primary">Сохранить и открыть квитанцию для печати</button>
  </div>
</form>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
