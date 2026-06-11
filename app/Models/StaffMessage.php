<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMessage extends Model
{
    protected $table = 'staff_messages';

    protected $fillable = [
        'thread_type',
        'thread_id',
        'sender_role',
        'sender_karyawan_id',
        'sender_user_id',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
