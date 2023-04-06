<?php

declare(strict_types=1);

use Chronhub\Larastorm\EventStore\Persistence\EventStreamProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateEventStreamsTable extends Migration
{
    public function up(): void
    {
        $this->connection = config('chronicler.event_stream_provider.connection.name');

        Schema::create(EventStreamProvider::TABLE_NAME, static function (Blueprint $table): void {
            $table->bigInteger('id', true);
            $table->string('real_stream_name', 150)->unique();
            $table->string('stream_name', 150);
            $table->string('category', 60)->nullable();

            $table->index('category');
        });
    }

    public function down(): void
    {
        $this->connection = config('chronicler.event_stream_provider.connection.name');

        Schema::dropIfExists(EventStreamProvider::TABLE_NAME);
    }
}
