<?php
require __DIR__ . '/../src/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
<title>Вход — АВИОР CMS</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <header>
      <div class="brand">
        <h1>АВИОР</h1>
        <p>Внутренняя CMS сервиса</p>
      </div>
    </header>
    <div class="auth-body">
      <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <label class="field">Логин
          <input type="text" name="username" value="<?= e($username ?? '') ?>" autofocus required>
        </label>
        <label class="field">Пароль
          <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary">Войти</button>
      </form>
      <div class="auth-hint">Доступ только для сотрудников АВИОР.</div>
    </div>
  </div>
</div>
</body>
</html>
