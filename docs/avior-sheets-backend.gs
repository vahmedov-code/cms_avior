/**
 * АВИОР — бэкенд базы комплектующих на Google Sheets.
 *
 * УСТАНОВКА:
 * 1. Создайте новую Google-таблицу (sheets.google.com -> Пустой файл).
 * 2. Меню "Расширения" -> "Apps Script".
 * 3. Удалите весь код по умолчанию (function myFunction(){}) и вставьте
 *    вместо него содержимое этого файла целиком.
 * 4. Нажмите "Сохранить" (значок дискеты), назовите проект, например "Avior Backend".
 * 5. Нажмите "Развернуть" -> "Новое развёртывание".
 *    - Тип: "Веб-приложение".
 *    - Описание: любое.
 *    - Выполнять как: "Я (ваш email)".
 *    - У кого есть доступ: "Все" (Anyone).
 * 6. Нажмите "Развернуть". Google попросит авторизовать доступ — разрешите
 *    (появится предупреждение "Google не проверил это приложение" —
 *    это нормально, т.к. скрипт ваш собственный; нажмите "Дополнительно" ->
 *    "Перейти к проекту (небезопасно)" -> "Разрешить").
 * 7. Скопируйте выданный "URL веб-приложения" (заканчивается на /exec).
 * 8. В форме сметы АВИОР нажмите "🔗 Google Sheets", вставьте этот URL
 *    в поле и нажмите "Сохранить и подключить".
 *
 * После этого лист "Parts" в таблице создастся автоматически при первом
 * обращении и будет хранить список: Название | Цена.
 *
 * Если вы позже измените код скрипта — нужно сделать "Развернуть" ->
 * "Управление развёртываниями" -> редактировать -> выбрать "Новая версия" ->
 * "Развернуть", иначе форма продолжит обращаться к старой версии кода.
 */

var SHEET_NAME = "Parts";

function getSheet_() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(SHEET_NAME);
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
    sheet.appendRow(["Название", "Цена"]);
    sheet.setFrozenRows(1);
  }
  return sheet;
}

function jsonOut_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

// Чтение всей базы комплектующих: GET <url>
function doGet(e) {
  var sheet = getSheet_();
  var data = sheet.getDataRange().getValues();
  var parts = [];
  for (var i = 1; i < data.length; i++) {
    var name = data[i][0];
    if (name) {
      parts.push({ name: String(name), price: Number(data[i][1]) || 0 });
    }
  }
  return jsonOut_({ ok: true, parts: parts });
}

// Запись: POST <url> с телом {"action":"upsert"|"delete"|"clearAll", ...}
function doPost(e) {
  try {
    var payload = JSON.parse(e.postData.contents);
    var sheet = getSheet_();
    var action = payload.action;

    if (action === "upsert") {
      var name = String(payload.name || "").trim();
      var price = Number(payload.price) || 0;
      if (!name) return jsonOut_({ ok: false, error: "empty name" });

      var data = sheet.getDataRange().getValues();
      var rowIndex = -1;
      for (var i = 1; i < data.length; i++) {
        if (String(data[i][0]).toLowerCase() === name.toLowerCase()) {
          rowIndex = i + 1;
          break;
        }
      }
      if (rowIndex > 0) {
        sheet.getRange(rowIndex, 2).setValue(price);
      } else {
        sheet.appendRow([name, price]);
      }
      return jsonOut_({ ok: true });
    }

    if (action === "delete") {
      var delName = String(payload.name || "").trim().toLowerCase();
      var data2 = sheet.getDataRange().getValues();
      for (var j = 1; j < data2.length; j++) {
        if (String(data2[j][0]).toLowerCase() === delName) {
          sheet.deleteRow(j + 1);
          break;
        }
      }
      return jsonOut_({ ok: true });
    }

    if (action === "clearAll") {
      var lastRow = sheet.getLastRow();
      if (lastRow > 1) {
        sheet.getRange(2, 1, lastRow - 1, 2).clearContent();
      }
      return jsonOut_({ ok: true });
    }

    return jsonOut_({ ok: false, error: "unknown action" });
  } catch (err) {
    return jsonOut_({ ok: false, error: String(err) });
  }
}
