<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WbSale extends Model
{
    protected $table = 'wb_sales';

    protected $fillable = [
        'g_number', 'date', 'last_change_date', 'supplier_article', 'tech_size',
        'barcode', 'total_price', 'discount_percent', 'is_supply', 'is_realization',
        'promo_code_discount', 'warehouse_name', 'country_name', 'oblast_okrug_name',
        'region_name', 'income_id', 'sale_id', 'odid', 'spp', 'for_pay',
        'finished_price', 'price_with_disc', 'nm_id', 'subject', 'category',
        'brand', 'is_storno', 'sticker', 'srid',
    ];

    protected $casts = [
        'date' => 'datetime',
        'last_change_date' => 'datetime',
        'is_supply' => 'boolean',
        'is_realization' => 'boolean',
        'total_price' => 'decimal:2',
        'promo_code_discount' => 'decimal:2',
        'spp' => 'decimal:2',
        'for_pay' => 'decimal:2',
        'finished_price' => 'decimal:2',
        'price_with_disc' => 'decimal:2',
    ];
}
