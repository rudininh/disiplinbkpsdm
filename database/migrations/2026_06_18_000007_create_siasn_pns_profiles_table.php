<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siasn_pns_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('pns_id')->nullable()->index();
            $table->string('nip', 32)->unique();
            $table->string('nama')->nullable()->index();
            $table->string('jabatan')->nullable()->index();
            $table->string('jenis_jabatan')->nullable()->index();
            $table->string('unit_organisasi')->nullable()->index();
            $table->string('unit_organisasi_induk')->nullable()->index();
            $table->string('unor_id')->nullable()->index();
            $table->string('instansi_kerja')->nullable()->index();
            $table->string('satuan_kerja')->nullable()->index();
            $table->string('lokasi_kerja')->nullable()->index();
            $table->date('tmt_jabatan')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siasn_pns_profiles');
    }
};
