<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    public const CHECKOUT_CORRECTION_REQUESTED = 'requested';
    public const CHECKOUT_CORRECTION_APPROVED = 'approved';
    public const CHECKOUT_CORRECTION_REJECTED = 'rejected';
    public const CHECKOUT_CORRECTION_MANUAL = 'manual';

    protected $table = 'absensi';
    protected $primaryKey = 'id_absensi';
    public $timestamps = false;

    protected $fillable = [
        'id_karyawan',
        'tanggal',
        'waktu_masuk',
        'waktu_pulang',
        'catatan',
        'status',
        'verification_status',
        'verification_note',
        'verified_by',
        'verified_at',
        'checkout_correction_status',
        'checkout_requested_pulang',
        'checkout_requested_at',
        'checkout_request_note',
        'checkout_review_note',
        'checkout_reviewed_by',
        'checkout_reviewed_at',
        'absensi_source',
        'shift_no',
        'selfie_path',
        'geo_lat',
        'geo_lng',
        'geo_accuracy_m',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'waktu_masuk' => 'datetime',
        'waktu_pulang' => 'datetime',
        'shift_no' => 'integer',
        'absensi_source' => 'string',
        'verification_status' => 'string',
        'verification_note' => 'string',
        'verified_by' => 'integer',
        'verified_at' => 'datetime',
        'checkout_correction_status' => 'string',
        'checkout_requested_pulang' => 'datetime',
        'checkout_requested_at' => 'datetime',
        'checkout_request_note' => 'string',
        'checkout_review_note' => 'string',
        'checkout_reviewed_by' => 'integer',
        'checkout_reviewed_at' => 'datetime',
        'geo_lat' => 'float',
        'geo_lng' => 'float',
        'geo_accuracy_m' => 'integer',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'id_karyawan', 'id_karyawan');
    }
}
