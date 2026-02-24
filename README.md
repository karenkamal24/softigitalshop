# SoftigitalShop — E-Commerce Backend API

A production-ready Laravel backend for an e-commerce platform, built with clean architecture principles, SOLID design patterns, and a service-layer approach.

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
```

### Running the Application

```bash
# Start the server
php artisan serve

# Start the queue worker (required for order fulfillment notifications)
php artisan queue:work

# Run the scheduler (for automated order archiving)
php artisan schedule:work
```

### Running Tests

```bash
php artisan test
```

### Running PHPStan

```bash
vendor/bin/phpstan analyse
```

### Default Credentials

| Role     | Email                    | Password |
|----------|--------------------------|----------|
| Customer | customer@softigital.com  | password |
| Admin    | admin@softigital.com     | password |

---

## High-Level Architecture

### Design Patterns

| Pattern             | Implementation                                    | Purpose                                                      |
|---------------------|---------------------------------------------------|--------------------------------------------------------------|
| **Service Layer**   | `App\Services\*`                                  | Encapsulates all business logic outside controllers          |
| **Strategy**        | `PaymentGatewayInterface` + `MockPaymentGateway`  | Swap payment providers without changing business logic        |
| **Polymorphic Media** | `Media` model with `mediable` morphs           | One table handles images for users, products, reviews, etc.  |
| **Repository-like** | Global scope on `Order` model                    | Transparent archiving without changing query code             |
| **Observer**        | Job dispatch on order creation                    | Decoupled fulfillment notification from order flow            |

### Project Structure

```
app/
├── Console/Commands/          # Artisan commands (order archiving)
├── Contracts/                 # Interfaces (PaymentGatewayInterface)
├── Http/
│   ├── Controllers/Api/V1/   # Thin API controllers
│   ├── Middleware/            # EnsureIsAdmin guard
│   ├── Requests/              # FormRequest validation
│   └── Resources/             # API resource transformers
├── Jobs/                      # Queue jobs (fulfillment notification)
├── Models/                    # Eloquent models
├── Services/                  # Business logic layer
│   └── Payment/               # Payment gateway implementations
├── Traits/                    # Reusable traits (MediaStorageTrait)
└── Utils/                     # Helpers (ApiResponse)
```

### Database Schema

```
users            — Customer accounts (Sanctum tokens)
admins           — Admin accounts (separate model, separate tokens)
products         — Product catalog
media            — Polymorphic media (images for products, users, etc.)
orders           — Customer orders (with archived_at for housekeeping)
order_items      — Line items per order
personal_access_tokens — Sanctum API tokens
jobs / failed_jobs     — Queue infrastructure
```

---

## API Endpoints

| Method | Endpoint                       | Auth   | Description                        |
|--------|--------------------------------|--------|------------------------------------|
| POST   | `/api/v1/register`             | Public | Customer registration              |
| POST   | `/api/v1/login`                | Public | Customer login                     |
| POST   | `/api/v1/admin/login`          | Public | Admin login                        |
| GET    | `/api/v1/products`             | Public | Browse product catalog (cached)    |
| POST   | `/api/v1/orders`               | User   | Place an order (rate-limited)      |
| POST   | `/api/v1/admin/products`       | Admin  | Create a product (with images)     |
| PATCH  | `/api/v1/admin/products/{id}`  | Admin  | Update a product (with images)     |

---

## Business Requirement Mapping

### 1. Unified Media System

Implemented via a **polymorphic `media` table** (`mediable_type`, `mediable_id`). Any model that needs media simply adds a `morphMany` relationship. Currently supports products and users. Extending to blog posts or reviews requires only adding the relationship to the new model — no schema or service changes needed.

### 2. Secure & Open Shopping

- **Public**: Product browsing requires no authentication.
- **Authenticated**: Orders require a valid Sanctum Bearer token.
- **Registration/Login**: Separate endpoints for customers (`/register`, `/login`) and admins (`/admin/login`), using distinct Eloquent models (`User` vs `Admin`).

### 3. Fast Checkout with Traffic Control

- **Instant Response**: Orders are created synchronously and return immediately.
- **Async Fulfillment**: A queued job (`NotifyFulfillmentServiceJob`) sends order data to the external fulfillment API with exponential backoff (10 retries, up to 1 hour delay).
- **Rate Limiting**: The `/orders` endpoint is throttled to **10 requests per minute per user** via Laravel's rate limiter.

### 4. Automated Housekeeping

A scheduled command (`orders:archive`) runs **daily** and marks orders older than 3 years with an `archived_at` timestamp. A **global scope** on the `Order` model automatically filters these from all queries, keeping the active dashboard clean while preserving records.

### 5. Administrative Control

Admin routes are protected by a **double middleware layer**: `auth:sanctum` + custom `EnsureIsAdmin`. Admin and customer tokens are isolated via separate Eloquent models (`Admin` vs `User`). Admins can create and update products, including uploading images via multipart form data.

### 6. Payments

Uses the **Strategy Pattern**: `PaymentGatewayInterface` defines the contract; `MockPaymentGateway` provides the current implementation. Swapping to Stripe, PayPal, or any real provider means creating a new implementation class and updating one line in `AppServiceProvider`.

---

## Performance Optimizations

- **Product Caching**: Product listings are cached with a versioned key strategy. Cache is transparently invalidated when products are created or updated.
- **Eager Loading**: All queries use `with()` to prevent N+1 issues.
- **Database Indexing**: `archived_at` and `slug` columns are indexed for fast lookups.
- **Queue-based Processing**: Heavy external API calls are offloaded to the queue.

---

## Environment Variables

Add these to your `.env` file:

```env
FULFILLMENT_API_URL=https://external-service.softigital.com/api
FULFILLMENT_API_KEY=J1eAkhlHJhzAPWADii7TwlABIig7LD2e
QUEUE_CONNECTION=database
```
"# softigitalshop" 
