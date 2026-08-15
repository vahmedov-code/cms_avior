<?php
/**
 * Сотрудники — добавление/удаление учётных записей CMS, смена роли,
 * сброс пароля сотруднику. Доступно только администраторам.
 */
require __DIR__ . '/../src/bootstrap.php';
require_admin();

$me = current_user();
$error = '';

function admin_count(): int
{
    return (int) db()->query("SELECT COUNT(*) c FROM users WHERE role = 'admin'")->fetch()['c'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'add_employee') {
        $username = post('username');
        $fullName = post('full_name');
        $password = $_POST['password'] ?? '';
        $role = post('role') === 'admin' ? 'admin' : 'manager';

        if (strlen($username) < 3) {
            $error = 'Логин должен быть не короче 3 символов.';
        } elseif ($fullName === '') {
            $error = 'Укажите имя сотрудника.';
        } elseif (strlen($password) < 6) {
            $error = 'Пароль должен быть не короче 6 символов.';
        } else {
            $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Такой логин уже занят.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)')
                    ->execute([$username, $hash, $fullName, $role]);
                flash_set('Сотрудник «' . $fullName . '» добавлен.', 'success');
                redirect('employees.php');
            }
        }
    }

    if ($action === 'reset_password') {
        $targetId = (int) post('user_id');
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 6) {
            $error = 'Новый пароль должен быть не короче 6 символов.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $targetId]);
            flash_set('Пароль сотрудника сброшен.', 'success');
            redirect('employees.php');
        }
    }

    if ($action === 'update_role') {
        $targetId = (int) post('user_id');
        $newRole = post('role') === 'admin' ? 'admin' : 'manager';

        $stmt = db()->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if ($target && $target['role'] === 'admin' && $newRole !== 'admin' && admin_count() <= 1) {
            $error = 'Нельзя понизить последнего администратора — сначала назначьте другого.';
        } else {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetId]);
            if ($targetId === (int) $me['id']) {
                $_SESSION['user']['role'] = $newRole;
            }
            flash_set('Роль обновлена.', 'success');
            redirect('employees.php');
        }
    }

    if ($action === 'delete_employee') {
        $targetId = (int) post('user_id');

        if ($targetId === (int) $me['id']) {
            $error = 'Нельзя удалить свою же учётную запись.';
        } else {
            $stmt = db()->prepare('SELECT role, full_name FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();

            if (!$target) {
                $error = 'Сотрудник не найден.';
            } elseif ($target['role'] === 'admin' && admin_count() <= 1) {
                $error = 'Нельзя удалить последнего администратора.';
            } else {
                db()->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                flash_set('Сотрудник «' . $target['full_name'] . '» удалён.', 'success');
                redirect('employees.php');
            }
        }
    }
}

$employees = db()->query('SELECT * FROM users ORDER BY created_at')->fetchAll();

$pageTitle = 'Сотрудники';
$activeNav = 'employees';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="page-title">
  <h2>👥 Сотрудники</h2>
  <a href="index.php" class="btn btn-sm">← На панель</a>
</div>

<?php if ($error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="table-card" style="margin-bottom:24px;">
  <table>
    <thead>
      <tr><th>Имя</th><th>Логин</th><th>Роль</th><th>В системе с</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($employees as $emp): ?>
        <tr>
          <td data-label="Имя"><?= e($emp['full_name']) ?><?= (int) $emp['id'] === (int) $me['id'] ? ' <span style="color:var(--muted);font-size:12px;">(это вы)</span>' : '' ?></td>
          <td data-label="Логин"><?= e($emp['username']) ?></td>
          <td data-label="Роль">
            <form method="post" style="display:inline-flex;gap:6px;align-items:center;">
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="user_id" value="<?= (int) $emp['id'] ?>">
              <select name="role" onchange="this.form.submit()">
                <option value="manager" <?= $emp['role'] === 'manager' ? 'selected' : '' ?>>сотрудник</option>
                <option value="admin" <?= $emp['role'] === 'admin' ? 'selected' : '' ?>>администратор</option>
              </select>
            </form>
          </td>
          <td data-label="В системе с"><?= date('d.m.Y', strtotime($emp['created_at'])) ?></td>
          <td data-label="" style="white-space:nowrap;">
            <details style="display:inline-block;">
              <summary class="btn btn-sm" style="cursor:pointer;display:inline;">Сбросить пароль</summary>
              <form method="post" style="margin-top:8px;display:flex;gap:6px;">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?= (int) $emp['id'] ?>">
                <input type="password" name="new_password" placeholder="новый пароль" minlength="6" required style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;">
                <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
              </form>
            </details>
            <?php if ((int) $emp['id'] !== (int) $me['id']): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Удалить сотрудника «<?= e($emp['full_name']) ?>»? Отменить нельзя.');">
                <input type="hidden" name="action" value="delete_employee">
                <input type="hidden" name="user_id" value="<?= (int) $emp['id'] ?>">
                <button type="submit" class="btn btn-sm btn-warn">Удалить</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h3 style="color:var(--navy);font-size:16px;">Добавить сотрудника</h3>
<form method="post" class="form-grid" style="max-width:520px;">
  <input type="hidden" name="action" value="add_employee">
  <label class="field">Имя
    <input type="text" name="full_name" required>
  </label>
  <label class="field">Логин
    <input type="text" name="username" required>
  </label>
  <label class="field">Пароль
    <input type="password" name="password" minlength="6" required>
  </label>
  <label class="field">Роль
    <select name="role">
      <option value="manager">Сотрудник</option>
      <option value="admin">Администратор</option>
    </select>
  </label>
  <div class="field full">
    <button type="submit" class="btn btn-primary">+ Добавить сотрудника</button>
  </div>
</form>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
