<?php

namespace App\Models;

use App\Support\WhatsApp\Templates\WhatsAppTemplateException;
use App\Support\WhatsApp\Templates\WhatsAppTemplateRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WhatsAppCampaign extends Model
{
    protected $table = 'whatsapp_campaigns';

    protected $fillable = ['name', 'product_id', 'scheduled_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'next_dispatch_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'campaign_id');
    }

    public static function createDraft(array $data): self
    {
        Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'product_id' => ['required', Rule::exists('products', 'id')->where('active', true)],
            'customer_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'customer_ids.*' => ['required', 'integer', 'distinct', Rule::exists('customers', 'id')],
            'template_ids' => ['required', 'array', 'min:1', 'max:20'],
            'template_ids.*' => ['required', 'integer', 'distinct', Rule::exists('whatsapp_templates', 'id')->where('active', true)->where('type', 'product_announcement')],
            'scheduled_at' => ['nullable', 'date'],
        ])->validate();

        return DB::transaction(function () use ($data): self {
            $product = Product::query()->where('active', true)->findOrFail($data['product_id']);
            $customers = Customer::query()->whereKey($data['customer_ids'])->orderBy('id')->get();
            $templates = WhatsAppTemplate::query()->whereKey($data['template_ids'])->orderBy('id')->get();
            foreach ($customers as $customer) {
                if (! $customer->canReceiveWhatsAppMarketing()) {
                    throw ValidationException::withMessages(['customer_ids' => __('campaigns.invalid_audience')]);
                }
                foreach ($templates as $template) {
                    // Validate all selected variations before writing any recipient.
                    try {
                        app(WhatsAppTemplateRenderer::class)->render($template, $customer, $product);
                    } catch (WhatsAppTemplateException $exception) {
                        throw ValidationException::withMessages(['template_ids' => $exception->getMessage()]);
                    }
                }
            }
            $campaign = self::query()->create(collect($data)->only(['name', 'product_id', 'scheduled_at'])->all());
            foreach ($customers as $index => $customer) {
                $campaign->recipients()->create(['customer_id' => $customer->id, 'template_id' => $templates[$index % $templates->count()]->id]);
            }

            return $campaign;
        });
    }

    public function start(): void
    {
        $this->transition(['draft'], 'running');
    }

    public function updateDraft(array $data): self
    {
        Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'product_id' => ['required', Rule::exists('products', 'id')->where('active', true)],
            'scheduled_at' => ['nullable', 'date'],
        ])->validate();

        return DB::transaction(function () use ($data): self {
            $campaign = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($campaign->status !== 'draft') {
                throw ValidationException::withMessages(['name' => __('campaigns.invalid_transition')]);
            }
            $product = Product::findOrFail($data['product_id']);
            foreach ($campaign->recipients()->with(['customer', 'template'])->get() as $recipient) {
                try {
                    app(WhatsAppTemplateRenderer::class)->render($recipient->template, $recipient->customer, $product);
                } catch (WhatsAppTemplateException $exception) {
                    throw ValidationException::withMessages(['product_id' => $exception->getMessage()]);
                }
            }
            $campaign->fill(collect($data)->only(['name', 'product_id', 'scheduled_at'])->all())->save();

            return $campaign;
        });
    }

    public function pause(): void
    {
        $this->transition(['running'], 'paused');
    }

    public function resume(): void
    {
        $this->transition(['paused'], 'running');
    }

    private function transition(array $from, string $to): void
    {
        DB::transaction(function () use ($from, $to): void {
            $campaign = self::query()->lockForUpdate()->findOrFail($this->id);
            if (! in_array($campaign->status, $from, true)) {
                throw ValidationException::withMessages(['status' => __('campaigns.invalid_transition')]);
            }
            if ($to === 'running' && (! $campaign->product->active || ! $campaign->recipients()->exists())) {
                throw ValidationException::withMessages(['status' => __('campaigns.invalid_product')]);
            }
            $campaign->status = $to;
            $campaign->next_dispatch_at = $to === 'running' ? max(now(), $campaign->scheduled_at ?? now()) : $campaign->next_dispatch_at;
            $campaign->save();
            $this->refresh();
        });
    }
}
