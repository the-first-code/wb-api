<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['wb_orders', 'wb_sales', 'wb_stocks', 'wb_incomes'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'account_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('account_id')->nullable()->after('id')->constrained()->nullOnDelete();
                });
            }
        }

        $this->replaceUnique(
            'wb_orders',
            'wb_orders_row_unique',
            'wb_orders_account_row_unique',
            ['account_id', 'g_number', 'odid', 'barcode', 'last_change_date'],
        );

        $this->replaceUnique(
            'wb_sales',
            'wb_sales_sale_id_unique',
            'wb_sales_account_sale_unique',
            ['account_id', 'sale_id'],
        );

        $this->replaceUnique(
            'wb_stocks',
            'wb_stocks_row_unique',
            'wb_stocks_account_row_unique',
            ['account_id', 'date', 'nm_id', 'barcode', 'warehouse_name', 'sc_code', 'tech_size'],
        );

        $this->replaceUnique(
            'wb_incomes',
            'wb_incomes_row_unique',
            'wb_incomes_account_row_unique',
            ['account_id', 'income_id', 'barcode', 'tech_size', 'supplier_article'],
            'CREATE UNIQUE INDEX wb_incomes_account_row_unique ON wb_incomes (account_id, income_id, barcode(64), tech_size(64), supplier_article(128))',
        );
    }

    public function down(): void
    {
        $this->replaceUnique(
            'wb_incomes',
            'wb_incomes_account_row_unique',
            'wb_incomes_row_unique',
            ['income_id', 'barcode', 'tech_size', 'supplier_article'],
        );

        $this->replaceUnique(
            'wb_stocks',
            'wb_stocks_account_row_unique',
            'wb_stocks_row_unique',
            ['date', 'nm_id', 'barcode', 'warehouse_name', 'sc_code', 'tech_size'],
        );

        $this->replaceUnique(
            'wb_sales',
            'wb_sales_account_sale_unique',
            'wb_sales_sale_id_unique',
            ['sale_id'],
        );

        $this->replaceUnique(
            'wb_orders',
            'wb_orders_account_row_unique',
            'wb_orders_row_unique',
            ['g_number', 'odid', 'barcode', 'last_change_date'],
        );

        foreach (['wb_incomes', 'wb_stocks', 'wb_sales', 'wb_orders'] as $tableName) {
            if (Schema::hasColumn($tableName, 'account_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('account_id');
                });
            }
        }
    }

    private function replaceUnique(
        string $table,
        string $oldIndex,
        string $newIndex,
        array $columns,
        ?string $createIndexSql = null,
    ): void {
        if ($this->indexExists($table, $oldIndex)) {
            Schema::table($table, function (Blueprint $blueprint) use ($oldIndex) {
                $blueprint->dropUnique($oldIndex);
            });
        }

        if ($this->indexExists($table, $newIndex)) {
            return;
        }

        if ($createIndexSql !== null && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement($createIndexSql);

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $newIndex) {
            $blueprint->unique($columns, $newIndex);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return in_array($index, Schema::getIndexListing($table), true);
    }
};
