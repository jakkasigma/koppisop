<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMessageRead extends Model
{
    protected $table = 'staff_message_reads';

    protected $fillable = [
        'thread_type',
        'thread_id',
        'reader_role',
        'reader_karyawan_id',
        'reader_user_id',
        'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];
}
