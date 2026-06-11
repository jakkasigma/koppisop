<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pesanan extends Model
{
    protected $table = 'pesanan';
    protected $primaryKey = 'id_pesanan';
    public $timestamps = false;

    protected $fillable = [
        'id_pelanggan',
        'id_karyawan',
        'kasir_label',
        'kasir_shift_session_id',
        'no_urut_shift',
        'id_diskon',
        'total_harga',
        'subtotal_harga',
        'diskon_nominal',
        'diskon_nama',
        'diskon_tipe',
        'diskon_nilai',
        'pajak_persen',
        'pajak_nominal',
        'waktu_pembayaran',
        'metode_pembayaran',
        'status_pembayaran',
        'catatan_pesanan',
        'offline_ref',
    ];

    public function detail()
    {
        return $this->hasMany(DetailPesanan::class, 'id_pesanan', 'id_pesanan');
    }

    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'id_karyawan', 'id_karyawan');
    }

    public function diskon()
    {
        return $this->belongsTo(Diskon::class, 'id_diskon', 'id_diskon');
    }

    public function shift()
    {
        return $this->belongsTo(KasirShiftSession::class, 'kasir_shift_session_id');
    }
}
