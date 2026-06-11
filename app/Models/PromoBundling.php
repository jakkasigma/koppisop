<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PromoBundling extends Model
{
    protected $table = 'promo_bundling';
    protected $primaryKey = 'id_promo_bundling';

    protected $fillable = [
        'nama_promo',
        'harga_bundle',
        'status_aktif',
        'tanggal_mulai',
        'tanggal_selesai',
        'keterangan',
    ];

    protected $casts = [
        'harga_bundle' => 'float',
        'status_aktif' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(PromoBundlingItem::class, 'id_promo_bundling', 'id_promo_bundling');
    }

    public function scopeActiveForDate(Builder $query, Carbon|string|null $date = null): Builder
    {
        $dateValue = ($date instanceof Carbon ? $date : Carbon::parse($date ?? now()))->toDateString();

        return $query
            ->where('status_aktif', true)
            ->where(function (Builder $builder) use ($dateValue): void {
                $builder->whereNull('tanggal_mulai')->orWhereDate('tanggal_mulai', '<=', $dateValue);
            })
            ->where(function (Builder $builder) use ($dateValue): void {
                $builder->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $dateValue);
            });
    }

    public function isAktifPada(Carbon|string|null $date = null): bool
    {
        $dateValue = ($date instanceof Carbon ? $date : Carbon::parse($date ?? now()))->toDateString();
        $tanggalMulai = $this->tanggal_mulai?->toDateString();
        $tanggalSelesai = $this->tanggal_selesai?->toDateString();

        if (! $this->status_aktif) {
            return false;
        }

        if ($tanggalMulai !== null && $tanggalMulai > $dateValue) {
            return false;
        }

        if ($tanggalSelesai !== null && $tanggalSelesai < $dateValue) {
            return false;
        }

        return true;
    }
}
