<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrelloToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'access_token',
    ];

    /**
     * Get the user that owns the Trello token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
