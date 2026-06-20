<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->unique()->constrained('distributors')->cascadeOnDelete();
            $table->string('wallet_no')->unique();
            $table->string('status')->default('inactive')->index();
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('frozen_amount', 14, 2)->default(0);
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->string('currency')->default('CNY');
            $table->timestamp('last_activated_at')->nullable();
            $table->timestamp('last_frozen_at')->nullable();
            $table->timestamp('last_restricted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('freeze_reason', 500)->nullable();
            $table->string('restrict_reason', 500)->nullable();
            $table->string('close_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('distributor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_wallets');
    }
};
