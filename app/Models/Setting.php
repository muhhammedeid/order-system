<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    public static function whatsappNumber(): ?string
    {
        $number = static::get('whatsapp_number');

        if ($number === null || trim($number) === '') {
            return null;
        }

        return preg_replace('/\D/', '', $number);
    }

    public static function whatsappCustomerNotificationsEnabled(): bool
    {
        return static::get('whatsapp_customer_notifications_enabled') !== '0';
    }

    public static function whatsappManagerNotificationsEnabled(): bool
    {
        return static::get('whatsapp_manager_notifications_enabled') !== '0';
    }

    /**
     * Owner/operations number that receives new-order alerts. Null when not
     * configured; callers skip the alert instead of guessing a recipient.
     */
    public static function ownerWhatsappNumber(): ?string
    {
        $number = static::get('owner_whatsapp_number');

        if ($number === null || trim($number) === '') {
            return null;
        }

        return preg_replace('/\D/', '', $number);
    }
}
