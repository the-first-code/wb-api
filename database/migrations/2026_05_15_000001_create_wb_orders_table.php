<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wb_orders', function (Blueprint $table) {
            $table->id();
            $table->string('g_number')->nullable();
            $table->dateTime('date')->nullable()->index();
            $table->dateTime('last_change_date')->nullable()->index();
            $table->string('supplier_article')->nullable();
            $table->string('tech_size')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->string('warehouse_name')->nullable();
            $table->string('oblast')->nullable();
            $table->unsignedBigInteger('income_id')->nullable();
            $table->unsignedBigInteger('odid')->nullable();
            $table->bigInteger('nm_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->boolean('is_cancel')->default(false);
            $table->dateTime('cancel_dt')->nullable();
            $table->string('sticker')->nullable();
            $table->string('srid')->nullable();
            $table->timestamps();

            $table->unique(
                ['g_number', 'odid', 'barcode', 'last_change_date'],
                'wb_orders_row_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wb_orders');
    }
};
