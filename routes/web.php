<?php

use App\Http\Controllers\AbsensiCmsController;
use App\Http\Controllers\AbsensiScraperController;
use App\Http\Controllers\DisiplinScraperController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AbsensiCmsController::class, 'index'])->name('dashboard');

Route::prefix('cms')->name('cms.')->group(function () {
    Route::get('/absensi-cuti', [AbsensiCmsController::class, 'index'])->name('absensi-cuti.index');
    Route::post('/absensi-cuti/fetch', [AbsensiCmsController::class, 'fetchCuti'])->name('absensi-cuti.fetch');
    Route::get('/laporan-cuti', [AbsensiCmsController::class, 'laporanCuti'])->name('laporan-cuti.index');
    Route::get('/laporan-cuti/export', [AbsensiCmsController::class, 'exportLaporanCuti'])->name('laporan-cuti.export');
    Route::post('/laporan-cuti/fetch-all', [AbsensiCmsController::class, 'fetchAllCuti'])->name('laporan-cuti.fetch-all');
    Route::get('/pegawai', [AbsensiCmsController::class, 'pegawai'])->name('pegawai.index');
    Route::post('/pegawai/fetch', [AbsensiCmsController::class, 'fetchPegawai'])->name('pegawai.fetch');
    Route::get('/analisa-absensi', [AbsensiCmsController::class, 'analisaAbsensi'])->name('analisa-absensi.index');
    Route::get('/analisa-absensi/export', [AbsensiCmsController::class, 'exportAnalisaAbsensi'])->name('analisa-absensi.export');
    Route::get('/peta-jabatan-real', [AbsensiCmsController::class, 'petaJabatanReal'])->name('peta-jabatan-real.index');
    Route::post('/peta-jabatan-real/fetch', [AbsensiCmsController::class, 'fetchPetaJabatanReal'])->name('peta-jabatan-real.fetch');
    Route::get('/laporan-absensi-harian', [AbsensiCmsController::class, 'laporanAbsensiHarian'])->name('laporan-absensi-harian.index');
    Route::post('/laporan-absensi-harian/fetch', [AbsensiCmsController::class, 'fetchLaporanAbsensiHarian'])->name('laporan-absensi-harian.fetch');
    Route::get('/laporan-pppk', [AbsensiCmsController::class, 'laporanPppk'])->name('laporan-pppk.index');
    Route::post('/laporan-pppk/fetch', [AbsensiCmsController::class, 'fetchLaporanPppk'])->name('laporan-pppk.fetch');
    Route::get('/laporan-balai-kota', [AbsensiCmsController::class, 'laporanBalaiKota'])->name('laporan-balai-kota.index');
    Route::post('/laporan-balai-kota/fetch', [AbsensiCmsController::class, 'fetchLaporanBalaiKota'])->name('laporan-balai-kota.fetch');
});

Route::prefix('absensi-scraper')->name('absensi-scraper.')->group(function () {
    Route::get('/', [AbsensiScraperController::class, 'index'])->name('index');
    Route::post('/login', [AbsensiScraperController::class, 'login'])->name('login');
    Route::post('/cuti', [AbsensiScraperController::class, 'cuti'])->name('cuti');
});

Route::prefix('disiplin-scraper')->name('disiplin-scraper.')->group(function () {
    Route::get('/', [DisiplinScraperController::class, 'index'])->name('index');
    Route::post('/run', [DisiplinScraperController::class, 'run'])->name('run');
    Route::post('/login', [DisiplinScraperController::class, 'login'])->name('login');
    Route::post('/discover', [DisiplinScraperController::class, 'discover'])->name('discover');
    Route::post('/analyze', [DisiplinScraperController::class, 'analyze'])->name('analyze');
});
