<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;

class DesignRevision extends Model
{
    /** FR-CDSN-05 #5: satu baris per pengajuan/revisi desain, beserta catatan revisi. */
    protected $fillable = [
        'design_id',
        'versi',
        'data_desain',
        'preview_gambar',
        'status',
        'catatan_revisi',
        'divalidasi_oleh',
    ];

    protected $casts = [
        'data_desain' => 'array',
        'versi' => 'integer',
    ];

    public function design()
    {
        return $this->belongsTo(Design::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }
}
