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
}

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
      <div class="field full">
        <button type="submit" class="btn btn-primary">Сменить пароль</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
