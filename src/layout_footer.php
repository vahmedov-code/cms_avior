  </main>
  <footer class="legal">
    АВИОР · Можайское шоссе, 4к1, Москва ·
    Разработана <a href="https://ux.avior.moscow" target="_blank" rel="noopener">ux.avior.moscow</a>
    <?php $crmVersion = crm_version(); if ($crmVersion !== ''): ?>
      · версия <?= e($crmVersion) ?>
    <?php endif; ?>
  </footer>
</div>
<script>
/**
 * Клик по карточке типа устройства (см. render_device_type_picker() в
 * functions.php): подставляет значение в текстовое поле device_type.
 * Если выбрано «Другое» — поле очищается и открывается для ручного ввода.
 */
function selectDeviceType(radio, fieldId, isOther) {
  var field = document.getElementById(fieldId);
  if (!field) { return; }
  if (isOther) {
    field.value = '';
    field.readOnly = false;
    field.focus();
  } else {
    field.value = radio.value;
    field.readOnly = true;
  }
}

/**
 * Переключатель вида списка заказов (repairs.php) — плитки (по умолчанию
 * на мобильном, каждый заказ отдельным блоком) или список (компактная
 * таблица, со скроллом вбок на узком экране). Выбор запоминается в
 * localStorage, применяется на этой же странице при заходе — только
 * элементы с id="ordersTableCard" затрагиваются, остальные таблицы
 * (клиенты, сотрудники и т.д.) не трогает.
 */
function setOrdersView(mode) {
  var card = document.getElementById('ordersTableCard');
  if (!card) { return; }
  card.classList.toggle('list-view', mode === 'list');
  try { localStorage.setItem('avior-orders-view', mode); } catch (e) {}
  var tilesBtn = document.getElementById('viewTiles');
  var listBtn = document.getElementById('viewList');
  if (tilesBtn) { tilesBtn.classList.toggle('btn-primary', mode === 'tiles'); }
  if (listBtn) { listBtn.classList.toggle('btn-primary', mode === 'list'); }
}
document.addEventListener('DOMContentLoaded', function () {
  if (!document.getElementById('ordersTableCard')) { return; }
  var saved = null;
  try { saved = localStorage.getItem('avior-orders-view'); } catch (e) {}
  setOrdersView(saved === 'list' ? 'list' : 'tiles');
});

/**
 * Переключатель типа клиента (см. render_client_type_toggle() в
 * functions.php) — показывает/скрывает блок реквизитов компании
 * (#legalEntityFields) при выборе «Юридическое лицо».
 */
function toggleClientTypeFields(radio) {
  var block = document.getElementById('legalEntityFields');
  if (!block) { return; }
  block.style.display = radio.value === 'legal_entity' ? 'flex' : 'none';
}

/**
 * Раскрывающиеся группы плиток на панели (index.php) — клик по
 * родительской плитке разворачивает дочерние прямо на месте, без
 * перехода на другую страницу. Независимые друг от друга — открытие
 * одной группы не закрывает остальные. До 2 уровней вложенности
 * (Управление → Бухгалтерия → КУДиР/...).
 */
function toggleModuleGroup(trigger, groupId) {
  var el = document.getElementById(groupId);
  if (!el) { return; }
  el.classList.toggle('open');
  trigger.classList.toggle('expanded');
}

/**
 * Меню профиля в шапке (клик по имени) — «Настройки профиля» и «Выйти».
 * Клик, не наведение — hover не работает на тач-экранах, а основное
 * устройство здесь телефон.
 */
function toggleHeaderMenu(e) {
  e.stopPropagation();
  var menu = document.getElementById('headerProfileMenu');
  if (!menu) { return; }
  menu.classList.toggle('open');
}
document.addEventListener('click', function (e) {
  if (!e.target.closest('#headerProfileMenu') && !e.target.closest('.profile-menu-btn')) {
    var menu = document.getElementById('headerProfileMenu');
    if (menu) { menu.classList.remove('open'); }
  }
});
</script>
</body>
</html>
