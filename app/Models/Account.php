<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'external_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(AccountToken::class);
    }

    public function wbOrders(): HasMany
    {
        return $this->hasMany(WbOrder::class);
    }

    public function wbSales(): HasMany
    {
        return $this->hasMany(WbSale::class);
    }

    public function wbStocks(): HasMany
    {
        return $this->hasMany(WbStock::class);
    }

    public function wbIncomes(): HasMany
    {
        return $this->hasMany(WbIncome::class);
    }
}
