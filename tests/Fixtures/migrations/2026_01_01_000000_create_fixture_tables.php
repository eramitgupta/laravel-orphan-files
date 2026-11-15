<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->json('images')->nullable();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->text('body')->nullable();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('image_url')->nullable();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('file')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['documents', 'products', 'posts', 'comments', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
