<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('public_incident_reports')) {
            return;
        }

        Schema::table('public_incident_reports', function (Blueprint $table) {
            if (!Schema::hasColumn(
                'public_incident_reports',
                'arcgis_submitted_at'
            )) {
                $table->timestamp('arcgis_submitted_at')
                    ->nullable();
            }

            if (!Schema::hasColumn(
                'public_incident_reports',
                'arcgis_submission_error'
            )) {
                $table->text('arcgis_submission_error')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('public_incident_reports')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn(
                'public_incident_reports',
                'arcgis_submitted_at'
            ) ? 'arcgis_submitted_at' : null,

            Schema::hasColumn(
                'public_incident_reports',
                'arcgis_submission_error'
            ) ? 'arcgis_submission_error' : null,
        ]));

        if ($columns !== []) {
            Schema::table(
                'public_incident_reports',
                fn (Blueprint $table) => $table->dropColumn($columns)
            );
        }
    }
};
