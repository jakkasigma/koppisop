<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffActivityLog extends Model
{
    protected $fillable = [
        'karyawan_id',
        'actor_name',
        'actor_role',
        'employment_type',
        'action_key',
        'action_label',
        'summary',
        'target_type',
        'target_label',
        'target_id',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'karyawan_id' => 'integer',
        'target_id' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id_karyawan');
    }
}
