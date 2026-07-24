<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_image',
        'meta_title',
        'meta_description',
        'og_image',
        'published_at',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function ensureSlug(): void
    {
        if (filled($this->slug)) {
            return;
        }

        $base = Str::slug($this->title) ?: 'post';
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }
        $this->slug = $slug;
    }

    public function absoluteImage(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '/')) {
            return rtrim((string) config('app.url'), '/').$path;
        }
        if (Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'coverImage' => $this->absoluteImage($this->cover_image),
            'metaTitle' => $this->meta_title,
            'metaDescription' => $this->meta_description,
            'ogImage' => $this->absoluteImage($this->og_image ?: $this->cover_image),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            ...$this->toPublicArray(),
            'coverImageRaw' => $this->cover_image,
            'ogImageRaw' => $this->og_image,
            'isPublished' => (bool) $this->is_published,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
