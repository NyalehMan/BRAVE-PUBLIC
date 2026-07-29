<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('public_incident_reports', function (Blueprint $table) {
            $table->boolean('submitted_to_brave')->default(false)->after('status');
            $table->unsignedBigInteger('brave_incident_objectid')->nullable()->after('submitted_to_brave');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_incident_reports', function (Blueprint $table) {
            //
        });
    }
};
