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
</script>
</body>
</html>
