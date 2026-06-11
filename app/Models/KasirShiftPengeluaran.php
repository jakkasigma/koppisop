<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasirShiftPengeluaran extends Model
{
    protected $table = 'kasir_shift_pengeluaran';

    protected $fillable = [
        'kasir_shift_session_id',
        'user_id',
        'nominal',
        'keterangan',
        'pengeluaran_at',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'pengeluaran_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(KasirShiftSession::class, 'kasir_shift_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
