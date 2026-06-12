<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiDailyReport extends Model
{
    protected $fillable = [
        'skpd_id',
        'kode_skpd',
        'nama_skpd',
        'tanggal',
        'hari',
        'nama_pegawai',
        'nip',
        'pangkat',
        'jabatan',
        'pagi',
        'pulang',
        'apel',
        'row_data',
        'row_hash',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'row_data' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
