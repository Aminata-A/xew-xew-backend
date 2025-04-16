<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('registered_users', function (Blueprint $table) {
            $table->string('organization_name')->nullable();
            $table->string('organization_type')->nullable();
        });

        // Créer la table pivot pour la relation many-to-many
        Schema::create('organizer_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registered_user_id')->constrained('registered_users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('organizer_categories');

        Schema::table('registered_users', function (Blueprint $table) {
            $table->dropColumn(['organization_name', 'organization_type']);
        });
    }
};
