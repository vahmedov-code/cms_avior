# API для мобильного приложения (Android / Gemini в Android Studio)

Этот файл — всё, что нужно скормить Gemini в Android Studio, чтобы он
собрал Android-приложение: клиенты, заказы, смена статусов. Можно
целиком вставить этот файл (или отдельные разделы) в чат Gemini как
контекст/техзадание.

## Базовый адрес

```
https://cms.avior.moscow/api/mobile/
```

Домен рабочий и не изменится. HTTPS обязателен.

## Аутентификация

Аутентификация по токену (не по сессии/cookie — так проще для мобильного
приложения). Пользователи те же, что и в веб-CMS (`username`/`password`,
которые выдал Вейс сотрудникам).

### Вход — получить токен

```
POST /api/mobile/auth.php
Content-Type: application/json

{ "username": "ivan", "password": "secret123", "device_label": "Иван, Samsung A54" }
```

Ответ `201`:
```json
{
  "ok": true,
  "token": "a1b2c3...64 hex-символа...",
  "user": { "id": 2, "username": "ivan", "full_name": "Иван Петров", "role": "manager" }
}
```

`device_label` необязателен, но полезен — если завести несколько
устройств, потом будет видно в БД, какой токен чей (таблица
`api_tokens`, поле `device_label`).

Токен нужно сохранить на устройстве (в `EncryptedSharedPreferences`,
не в обычные `SharedPreferences` — там пароли/токены хранить не стоит)
и передавать во **всех** остальных запросах в заголовке:

```
Authorization: Bearer <token>
```

(Если по какой-то причине заголовок Authorization не доходит до сервера
через прокси — API также принимает токен в заголовке `X-Api-Token` или
в query-параметре `?token=...` как запасной вариант.)

### Выход — отозвать токен

```
POST /api/mobile/logout.php
Authorization: Bearer <token>
```
```json
{ "ok": true }
```

## Формат ответов

Успех — всегда `{"ok": true, ...}` с HTTP 200/201.
Ошибка — всегда `{"ok": false, "error": "текст на русском"}` с HTTP
400/401/404/405/422/500. Приложению достаточно проверять поле `ok`.

## Справочники (для выпадающих списков)

```
GET /api/mobile/meta.php
Authorization: Bearer <token>
```
```json
{
  "ok": true,
  "statuses": ["принят", "диагностика", "согласование", "в ремонте", "готов", "выдан", "отказ"],
  "order_types": { "repair": "Ремонт", "pc_build": "Сборка ПК", "account_memo": "Памятка по аккаунту" },
  "client_sources": { "avito": "Авито", "yandex": "Яндекс", "2gis": "2ГИС", "google_maps": "Google Карты", "referral": "Сарафанное радио", "walkin": "С улицы" }
}
```

## Клиенты

### Поиск / список

```
GET /api/mobile/clients.php?q=Екатерина
GET /api/mobile/clients.php               (без q — последние 50)
GET /api/mobile/clients.php?id=5          (один клиент)
Authorization: Bearer <token>
```
```json
{ "ok": true, "clients": [
  { "id": 5, "full_name": "Екатерина", "phone": "+7 963 762-26-66", "email": null,
    "address": null, "notes": null, "source": "avito", "created_at": "2026-08-13 10:02:00" }
] }
```

### Создать клиента

```
POST /api/mobile/clients.php
Authorization: Bearer <token>
Content-Type: application/json

{ "full_name": "Екатерина", "phone": "+7 963 762-26-66", "source": "avito" }
```
Необязательные поля: `email`, `address`, `notes`. `source` — один из
ключей `client_sources` выше (или не передавать вовсе).

Ответ `201`: `{ "ok": true, "client": { ...полная запись... } }`.

## Заказы

### Список (с фильтрами)

