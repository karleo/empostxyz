<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number');
            $table->string('uhawb')->nullable();
            $table->json('payload');
            $table->string('sync_status')->default('pending');
            $table->text('sync_message')->nullable();
            $table->json('sync_errors')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('source')->default('form');
            $table->timestamps();

            $table->index(['user_id', 'sync_status']);
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
