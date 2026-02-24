<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\HasMediaInterface;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasMediaInterface
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasMedia, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
