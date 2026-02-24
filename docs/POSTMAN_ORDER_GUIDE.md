# دليل اختبار Order API على Postman

## نظرة عامة على سيناريو الطلبات

### الخطوات الرئيسية:
1. **التسجيل أو تسجيل الدخول** → الحصول على Token
2. **عرض المنتجات** → الحصول على `product_id` المتاحة
3. **إنشاء طلب** → إرسال الطلب مع المنتجات والكميات

---

## الخطوة 1: تسجيل الدخول (Login)

**Method:** `POST`  
**URL:** `http://localhost:8000/api/v1/login`  
(غيّر الـ URL حسب إعداداتك المحلية)

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (raw JSON):**
```json
{
    "email": "user@example.com",
    "password": "password"
}
```

**الاستجابة الناجحة (200):**
```json
{
    "status": "success",
    "message": "Login successful",
    "data": {
        "user": { "id": 1, "name": "...", "email": "..." },
        "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
    }
}
```

**انسخ الـ `token`** — ستستخدمه في الخطوات التالية.

---

## الخطوة 2: عرض المنتجات (احصل على product_id)

**Method:** `GET`  
**URL:** `http://localhost:8000/api/v1/products`

**Headers:**
```
Accept: application/json
```

**لا تحتاج Body** — هذا الـ endpoint عام (بدون تسجيل دخول).

**الاستجابة الناجحة (200):**
```json
{
    "status": "success",
    "message": null,
    "data": [
        {
            "id": 1,
            "name": "Product Name",
            "price_in_cents": 1500,
            "stock": 10,
            ...
        }
    ]
}
```

**اختر `id` المنتجات** التي تريدها من الاستجابة.

---

## الخطوة 3: إنشاء الطلب (Place Order)

**Method:** `POST`  
**URL:** `http://localhost:8000/api/v1/orders`

**Headers:**
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer YOUR_TOKEN_HERE
```

**استبدل `YOUR_TOKEN_HERE`** بالـ token الذي حصلت عليه من Login.

**Body (raw JSON):**
```json
{
    "items": [
        {
            "product_id": 1,
            "quantity": 2
        },
        {
            "product_id": 2,
            "quantity": 1
        }
    ]
}
```

### قواعد التحقق (Validation):
- `items` **مطلوب** ومصفوفة غير فارغة
- كل عنصر يحتوي على:
  - `product_id`: رقم المنتج (موجود في جدول products)
  - `quantity`: الكمية (رقم صحيح ≥ 1)

---

## الاستجابة الناجحة (201 Created)

```json
{
    "status": "success",
    "message": "Order placed successfully",
    "data": {
        "id": 1,
        "order_number": "ORD-XXXXXXXXXX",
        "total_amount_cents": 3000,
        "total_quantity": 2,
        "status": "confirmed",
        "items": [
            {
                "id": 1,
                "product_id": 1,
                "quantity": 2,
                "unit_price_cents": 1500
            }
        ],
        "created_at": "...",
        "updated_at": "..."
    }
}
```

---

## أخطاء متوقعة

| الحالة | السبب |
|--------|--------|
| **401 Unauthorized** | لم ترسل الـ Token أو الـ Token غير صالح |
| **422 Validation Error** | بيانات غير صحيحة (مثلاً product_id غير موجود، أو quantity < 1) |
| **422** | المنتج غير متوفر (غير نشط `is_active = false`) |
| **422** | المخزون غير كافي للكمية المطلوبة |
| **429 Too Many Requests** | تجاوزت حد 10 طلبات في الدقيقة (rate limit) |

---

## إعداد Postman سريع

### 1. إنشاء Environment:
- اسم: `SoftigitalShop Local`
- متغيرات:
  - `base_url`: `http://localhost:8000`
  - `token`: (اتركه فارغاً، سيُملأ بعد Login)

### 2. إنشاء Request للتسجيل/الدخول:
- احفظ الـ token في Environment من خلال **Tests** في Postman:
```javascript
if (pm.response.code === 200) {
    const json = pm.response.json();
    pm.environment.set("token", json.data.token);
}
```

### 3. إنشاء Request للطلب:
- في Authorization اختر **Bearer Token** واستخدم `{{token}}`
- URL: `{{base_url}}/api/v1/orders`

---

## تشغيل السيرفر محلياً

```bash
php artisan serve
```

السيرفر سيعمل على: `http://127.0.0.1:8000`

---

## إذا لم يكن لديك مستخدمين أو منتجات

```bash
# إنشاء مستخدم عبر Tinker
php artisan tinker
>>> \App\Models\User::factory()->create(['email' => 'test@test.com', 'password' => bcrypt('password')]);

# أو تشغيل الـ Seeders إذا متوفرة
php artisan db:seed
```

