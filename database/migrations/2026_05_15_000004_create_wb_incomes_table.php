<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wb_incomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('income_id')->nullable()->index();
            $table->string('number')->nullable();
            $table->dateTime('date')->nullable()->index();
            $table->dateTime('last_change_date')->nullable();
            $table->string('supplier_article')->nullable();
            $table->string('tech_size')->nullable();
            $table->string('barcode')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('total_price', 12, 2)->nullable();
            $table->dateTime('date_close')->nullable();
            $table->string('warehouse_name')->nullable();
            $table->bigInteger('nm_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->unique(
                ['income_id', 'barcode', 'tech_size', 'supplier_article'],
                'wb_incomes_row_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wb_incomes');
    }
};
