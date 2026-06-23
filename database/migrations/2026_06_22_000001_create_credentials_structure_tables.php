<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('api_services', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('base_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('token_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('credentials_schema')->nullable();
            $table->timestamps();
        });

        Schema::create('api_service_token_type', function (Blueprint $table) {
            $table->foreignId('api_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_type_id')->constrained()->cascadeOnDelete();

            $table->primary(['api_service_id', 'token_type_id']);
        });

        Schema::create('account_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('token_type_id')->constrained()->restrictOnDelete();
            $table->text('credentials');
            $table->string('label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['account_id', 'api_service_id']);
            $table->index(['api_service_id', 'token_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_tokens');
        Schema::dropIfExists('api_service_token_type');
        Schema::dropIfExists('token_types');
        Schema::dropIfExists('api_services');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('companies');
    }
};
