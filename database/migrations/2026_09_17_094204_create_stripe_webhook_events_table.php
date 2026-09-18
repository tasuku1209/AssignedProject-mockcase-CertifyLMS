<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('stripe_event_id', 255)->unique();
            $table->string('event_type', 100);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
