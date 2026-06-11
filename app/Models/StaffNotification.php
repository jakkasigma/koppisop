<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffNotification extends Model
{
    public const CATEGORY_PAYROLL = 'payroll';
    public const CATEGORY_LEAVE = 'leave';
    public const CATEGORY_SWAP = 'swap';
    public const CATEGORY_ATTENDANCE = 'attendance';
    public const CATEGORY_SYSTEM = 'system';

    protected $fillable = [
        'id_karyawan',
        'category',
        'title',
        'body',
        'action_url',
        'action_label',
        'event_key',
        'read_at',
        'meta',
    ];

    protected $casts = [
        'id_karyawan' => 'integer',
        'category' => 'string',
        'title' => 'string',
        'body' => 'string',
        'action_url' => 'string',
        'action_label' => 'string',
        'event_key' => 'string',
        'read_at' => 'datetime',
        'meta' => 'array',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'id_karyawan', 'id_karyawan');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function categoryBadge(): string
    {
        return match ((string) $this->category) {
            self::CATEGORY_PAYROLL => 'Gaji',
            self::CATEGORY_LEAVE => 'Izin',
            self::CATEGORY_SWAP => 'Swap',
            self::CATEGORY_ATTENDANCE => 'Absen',
            default => 'Info',
        };
    }
}
