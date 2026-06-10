<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_times', function (Blueprint $table) {
            $table->id();

            // Polymorphic parent: brand | venue | menu | order_type
            $table->string('parent_type');
            $table->unsignedBigInteger('parent_id');

            // weekday = recurring weekly schedule
            // special  = specific date or date range (override)
            $table->enum('type', ['weekday', 'special']);

            // Weekday fields — used when type = weekday
            // JSON array of day names: ["monday", "tuesday", ...]
            $table->json('days')->nullable();

            // Special date fields — used when type = special
            $table->date('date')->nullable();
            $table->date('date_to')->nullable()->comment('Optional end of date range');

            // Time window — null means all day (or all day closed)
            $table->string('time_from', 5)->nullable()->comment('HH:MM');
            $table->string('time_to', 5)->nullable()->comment('HH:MM');

            // working = false means closed during this slot
            $table->boolean('working')->default(true);

            $table->timestamps();

            $table->index(['parent_type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_times');
    }
};
