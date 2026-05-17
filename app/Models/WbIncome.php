<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WbIncome extends Model
{
    protected $table = 'wb_incomes';

    protected $fillable = [
        'income_id', 'number', 'date', 'last_change_date', 'supplier_article',
        'tech_size', 'barcode', 'quantity', 'total_price', 'date_close',
        'warehouse_name', 'nm_id', 'status',
    ];

    protected $casts = [
        'date' => 'datetime',
        'last_change_date' => 'datetime',
        'date_close' => 'datetime',
        'total_price' => 'decimal:2',
    ];
}
