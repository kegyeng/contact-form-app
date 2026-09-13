<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'first_name',
        'last_name',
        'gender',
        'email',
        'tel',
        'address',
        'building',
        'detail',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps();
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $keyword = $filters['keyword'] ?? null;

        if ($keyword !== null && $keyword !== '') {
            $query->where(function (Builder $query) use ($keyword) {
                $query->where('first_name', 'like', "%{$keyword}%")
                    ->orWhere('last_name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        $gender = $filters['gender'] ?? null;

        if ($gender !== null && in_array((int) $gender, [1, 2, 3], true)) {
            $query->where('gender', (int) $gender);
        }

        $categoryId = $filters['category_id'] ?? null;

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        $date = $filters['date'] ?? null;

        if ($date !== null && $date !== '') {
            $query->whereDate('created_at', $date);
        }

        return $query;
    }
}
