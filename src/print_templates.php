<?php
/**
 * Печатные документы заказа — квитанция о приёмке и акт выполненных работ.
 * Общая точка правды для вёрстки/стиля: используется и на странице CRM
 * (с формой редактирования/кнопками отправки), и на публичной ссылке
 * (доступ по id+public_token, без входа в CRM — для WhatsApp/Telegram/
 * Email и мобильного приложения; см. receipt_public.php/act_public.php).
 *
 * $publicMode = true  → без кнопок «Изменить»/«Открыть в CRM»/отправки,
 *                        только печать (это уже сама «отправленная» ссылка).
 * $publicMode = false → полный набор действий (используется внутри CRM).
 */

/** Квитанция о приёмке устройства в ремонт — 2 экземпляра на листе. */
function render_receipt_page(array $repair, bool $publicMode = false): string
{
    $id = (int) $repair['id'];
    $company = company_info();
    $docDate = date('d.m.Y', strtotime($repair['created_at']));
    $deadlineStr = $repair['deadline_date'] ? date('d.m.Y', strtotime($repair['deadline_date'])) : '—';

    $statusUrl = public_site_url('order_status.php?order_no=' . urlencode($repair['order_no']) . '&phone=' . urlencode($repair['client_phone']));
    $qrSrc = $statusUrl ? 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&margin=0&data=' . urlencode($statusUrl) : null;

    $publicUrl = !empty($repair['public_token'])
        ? public_site_url('receipt_public.php?id=' . $id . '&token=' . urlencode($repair['public_token']))
        : public_site_url('receipt_public.php?order_no=' . urlencode($repair['order_no']) . '&phone=' . urlencode($repair['client_phone']));
    $shareLinks = ($publicUrl && !$publicMode) ? build_share_links(
        $publicUrl,
        'Здравствуйте, ' . $repair['client_name'] . '! Ваша квитанция о приёмке по заказу ' . $repair['order_no'] . ':',
        'Квитанция ' . $repair['order_no'] . ' — ' . $company['name'],
        $repair['client_phone'],
        null
    ) : null;

    $clientLine = client_display_line($repair);

    ob_start();
    for ($copy = 1; $copy <= 2; $copy++): ?>
      <div class="copy">
        <div class="head-row">
          <div class="head-fields">
            <h1 class="doc-title">Квитанция № <?= e($repair['order_no']) ?> от <?= e($docDate) ?></h1>
            <p class="field"><strong>Исполнитель:</strong> <?= e(executor_display_name($repair)) ?></p>
            <p class="field"><strong>Адрес:</strong> <?= e($company['address']) ?></p>
            <p class="field"><strong>Телефон:</strong> <?= e($company['phone']) ?></p>
            <p class="field"><strong>Заказчик:</strong> <?= $clientLine ?></p>
            <p class="field"><strong>Телефон:</strong> <?= e($repair['client_phone']) ?></p>
          </div>
          <?php if ($qrSrc): ?>
          <div class="qr-block">
            <a href="<?= e($statusUrl) ?>" target="_blank" rel="noopener">
              <img src="<?= e($qrSrc) ?>" width="90" height="90" alt="QR — статус заказа" title="Текущий статус: <?= e($repair['status']) ?>">
            </a>
            <div class="qr-caption">статус заказа</div>
          </div>
          <?php endif; ?>
        </div>

        <p class="field"><strong>Марка/модель:</strong> <?= e($repair['device_type']) ?><?= $repair['device_model'] ? ' ' . e($repair['device_model']) : '' ?><?= $repair['device_serial'] ? ' (' . e($repair['device_serial']) . ')' : '' ?></p>
        <p class="field"><strong>Комплектация:</strong> <?= $repair['device_complete'] ? e($repair['device_complete']) : '' ?></p>
        <p class="field"><strong>Внешний вид:</strong> <?= $repair['device_condition'] ? e($repair['device_condition']) : '' ?></p>
        <p class="field"><strong>Причина ремонта со слов заказчика:</strong> <?= $repair['problem_description'] ? nl2br(e($repair['problem_description'])) : '' ?></p>
        <p class="field"><strong>Предоплата:</strong> <?= money_plain((float) $repair['prepayment']) ?></p>
        <p class="field"><strong>Ориентировочная стоимость ремонта:</strong> <?= money_plain((float) $repair['price_estimate']) ?></p>
        <p class="field"><strong>Ориентировочная дата готовности:</strong> <?= $repair['deadline_date'] ? e($deadlineStr) : '' ?></p>
        <p class="field"><strong>Примечание:</strong> <?= $repair['receipt_note'] ? nl2br(e($repair['receipt_note'])) : '' ?></p>

        <ol class="terms">
          <li>Технический центр не несёт ответственности за возможную потерю данных в памяти устройства, связанную с заменой плат, установкой программного обеспечения, заменой носителя информации.</li>
          <li>Заказчик принимает на себя риск возможной полной или частичной утраты работоспособности устройства в процессе ремонта (тепловой обработки), в случае грубых нарушений пользователем условий эксплуатации, наличий следов попадания токопроводящей жидкости (коррозии), либо механических повреждений.</li>
          <li>На восстановленные после попадания жидкости на устройство гарантия не распространяется и не продлевается.</li>
          <li>Срок бесплатного хранения устройства составляет 30 дней с момента приёма его в ремонт. В случае, если по истечении указанного срока клиентом не заявлено требование о выдаче устройства, оно принимается на ответственное хранение. Стоимость услуг по ответственному хранению составляет ___ руб в сутки. Максимальный срок ответственного хранения составляет 30 дней. В случае, если в течение указанного срока Клиент не требует возврата устройства (либо с Клиентом не представляется возможным связаться по указанному в квитанции телефону), устройство утилизируется без компенсации его стоимости клиенту.</li>
          <li>В случае отказа заказчика от ремонта устройства стоимость диагностики неисправности платная.</li>
          <li>В случае утери квитанции, устройство выдаётся по предъявлению паспорта на имя заказчика.</li>
        </ol>

        <div class="sign-row">
          <span>Исполнитель: ___________ / <?= e($repair['manager_name'] ?? '') ?>/</span>
          <span>________________ / <?= e($repair['client_name']) ?>/</span>
        </div>
        <div class="sign-note">с условием гарантии ознакомлен и согласен</div>
      </div>
      <?php if ($copy === 1): ?><div class="cut-line">✂ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─</div><?php endif;
    endfor;
    $copiesHtml = ob_get_clean();

    $title = 'Квитанция ' . $repair['order_no'] . ' — ' . $repair['client_name'];
    $editUrl = 'repair_receipt.php?id=' . $id . '&edit=1';
    $cmsUrl = 'repair_view.php?id=' . $id;

    return render_print_document_shell($title, $copiesHtml, $publicMode, $editUrl, $cmsUrl, $shareLinks);
}

