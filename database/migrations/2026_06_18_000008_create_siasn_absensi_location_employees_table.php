<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siasn_pns_profiles', function (Blueprint $table) {
            $table->string('jenis_asn')->nullable()->after('nip')->index();
        });

        Schema::create('siasn_absensi_location_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('skpd_id')->index();
            $table->string('kode_skpd')->nullable()->index();
            $table->string('nama_skpd')->nullable()->index();
            $table->string('lokasi_id')->index();
            $table->string('lokasi_nama')->index();
            $table->string('lokasi_alamat')->nullable();
            $table->string('lokasi_lat')->nullable();
            $table->string('lokasi_long')->nullable();
            $table->string('nip', 32)->index();
            $table->string('nama')->nullable()->index();
            $table->foreignId('siasn_pns_profile_id')->nullable()->constrained('siasn_pns_profiles')->nullOnDelete();
            $table->string('siasn_unit_organisasi')->nullable()->index();
            $table->string('siasn_jabatan')->nullable()->index();
            $table->string('match_status')->nullable()->index();
            $table->json('row_data')->nullable();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['lokasi_id', 'nip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siasn_absensi_location_employees');

        Schema::table('siasn_pns_profiles', function (Blueprint $table) {
            $table->dropColumn('jenis_asn');
        });
    }
};
