<?php

use App\Http\Controllers\AbsensiCmsController;
use App\Http\Controllers\AbsensiScraperController;
use App\Http\Controllers\DisiplinScraperController;
use App\Http\Controllers\SiasnProfileController;
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
    Route::post('/pegawai/import-siasn-excel', [AbsensiCmsController::class, 'importPegawaiSiasnExcel'])->name('pegawai.import-siasn-excel');
    Route::get('/analisa-absensi', [AbsensiCmsController::class, 'analisaAbsensi'])->name('analisa-absensi.index');
    Route::get('/analisa-absensi/export', [AbsensiCmsController::class, 'exportAnalisaAbsensi'])->name('analisa-absensi.export');
    Route::get('/peta-jabatan-real', [AbsensiCmsController::class, 'petaJabatanReal'])->name('peta-jabatan-real.index');
    Route::post('/peta-jabatan-real/fetch', [AbsensiCmsController::class, 'fetchPetaJabatanReal'])->name('peta-jabatan-real.fetch');
    Route::get('/peta-jabatan-siasn', [AbsensiCmsController::class, 'petaJabatanSiasn'])->name('peta-jabatan-siasn.index');
    Route::get('/siasn', [SiasnProfileController::class, 'index'])->name('siasn.index');
    Route::post('/siasn/fetch', [SiasnProfileController::class, 'fetch'])->name('siasn.fetch');
    Route::get('/siasn/test-login', fn () => redirect()->route('cms.siasn.index'));
    Route::post('/siasn/test-login', [SiasnProfileController::class, 'testLogin'])->name('siasn.test-login');
    Route::post('/siasn/forget-token', [SiasnProfileController::class, 'forgetToken'])->name('siasn.forget-token');
    Route::post('/siasn/sync-absensi-reference-employees', [SiasnProfileController::class, 'syncAbsensiReferenceEmployees'])->name('siasn.sync-absensi-reference-employees');
    Route::post('/siasn/sync-absensi-employee-siasn', [SiasnProfileController::class, 'syncAbsensiEmployeeSiasn'])->name('siasn.sync-absensi-employee-siasn');
    Route::post('/siasn/sync-all-absensi-employees-siasn', [SiasnProfileController::class, 'syncAllAbsensiEmployeesSiasn'])->name('siasn.sync-all-absensi-employees-siasn');
    Route::post('/siasn/sync-pns-excel-siasn', [SiasnProfileController::class, 'syncPnsExcelSiasn'])->name('siasn.sync-pns-excel-siasn');
    Route::post('/siasn/sync-education-locations', [SiasnProfileController::class, 'syncEducationLocations'])->name('siasn.sync-education-locations');
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
