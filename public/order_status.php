<?php
/**
 * Публичная страница статуса заказа для клиента — открывается по QR-коду
 * с квитанции о приёмке. Требует и номер заказа, и телефон (как и
 * api/status.php) — чтобы нельзя было перебором номеров узнать чужие
 * данные. Не требует входа в CRM.
 */
require __DIR__ . '/../src/bootstrap.php';

function only_digits_os(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

$orderNo = get('order_no');
$phone = get('phone');
$company = company_info();

$repair = null;
if ($orderNo !== '' && $phone !== '') {
    $stmt = db()->prepare(
        'SELECT r.order_no, r.status, r.device_type, r.device_model, r.updated_at, c.phone
         FROM repairs r JOIN clients c ON c.id = r.client_id
         WHERE r.order_no = ?
         LIMIT 1'
    );
    $stmt->execute([$orderNo]);
    $found = $stmt->fetch();
    if ($found && only_digits_os($found['phone']) === only_digits_os($phone)) {
        $repair = $found;
    }
}

$statusColors = [
    'принят'       => '#6b7385',
    'диагностика'  => '#b8860b',
    'согласование' => '#b8860b',
    'в ремонте'    => '#1565c0',
    'готов'        => '#1e7e34',
    'выдан'        => '#1e7e34',
    'отказ'        => '#c0392b',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Статус заказа<?= $repair ? ' ' . e($repair['order_no']) : '' ?> — <?= e($company['name']) ?></title>
<style>
  :root{ --navy:#152a4e; --navy-dark:#0e1e3a; --gold:#d4a63a; --bg:#f4f6fa; --border:#dde3ec; --text:#1c2436; --muted:#6b7385; }
  *{box-sizing:border-box;}
  body{margin:0;font-family:"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text);padding:24px;display:flex;justify-content:center;}
  .card{max-width:420px;width:100%;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(20,30,60,.1);overflow:hidden;}
  header{background:linear-gradient(135deg,var(--navy),var(--navy-dark));color:#fff;padding:22px 24px;text-align:center;}
  header h1{margin:0;font-size:20px;letter-spacing:3px;color:var(--gold);}
  header p{margin:4px 0 0;font-size:12px;color:#cfd6e6;}
  .body{padding:26px 24px;text-align:center;}
  .order-no{font-size:13px;color:var(--muted);margin-bottom:14px;}
  .status-badge{display:inline-block;padding:8px 20px;border-radius:999px;color:#fff;font-weight:700;font-size:16px;margin-bottom:14px;}
  .device{font-size:14px;color:var(--text);margin-bottom:6px;}
  .updated{font-size:12px;color:var(--muted);}
  .error{font-size:14px;color:var(--muted);padding:10px 0;}
  footer{padding:14px 24px;text-align:center;font-size:11px;color:var(--muted);border-top:1px solid var(--border);}
</style>
</head>
<body>
<div class="card">
  <header>
    <h1><?= e($company['name']) ?></h1>
    <p><?= e($company['address']) ?> · <?= e($company['phone']) ?></p>
  </header>
  <div class="body">
    <?php if ($repair): ?>
      <div class="order-no">Заказ <?= e($repair['order_no']) ?></div>
      <div class="status-badge" style="background:<?= e($statusColors[$repair['status']] ?? '#6b7385') ?>;"><?= e($repair['status']) ?></div>
      <div class="device"><?= e(trim($repair['device_type'] . ' ' . ($repair['device_model'] ?? ''))) ?></div>
      <div class="updated">Обновлено: <?= date('d.m.Y H:i', strtotime($repair['updated_at'])) ?></div>
    <?php else: ?>
      <div class="error">Заказ не найден — проверьте ссылку или обратитесь в сервис по телефону <?= e($company['phone']) ?>.</div>
    <?php endif; ?>
  </div>
  <footer>Актуальный статус заказа — просто откройте эту страницу ещё раз.</footer>
</div>
</body>
</html>
