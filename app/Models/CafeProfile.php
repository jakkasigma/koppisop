<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CafeProfile extends Model
{
    protected $table = 'cafe_profiles';

    protected $fillable = [
        'nama_cafe',
        'tagline',
        'alamat',
        'kota',
        'telepon',
        'email',
        'instagram',
        'website',
        'deskripsi',
        'logo_path',
    ];
}
