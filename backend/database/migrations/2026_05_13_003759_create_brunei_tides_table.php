<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brunei_tides', function (Blueprint $table) {
            $table->id();

            $table->string('district');
            $table->string('station');

            $table->string('source_url')->nullable();

            $table->dateTime('tide_datetime');

            $table->decimal('tide_height', 8, 2)->nullable();

            $table->string('tide_type')->nullable();

            $table->timestamps();

            $table->unique([
                'district',
                'station',
                'tide_datetime',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brunei_tides');
    }
};