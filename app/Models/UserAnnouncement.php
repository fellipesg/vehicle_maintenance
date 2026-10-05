<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comunicado único enviado a um usuário (users:announce-ios-app). O par usuário + comunicado é único.
 */
class UserAnnouncement extends Model
{
    /** @use HasFactory<\Database\Factories\UserAnnouncementFactory> */
    use HasFactory;

    public const IOS_APP_LAUNCH = 'ios_app_launch';

    protected $fillable = [
        'user_id',
        'announcement',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
