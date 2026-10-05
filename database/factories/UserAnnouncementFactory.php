<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAnnouncement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAnnouncement>
 */
class UserAnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'announcement' => UserAnnouncement::IOS_APP_LAUNCH,
            'sent_at' => now(),
        ];
    }
}
