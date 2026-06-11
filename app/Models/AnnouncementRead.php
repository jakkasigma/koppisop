<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRead extends Model
{
    protected $table = 'announcement_reads';

    protected $fillable = [
        'announcement_id',
        'karyawan_id',
        'read_at',
    ];

    protected $casts = [
        'announcement_id' => 'integer',
        'karyawan_id' => 'integer',
        'read_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'announcement_id');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id_karyawan');
    }
}

