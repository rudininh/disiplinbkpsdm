<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiasnAbsensiLocationEmployee extends Model
{
    protected $fillable = [
        'skpd_id',
        'kode_skpd',
        'nama_skpd',
        'lokasi_id',
        'lokasi_nama',
        'lokasi_alamat',
        'lokasi_lat',
        'lokasi_long',
        'nip',
        'nama',
        'siasn_pns_profile_id',
        'siasn_unit_organisasi',
        'siasn_jabatan',
        'match_status',
        'row_data',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function siasnProfile(): BelongsTo
    {
        return $this->belongsTo(SiasnPnsProfile::class, 'siasn_pns_profile_id');
    }
}
