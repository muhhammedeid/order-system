<?php

namespace App\Models;

use App\Enums\WhatsAppTemplateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $attributes = [
        'active' => true,
    ];

    protected $fillable = [
        'name',
        'type',
        'body',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => WhatsAppTemplateType::class,
            'active' => 'boolean',
        ];
    }
}
