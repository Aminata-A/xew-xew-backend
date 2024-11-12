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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_paid')->default(false);
            $table->string('url_payment')->nullable();
            $table->string('naboo_order_id')->nullable();
            $table->string('status')->default('not_paid');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->string('ticket_type'); // Added column for the selected ticket type
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
