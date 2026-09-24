<?php

namespace App\Models;

use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    /** @use HasFactory<\Database\Factories\BlogPostFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'blog_category_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_photo_path',
        'cover_photo_alt',
        'cover_art',
        'meta_title',
        'meta_description',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Published posts whose publication date has already arrived.
     *
     * @param  Builder<BlogPost>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->cover_photo_path === null || $this->cover_photo_path === '') {
                return null;
            }

            return AppStorage::coversUrl($this->cover_photo_path);
        });
    }

    /**
     * Estimated reading time in minutes, at 200 words per minute.
     */
    protected function readingMinutes(): Attribute
    {
        return Attribute::get(function (): int {
            $words = preg_match_all('/\pL+/u', strip_tags((string) $this->content)) ?: 0;

            return max(1, (int) ceil($words / 200));
        });
    }

    protected function summary(): Attribute
    {
        return Attribute::get(function (): string {
            if (is_string($this->excerpt) && $this->excerpt !== '') {
                return $this->excerpt;
            }

            return Str::limit(trim(strip_tags(Str::markdown((string) $this->content))), 160);
        });
    }

    /**
     * Unique slug derived from the title, ignoring the given post on edits.
     */
    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
