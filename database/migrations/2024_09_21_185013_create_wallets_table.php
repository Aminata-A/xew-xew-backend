<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('identifier');
            $table->enum('name' , ['WAVE', 'ORANGE_MONEY', 'FREE_MONEY']);
            $table->string('wallet_number');
            $table->decimal('balance', 10, 2)->default(0);
            $table->foreign('user_id')->references('id')->on('registered_users');
            $table->timestamps();
        });


    // Ajouter la contrainte CHECK pour forcer les valeurs en majuscules
    DB::statement("ALTER TABLE wallets ADD CONSTRAINT check_wallet_name CHECK (name = UPPER(name));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
