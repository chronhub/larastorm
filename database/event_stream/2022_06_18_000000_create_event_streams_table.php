<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

final class CreateEventStreamsTable extends Migration
{
    public function up(): void
    {
        Schema::create('event_streams', static function (Blueprint $table): void {
            $table->bigInteger('id', true);
            $table->string('real_stream_name', 150)->unique();
            $table->string('stream_name', 150);
            $table->string('category', 60)->nullable();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_streams');
    }
}
