<?php

namespace App\Models;

use App\Enums\WhatsAppMarketingStatus;
use App\Support\WhatsApp\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

class Customer extends Model
{
    use HasFactory;

    protected $attributes = [
        'whatsapp_marketing_status' => 'unknown',
    ];

    protected $fillable = [
        'customer_code',
        'name',
        'company_name',
        'phone',
        'whatsapp',
        'governorate',
        'city',
        'address',
        'notes',
        'whatsapp_marketing_status',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_marketing_status' => WhatsAppMarketingStatus::class,
            'whatsapp_marketing_opted_in_at' => 'datetime',
            'whatsapp_marketing_opted_out_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $customer): void {
            $customer->applyMarketingStatusTransition();
        });
    }

    protected function customerCode(): Attribute
    {
        return Attribute::set(function ($value) {
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isReferencedByOrders(): bool
    {
        return $this->orders()->exists();
    }

    public function canReceiveWhatsAppMarketing(): bool
    {
        return $this->whatsapp_marketing_status === WhatsAppMarketingStatus::Subscribed
            && $this->hasUsableWhatsAppNumber();
    }

    public function hasUsableWhatsAppNumber(): bool
    {
        $number = filled($this->whatsapp) ? $this->whatsapp : $this->phone;

        return PhoneNumber::isSyntacticallyUsable(is_string($number) ? $number : null);
    }

    /**
     * Centralized consent transition shared by Admin edits and the inbound
     * keyword opt-out. Timestamps are never written anywhere else.
     */
    private function applyMarketingStatusTransition(): void
    {
        $original = WhatsAppMarketingStatus::tryFrom((string) $this->getRawOriginal('whatsapp_marketing_status'));
        $current = $this->whatsapp_marketing_status ?? WhatsAppMarketingStatus::Unknown;

        if ($original === $current) {
            return;
        }

        match ($current) {
            WhatsAppMarketingStatus::Subscribed => $this->whatsapp_marketing_opted_in_at = now(),
            WhatsAppMarketingStatus::Unsubscribed => $this->whatsapp_marketing_opted_out_at = now(),
            WhatsAppMarketingStatus::Unknown => $this->clearMarketingTimestamps(),
        };
    }

    private function clearMarketingTimestamps(): void
    {
        $this->whatsapp_marketing_opted_in_at = null;
        $this->whatsapp_marketing_opted_out_at = null;
    }

    public static function validate(array $data): array
    {
        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) ($data['name'] ?? ''));
        }

        if (array_key_exists('phone', $data)) {
            $data['phone'] = trim((string) ($data['phone'] ?? ''));
        }

        Validator::make(
            $data,
            [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:255'],
                'customer_code' => ['nullable', 'string', 'max:255'],
            ],
        )->validate();

        return $data;
    }

    /**
     * Deterministic behavior with duplicate phones: the earliest
     * created customer (lowest id) is matched and refreshed.
     */
    public static function matchOrCreate(array $data): self
    {
        $data = self::validate($data);

        $customer = static::query()
            ->where('phone', $data['phone'])
            ->orderBy('id')
            ->first();

        if ($customer) {
            $customer->fill(collect($data)
                ->only(['customer_code', 'name', 'company_name', 'whatsapp', 'governorate', 'city', 'address'])
                ->filter(fn ($value) => filled($value))
                ->all());
            $customer->save();

            return $customer;
        }

        return static::create($data);
    }
}
