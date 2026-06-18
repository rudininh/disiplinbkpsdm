<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$records = json_decode(file_get_contents(__DIR__ . '/storage/app/orientasi_pppk_tl_2026.json'), true);
$count = 0;

foreach ($records as $record) {
    $hash = hash('sha256', 'orientasi-pppk-' . $record['nip'] . '-' . $record['date_start'] . '-' . $record['date_end'] . '-' . $record['angkatan']);

    App\Models\AbsensiCutiReport::query()->updateOrCreate(
        ['row_hash' => $hash],
        [
            'skpd_id' => 2,
            'kode_skpd' => '1.01.01.',
            'nama_skpd' => 'Dinas Pendidikan',
            'tanggal_mulai' => $record['date_start'],
            'tanggal_selesai' => $record['date_end'],
            'jenis_cuti' => 'Tugas Luar - Orientasi PPPK Angkatan ' . $record['angkatan'],
            'status' => 'Tugas Luar',
            'nama_pegawai' => $record['nama'],
            'nip' => $record['nip'],
            'upload_url' => null,
            'upload_label' => '2.3 PPPK 2026 - Jadwal Orientasi PPPK',
            'row_data' => $record,
            'fetched_at' => now(),
        ]
    );

    $count++;
}

echo "imported={$count}\n";