```
GET /api/mobile/orders.php
GET /api/mobile/orders.php?status=в ремонте
GET /api/mobile/orders.php?type=repair&limit=20
Authorization: Bearer <token>
```
```json
{ "ok": true, "orders": [
  { "id": 12, "order_no": "26-012", "order_type": "repair", "status": "в ремонте",
    "device_type": "Смартфон", "device_model": "Poco M7", "created_at": "...", "updated_at": "...",
    "client_name": "Екатерина", "client_phone": "+7 963 762-26-66", "total": 6500,
    "receipt_ready": true, "public_token": "a1b2c3...64 hex-символа...",
    "receipt_url": "https://cms.avior.moscow/receipt_public.php?id=12&token=a1b2c3...",
    "report_url": "https://cms.avior.moscow/act_public.php?id=12&token=a1b2c3..." }
] }
```

`receipt_url`/`report_url` — прямые ссылки на печатные документы (квитанция
о приёмке / акт выполненных работ), уже готовые для открытия в браузере
приложения или для «поделиться» — это те же публичные страницы, по
которым отправляют документ клиенту в WhatsApp/Telegram/Email (без входа
в CMS). Адресуются по `id` заказа и уникальному `public_token` (колонка
`repairs.public_token`, генерируется один раз при создании заказа) —
никакого логина/пароля не требуется, и в самой ссылке не палится телефон
клиента. `receipt_url` — `null`, если квитанция ещё не оформлена
(`receipt_ready: false`). `report_url` — `null`, если в заказе пока нет
ни одной позиции (нечего вносить в акт). Оба поля также будут `null`,
если на сервере не настроен `site_url` в `config/config.php`.

### Деталь заказа (с позициями и историей статусов)

```
GET /api/mobile/orders.php?id=12
Authorization: Bearer <token>
```
```json
{ "ok": true, "order": {
  "id": 12, "order_no": "26-012", "order_type": "repair", "status": "в ремонте",
  "device_type": "Смартфон", "device_model": "Poco M7", "problem_description": "Битая матрица",
  "price_estimate": 6500, "prepayment": 6500, "client_name": "Екатерина", "client_phone": "...",
  "receipt_ready": true, "public_token": "a1b2c3...",
  "receipt_url": "https://cms.avior.moscow/receipt_public.php?id=12&token=a1b2c3...",
  "report_url": "https://cms.avior.moscow/act_public.php?id=12&token=a1b2c3...",
  "parts": [ { "id": 1, "category": "part", "name": "Матрица", "qty": 1, "price": 6500, "warranty": "нет" } ],
  "status_log": [ { "status": "в ремонте", "comment": null, "changed_at": "2026-08-14 09:00:00" } ]
} }
```

### Создать заказ (ремонт)

Через существующего клиента:
```json
POST /api/mobile/orders.php
{ "client_id": 5, "device_type": "Смартфон", "device_model": "Poco M7",
  "problem_description": "Битая матрица", "price_estimate": 6500 }
```

Или сразу с новым клиентом одним запросом:
```json
POST /api/mobile/orders.php
{ "new_client": { "full_name": "Екатерина", "phone": "+7 963 762-26-66", "source": "avito" },
  "device_type": "Смартфон", "problem_description": "Битая матрица" }
```

Обязательно только `device_type` и (`client_id` либо `new_client`).
Заказ всегда создаётся со статусом «принят» и типом `repair` — заказ
сразу появляется в общем списке «Заказы» в веб-CMS. (Создание «Сборки
ПК» и «Памятки по аккаунту» через API пока не реализовано — это на
будущее, см. «Возможные доработки» ниже.)

Ответ `201`: `{ "ok": true, "order": { ...созданный заказ... } }`.

## Смена статуса заказа

```
POST /api/mobile/update_status.php
Authorization: Bearer <token>
Content-Type: application/json

{ "id": 12, "status": "готов", "comment": "Матрица заменена" }
```
```json
{ "ok": true }
```

