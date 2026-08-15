<?php
/**
 * Одноразовая установка: создаёт первого администратора CMS.
 * После успешного создания сама блокирует себя (файл setup.lock),
 * повторный запуск невозможен. После использования файл можно
 * удалить с сервера вручную — он больше не нужен.
 */
require __DIR__ . '/../src/bootstrap.php';

$lockFile = __DIR__ . '/../config/setup.lock';

if (file_exists($lockFile)) {
    http_response_code(403);
    die('Установка уже выполнена. Файл setup.php можно удалить с сервера.');
}

$stmt = db()->query('SELECT COUNT(*) AS c FROM users');
if ((int) $stmt->fetch()['c'] > 0) {
    file_put_contents($lockFile, date('c'));
    http_response_code(403);
    die('Пользователи уже созданы. Установка заблокирована. Файл setup.php можно удалить.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $fullName = post('full_name');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($username) < 3) {
        $error = 'Логин должен быть не короче 3 символов.';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не короче 6 символов.';
    } elseif ($password !== $password2) {
        $error = 'Пароли не совпадают.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            'INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $hash, $fullName !== '' ? $fullName : $username, 'admin']);
        file_put_contents($lockFile, date('c'));
        flash_set('Администратор создан. Теперь можно войти.', 'success');
        redirect('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Первичная настройка — АВИОР CMS</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <header>
      <div class="brand">
        <h1>АВИОР</h1>
        <p>Первичная настройка CMS</p>
      </div>
    </header>
    <div class="auth-body">
      <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <label class="field">Логин
          <input type="text" name="username" required>
        </label>
        <label class="field">Имя
          <input type="text" name="full_name" placeholder="Как отображать в CMS">
        </label>
        <label class="field">Пароль
          <input type="password" name="password" minlength="6" required>
        </label>
        <label class="field">Повтор пароля
          <input type="password" name="password2" minlength="6" required>
        </label>
        <button type="submit" class="btn btn-primary">Создать администратора</button>
      </form>
      <div class="auth-hint">Эта страница сработает только один раз, затем заблокируется автоматически.</div>
    </div>
  </div>
</div>
</body>
</html>
