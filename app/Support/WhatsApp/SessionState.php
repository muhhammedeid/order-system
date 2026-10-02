<?php

namespace App\Support\WhatsApp;

/**
 * Normalized view of a WAHA session. `status` is the raw WAHA status
 * (STOPPED, STARTING, SCAN_QR_CODE, WORKING, FAILED, ...); it is kept
 * as-is so later work packages can map it without loss.
 */
class SessionState
{
    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly ?array $me = null,
        public readonly ?string $engine = null,
    ) {}

    public static function fromArray(string $name, array $data): self
    {
        $engine = $data['engine'] ?? null;

        if (is_array($engine)) {
            $engine = $engine['engine'] ?? null;
        }

        return new self(
            name: $name,
            status: (string) ($data['status'] ?? 'UNKNOWN'),
            me: is_array($data['me'] ?? null) ? $data['me'] : null,
            engine: is_string($engine) ? $engine : null,
        );
    }

    public function isWorking(): bool
    {
        return $this->status === 'WORKING';
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'me' => $this->me,
            'engine' => $this->engine,
        ];
    }
}
