<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * NFR-SEC-07: catatan aksi sensitif Admin — pelaku, waktu (created_at),
     * dan perubahan (data_lama -> data_baru).
     */
    protected $fillable = [
        'user_id',
        'aksi',
        'entitas',
        'entitas_id',
        'data_lama',
        'data_baru',
    ];

    protected $casts = [
        'data_lama' => 'array',
        'data_baru' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
