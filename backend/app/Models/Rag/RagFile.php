<?php

namespace App\Models\Rag;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;

class RagFile extends Model {
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    // chunks
    public function chunks() {
        return $this->hasMany(RagFileChunk::class);
    }

    // creator
    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
