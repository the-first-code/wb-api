<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wb_sales', function (Blueprint $table) {
            $table->id();
            $table->string('g_number')->nullable();
            $table->dateTime('date')->nullable()->index();
            $table->dateTime('last_change_date')->nullable()->index();
            $table->string('supplier_article')->nullable();
            $table->string('tech_size')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->boolean('is_supply')->default(false);
            $table->boolean('is_realization')->default(false);
            $table->decimal('promo_code_discount', 12, 2)->nullable();
            $table->string('warehouse_name')->nullable();
            $table->string('country_name')->nullable();
            $table->string('oblast_okrug_name')->nullable();
            $table->string('region_name')->nullable();
            $table->unsignedBigInteger('income_id')->nullable();
            $table->string('sale_id')->nullable();
            $table->unsignedBigInteger('odid')->nullable();
            $table->decimal('spp', 8, 2)->nullable();
            $table->decimal('for_pay', 12, 2)->nullable();
            $table->decimal('finished_price', 12, 2)->nullable();
            $table->decimal('price_with_disc', 12, 2)->nullable();
            $table->bigInteger('nm_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->unsignedTinyInteger('is_storno')->default(0);
            $table->string('sticker')->nullable();
            $table->string('srid')->nullable();
            $table->timestamps();

            $table->unique('sale_id', 'wb_sales_sale_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wb_sales');
    }
};
