<?php

namespace Database\Factories;

use App\Models\ActivityNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityNotification> */
class ActivityNotificationFactory extends Factory
{
    protected $model = ActivityNotification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'section' => 'sales',
            'title' => 'Factory notice',
            'body' => 'A factory sale was recorded.',
            'url' => '/sales',
        ];
    }
}
