<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KasirShiftSession extends Model
{
    protected $table = 'kasir_shift_sessions';

    protected $fillable = [
        'user_id',
        'shift_ke',
        'kas_awal',
        'started_at',
        'ended_at',
        'total_trx',
        'total_omzet',
        'total_cash',
        'total_qris',
        'total_debit',
        'total_delivery',
        'total_pengeluaran',
        'estimasi_kas_akhir',
        'kas_akhir_input',
    ];

    protected $casts = [
        'shift_ke' => 'integer',
        'kas_awal' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'total_trx' => 'integer',
        'total_omzet' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_qris' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'total_delivery' => 'decimal:2',
        'total_pengeluaran' => 'decimal:2',
        'estimasi_kas_akhir' => 'decimal:2',
        'kas_akhir_input' => 'decimal:2',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pengeluaran(): HasMany
    {
        return $this->hasMany(KasirShiftPengeluaran::class, 'kasir_shift_session_id');
    }
}
