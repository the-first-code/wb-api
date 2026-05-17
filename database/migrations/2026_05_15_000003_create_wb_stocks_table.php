<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wb_stocks', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->dateTime('last_change_date')->nullable();
            $table->string('supplier_article', 128)->nullable();
            $table->string('tech_size', 64)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->integer('quantity')->nullable()->default(0);
            $table->boolean('is_supply')->default(false);
            $table->boolean('is_realization')->default(false);
            $table->integer('quantity_full')->nullable()->default(0);
            $table->integer('quantity_not_in_orders')->nullable();
            $table->unsignedBigInteger('warehouse')->nullable();
            $table->string('warehouse_name', 128)->nullable();
            $table->integer('in_way_to_client')->nullable()->default(0);
            $table->integer('in_way_from_client')->nullable()->default(0);
            $table->bigInteger('nm_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('category')->nullable();
            $table->integer('days_on_site')->nullable();
            $table->string('brand')->nullable();
            $table->string('sc_code', 64)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('discount', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['date', 'nm_id', 'barcode', 'warehouse_name', 'sc_code', 'tech_size'],
                'wb_stocks_row_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wb_stocks');
    }
};
