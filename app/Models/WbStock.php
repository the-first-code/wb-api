<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WbStock extends Model
{
    protected $table = 'wb_stocks';

    protected $fillable = [
        'account_id',
        'date', 'last_change_date', 'supplier_article', 'tech_size', 'barcode',
        'quantity', 'is_supply', 'is_realization', 'quantity_full',
        'quantity_not_in_orders', 'warehouse', 'warehouse_name',
        'in_way_to_client', 'in_way_from_client', 'nm_id', 'subject',
        'category', 'days_on_site', 'brand', 'sc_code', 'price', 'discount',
    ];

    protected $casts = [
        'date' => 'date',
        'last_change_date' => 'datetime',
        'is_supply' => 'boolean',
        'is_realization' => 'boolean',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
