<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalTukarRequest extends Model
{
    protected $table = 'jadwal_tukar_requests';

    protected $fillable = [
        'tanggal',
        'from_tanggal',
        'to_tanggal',
        'from_shift',
        'to_shift',
        'from_karyawan_id',
        'to_karyawan_id',
        'status',
        'staff_status',
        'staff_note',
        'staff_responded_by',
        'staff_responded_at',
        'note',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'from_tanggal' => 'date',
        'to_tanggal' => 'date',
        'from_shift' => 'integer',
        'to_shift' => 'integer',
        'from_karyawan_id' => 'integer',
        'to_karyawan_id' => 'integer',
        'staff_responded_by' => 'integer',
        'staff_responded_at' => 'datetime',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function fromKaryawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'from_karyawan_id', 'id_karyawan');
    }

    public function toKaryawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'to_karyawan_id', 'id_karyawan');
    }
}
