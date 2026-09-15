<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->uuid('arcgis_submission_uuid')
                ->nullable()
                ->unique()
                ->after('brave_incident_objectid');
        });
    }

    public function down(): void
    {
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->dropUnique(['arcgis_submission_uuid']);
            $table->dropColumn('arcgis_submission_uuid');
        });
    }
};
