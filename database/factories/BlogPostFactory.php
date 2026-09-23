<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = Str::title(fake()->unique()->sentence(6));

        return [
            'blog_category_id' => BlogCategory::factory(),
            'author_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(14),
            'content' => collect(fake()->paragraphs(5))->implode("\n\n"),
            'cover_photo_path' => null,
            'cover_photo_alt' => null,
            'meta_title' => null,
            'meta_description' => null,
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDays(fake()->numberBetween(1, 120)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->addWeek(),
        ]);
    }
}
