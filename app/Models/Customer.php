<?php

namespace App\Models;

use App\Enums\WhatsAppMarketingStatus;
use App\Rules\UsablePhoneNumber;
use App\Support\WhatsApp\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
            $customer->phone_normalized = PhoneNumber::normalize($customer->phone);
            $customer->whatsapp_normalized = PhoneNumber::normalize($customer->whatsapp);
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

        $this->whatsapp_marketing_status = $current;

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
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (isset($data['phone']) && is_string($data['phone'])) {
            $data['phone'] = trim($data['phone']);
        }

        Validator::make(
            $data,
            [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:255', new UsablePhoneNumber],
                'whatsapp' => ['nullable', 'string', 'max:255', new UsablePhoneNumber],
                'customer_code' => ['nullable', 'string', 'max:255'],
            ],
        )->validate();

        return $data;
    }

    /**
     * Deterministic behavior with duplicate phones: the earliest
     * created customer (lowest id) is matched. Public checkout must not
     * overwrite a saved customer profile based only on a supplied phone.
     */
    public static function matchOrCreate(array $data): self
    {
        $data = self::validate($data);

        return DB::transaction(function () use ($data): self {
            $normalized = PhoneNumber::normalize($data['phone']);
            $lockKey = hash('sha256', $normalized);
            DB::table('customer_phone_locks')->insertOrIgnore(['phone_key' => $lockKey]);
            DB::table('customer_phone_locks')->where('phone_key', $lockKey)->lockForUpdate()->first();

            return self::matchUnderLock($data, $normalized);
        });
    }

    private static function matchUnderLock(array $data, string $normalized): self
    {

        $customer = static::query()
            ->where('phone_normalized', $normalized)
            ->orderBy('id')
            ->first();

        if ($customer) {
            return $customer;
        }

        return static::create($data);
    }
}
