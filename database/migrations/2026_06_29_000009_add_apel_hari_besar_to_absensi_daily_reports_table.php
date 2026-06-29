<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('absensi_daily_reports', 'apel_hari_besar')) {
            Schema::table('absensi_daily_reports', function (Blueprint $table) {
                $table->string('apel_hari_besar')->nullable()->index()->after('apel');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('absensi_daily_reports', 'apel_hari_besar')) {
            Schema::table('absensi_daily_reports', function (Blueprint $table) {
                $table->dropColumn('apel_hari_besar');
            });
        }
    }
};
