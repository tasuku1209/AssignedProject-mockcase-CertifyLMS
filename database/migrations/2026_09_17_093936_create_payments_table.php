<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('meeting_pack_id')
                ->nullable()
                ->constrained('meeting_packs')
                ->nullOnDelete();
            $table->string('stripe_checkout_session_id', 255)
                ->nullable()
                ->unique();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('amount');
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
