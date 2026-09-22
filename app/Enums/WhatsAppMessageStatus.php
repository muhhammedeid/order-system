<?php

namespace App\Enums;

/**
 * Application-facing delivery state for outbound messages.
 *
 * Provider acknowledgements map onto this state monotonically: a later ack
 * never downgrades an earlier one, `failed` is terminal, and unknown ack
 * names leave the current state untouched. No business behavior depends on
 * `read`, which is not reliably emitted by the current engine/account.
 */
enum WhatsAppMessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function label(): string
    {
        return __("admin.whatsapp.statuses.{$this->value}");
    }

    public function rank(): int
    {
        return match ($this) {
            self::Pending => 1,
            self::Sent => 2,
            self::Delivered => 3,
            self::Read => 4,
            self::Failed => 5,
        };
    }

    public static function fromAckName(?string $ackName): ?self
    {
        return match (strtoupper((string) $ackName)) {
            'PENDING' => self::Pending,
            'SERVER' => self::Sent,
            'DEVICE' => self::Delivered,
            'READ' => self::Read,
            'ERROR' => self::Failed,
            default => null,
        };
    }

    /**
     * Whether this status may replace the current one. Unknown ack names are
     * filtered out before this point.
     */
    public function canBeAppliedTo(?self $current): bool
    {
        if ($current === null) {
            return true;
        }

        if ($current === self::Failed) {
            return false;
        }

        if ($this === self::Failed) {
            return $current->rank() <= self::Sent->rank();
        }

        return $this->rank() > $current->rank();
    }
}
