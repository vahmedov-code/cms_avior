  </main>
  <footer class="legal">АВИОР · Можайское шоссе, 4к1, Москва</footer>
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
</script>
</body>
</html>
