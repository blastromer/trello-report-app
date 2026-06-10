<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoardReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'board_id',
        'board_name',
        'report_data',
        'generated_at',
    ];

    protected $casts = [
        'report_data' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the board report.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
