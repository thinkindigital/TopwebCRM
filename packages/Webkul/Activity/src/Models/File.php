<?php

namespace Webkul\Activity\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Contracts\File as FileContract;

class File extends Model implements FileContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'activity_files';

    /**
     * The attributes that should be appended to the model.
     *
     * @var array
     */
    protected $appends = ['url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'path',
        'activity_id',
    ];

    /**
     * Get image url for the product image.
     */
    public function url(): ?string
    {
        if (! $this->getKey()) {
            return null;
        }

        return route('admin.activities.file_download', ['id' => $this->getKey()]);
    }

    /**
     * Get image url for the product image.
     */
    public function getUrlAttribute(): ?string
    {
        return $this->url();
    }

    /**
     * Get the activity that owns the file.
     */
    public function activity()
    {
        return $this->belongsTo(ActivityProxy::modelClass());
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        $array['url'] = $this->url;

        return $array;
    }
}