`status` — строго одно из значений из `meta.php.statuses`. `comment`
необязателен. Автор смены статуса определяется по токену (кто из
сотрудников залогинен в приложении) и попадает в историю заказа,
видную в веб-CMS.

## Проверка curl (быстрый тест перед тем, как писать код в Android Studio)

```bash
# Вход
curl -s -X POST https://cms.avior.moscow/api/mobile/auth.php \
  -H "Content-Type: application/json" \
  -d '{"username":"ВАШ_ЛОГИН","password":"ВАШ_ПАРОЛЬ"}'

# Дальше подставить токен из ответа
TOKEN="..."

curl -s https://cms.avior.moscow/api/mobile/meta.php \
  -H "Authorization: Bearer $TOKEN"

curl -s https://cms.avior.moscow/api/mobile/orders.php?limit=5 \
  -H "Authorization: Bearer $TOKEN"
```

---

## Готовый Kotlin-каркас (Retrofit) — можно вставить в проект как есть

Зависимости в `app/build.gradle.kts` (если ещё не добавлены):

```kotlin
implementation("com.squareup.retrofit2:retrofit:2.11.0")
implementation("com.squareup.retrofit2:converter-gson:2.11.0")
implementation("com.squareup.okhttp3:logging-interceptor:4.12.0")
implementation("androidx.security:security-crypto:1.1.0-alpha06") // EncryptedSharedPreferences
```

### Модели данных

```kotlin
data class ApiUser(val id: Int, val username: String, val full_name: String, val role: String)
data class AuthResponse(val ok: Boolean, val token: String?, val user: ApiUser?, val error: String?)

data class Client(
    val id: Int, val full_name: String, val phone: String,
    val email: String?, val address: String?, val notes: String?,
    val source: String?, val created_at: String
)
data class ClientsResponse(val ok: Boolean, val clients: List<Client>?, val error: String?)
data class ClientResponse(val ok: Boolean, val client: Client?, val error: String?)

data class OrderPart(
    val id: Int, val category: String, val name: String, val qty: Double, val price: Double,
    val warranty: String? = null
)
data class StatusLogEntry(val status: String, val comment: String?, val changed_at: String)

data class Order(
    val id: Int, val order_no: String, val order_type: String, val status: String,
    val device_type: String, val device_model: String?, val problem_description: String?,
    val price_estimate: Double?, val prepayment: Double?, val total: Double?,
    val client_name: String, val client_phone: String,
    val created_at: String?, val updated_at: String?,
    val receipt_ready: Boolean? = null,
    val public_token: String? = null,
    val receipt_url: String? = null,
    val report_url: String? = null,
    val parts: List<OrderPart>? = null,
    val status_log: List<StatusLogEntry>? = null
)
data class OrdersResponse(val ok: Boolean, val orders: List<Order>?, val error: String?)
data class OrderResponse(val ok: Boolean, val order: Order?, val error: String?)

data class MetaResponse(
    val ok: Boolean,
    val statuses: List<String>?,
    val order_types: Map<String, String>?,
    val client_sources: Map<String, String>?,
    val error: String?
)

data class SimpleOk(val ok: Boolean, val error: String?)

// Тела запросов
data class LoginRequest(val username: String, val password: String, val device_label: String? = null)
data class NewClientRequest(
    val full_name: String, val phone: String,
    val email: String? = null, val address: String? = null,
    val notes: String? = null, val source: String? = null
)
data class InlineNewClient(val full_name: String, val phone: String, val source: String? = null)
data class NewOrderRequest(
    val client_id: Int? = null,
    val new_client: InlineNewClient? = null,
    val device_type: String,
    val device_model: String? = null,
    val problem_description: String? = null,
    val price_estimate: Double? = null
)
data class UpdateStatusRequest(val id: Int, val status: String, val comment: String? = null)
```

### Retrofit-интерфейс

