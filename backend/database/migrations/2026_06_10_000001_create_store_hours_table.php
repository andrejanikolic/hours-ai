<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('store_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->enum('day', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('open')->nullable();
            $table->time('close')->nullable();
            $table->boolean('closed')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'day']);
        });

        Schema::create('store_special_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'date']);
        });

        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->integer('order_cutoff_minutes')->nullable();
            $table->time('delivery_open')->nullable();
            $table->time('delivery_close')->nullable();
            $table->time('pickup_open')->nullable();
            $table->time('pickup_close')->nullable();
            $table->timestamps();

            $table->unique('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
        Schema::dropIfExists('store_special_closures');
        Schema::dropIfExists('store_hours');
        Schema::dropIfExists('stores');
    }
};
