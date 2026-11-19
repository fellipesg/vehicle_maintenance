<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Comentário de um artigo do blog (um nível só, texto puro). Regras em .ai/rules/blog.md.
 */
class BlogComment extends Model
{
    /** @use HasFactory<\Database\Factories\BlogCommentFactory> */
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_PENDING = 'pending';

    public const STATUS_HIDDEN = 'hidden';

    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 2000;

    protected $fillable = [
        'blog_post_id',
        'user_id',
        'body',
        'status',
        'moderated_by_user_id',
        'moderated_at',
    ];

    protected function casts(): array
    {
        return [
            'moderated_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    /**
     * @param  Builder<BlogComment>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param  Builder<BlogComment>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Texto que leva link (http://, https://, www. ou algo no formato de domínio, como
     * "site.com.br") entra em moderação em vez de ir direto ao ar.
     */
    public static function containsLink(string $body): bool
    {
        return preg_match('~https?://|www\.|(?<![\p{L}\p{N}])[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)*\.[a-z]{2,24}(?![\p{L}\p{N}])~iu', $body) === 1;
    }

    /**
     * Nome mostrado em público: primeiro nome e inicial do último ("Felipe G."). Nunca o nome
     * completo nem o e-mail.
     */
    protected function authorDisplayName(): Attribute
    {
        return Attribute::get(fn (): string => self::displayNameFor($this->user?->name));
    }

    public static function displayNameFor(?string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim((string) $fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return 'Leitor';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.Str::upper(mb_substr(end($parts), 0, 1)).'.';
    }
}
