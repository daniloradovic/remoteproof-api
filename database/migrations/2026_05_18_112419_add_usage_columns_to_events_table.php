<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('model', 64)->nullable()->after('verdict');
            $table->unsignedInteger('input_tokens')->nullable()->after('model');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            $table->unsignedInteger('cache_read_input_tokens')->nullable()->after('output_tokens');
            $table->unsignedInteger('cache_creation_input_tokens')->nullable()->after('cache_read_input_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'model',
                'input_tokens',
                'output_tokens',
                'cache_read_input_tokens',
                'cache_creation_input_tokens',
            ]);
        });
    }
};
