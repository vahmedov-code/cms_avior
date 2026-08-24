<?php
/**
 * Мой профиль — смена своего пароля (и отображаемого имени).
 * Доступно любому авторизованному сотруднику, без ограничений по роли.
 */
require __DIR__ . '/../src/bootstrap.php';
require_login();

$me = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'update_name') {
        $fullName = post('full_name');
        if ($fullName === '') {
            $error = 'Укажите имя.';
        } else {
            db()->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$fullName, $me['id']]);
            $_SESSION['user']['full_name'] = $fullName;
            flash_set('Имя обновлено.', 'success');
            redirect('profile.php');
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$me['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $error = 'Текущий пароль указан неверно.';
        } elseif (strlen($new) < 6) {
            $error = 'Новый пароль должен быть не короче 6 символов.';
        } elseif ($new !== $new2) {
            $error = 'Новые пароли не совпадают.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $me['id']]);
            flash_set('Пароль изменён.', 'success');
            redirect('profile.php');
        }
    }

    if ($action === 'delete_webauthn_credential') {
        $credId = (int) post('credential_id');
        db()->prepare('DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?')->execute([$credId, $me['id']]);
        flash_set('Устройство отвязано.', 'success');
        redirect('profile.php');
    }
}

$webauthnCredentials = webauthn_list_credentials($me['id']);

$pageTitle = 'Мой профиль';
$activeNav = 'profile';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>👤 Мой профиль</h2>
  <a href="index.php" class="btn btn-sm">← На панель</a>
</div>

<?php if ($error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="finance-grid" style="max-width:820px;">
  <div class="table-card" style="padding:16px;">
    <h3 style="margin:0 0 12px;font-size:15px;color:var(--navy);">Данные учётной записи</h3>
    <p style="color:var(--muted);font-size:13px;margin:0 0 14px;">Логин: <strong><?= e($me['username']) ?></strong> · роль: <?= e(role_label($me['role'])) ?></p>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="update_name">
      <label class="field full">Имя (отображается в CRM)
        <input type="text" name="full_name" value="<?= e($me['full_name']) ?>" required>
      </label>
      <div class="field full">
        <button type="submit" class="btn">Сохранить имя</button>
      </div>
    </form>
  </div>

  <div class="table-card" style="padding:16px;">
    <h3 style="margin:0 0 12px;font-size:15px;color:var(--navy);">Сменить пароль</h3>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="change_password">
      <label class="field full">Текущий пароль
        <input type="password" name="current_password" required>
      </label>
      <label class="field full">Новый пароль
        <input type="password" name="new_password" minlength="6" required>
      </label>
      <label class="field full">Повтор нового пароля
        <input type="password" name="new_password2" minlength="6" required>
      </label>
  <div class="table-card" style="padding:16px;">
    <h3 style="margin:0 0 12px;font-size:15px;color:var(--navy);">Вход по отпечатку пальца / Face ID</h3>
    <p style="color:var(--muted);font-size:13px;margin:0 0 14px;">Работает только на HTTPS и на телефонах/ноутбуках со встроенным сканером. Привязать нужно на каждом устройстве отдельно.</p>

    <?php if (!$webauthnCredentials): ?>
      <p style="color:var(--muted);font-size:13px;">Пока ни одно устройство не привязано.</p>
    <?php else: ?>
      <table style="width:100%;margin-bottom:14px;">
        <?php foreach ($webauthnCredentials as $cred): ?>
          <tr>
            <td><?= e($cred['device_label'] ?: 'Без названия') ?></td>
            <td style="color:var(--muted);font-size:12px;">с <?= e(date('d.m.Y', strtotime($cred['created_at']))) ?><?= $cred['last_used_at'] ? ', вход ' . e(date('d.m.Y H:i', strtotime($cred['last_used_at']))) : '' ?></td>
            <td style="text-align:right;">
              <form method="post" style="display:inline;" onsubmit="return confirm('Отвязать устройство «<?= e($cred['device_label'] ?: 'Без названия') ?>»? Вход по отпечатку на нём перестанет работать.');">
                <input type="hidden" name="action" value="delete_webauthn_credential">
                <input type="hidden" name="credential_id" value="<?= (int) $cred['id'] ?>">
                <button type="submit" class="btn btn-sm">Отвязать</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <div class="field full" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="text" id="webauthnDeviceLabel" placeholder="Название устройства, напр. «Мой iPhone»" style="flex:1;min-width:200px;">
      <button type="button" class="btn btn-primary" id="webauthnRegisterBtn" onclick="webauthnRegister()">+ Привязать это устройство</button>
    </div>
    <p id="webauthnMsg" style="font-size:13px;margin-top:8px;"></p>
    <p id="webauthnUnsupported" style="display:none;color:var(--muted);font-size:13px;margin-top:8px;">Этот браузер не поддерживает вход по отпечатку.</p>
  </div>
</div>

<script>
function b64urlToBuf(b64url) {
  var b64 = b64url.replace(/-/g, '+').replace(/_/g, '/');
  while (b64.length % 4) { b64 += '='; }
  var bin = window.atob(b64);
  var bytes = new Uint8Array(bin.length);
  for (var i = 0; i < bin.length; i++) { bytes[i] = bin.charCodeAt(i); }
  return bytes.buffer;
}
function bufToB64url(buf) {
  var bytes = new Uint8Array(buf);
  var bin = '';
  for (var i = 0; i < bytes.byteLength; i++) { bin += String.fromCharCode(bytes[i]); }
  return window.btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

document.addEventListener('DOMContentLoaded', function () {
  if (!window.PublicKeyCredential) {
    document.getElementById('webauthnRegisterBtn').style.display = 'none';
    document.getElementById('webauthnUnsupported').style.display = 'block';
  }
});

function webauthnRegister() {
  var msgEl = document.getElementById('webauthnMsg');
  var btn = document.getElementById('webauthnRegisterBtn');
  var label = document.getElementById('webauthnDeviceLabel').value.trim();
  msgEl.style.color = 'var(--muted)';
  msgEl.textContent = 'Ждём подтверждение отпечатком...';
  btn.disabled = true;

  fetch('api/webauthn/register_begin.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.ok) { throw new Error(res.error || 'Ошибка подготовки регистрации'); }
      var opts = res.options;
      opts.challenge = b64urlToBuf(opts.challenge);
      opts.user.id = b64urlToBuf(opts.user.id);
      if (opts.excludeCredentials) {
        opts.excludeCredentials.forEach(function (c) { c.id = b64urlToBuf(c.id); });
      }
      return navigator.credentials.create({ publicKey: opts });
    })
    .then(function (cred) {
      var body = {
        clientDataJSON: bufToB64url(cred.response.clientDataJSON),
        attestationObject: bufToB64url(cred.response.attestationObject),
        deviceLabel: label,
        csrfToken: document.querySelector('meta[name="csrf-token"]').content
      };
      return fetch('api/webauthn/register_finish.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.ok) { throw new Error(res.error || 'Не удалось привязать устройство'); }
      msgEl.style.color = 'var(--good, green)';
      msgEl.textContent = res.message;
      setTimeout(function () { window.location.reload(); }, 800);
    })
    .catch(function (err) {
      msgEl.style.color = 'var(--danger, red)';
      msgEl.textContent = err.message || 'Не удалось привязать устройство.';
      btn.disabled = false;
    });
}
</script>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
