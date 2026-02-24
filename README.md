# SoftigitalShop — E-Commerce Backend API

A production-ready Laravel 12 backend for an e-commerce platform, built with clean architecture, SOLID design patterns, and a service-layer approach. Supports Mock and Paymob payment gateways, order lifecycle management, automated archiving, and Swagger API documentation.

---

## Setup & Installation

### Prerequisites

- PHP 8.2+
- Composer
- SQLite (default) or MySQL/PostgreSQL
- Node.js & NPM (for asset compilation)

### Quick Start

```bash
git clone <repository-url> softigital-shop
cd softigital-shop

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

npm install
npm run build
```

### Running the Application

```bash
php artisan serve
```

For full development (server + queue + logs + Vite):

```bash
composer dev
```

Or run separately:

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
```

- **Queue worker** — Required for fulfillment notifications and async jobs
- **Scheduler** — Runs `orders:archive` daily at 02:00 to archive old orders

### Default Credentials

| Role     | Email                    | Password |
|----------|--------------------------|----------|
| Customer | customer@softigital.com  | password |
| Admin    | admin@softigital.com     | password |

### Running Tests

```bash
php artisan test
```

### Running PHPStan

```bash
vendor/bin/phpstan analyse
```

---

## High-Level Design

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           API Layer (HTTP)                               │
│  Controllers → FormRequests → Resources → ApiResponse                    │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         Service Layer                                    │
│  AuthService | OrderService | ProductService | MediaService | Fulfillment │
│  PaymentGatewayManager → MockPaymentGateway | PaymobPaymentGateway        │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         Data Layer                                       │
│  Models: User, Admin, Product, Order, OrderItem, Media                   │
│  Enums: OrderStatus, PaymentStatus                                       │
└─────────────────────────────────────────────────────────────────────────┘
```

### Design Patterns

| Pattern                | Implementation                                    | Purpose                                                  |
|------------------------|---------------------------------------------------|----------------------------------------------------------|
| **Service Layer**      | `App\Services\*`                                  | Business logic outside controllers                       |
| **Strategy**           | `PaymentGatewayInterface` + Mock/Paymob gateways   | Swap payment providers without changing order flow       |
| **Polymorphic Media**  | `Media` model with `mediable` morphs               | One table for images (products, users, etc.)             |
| **Global Scope**       | `Order` model `active` scope                       | Transparent archiving; non-archived by default           |
| **Custom Validation**  | `OrderStatusTransition` rule                       | Encapsulate status transition logic                      |
| **Observer-style**     | `NotifyFulfillmentServiceJob`                      | Decoupled fulfillment notification from order creation   |

### Project Structure

```
app/
├── Console/Commands/       # orders:archive (housekeeping)
├── Contracts/              # PaymentGatewayInterface, HasMediaInterface
├── Enums/                  # OrderStatus, PaymentStatus
├── Http/
│   ├── Controllers/Api/V1/ # Thin API controllers
│   ├── Middleware/         # EnsureIsAdmin
│   ├── Requests/           # FormRequest validation
│   ├── Resources/          # API resource transformers
│   └── Rules/              # OrderStatusTransition
├── Jobs/                   # NotifyFulfillmentServiceJob
├── Models/                 # User, Admin, Product, Order, OrderItem, Media
├── Services/
│   ├── AuthService
│   ├── OrderService
│   ├── ProductService
│   ├── MediaService
│   ├── FulfillmentService
│   └── Payment/            # MockPaymentGateway, PaymobPaymentGateway
├── Traits/                 # HasMedia, MediaStorageTrait
└── Utils/                  # ApiResponse
```

### Database Schema

| Table                   | Purpose                                                |
|-------------------------|--------------------------------------------------------|
| `users`                 | Customer accounts (Sanctum tokens)                     |
| `admins`                | Admin accounts (separate model, separate tokens)       |
| `products`              | Product catalog                                        |
| `media`                 | Polymorphic media (products, users, etc.)              |
| `orders`                | Orders with `status`, `payment_status`, `archived_at`  |
| `order_items`           | Line items per order                                   |
| `personal_access_tokens`| Sanctum API tokens                                     |
| `jobs` / `failed_jobs`  | Queue infrastructure                                   |

### Order & Payment Status

**Order status** (lifecycle):

- `pending` → `confirmed` → `shipped` → `delivered`
- `cancelled` (terminal)

**Payment status** (separate field):

- `pending_payment` | `paid` | `payment_failed` | `refunded`

Admin can update order status via `PATCH /api/v1/admin/orders/{order}/status` with valid transitions: `confirmed→shipped`, `shipped→delivered`.

---

## API Endpoints

| Method | Endpoint                              | Auth   | Description                    |
|--------|---------------------------------------|--------|--------------------------------|
| POST   | `/api/v1/register`                    | Public | Customer registration          |
| POST   | `/api/v1/login`                       | Public | Customer login                 |
| POST   | `/api/v1/admin/login`                 | Public | Admin login                    |
| GET    | `/api/v1/products`                    | Public | Browse products                |
| GET    | `/api/v1/orders`                      | User   | List orders (own or all if admin) |
| POST   | `/api/v1/orders`                      | User   | Place order (rate-limited)     |
| POST   | `/api/v1/media`                       | User   | Upload media                   |
| DELETE | `/api/v1/media/{media}`               | User   | Delete media                   |
| GET    | `/api/v1/admin/products`              | Admin  | List products                  |
| GET    | `/api/v1/admin/products/{product}`    | Admin  | Get product                    |
| POST   | `/api/v1/admin/products`              | Admin  | Create product                 |
| POST   | `/api/v1/admin/products/{product}`    | Admin  | Update product                 |
| DELETE | `/api/v1/admin/products/{product}`    | Admin  | Delete product                 |
| GET    | `/api/v1/admin/orders`                | Admin  | List all orders                |
| GET    | `/api/v1/admin/orders/{order}`        | Admin  | Get order                      |
| PATCH  | `/api/v1/admin/orders/{order}/status` | Admin  | Update order status (shipped/delivered) |

### Swagger Documentation

Interactive API docs at:

```
http://localhost:8000/api/documentation
```

OpenAPI spec: `public/api-docs.json`

Use **Authorize** in Swagger UI to add a Bearer token (from `/login` or `/admin/login`).

---

## Environment Variables

```env
APP_URL=http://localhost:8000
APP_URL=http://localhost:8000


PAYMENT_GATEWAY=mock
# PAYMENT_GATEWAY=paymob

# Paymob (when PAYMENT_GATEWAY=paymob)
PAYMOB_API_KEY=
PAYMOB_INTEGRATION_ID=
PAYMOB_IFRAME_ID=
PAYMOB_MERCHANT_ID=
PAYMOB_HMAC_SECRET=
PAYMOB_BASE_URL=https://accept.paymob.com/api
```

---

## Key Features

### Payments

- **Mock gateway** — Instant success for development
- **Paymob** — Real integration with webhook/response handlers
- Switch via `PAYMENT_GATEWAY` env
- `payment_url` returned only when placing an order (not stored)

### Automated Housekeeping

- `orders:archive` runs daily at 02:00
- Marks orders older than 3 years with `archived_at`
- Global scope hides archived orders from default queries
- Archived orders accessible via `Order::archived()`

### Fulfillment

- Queued job sends order data to external fulfillment API
- Exponential backoff (10 retries, up to 1 hour)
- Decoupled from order creation

### Rate Limiting

- `/orders` (POST): 10 requests/minute per user

---

## Performance

- Product listings cached with versioned keys
- Eager loading (`with()`) to avoid N+1
- Indexed `archived_at`, `slug`
- Queue-based external API calls
