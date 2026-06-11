<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoBundlingItem extends Model
{
    protected $table = 'promo_bundling_item';
    protected $primaryKey = 'id_promo_bundling_item';

    protected $fillable = [
        'id_promo_bundling',
        'id_produk',
        'qty',
    ];

    public function promoBundling()
    {
        return $this->belongsTo(PromoBundling::class, 'id_promo_bundling', 'id_promo_bundling');
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }
}
