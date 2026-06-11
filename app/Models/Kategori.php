<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Kategori extends Model
{
    protected $table = 'kategori';
    protected $primaryKey = 'id_kategori';
    public $timestamps = false;

    protected $fillable = [
        'nama_kategori',
        'deskripsi',
    ];

    public function produk()
    {
        return $this->hasMany(Produk::class, 'id_kategori', 'id_kategori');
    }

    public static function normalizedKey(?string $namaKategori): string
    {
        if (!is_string($namaKategori) || trim($namaKategori) === '') {
            return 'tanpa-kategori';
        }

        return Str::lower((string) Str::of($namaKategori)->replaceMatches('/[^[:alnum:]]+/u', ''));
    }
}