يمكنك أيضاً إنشاء منتجات عبر Admin API:
- `POST /api/v1/admin/login` لتسجيل دخول الأدمن
- `POST /api/v1/admin/products` لإنشاء منتجات

---

## إعداد بوابات الدفع (Payment Gateways)

### التبديل بين Mock و Paymob عبر `.env`

أضف أو عدّل الأسطر التالية في ملف `.env`:

```env
# البوابة الافتراضية: mock أو paymob
PAYMENT_GATEWAY=mock

# إعدادات Paymob (مطلوبة فقط عند PAYMENT_GATEWAY=paymob)
PAYMOB_API_KEY=
PAYMOB_INTEGRATION_ID=
PAYMOB_IFRAME_ID=
PAYMOB_MERCHANT_ID=
PAYMOB_HMAC_SECRET=
PAYMOB_BASE_URL=https://accept.paymob.com/api
```

### استخدام Mock (للتطوير والاختبار)
```env
PAYMENT_GATEWAY=mock
```
- لا يحتاج أي إعدادات إضافية
- الدفع يُعتبر ناجحاً تلقائياً
- مناسب للاختبار على Postman

### استخدام Paymob (للإنتاج أو الاختبار الحقيقي)
```env
PAYMENT_GATEWAY=paymob
PAYMOB_API_KEY=your_api_key_from_paymob
PAYMOB_INTEGRATION_ID=your_integration_id
PAYMOB_IFRAME_ID=your_iframe_id
PAYMOB_MERCHANT_ID=your_merchant_id
PAYMOB_HMAC_SECRET=your_hmac_secret
```

- احصل على القيم من [لوحة تحكم Paymob](https://accept.paymob.com/)
- عند النجاح، الاستجابة تحتوي على `payment_url` لإكمال الدفع في iframe

---

## خطوات تجربة Paymob خطوة بخطوة

### الخطوة 1: إنشاء حساب والحصول على بيانات Paymob

1. ادخل على [accept.paymob.com](https://accept.paymob.com/)
2. سجّل دخول أو أنشئ حساب
3. من لوحة التحكم، اذهب إلى **Integrations** أو **API**
4. احفظ القيم التالية:
   - **API Key** → `PAYMOB_API_KEY`
   - **Integration ID** (للـ Card Payment) → `PAYMOB_INTEGRATION_ID`
   - **iFrame ID** → `PAYMOB_IFRAME_ID`
   - **Merchant ID** → `PAYMOB_MERCHANT_ID`

### الخطوة 2: تحديث ملف `.env`

```env
PAYMENT_GATEWAY=paymob
PAYMOB_API_KEY=ZXlKaGJHY2lPaUpJVXpJMU5pSXNJblI1Y0NJNklrcFhWQ0o5...
PAYMOB_INTEGRATION_ID=1234567
PAYMOB_IFRAME_ID=123456
PAYMOB_MERCHANT_ID=12345
```

(ضع القيم الحقيقية من حسابك مكان هذه الأرقام)

### الخطوة 3: مسح الـ Cache

```bash
php artisan config:clear
```

### الخطوة 4: التشغيل واختبار الطلب في Postman

1. **شغّل السيرفر** (إن لم يكن يعمل):
   ```bash
   php artisan serve
   ```

2. **Login** لاستخراج التوكن:
   - Method: `POST`
   - URL: `http://127.0.0.1:8000/api/v1/login`
   - Body (JSON):
   ```json
   {
       "email": "test@test.com",
       "password": "password"
   }
   ```
   - انسخ قيمة `token` من الاستجابة

3. **إنشاء طلب Order**:
   - Method: `POST`
   - URL: `http://127.0.0.1:8000/api/v1/orders`
   - Headers:
     - `Authorization: Bearer {التوكن_اللي_نسخته}`
     - `Content-Type: application/json`
   - Body (JSON):
   ```json
   {
       "items": [
           { "product_id": 1, "quantity": 2 }
       ]
   }
   ```

### الخطوة 5: ماذا تتوقع في الاستجابة؟

إذا كل شي مضبوط، ستستقبل شيء بهذا الشكل:

```json
{
    "status": true,
    "message": "Order placed successfully",
    "data": {
        "id": 1,
        "order_number": "ORD-XXXXXXXXXX",
        "total_amount_cents": 3000,
        "total_quantity": 2,
        "status": "confirmed",
        "payment_url": "https://accept.paymob.com/api/acceptance/iframes/123456?payment_token=xxx...",
        "items": [...]
    }
}
```

### الخطوة 6: إكمال الدفع في Paymob

- انسخ رابط `payment_url` من الاستجابة
- افتحه في المتصفح
- استخدم بطاقة تجريبية (إن وجدت) أو بطاقتك لإتمام الدفع

---

**ملاحظة:** إذا ظهر خطأ مثل `Paymob gateway is selected but not configured` تأكد أن كل القيم في `.env` صحيحة ومليانة.

