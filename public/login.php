<?php
require __DIR__ . '/../src/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = post('username');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } elseif (attempt_login($username, $password)) {
        redirect('index.php');
    } else {
        $error = 'Неверный логин или пароль.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход — АВИОР CRM</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <header>
      <div class="brand">
        <h1>АВИОР</h1>
        <p>Внутренняя CRM сервиса</p>
      </div>
    </header>
    <div class="auth-body">
      <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label class="field">Логин
          <input type="text" name="username" id="loginUsername" value="<?= e($username ?? '') ?>" autofocus required>
        </label>
        <label class="field">Пароль
          <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary">Войти</button>
      </form>
      <button type="button" class="btn" id="webauthnLoginBtn" style="display:none;width:100%;margin-top:10px;" onclick="webauthnLogin()">🔒 Войти по отпечатку пальца</button>
      <p id="webauthnLoginMsg" style="font-size:13px;margin-top:8px;"></p>
      <div class="auth-hint">Доступ только для сотрудников АВИОР.</div>
    </div>
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
  if (window.PublicKeyCredential) {
    document.getElementById('webauthnLoginBtn').style.display = 'block';
  }
});

function webauthnLogin() {
  var msgEl = document.getElementById('webauthnLoginMsg');
  var username = document.getElementById('loginUsername').value.trim();

  if (!username) {
    msgEl.style.color = 'var(--danger, red)';
    msgEl.textContent = 'Сначала введите логин выше.';
    return;
  }

  msgEl.style.color = 'var(--muted)';
  msgEl.textContent = 'Ждём подтверждение отпечатком...';

  fetch('api/webauthn/login_begin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: username })
  })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.ok) { throw new Error(res.error || 'Устройство не привязано'); }
      var opts = res.options;
      opts.challenge = b64urlToBuf(opts.challenge);
      if (opts.allowCredentials) {
        opts.allowCredentials.forEach(function (c) { c.id = b64urlToBuf(c.id); });
      }
      return navigator.credentials.get({ publicKey: opts });
    })
    .then(function (cred) {
      var body = {
        id: bufToB64url(cred.rawId),
        clientDataJSON: bufToB64url(cred.response.clientDataJSON),
        authenticatorData: bufToB64url(cred.response.authenticatorData),
        signature: bufToB64url(cred.response.signature)
      };
      return fetch('api/webauthn/login_finish.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.ok) { throw new Error(res.error || 'Не удалось войти'); }
      window.location.href = res.redirect || 'index.php';
    })
    .catch(function (err) {
      msgEl.style.color = 'var(--danger, red)';
      msgEl.textContent = err.message || 'Не удалось войти по отпечатку.';
    });
}
</script>
</body>
</html>
