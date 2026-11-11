<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StyleImage extends Model
{
    protected $fillable = [
        'style_id',
        'disk',
        'path',
        'thumbnail_path',
        'file_name',
        'mime_type',
        'size',
        'created_by_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // Storage locations are server-internal; clients address files by image id only.
    protected $hidden = ['disk', 'path', 'thumbnail_path'];

    protected $appends = ['has_thumbnail'];

    public function style()
    {
        return $this->belongsTo(Style::class, 'style_id');
    }

    public function getHasThumbnailAttribute(): bool
    {
        return ! empty($this->thumbnail_path);
    }
}
