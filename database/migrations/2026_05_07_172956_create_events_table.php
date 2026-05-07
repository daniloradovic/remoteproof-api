<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('anon_id', 64)->index();
            $table->string('name', 64)->index();
            $table->string('host', 255)->nullable()->index();
            $table->string('verdict', 16)->nullable()->index();
            $table->boolean('cached')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
