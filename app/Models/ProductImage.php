<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Public URL for this image on the configured product images disk.
     *
     * The local "public" disk keeps the request-based /storage URL because
     * local APP_URL does not match the artisan serve host. Remote disks use
     * the configured disk URL (for example the R2 public bucket URL).
     */
    public function url(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        if (config('filesystems.product_images_disk', 'public') === 'public') {
            return url("/storage/{$this->image_path}");
        }

        return Storage::disk(config('filesystems.product_images_disk'))->url($this->image_path);
    }
}
