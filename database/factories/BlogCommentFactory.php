<?php

namespace Database\Factories;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlogComment>
 */
class BlogCommentFactory extends Factory
{
    protected $model = BlogComment::class;

    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(12),
            'status' => BlogComment::STATUS_PUBLISHED,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BlogComment::STATUS_PENDING]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BlogComment::STATUS_HIDDEN]);
    }
}
