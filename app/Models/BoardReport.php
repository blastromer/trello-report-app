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
        'report_type',
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

    public function resolvedType(): string
    {
        return $this->report_type ?? ($this->report_data['report_type'] ?? 'board');
    }

    public function typeLabel(): string
    {
        return match ($this->resolvedType()) {
            'accountability' => 'Accountability',
            'individual_kpi' => 'Individual KPI',
            'team_performance' => 'Team performance',
            default => 'Board',
        };
    }

    public function viewUrl(): string
    {
        return match ($this->resolvedType()) {
            'accountability' => route('trello.accountability.show', $this),
            'individual_kpi' => route('trello.kpi.show', $this),
            'team_performance' => route('trello.team.show', $this),
            default => route('trello.report.show', $this),
        };
    }
}
