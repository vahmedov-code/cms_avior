<?php
/**
 * Памятка: данные учётной записи (Apple ID / Google) — печатная форма.
 *
 * ВАЖНО про безопасность: пароль, ответы на секретные вопросы и код 2FA
 * НЕ сохраняются в базе данных — они только попадают в распечатанный лист.
 * В базе остаётся лишь сам факт заказа (клиент, тип аккаунта, дата) —
 * чтобы это попало в общий список «Заказы», как и остальные типы заказов,
 * но без риска хранить чужие пароли в открытом виде на сервере.
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$clients = db()->query('SELECT id, full_name, phone FROM clients ORDER BY full_name')->fetchAll();

$error = '';
$printData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientMode = post('client_mode', 'existing');
    $accountType = post('account_type', 'Apple ID');

    $clientId = null;
    if ($clientMode === 'new') {
        $newName = post('new_client_name');
        $newPhone = post('new_client_phone');
        if ($newName === '' || $newPhone === '') {
            $error = 'Укажите имя и телефон нового клиента.';
        } else {
            $stmt = db()->prepare('INSERT INTO clients (full_name, phone) VALUES (?, ?)');
            $stmt->execute([$newName, $newPhone]);
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
            "INSERT INTO repairs (order_no, order_type, client_id, device_type, problem_description, status, price_estimate, public_token)
             VALUES (?, 'account_memo', ?, ?, ?, 'выдан', 0, ?)"
        );
        $stmt->execute([$orderNo, $clientId, $accountType, 'Памятка по учётной записи оформлена и выдана клиенту на бумаге.', generate_public_token()]);
        $repairId = (int) db()->lastInsertId();

        $log = db()->prepare('INSERT INTO repair_status_log (repair_id, status, comment, changed_by) VALUES (?, ?, ?, ?)');
        $log->execute([$repairId, 'выдан', 'Памятка по аккаунту оформлена', current_user()['id']]);

        $stmtC = db()->prepare('SELECT * FROM clients WHERE id = ?');
        $stmtC->execute([$clientId]);
        $client = $stmtC->fetch();

        // Данные для печати — берутся напрямую из формы и нигде не сохраняются.
        $printData = [
            'order_no'     => $orderNo,
            'account_type' => $accountType,
            'client_name'  => $client['full_name'],
            'phone'        => post('phone') ?: $client['phone'],
            'birthdate'    => post('birthdate'),
            'login_email'  => post('login_email'),
            'password'     => post('account_password'),
            'recovery'     => post('recovery'),
            'q1'           => post('question1'),
            'a1'           => post('answer1'),
            'q2'           => post('question2'),
            'a2'           => post('answer2'),
            'code2fa'      => post('code2fa'),
            'notes'        => post('master_notes'),
        ];
    }
}

if ($printData): ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Памятка — <?= e($printData['client_name']) ?></title>
<style>
  :root{ --navy:#152a4e; --navy-dark:#0e1e3a; --gold:#d4a63a; --bg:#f4f6fa; --border:#dde3ec; --text:#1c2436; --muted:#6b7385; }
  *{box-sizing:border-box;}
  body{margin:0;font-family:"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text);padding:24px;}
  .sheet{max-width:700px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 14px rgba(20,30,60,.08);}
  header{background:linear-gradient(135deg,var(--navy),var(--navy-dark));color:#fff;padding:22px 28px;border-top-left-radius:10px;border-top-right-radius:10px;}
  header h1{margin:0;font-size:22px;letter-spacing:3px;color:var(--gold);}
  header p{margin:4px 0 0;font-size:12px;color:#cfd6e6;}
  .body{padding:24px 28px;}
  h2{font-size:17px;color:var(--navy);margin:0 0 4px;}
  .sub{color:var(--muted);font-size:13px;margin-bottom:18px;}
  table{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:14px;}
  td{padding:8px 6px;border-bottom:1px solid var(--border);}
  td.label{color:var(--muted);width:46%;}
  .section-title{font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin:18px 0 6px;}
  .sign{margin-top:26px;font-size:13px;color:var(--muted);display:flex;justify-content:space-between;}
  .actions{padding:0 28px 24px;display:flex;gap:10px;}
  .btn{padding:10px 16px;border-radius:6px;border:1px solid var(--border);background:#fff;cursor:pointer;font-size:14px;text-decoration:none;color:var(--text);}
  .btn-primary{background:var(--navy);color:#fff;border-color:var(--navy);}
  .warn{background:#fbe9e7;color:#c0392b;padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:16px;}
  @media print{
    body{background:#fff;padding:0;}
    .sheet{box-shadow:none;border-radius:0;max-width:100%;}
    .actions{display:none !important;}
  }
</style>
</head>
<body>
<div class="sheet">
  <header>
    <h1>АВИОР</h1>
    <p>Можайское шоссе, 4к1, Москва · +7 (901) 222-81-11</p>
  </header>
  <div class="body">
    <h2>Памятка: данные учётной записи</h2>
    <div class="sub">Заказ <?= e($printData['order_no']) ?> · <?= e($printData['account_type']) ?></div>

    <div class="warn">Заполните и распечатайте этот лист. Не передавайте данные третьим лицам. Этот экран не сохраняется в системе — после закрытия вкладки восстановить его нельзя, при необходимости распечатайте сейчас.</div>

    <div class="section-title">Данные владельца</div>
    <table>
      <tr><td class="label">ФИО</td><td><?= e($printData['client_name']) ?></td></tr>
      <tr><td class="label">Номер телефона</td><td><?= e($printData['phone']) ?></td></tr>
      <tr><td class="label">Дата рождения</td><td><?= e($printData['birthdate']) ?></td></tr>
    </table>

    <div class="section-title">Учётные данные</div>
    <table>
      <tr><td class="label">Email (логин)</td><td><?= e($printData['login_email']) ?></td></tr>
      <tr><td class="label">Пароль</td><td><?= e($printData['password']) ?></td></tr>
      <tr><td class="label">Резервный email / телефон</td><td><?= e($printData['recovery']) ?></td></tr>
    </table>

    <div class="section-title">Дополнительная защита</div>
    <table>
      <tr><td class="label">Секретный вопрос 1</td><td><?= e($printData['q1']) ?></td></tr>
      <tr><td class="label">Ответ</td><td><?= e($printData['a1']) ?></td></tr>
      <tr><td class="label">Секретный вопрос 2</td><td><?= e($printData['q2']) ?></td></tr>
      <tr><td class="label">Ответ</td><td><?= e($printData['a2']) ?></td></tr>
      <tr><td class="label">Код двухфакторной аутентификации</td><td><?= e($printData['code2fa']) ?></td></tr>
    </table>

    <div class="section-title">Заметки мастера</div>
    <table>
      <tr><td><?= nl2br(e($printData['notes'])) ?></td></tr>
    </table>

    <div class="sign">
      <span>Дата: <?= date('d.m.Y') ?></span>
      <span>Подпись: ______________</span>
    </div>
  </div>
  <div class="actions">
    <button class="btn btn-primary" onclick="window.print()">🖨 Печать</button>
    <a class="btn" href="repair_view.php?id=<?= (int) $repairId ?>">Открыть заказ в CRM →</a>
  </div>
</div>
</body>
</html>
<?php
exit;
endif;

$pageTitle = 'Памятка по аккаунту';
$activeNav = 'repairs';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>🔐 Памятка: данные учётной записи</h2>
  <a href="repairs.php" class="btn btn-sm">← К списку заказов</a>
</div>

<div class="flash flash-info" style="max-width:640px;">
  Пароль, ответы на секретные вопросы и код 2FA не сохраняются в базе —
  только на распечатанном листе, который вы выдадите клиенту. В системе
  останется лишь запись о заказе.
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

  <label class="field full">Тип аккаунта
    <select name="account_type">
      <option value="Apple ID">Apple ID</option>
      <option value="Google-аккаунт">Google-аккаунт</option>
    </select>
  </label>

  <div class="field full" style="border-top:1px solid var(--border);padding-top:14px;margin-top:6px;">
    <strong style="font-size:13px;color:var(--navy);">Данные владельца</strong>
  </div>
  <label class="field">Телефон (если отличается)
    <input type="text" name="phone" placeholder="+7 ...">
  </label>
  <label class="field">Дата рождения
    <input type="text" name="birthdate" placeholder="дд.мм.гггг">
  </label>

  <div class="field full" style="border-top:1px solid var(--border);padding-top:14px;margin-top:6px;">
    <strong style="font-size:13px;color:var(--navy);">Учётные данные</strong>
  </div>
  <label class="field">Email (логин)
    <input type="text" name="login_email">
  </label>
  <label class="field">Пароль
    <input type="text" name="account_password">
  </label>
  <label class="field full">Резервный email / телефон для восстановления
    <input type="text" name="recovery">
  </label>

  <div class="field full" style="border-top:1px solid var(--border);padding-top:14px;margin-top:6px;">
    <strong style="font-size:13px;color:var(--navy);">Дополнительная защита</strong>
  </div>
  <label class="field">Секретный вопрос 1
    <input type="text" name="question1">
  </label>
  <label class="field">Ответ
    <input type="text" name="answer1">
  </label>
  <label class="field">Секретный вопрос 2
    <input type="text" name="question2">
  </label>
  <label class="field">Ответ
    <input type="text" name="answer2">
  </label>
  <label class="field full">Код двухфакторной аутентификации (если есть)
    <input type="text" name="code2fa">
  </label>

  <label class="field full">Заметки мастера
    <textarea name="master_notes" rows="3"></textarea>
  </label>

  <div class="field full">
    <button type="submit" class="btn btn-primary">Сформировать памятку для печати</button>
  </div>
</form>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
