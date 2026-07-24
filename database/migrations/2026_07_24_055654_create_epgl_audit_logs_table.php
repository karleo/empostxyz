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
        Schema::create('epgl_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('endpoint');
            $table->string('method')->default('POST');
            $table->string('tracking_number')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('correlation_id')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('tracking_number');
            $table->index('successful');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('epgl_audit_logs');
    }
};
