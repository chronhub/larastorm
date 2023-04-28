<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create(
            'snapshots',
            static function (Blueprint $table): void {
                $table->uuid('aggregate_id')->primary();
                $table->string('aggregate_type', 150);
                $table->bigInteger('last_version');
                $table->binary('aggregate_root'); //todo use bytea for postgres
                $table->timestampTz('created_at', 6);

                $table->unique(['aggregate_id', 'aggregate_type']);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }
};
