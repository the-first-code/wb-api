<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WbOrder extends Model
{
    protected $table = 'wb_orders';

    protected $fillable = [
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
}
