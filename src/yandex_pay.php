<?php
/**
 * Яндекс Пэй / Сплит — Cash Register API для офлайн-точек (динамический
 * QR-код, генерируется на каждый заказ отдельно — не нужны физические
 * QR-таблички от Яндекса). Документация: https://pay.yandex.ru/docs/ru/qr-code/api
 *
 * ВАЖНО (283-ФЗ): при оплате в Сплит закон обязывает передавать состав
 * корзины (cart.items) — именно поэтому расширенная гарантия должна
 * попадать в корзину отдельной позицией, а не просто добавляться к
 * итоговой сумме.
 *
 * Требует настроек (settings.php → «Яндекс Пэй / Сплит»): ypay_merchant_id,
 * ypay_api_key, ypay_software_auth. Без них функции возвращают ok=false
 * с понятной причиной, никуда не стучатся.
 */

function yandex_pay_base_url(): string
{
    $sandbox = get_setting('ypay_sandbox') === '1';
    return $sandbox
        ? 'https://sandbox.pay.yandex.ru/api/merchant/cash-register'
        : 'https://pay.yandex.ru/api/merchant/cash-register';
}

function yandex_pay_configured(): bool
{
    return (get_setting('ypay_merchant_id') ?: '') !== ''
        && (get_setting('ypay_api_key') ?: '') !== ''
        && (get_setting('ypay_software_auth') ?: '') !== '';
}

/**
 * Общий HTTP-запрос к Cash Register API с нужными заголовками
 * авторизации. Возвращает распарсенный JSON-ответ (массив) или null,
 * если не удалось получить/распарсить ответ.
 */
function yandex_pay_request(string $method, string $path, ?array $body = null): ?array
{
    $apiKey = get_setting('ypay_api_key') ?: '';
    $softwareAuth = get_setting('ypay_software_auth') ?: '';

    $ch = curl_init(yandex_pay_base_url() . $path);
    $headers = [
        'Authorization: Api-Key ' . $apiKey,
        'Software-Authorization: ' . $softwareAuth,
        'Content-Type: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($ch);
    curl_close($ch);

    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Строит корзину для Яндекс Пэй из позиций заказа + (опционально)
 * расширенной гарантии. Формат — «базовая логика» (без unitPrice), раз
 * у нас цены обычно целые — см. extended_warranty_price() в
 * functions.php для расчёта суммы гарантии.
 */
function yandex_pay_build_cart(array $parts, float $extendedWarrantyPrice = 0.0): array
{
    $items = [];
    foreach ($parts as $i => $p) {
        $qty = (float) $p['qty'];
        $items[] = [
            'productId' => 'part-' . ($p['id'] ?? $i),
            'title'     => mb_substr($p['name'], 0, 100),
            'quantity'  => ['count' => (string) $qty],
            'total'     => (string) part_line_total($p),
        ];
    }
    if ($extendedWarrantyPrice > 0) {
        $items[] = [
            'productId' => 'extended-warranty',
            'title'     => 'Расширенная гарантия',
            'quantity'  => ['count' => '1'],
            'total'     => (string) $extendedWarrantyPrice,
        ];
    }
    $total = array_sum(array_map(fn($it) => (float) $it['total'], $items));
    return [
        'items' => $items,
        'total' => ['amount' => (string) $total],
    ];
}

/**
 * Создаёт заказ на оплату (динамический QR) — POST /orders/dynamic.
 * $repairId уходит в orderId, чтобы потом легко сопоставить с заказом
 * CRM. Возвращает ['ok'=>bool, 'orderId'=>?, 'paymentUrl'=>?, 'error'=>?].
 */
function yandex_pay_create_split_order(int $repairId, array $parts, float $extendedWarrantyPrice): array
{
    if (!yandex_pay_configured()) {
        return ['ok' => false, 'error' => 'Яндекс Пэй не настроен — заполните Merchant ID/API Key/Software-Authorization в Настройках.'];
    }
    if (!$parts) {
        return ['ok' => false, 'error' => 'В заказе нет ни одной позиции — нечего передавать в корзину.'];
    }

    $orderId = 'avior-' . $repairId . '-' . time();
    $cart = yandex_pay_build_cart($parts, $extendedWarrantyPrice);

    $response = yandex_pay_request('POST', '/v1/orders/dynamic', [
        'orderId'                 => $orderId,
        'cart'                    => $cart,
        'currencyCode'            => 'RUB',
        'availablePaymentMethods' => ['CARD', 'SPLIT'],
    ]);

    if (!$response || empty($response['paymentUrl'])) {
        $reason = $response['message'] ?? $response['reason'] ?? 'нет ответа от Яндекс Пэй';
        return ['ok' => false, 'error' => 'Не удалось создать заказ: ' . $reason];
    }

    return ['ok' => true, 'orderId' => $orderId, 'paymentUrl' => $response['paymentUrl']];
}

/**
 * Проверяет статус заказа — GET /orders/{orderId}. Возвращает
 * ['ok'=>bool, 'status'=>'PENDING'|'CAPTURED'|'FAILED'|?, 'error'=>?].
 */
function yandex_pay_get_order_status(string $orderId): array
{
    $response = yandex_pay_request('GET', '/v1/orders/' . urlencode($orderId));
    if (!$response || empty($response['paymentStatus'])) {
        return ['ok' => false, 'error' => 'Не удалось получить статус заказа.'];
    }
    return ['ok' => true, 'status' => $response['paymentStatus']];
}