/** Акт сдачи-приёмки выполненных работ — 1 экземпляр (по образцу Вейса). */
function render_act_page(array $repair, array $parts, bool $publicMode = false): string
{
    $id = (int) $repair['id'];
    $company = company_info();
    $docDate = date('d.m.Y');

    $partsTotal = 0.0;
    foreach ($parts as $p) {
        $partsTotal += (float) $p['qty'] * (float) $p['price'];
    }
    $discount = 0.0;
    $total = $partsTotal - $discount;

    $statusUrl = public_site_url('order_status.php?order_no=' . urlencode($repair['order_no']) . '&phone=' . urlencode($repair['client_phone']));
    $qrSrc = $statusUrl ? 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&data=' . urlencode($statusUrl) : null;

    $publicUrl = !empty($repair['public_token'])
        ? public_site_url('act_public.php?id=' . $id . '&token=' . urlencode($repair['public_token']))
        : public_site_url('act_public.php?order_no=' . urlencode($repair['order_no']) . '&phone=' . urlencode($repair['client_phone']));
    $shareLinks = ($publicUrl && !$publicMode) ? build_share_links(
        $publicUrl,
        'Здравствуйте, ' . $repair['client_name'] . '! Заказ ' . $repair['order_no'] . ' выполнен, акт выполненных работ:',
        'Акт выполненных работ ' . $repair['order_no'] . ' — ' . $company['name'],
        $repair['client_phone'],
        null
    ) : null;

    $clientLine = client_display_line($repair);

    ob_start(); ?>
      <div class="copy">
        <div class="head-row">
          <div class="head-fields">
            <h1 class="doc-title">Акт сдачи-приёмки выполненных работ (оказанных услуг)<br>№ <?= e($repair['order_no']) ?> от <?= e($docDate) ?></h1>
            <p class="field"><strong>Исполнитель:</strong> <?= e(executor_display_name($repair)) ?></p>
            <p class="field"><strong>Адрес:</strong> <?= e($company['address']) ?></p>
            <p class="field"><strong>Телефон:</strong> <?= e($company['phone']) ?></p>
            <p class="field"><strong>Заказчик:</strong> <?= $clientLine ?></p>
            <p class="field"><strong>Устройство:</strong> <?= e($repair['device_type']) ?><?= $repair['device_model'] ? ' ' . e($repair['device_model']) : '' ?><?= $repair['device_serial'] ? ' (' . e($repair['device_serial']) . ')' : '' ?></p>
          </div>
          <?php if ($qrSrc): ?>
          <div class="qr-block">
            <a href="<?= e($statusUrl) ?>" target="_blank" rel="noopener">
              <img src="<?= e($qrSrc) ?>" width="90" height="90" alt="QR — статус заказа" title="Текущий статус: <?= e($repair['status']) ?>">
            </a>
            <div class="qr-caption">статус заказа</div>
          </div>
          <?php endif; ?>
        </div>

        <table class="items-table">
          <thead>
            <tr><th>№</th><th>Наименование товаров и услуг</th><th>Гар-тия</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr>
          </thead>
          <tbody>
            <?php if (!$parts): ?>
              <tr><td colspan="6" style="text-align:center;color:#666;">Позиции не добавлены</td></tr>
            <?php endif; ?>
            <?php foreach ($parts as $i => $p): ?>
              <tr>
                <td><?= (int) $i + 1 ?></td>
                <td><?= e($p['name']) ?></td>
                <td><?= $p['warranty'] ? e($p['warranty']) : 'нет' ?></td>
                <td><?= rtrim(rtrim((string) (float) $p['qty'], '0'), '.') ?> шт.</td>
                <td><?= money_plain((float) $p['price']) ?></td>
                <td><?= money_plain((float) $p['qty'] * (float) $p['price']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <table class="totals-table">
          <tr><td class="label">Сумма чека:</td><td><?= money_plain($partsTotal) ?></td></tr>
          <tr><td class="label">Скидка:</td><td><?= money_plain($discount) ?></td></tr>
          <tr><td class="label"><strong>Итого:</strong></td><td><strong><?= money_plain($total) ?></strong></td></tr>
        </table>

        <p class="field">Всего наименований <?= count($parts) ?>, на сумму <?= money_plain($total) ?> руб</p>
        <p class="field sum-words"><?= e(money_in_words_rub($total)) ?></p>

        <p class="field" style="margin-top:14px;"><strong>Вердикт:</strong></p>
        <p class="field">Вышеперечисленные услуги выполнены полностью и в срок. Заказчик претензий по объёму, качеству и срокам оказания услуг не имеет.</p>

        <div class="sign-row" style="margin-top:24px;">
          <span>Исполнитель: ___________ / <?= e($repair['manager_name'] ?? '') ?>/</span>
          <span>________________ / <?= e($repair['client_name']) ?>/</span>
        </div>
      </div>
    <?php
    $copyHtml = ob_get_clean();

    $title = 'Акт выполненных работ ' . $repair['order_no'] . ' — ' . $repair['client_name'];
    $editUrl = null; // акт полностью формируется из данных заказа, отдельной формы редактирования нет
    $cmsUrl = 'repair_view.php?id=' . $id;

    return render_print_document_shell($title, $copyHtml, $publicMode, $editUrl, $cmsUrl, $shareLinks, true);
}

/**
 * Общая HTML-обёртка печатного документа (шрифты/размеры под образец
 * LiveSklad, который прислал Вейс) — используется и квитанцией, и актом.
 */
/**
 * Счёт на оплату — только для клиентов-юрлиц (безналичная оплата по
 * реквизитам). По образцу, который прислал Вейс (Счета.pdf): банковский
 * блок получателя сверху, Поставщик/Покупатель с ИНН/КПП, таблица позиций
 * со скидкой (по позициям скидка не ведётся — всегда 0.00, как и в акте),
 * «Без НДС» (ИП/ООО на упрощёнке/патенте), сумма прописью.
 *
 * Номер счёта — тот же order_no заказа (не отдельная нумерация): проще
 * и без риска коллизий, которые уже ловили на генераторе номеров заказов.
 */
function render_invoice_page(array $repair, array $parts, bool $publicMode = false): string
{
    $id = (int) $repair['id'];
    $company = company_info();
    $docDate = date('d.m.Y');

    $partsTotal = 0.0;
    foreach ($parts as $p) {
        $partsTotal += (float) $p['qty'] * (float) $p['price'];
    }
    $discount = 0.0;
    $total = $partsTotal - $discount;

    $clientLine = client_display_line($repair);
    $bankInnKpp = trim($company['inn'] . ($company['kpp'] !== '' ? ' / ' . $company['kpp'] : ''));

    $publicUrl = !empty($repair['public_token'])
        ? public_site_url('invoice_public.php?id=' . $id . '&token=' . urlencode($repair['public_token']))
        : null;
    $shareLinks = ($publicUrl && !$publicMode) ? build_share_links(
        $publicUrl,
        'Здравствуйте, ' . $repair['client_name'] . '! Счёт на оплату по заказу ' . $repair['order_no'] . ':',
        'Счёт на оплату ' . $repair['order_no'] . ' — ' . $company['name'],
        $repair['client_phone'],
        null
    ) : null;

    ob_start(); ?>
      <div class="copy">
        <table class="bank-details">
          <tr>
            <td class="label" rowspan="2">Банк получателя</td>
            <td class="value" rowspan="2"><?= e($company['bank_name']) ?></td>
            <td class="label">БИК</td>
            <td class="value"><?= e($company['bank_bik']) ?></td>
          </tr>
          <tr>
            <td class="label">Сч. №</td>
            <td class="value"><?= e($company['bank_corr_account']) ?></td>
          </tr>
          <tr>
            <td class="label" rowspan="2">Получатель</td>
            <td class="value" rowspan="2"><?= e($company['executor_name']) ?></td>
            <td class="label">ИНН/КПП</td>
            <td class="value"><?= e($bankInnKpp) ?></td>
          </tr>
          <tr>
            <td class="label">Сч. №</td>
            <td class="value"><?= e($company['bank_account']) ?></td>
          </tr>
        </table>

        <h1 class="doc-title" style="margin-top:16px;">Счёт на оплату № <?= e($repair['order_no']) ?> от <?= e($docDate) ?></h1>
        <p class="field"><strong>Поставщик:</strong> <?= e($company['executor_name']) ?> ИНН <?= e($company['inn']) ?></p>
        <p class="field"><strong>Покупатель:</strong> <?= $clientLine ?></p>

        <table class="items-table">
          <thead>
            <tr><th>№</th><th>Товары (работы, услуги)</th><th>Кол-во</th><th>Ед.</th><th>Цена</th><th>Скидка</th><th>Сумма</th></tr>
          </thead>
          <tbody>
            <?php if (!$parts): ?>
              <tr><td colspan="7" style="text-align:center;color:#666;">Позиции не добавлены</td></tr>
            <?php endif; ?>
            <?php foreach ($parts as $i => $p): ?>
              <tr>
                <td><?= (int) $i + 1 ?></td>
                <td><?= e($p['name']) ?></td>
                <td><?= rtrim(rtrim((string) (float) $p['qty'], '0'), '.') ?></td>
                <td>шт.</td>
                <td><?= money_plain((float) $p['price']) ?></td>
                <td><?= money_plain(0) ?></td>
                <td><?= money_plain((float) $p['qty'] * (float) $p['price']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <table class="totals-table">
          <tr><td class="label">Итого без учёта скидки:</td><td><?= money_plain($partsTotal) ?></td></tr>
          <tr><td class="label">Сумма скидки:</td><td><?= money_plain($discount) ?></td></tr>
          <tr><td class="label"><strong>Итого к оплате:</strong></td><td><strong><?= money_plain($total) ?></strong></td></tr>
          <tr><td class="label">В том числе НДС:</td><td>Без НДС</td></tr>
        </table>

        <p class="field sum-words">Всего к оплате: <?= e(money_in_words_rub($total)) ?></p>

        <div class="sign-row" style="margin-top:28px;">
          <span>Поставщик ___________________ (подпись) ___________________ (расшифровка подписи)</span>
        </div>
      </div>
    <?php
    $copyHtml = ob_get_clean();

    $title = 'Счёт на оплату ' . $repair['order_no'] . ' — ' . $repair['client_name'];
    $cmsUrl = 'repair_view.php?id=' . $id;

    return render_print_document_shell($title, $copyHtml, $publicMode, null, $cmsUrl, $shareLinks, true);
}

function render_print_document_shell(
    string $title,
    string $bodyHtml,
    bool $publicMode,
    ?string $editUrl,
    ?string $cmsUrl,
    ?array $shareLinks,
    bool $isAct = false
): string {
    // Извлекаем чистый publicUrl обратно из уже готовой telegram-ссылки
    // (там он есть в параметре url= в urlencoded виде) — чтобы не менять
    // сигнатуру функции и не трогать все места вызова (receipt/act/invoice).
    $rawShareUrl = '';
    if (!empty($shareLinks['telegram'])) {
        $q = parse_url($shareLinks['telegram'], PHP_URL_QUERY);
        parse_str($q ?? '', $qs);
        $rawShareUrl = $qs['url'] ?? '';
    }

    ob_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<style>
  :root{ --navy:#152a4e; --border:#dde3ec; --muted:#6b7385; }
  *{box-sizing:border-box;}
  body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f6fa;color:#111;padding:24px;}
  .page{max-width:780px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 14px rgba(20,30,60,.08);padding:26px 30px;}

  /* Стиль печатной формы — под образцы квитанции/акта ЛайвСклад: чёрный
     текст, без цветных акцентов, поля списком «жирная подпись: значение». */
  .copy{padding:10px 0;font-size:13px;line-height:1.5;color:#111;}
  .head-row{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;}
  .head-fields{flex:1;}
  .doc-title{font-size:19px;font-weight:700;margin:0 0 12px;color:#111;line-height:1.3;}
  .field{margin:2px 0;}
  .field strong{font-weight:700;}
  .qr-block{text-align:center;flex-shrink:0;}
  .qr-block img{display:block;border:1px solid var(--border);}
  .qr-caption{font-size:9px;color:var(--muted);margin-top:2px;letter-spacing:.3px;}
  .terms{margin:14px 0 12px;padding-left:18px;font-size:11px;color:#222;line-height:1.5;}
  .terms li{margin-bottom:5px;}
  .sign-row{font-size:12.5px;margin-top:18px;display:flex;justify-content:space-between;gap:20px;}
  .sign-note{color:#444;font-size:11.5px;text-align:right;margin-top:2px;}
  .cut-line{text-align:center;color:var(--muted);font-size:11px;margin:16px 0;letter-spacing:1px;}

  .items-table{width:100%;border-collapse:collapse;margin:14px 0;font-size:12px;}
  .items-table th, .items-table td{border:1px solid #999;padding:5px 7px;text-align:left;}
  .items-table th{background:#f2f2f2;font-weight:700;}
  .items-table td:first-child, .items-table th:first-child{text-align:center;width:28px;}
  .items-table td:nth-child(3), .items-table th:nth-child(3){text-align:center;width:70px;}
  .items-table td:nth-child(4), .items-table th:nth-child(4){text-align:center;width:60px;}
  .items-table td:nth-child(5), .items-table td:nth-child(6),
  .items-table th:nth-child(5), .items-table th:nth-child(6){text-align:right;width:100px;}

  .totals-table{margin-left:auto;margin-bottom:14px;font-size:12.5px;}
  .totals-table td{padding:2px 8px;}
  .totals-table td.label{color:#444;}
  .totals-table td:last-child{text-align:right;min-width:100px;}

  .sum-words{font-weight:700;}

  .bank-details{border-collapse:collapse;font-size:11.5px;margin-bottom:6px;}
  .bank-details td{border:1px solid #999;padding:3px 8px;}
  .bank-details td.label{background:#f2f2f2;color:#444;white-space:nowrap;}
  .bank-details td.value{font-weight:700;}

  .actions{max-width:780px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap;}
  .btn{padding:10px 16px;border-radius:6px;border:1px solid var(--border);background:#fff;cursor:pointer;font-size:14px;text-decoration:none;color:#1c2436;}
  .btn-primary{background:var(--navy);color:#fff;border-color:var(--navy);}
  @media print{
    body{background:#fff;padding:0;}
    .page{box-shadow:none;border-radius:0;max-width:100%;padding:0 8mm;}
    .actions{display:none !important;}
  }
</style>
</head>
<body>
<div class="page">
  <?= $bodyHtml ?>
</div>
<div class="actions">
  <button class="btn btn-primary" onclick="window.print()">🖨 Печать</button>
  <?php if (!$publicMode): ?>
    <?php if ($editUrl): ?><a class="btn" href="<?= e($editUrl) ?>">✎ Изменить данные</a><?php endif; ?>
    <?php if ($shareLinks): ?>
      <a class="btn" href="<?= e($shareLinks['whatsapp']) ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
      <a class="btn" href="<?= e($shareLinks['telegram']) ?>" target="_blank" rel="noopener">✈️ Telegram</a>
      <a class="btn" href="<?= e($shareLinks['email']) ?>">📧 Email</a>
      <?php if ($rawShareUrl !== ''): ?>
        <button type="button" class="btn" id="copyLinkBtn" onclick="copyDocLink(this, <?= json_encode($rawShareUrl) ?>)">📋 Скопировать ссылку</button>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($cmsUrl): ?><a class="btn" href="<?= e($cmsUrl) ?>">Открыть заказ в CRM →</a><?php endif; ?>
  <?php endif; ?>
</div>
<script>
/**
 * Копирует ссылку на документ в буфер обмена — запасной вариант, когда
 * на устройстве не настроено почтовое приложение и mailto: ничего не
 * делает (браузер просто остаётся на месте). Работает всегда, независимо
 * от того, что установлено на телефоне/компьютере — ссылку потом можно
 * вставить в любой мессенджер или письмо вручную.
 */
function copyDocLink(btn, url) {
  var done = function () {
    var original = btn.textContent;
    btn.textContent = '✓ Скопировано';
    setTimeout(function () { btn.textContent = original; }, 2000);
  };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(done).catch(function () { legacyCopy(url, done); });
  } else {
    legacyCopy(url, done);
  }
}
function legacyCopy(text, done) {
  var ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.focus();
  ta.select();
  try { document.execCommand('copy'); done(); } catch (e) {}
  document.body.removeChild(ta);
}
</script>
</body>
</html>
    <?php
    return ob_get_clean();
}
