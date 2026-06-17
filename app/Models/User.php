<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'visible_board_ids',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'visible_board_ids' => 'array',
    ];

    /**
     * Whether the user has a saved subset of boards (not "show all").
     */
    public function hasBoardFilter(): bool
    {
        $ids = $this->visible_board_ids;

        return is_array($ids) && $ids !== [];
    }

    /**
     * @param array<int, array<string, mixed>> $boards Trello board payloads
     * @return array<int, array<string, mixed>>
     */
    public function filterVisibleBoards(array $boards): array
    {
        if (!$this->hasBoardFilter()) {
            return $boards;
        }

        $allowed = array_flip($this->visible_board_ids);

        return array_values(array_filter($boards, function (array $board) use ($allowed) {
            return isset($allowed[$board['id'] ?? '']);
        }));
    }

    /**
     * Get the Trello token for the user.
     */
    public function trelloToken()
    {
        return $this->hasOne(TrelloToken::class);
    }

    /**
     * Get the board reports for the user.
     */
    public function boardReports()
    {
        return $this->hasMany(BoardReport::class);
    }
}
