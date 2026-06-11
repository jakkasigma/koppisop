<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Diskon extends Model
{
    protected $table = 'diskon';
    protected $primaryKey = 'id_diskon';
    public $timestamps = false;

    protected $fillable = [
        'nama_diskon',
        'tipe_diskon',
        'nilai_diskon',
        'minimal_belanja',
        'maksimal_diskon',
        'id_kategori_target',
        'harga_spesial',
        'status_aktif',
        'tanggal_mulai',
        'tanggal_selesai',
        'keterangan',
    ];

    protected $casts = [
        'nilai_diskon' => 'float',
        'minimal_belanja' => 'float',
        'maksimal_diskon' => 'float',
        'id_kategori_target' => 'integer',
        'harga_spesial' => 'float',
        'status_aktif' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

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

    public function pesanan()
    {
        return $this->hasMany(Pesanan::class, 'id_diskon', 'id_diskon');
    }

    public function kategoriTarget()
    {
        return $this->belongsTo(Kategori::class, 'id_kategori_target', 'id_kategori');
    }
}
