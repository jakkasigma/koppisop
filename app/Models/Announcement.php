<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Announcement extends Model
{
    protected $table = 'announcements';

    protected $fillable = [
        'title',
        'body',
        'image_path',
        'target_role',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class, 'announcement_id');
    }

    public function adminReads(): HasMany
    {
        return $this->hasMany(AnnouncementAdminRead::class, 'announcement_id');
    }

    public function resolvePromoStatus(Carbon $now): array
    {
        $body = (string) ($this->body ?? '');
        $startAt = null;
        $endAt = null;

        if (preg_match('/Periode:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})\s*s\/d\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $body, $matches)) {
            try {
                $startAt = Carbon::parse($matches[1])->startOfDay();
                $endAt = Carbon::parse($matches[2])->endOfDay();
            } catch (\Throwable $e) {
                $startAt = null;
                $endAt = null;
            }
        } elseif (preg_match('/Berakhir pada:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $body, $matches)) {
            try {
                $endAt = Carbon::parse($matches[1])->endOfDay();
            } catch (\Throwable $e) {
                $endAt = null;
            }
        }

        $status = null;
        if ($endAt instanceof Carbon) {
            if ($startAt instanceof Carbon && $now->lessThan($startAt)) {
                $status = 'Akan Mulai';
            } elseif ($now->greaterThan($endAt)) {
                $status = 'Berakhir';
            } else {
                $status = 'Aktif';
            }
        }

        return [
            'status' => $status,
            'end_at' => $endAt,
            'start_at' => $startAt,
        ];
    }
}
