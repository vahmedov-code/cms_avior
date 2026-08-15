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
        'SELECT r.*, c.full_name AS client_name, c.phone AS client_phone
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
    $company = company_info();
    $docDate = date('d.m.Y', strtotime($repair['created_at']));
    $deadlineStr = $repair['deadline_date'] ? date('d.m.Y', strtotime($repair['deadline_date'])) : '—';

    ob_start();
    for ($copy = 1; $copy <= 2; $copy++): ?>
      <div class="copy">
        <div class="copy-label"><?= $copy === 1 ? 'Экземпляр клиента' : 'Экземпляр сервиса' ?></div>
        <h2>Квитанция № <?= e($repair['order_no']) ?> от <?= e($docDate) ?></h2>
        <table class="head-table">
          <tr>
            <td class="label">Исполнитель:</td>
            <td><?= e($company['name']) ?></td>
            <td class="label">Адрес:</td>
            <td><?= e($company['address']) ?></td>
          </tr>
          <tr>
            <td class="label">Телефон:</td>
            <td><?= e($company['phone']) ?></td>
            <td class="label">Заказ:</td>
            <td><?= e($repair['order_no']) ?></td>
          </tr>
        </table>

        <table class="head-table">
          <tr>
            <td class="label">Заказчик:</td>
            <td><?= e($repair['client_name']) ?></td>
            <td class="label">Телефон:</td>
            <td><?= e($repair['client_phone']) ?></td>
          </tr>
        </table>

        <table class="details-table">
          <tr><td class="label">Марка/модель:</td><td><?= e($repair['device_type']) ?><?= $repair['device_model'] ? ' ' . e($repair['device_model']) : '' ?><?= $repair['device_serial'] ? ' (' . e($repair['device_serial']) . ')' : '' ?></td></tr>
          <tr><td class="label">Комплектация:</td><td><?= e($repair['device_complete'] ?? '') ?: '—' ?></td></tr>
          <tr><td class="label">Внешний вид:</td><td><?= e($repair['device_condition'] ?? '') ?: '—' ?></td></tr>
          <tr><td class="label">Причина ремонта со слов заказчика:</td><td><?= nl2br(e($repair['problem_description'] ?? '')) ?: '—' ?></td></tr>
          <tr><td class="label">Предоплата:</td><td><?= money((float) $repair['prepayment']) ?></td></tr>
          <tr><td class="label">Ориентировочная стоимость ремонта:</td><td><?= money((float) $repair['price_estimate']) ?></td></tr>
          <tr><td class="label">Ориентировочная дата готовности:</td><td><?= e($deadlineStr) ?></td></tr>
          <tr><td class="label">Примечание:</td><td><?= nl2br(e($repair['receipt_note'] ?? '')) ?: '—' ?></td></tr>
        </table>

        <ol class="terms">
          <li>Технический центр не несёт ответственности за возможную потерю данных в памяти устройства, связанную с заменой плат, установкой программного обеспечения, заменой носителя информации.</li>
          <li>Заказчик принимает на себя риск возможной полной или частичной утраты работоспособности устройства в процессе ремонта (тепловой обработки), в случае грубых нарушений пользователем условий эксплуатации, наличий следов попадания токопроводящей жидкости (коррозии), либо механических повреждений.</li>
          <li>На восстановленные после попадания жидкости на устройство гарантия не распространяется и не продлевается.</li>
          <li>Срок бесплатного хранения устройства составляет 30 дней с момента приёма его в ремонт. В случае, если по истечении указанного срока клиентом не заявлено требование о выдаче устройства, оно принимается на ответственное хранение. Стоимость услуг по ответственному хранению составляет ___ ₽ в сутки. Максимальный срок ответственного хранения составляет 30 дней. В случае, если в течение указанного срока Клиент не требует возврата устройства (либо с Клиентом не представляется возможным связаться по указанному в квитанции телефону), устройство утилизируется без компенсации его стоимости клиенту.</li>
          <li>В случае отказа заказчика от ремонта устройства стоимость диагностики неисправности платная.</li>
          <li>В случае утери квитанции, устройство выдаётся по предъявлению паспорта на имя заказчика.</li>
        </ol>

        <div class="sign-block">
          <div>Исполнитель: ___________ / <?= e($repair['manager_name'] ?? '') ?> /</div>
          <div>________________ / <?= e($repair['client_name']) ?> /</div>
          <div class="sign-note">с условием гарантии ознакомлен и согласен</div>
        </div>
      </div>
      <?php if ($copy === 1): ?><div class="cut-line">✂ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─</div><?php endif;
    endfor;
    $copiesHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Квитанция <?= e($repair['order_no']) ?> — <?= e($repair['client_name']) ?></title>
<style>
  :root{ --navy:#152a4e; --navy-dark:#0e1e3a; --gold:#d4a63a; --bg:#f4f6fa; --border:#dde3ec; --text:#1c2436; --muted:#6b7385; }
  *{box-sizing:border-box;}
  body{margin:0;font-family:"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text);padding:24px;font-size:12.5px;}
  .page{max-width:760px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 14px rgba(20,30,60,.08);padding:20px 26px;}
  .copy{padding:6px 0;}
  .copy-label{font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--gold);font-weight:700;margin-bottom:2px;}
  h2{font-size:15px;color:var(--navy);margin:0 0 8px;}
  table{width:100%;border-collapse:collapse;margin-bottom:6px;}
  .head-table td{padding:2px 4px;vertical-align:top;}
  .details-table td{padding:3px 4px;vertical-align:top;border-bottom:1px dotted var(--border);}
  td.label{color:var(--muted);white-space:nowrap;width:1%;padding-right:8px;font-weight:600;}
  .terms{margin:10px 0 12px;padding-left:16px;font-size:10.5px;color:var(--text);line-height:1.45;}
  .terms li{margin-bottom:5px;}
  .sign-block{font-size:12px;line-height:2;margin-top:6px;}
  .sign-note{color:var(--muted);font-size:11px;}
  .cut-line{text-align:center;color:var(--muted);font-size:11px;margin:14px 0;letter-spacing:1px;}
  .actions{max-width:760px;margin:16px auto 0;display:flex;gap:10px;}
  .btn{padding:10px 16px;border-radius:6px;border:1px solid var(--border);background:#fff;cursor:pointer;font-size:14px;text-decoration:none;color:var(--text);}
  .btn-primary{background:var(--navy);color:#fff;border-color:var(--navy);}
  @media print{
    body{background:#fff;padding:0;}
    .page{box-shadow:none;border-radius:0;max-width:100%;padding:0 8mm;}
    .actions{display:none !important;}
  }
</style>
</head>
<body>
<div class="page">
  <?= $copiesHtml ?>
</div>
<div class="actions">
  <button class="btn btn-primary" onclick="window.print()">🖨 Печать</button>
  <a class="btn" href="repair_receipt.php?id=<?= (int) $id ?>&edit=1">✎ Изменить данные</a>
  <a class="btn" href="repair_view.php?id=<?= (int) $id ?>">Открыть заказ в CMS →</a>
</div>
</body>
</html>
<?php
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
