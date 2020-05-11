<?php

namespace Modules\Blog\Entities;

use App\Traits\Mediatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Forum\Entities\Traits\RecordsActivity;
use Modules\User\Entities\User;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class Post extends Model implements Searchable
{
    use RecordsActivity, Mediatable;

    const STATUS_PUBLISHED = 'PUBLISHED';
    const STATUS_DRAFT = 'DRAFT';
    const STATUS_PENDING = 'PENDING';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['category', 'creator', 'propose'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'featured' => 'boolean',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'published_at'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'status_classname',
    ];

    /**
     * Spatie Searcheblable type.
     *
     * @var string
     */
    public string $searchableType = 'Articles';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($post) {
            $post->update(['slug' => $post->title]);
        });
    }

    /**
     * Select all publish post
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function scopePublish(Builder $builder)
    {
        return $builder
            ->where('status', self::STATUS_PUBLISHED)
            ->whereDate('published_at', '<=', now());
    }

    /**
     * Set the proper slug attribute.
     *
     * @param string $value
     */
    public function setSlugAttribute($value)
    {
        if (static::where('slug', $slug = str_slug($value))->exists()) {
            $slug = "{$slug}-{$this->id}";
        }

        $this->attributes['slug'] = $slug;
    }

    /**
     * Return The post resume.
     *
     * @param  string  $value
     * @return string
     */
    public function getSummaryAttribute($value)
    {
        return str_limit(strip_tags($this->body), 150);
    }

    /**
     * @param  string  $value
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public function getImageAttribute($value)
    {
        if ($value) {
            return url('storage/' . $value);
        }

        if ($this->previewImage && !$value) {
            return $this->preview_image_link;
        }

        return null;
    }

    /**
     * Return the current format of status.
     *
     * @return string
     */
    public function getStatus()
    {
        switch ($this->status) {
            case self::STATUS_DRAFT:
                return 'Brouillon';
            case self::STATUS_PENDING:
                return 'En Attente';
            case self::STATUS_PUBLISHED:
                return 'Publié';
        }
    }

    /**
     * Return the current classname of status.
     *
     * @return string
     */
    public function getStatusClassnameAttribute()
    {
        switch ($this->status) {
            case self::STATUS_DRAFT:
                return 'bg-blue-100 text-blue-800';
            case self::STATUS_PENDING:
                return 'bg-orange-100 text-orange-800';
            case self::STATUS_PUBLISHED:
                return 'bg-green-100 text-green-800';
        }
    }

    /**
     * Return Category of the current post.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Return the Post author.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Return the post propose author.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function propose()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /**
     * Return search result for post.
     *
     * @return SearchResult
     */
    public function getSearchResult(): SearchResult
    {
        $url = route('blog.post', ['slug' => $this->slug]);

        return new SearchResult(
            $this,
            $this->title,
            $url
        );
    }
}
