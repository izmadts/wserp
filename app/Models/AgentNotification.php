<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'sale_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
