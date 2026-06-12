<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('skpd_id')->index();
            $table->string('kode_skpd')->nullable()->index();
            $table->string('nama_skpd')->nullable()->index();
            $table->date('tanggal')->index();
            $table->string('hari')->nullable()->index();
            $table->string('nama_pegawai')->nullable()->index();
            $table->string('nip')->nullable()->index();
            $table->string('pangkat')->nullable();
            $table->string('jabatan')->nullable()->index();
            $table->string('pagi')->nullable()->index();
            $table->string('pulang')->nullable()->index();
            $table->string('apel')->nullable()->index();
            $table->json('row_data')->nullable();
            $table->string('row_hash')->unique();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_daily_reports');
    }
};
