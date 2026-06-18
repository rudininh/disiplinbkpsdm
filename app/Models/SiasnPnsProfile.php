<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiasnPnsProfile extends Model
{
    protected $fillable = [
        'pns_id',
        'nip',
        'jenis_asn',
        'nama',
        'jabatan',
        'jenis_jabatan',
        'unit_organisasi',
        'unit_organisasi_induk',
        'unor_id',
        'instansi_kerja',
        'satuan_kerja',
        'lokasi_kerja',
        'tmt_jabatan',
        'raw_data',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'fetched_at' => 'datetime',
            'tmt_jabatan' => 'date',
        ];
    }
}
