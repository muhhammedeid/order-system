<?php

namespace App\Models;

use App\Enums\WhatsAppTemplateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $attributes = [
        'active' => true,
    ];

    protected $fillable = [
        'key',
        'name',
        'type',
        'body',
        'active',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $template): void {
            if ($template->key !== null) {
                throw new RuntimeException('System order templates cannot be deleted; deactivate them instead.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => WhatsAppTemplateType::class,
            'active' => 'boolean',
        ];
    }
}
