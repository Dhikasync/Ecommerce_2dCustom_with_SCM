<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'judul',
        'pesan',
        'tipe',
        'is_dibaca',
    ];

    protected $casts = [
        'is_dibaca' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