```kotlin
interface AviorApi {
    @POST("auth.php")
    suspend fun login(@Body body: LoginRequest): AuthResponse

    @POST("logout.php")
    suspend fun logout(): SimpleOk

    @GET("meta.php")
    suspend fun meta(): MetaResponse

    @GET("clients.php")
    suspend fun clients(@Query("q") q: String? = null): ClientsResponse

    @GET("clients.php")
    suspend fun clientById(@Query("id") id: Int): ClientResponse

    @POST("clients.php")
    suspend fun createClient(@Body body: NewClientRequest): ClientResponse

    @GET("orders.php")
    suspend fun orders(
        @Query("status") status: String? = null,
        @Query("type") type: String? = null,
        @Query("limit") limit: Int? = null
    ): OrdersResponse

    @GET("orders.php")
    suspend fun orderById(@Query("id") id: Int): OrderResponse

    @POST("orders.php")
    suspend fun createOrder(@Body body: NewOrderRequest): OrderResponse

    @POST("update_status.php")
    suspend fun updateStatus(@Body body: UpdateStatusRequest): SimpleOk
}
```

### Настройка Retrofit + подстановка токена во все запросы

```kotlin
object ApiClient {
    private const val BASE_URL = "https://cms.avior.moscow/api/mobile/"

    // tokenProvider читает токен из EncryptedSharedPreferences
    fun create(tokenProvider: () -> String?): AviorApi {
        val authInterceptor = Interceptor { chain ->
            val token = tokenProvider()
            val request = if (token != null) {
                chain.request().newBuilder()
                    .addHeader("Authorization", "Bearer $token")
                    .build()
            } else chain.request()
            chain.proceed(request)
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(authInterceptor)
            .addInterceptor(HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BODY })
            .build()

        return Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(AviorApi::class.java)
    }
}
```

Хранение токена — пример на `EncryptedSharedPreferences`:

```kotlin
val masterKey = MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build()
val prefs = EncryptedSharedPreferences.create(
    context, "avior_secure_prefs", masterKey,
    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
    EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
)
// сохранить: prefs.edit().putString("token", response.token).apply()
// прочитать: prefs.getString("token", null)
```

---

## Что попросить у Gemini в Android Studio (готовый текст промпта)

Можно вставить это сообщение прямо в чат Gemini внутри Android Studio,
приложив (или вставив содержимым) этот файл целиком как контекст:

> Собери Android-приложение на Kotlin (Jetpack Compose) для сотрудников
> сервиса АВИОР. Экраны: 1) Вход (логин/пароль → POST /auth.php, токен
> сохранить в EncryptedSharedPreferences), 2) Список заказов с фильтром
> по статусу (GET /orders.php), карточка заказа с деталями и историей
> статусов (GET /orders.php?id=), кнопка смены статуса (POST
> /update_status.php) с выбором из списка статусов через GET /meta.php,
> 3) Список/поиск клиентов (GET /clients.php?q=) и создание нового
> клиента (POST /clients.php) с выбором источника из GET /meta.php, 4)
> Создание нового заказа на ремонт — выбор существующего клиента или
> создание нового прямо в форме (POST /orders.php). Используй Retrofit
> + OkHttp + Gson, модели и интерфейс API — как в разделе «Готовый
> Kotlin-каркас» ниже. Базовый URL: https://cms.avior.moscow/api/mobile/.

## Возможные доработки (не реализовано сейчас)

- Создание заказов «Сборка ПК» / «Памятка по аккаунту» через API (сейчас
  только `repair`) — можно добавить по аналогии, если понадобится.
- Добавление/просмотр комплектующих и услуг заказа через API (сейчас
  только чтение внутри деталей заказа — `order.parts`).
- Push-уведомления при смене статуса.
- Ограничение действий по ролям (`role`) — сейчас все авторизованные
  пользователи API могут всё, как и в веб-CMS.
- Экран управления токенами/устройствами (отозвать чужой/старый токен)
  — сейчас это можно сделать только напрямую в БД (`DELETE FROM
  api_tokens WHERE id = ...`).
