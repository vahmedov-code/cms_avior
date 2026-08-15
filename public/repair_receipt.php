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

    // Ссылка на публичный статус заказа (без входа в CMS) — зашивается в QR.
    $siteUrl = rtrim(config()['site_url'] ?? '', '/');
    $statusUrl = $siteUrl !== '' && strpos($siteUrl, 'example.ru') === false
        ? $siteUrl . '/order_status.php?order_no=' . urlencode($repair['order_no']) . '&phone=' . urlencode($repair['client_phone'])
        : null;
    $qrSrc = $statusUrl ? 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&margin=0&data=' . urlencode($statusUrl) : null;

    $money2 = static fn (float $n): string => number_format($n, 2, ',', ' ');

    ob_start();
    for ($copy = 1; $copy <= 2; $copy++): ?>
      <div class="copy">
        <div class="head-row">
          <div class="head-fields">
            <h1 class="doc-title">Квитанция № <?= e($repair['order_no']) ?> от <?= e($docDate) ?></h1>
            <p class="field"><strong>Исполнитель:</strong> <?= e($company['name']) ?></p>
            <p class="field"><strong>Адрес:</strong> <?= e($company['address']) ?></p>
            <p class="field"><strong>Телефон:</strong> <?= e($company['phone']) ?></p>
            <p class="field"><strong>Заказчик:</strong> <?= e($repair['client_name']) ?></p>
            <p class="field"><strong>Телефон:</strong> <?= e($repair['client_phone']) ?></p>
          </div>
          <?php if ($qrSrc): ?>
          <div class="qr-block">
            <a href="<?= e($statusUrl) ?>" target="_blank" rel="noopener">
              <img src="<?= e($qrSrc) ?>" width="90" height="90" alt="QR — статус заказа" title="Текущий статус: <?= e($repair['status']) ?>">
            </a>
            <div class="qr-caption">статус заказа</div>
          </div>
          <?php endif; ?>
        </div>

        <p class="field"><strong>Марка/модель:</strong> <?= e($repair['device_type']) ?><?= $repair['device_model'] ? ' ' . e($repair['device_model']) : '' ?><?= $repair['device_serial'] ? ' (' . e($repair['device_serial']) . ')' : '' ?></p>
        <p class="field"><strong>Комплектация:</strong> <?= $repair['device_complete'] ? e($repair['device_complete']) : '' ?></p>
        <p class="field"><strong>Внешний вид:</strong> <?= $repair['device_condition'] ? e($repair['device_condition']) : '' ?></p>
        <p class="field"><strong>Причина ремонта со слов заказчика:</strong> <?= $repair['problem_description'] ? nl2br(e($repair['problem_description'])) : '' ?></p>
        <p class="field"><strong>Предоплата:</strong> <?= $money2((float) $repair['prepayment']) ?></p>
        <p class="field"><strong>Ориентировочная стоимость ремонта:</strong> <?= $money2((float) $repair['price_estimate']) ?></p>
        <p class="field"><strong>Ориентировочная дата готовности:</strong> <?= $repair['deadline_date'] ? e($deadlineStr) : '' ?></p>
        <p class="field"><strong>Примечание:</strong> <?= $repair['receipt_note'] ? nl2br(e($repair['receipt_note'])) : '' ?></p>

        <ol class="terms">
          <li>Технический центр не несёт ответственности за возможную потерю данных в памяти устройства, связанную с заменой плат, установкой программного обеспечения, заменой носителя информации.</li>
          <li>Заказчик принимает на себя риск возможной полной или частичной утраты работоспособности устройства в процессе ремонта (тепловой обработки), в случае грубых нарушений пользователем условий эксплуатации, наличий следов попадания токопроводящей жидкости (коррозии), либо механических повреждений.</li>
          <li>На восстановленные после попадания жидкости на устройство гарантия не распространяется и не продлевается.</li>
          <li>Срок бесплатного хранения устройства составляет 30 дней с момента приёма его в ремонт. В случае, если по истечении указанного срока клиентом не заявлено требование о выдаче устройства, оно принимается на ответственное хранение. Стоимость услуг по ответственному хранению составляет ___ руб в сутки. Максимальный срок ответственного хранения составляет 30 дней. В случае, если в течение указанного срока Клиент не требует возврата устройства (либо с Клиентом не представляется возможным связаться по указанному в квитанции телефону), устройство утилизируется без компенсации его стоимости клиенту.</li>
          <li>В случае отказа заказчика от ремонта устройства стоимость диагностики неисправности платная.</li>
          <li>В случае утери квитанции, устройство выдаётся по предъявлению паспорта на имя заказчика.</li>
        </ol>

        <div class="sign-row">
          <span>Исполнитель: ___________ / <?= e($repair['manager_name'] ?? '') ?>/</span>
          <span>________________ / <?= e($repair['client_name']) ?>/</span>
        </div>
        <div class="sign-note">с условием гарантии ознакомлен и согласен</div>
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
  :root{ --navy:#152a4e; --border:#dde3ec; --muted:#6b7385; }
  *{box-sizing:border-box;}
  body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f6fa;color:#111;padding:24px;}
  .page{max-width:780px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 14px rgba(20,30,60,.08);padding:26px 30px;}

  /* Стиль печатной формы — под образец квитанции ЛайвСклад: чёрный текст,
     без цветных акцентов, поля списком «жирная подпись: значение». */
  .copy{padding:10px 0;font-size:13px;line-height:1.5;color:#111;}
  .head-row{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;}
  .head-fields{flex:1;}
  .doc-title{font-size:19px;font-weight:700;margin:0 0 12px;color:#111;}
  .field{margin:2px 0;}
  .field strong{font-weight:700;}
  .qr-block{text-align:center;flex-shrink:0;}
  .qr-block img{display:block;border:1px solid var(--border);}
  .qr-caption{font-size:9px;color:var(--muted);margin-top:2px;letter-spacing:.3px;}
  .terms{margin:14px 0 12px;padding-left:18px;font-size:11px;color:#222;line-height:1.5;}
  .terms li{margin-bottom:5px;}
  .sign-row{font-size:12.5px;margin-top:18px;display:flex;justify-content:space-between;gap:20px;}
  .sign-note{color:#444;font-size:11.5px;text-align:right;margin-top:2px;}
  .cut-line{text-align:center;color:var(--muted);font-size:11px;margin:16px 0;letter-spacing:1px;}
  .actions{max-width:780px;margin:16px auto 0;display:flex;gap:10px;}
  .btn{padding:10px 16px;border-radius:6px;border:1px solid var(--border);background:#fff;cursor:pointer;font-size:14px;text-decoration:none;color:#1c2436;}
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
