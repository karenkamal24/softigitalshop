<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'total_amount_cents',
        'total_quantity',
        'status',
        'payment_status',
        'address',
        'archived_at',
        'paymob_order_id',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount_cents' => 'integer',
            'total_quantity' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $builder): void {
            $builder->whereNull('archived_at');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @param Builder<Order> $query
     * @return Builder<Order>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active')->whereNotNull('archived_at');
    }
}
