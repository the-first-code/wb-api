<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WbOrder extends Model
{
    protected $table = 'wb_orders';

    protected $fillable = [
        'account_id',
        'g_number', 'date', 'last_change_date', 'supplier_article', 'tech_size',
        'barcode', 'total_price', 'discount_percent', 'warehouse_name', 'oblast',
        'income_id', 'odid', 'nm_id', 'subject', 'category', 'brand',
        'is_cancel', 'cancel_dt', 'sticker', 'srid',
    ];

    protected $casts = [
        'date' => 'datetime',
        'last_change_date' => 'datetime',
        'cancel_dt' => 'datetime',
        'is_cancel' => 'boolean',
        'total_price' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
