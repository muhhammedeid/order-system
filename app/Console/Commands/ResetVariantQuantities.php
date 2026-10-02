<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetVariantQuantities extends Command
{
    protected $signature = 'products:reset-variant-quantities';

    protected $description = 'Set available quantity to 50 for all existing product variants';

    public function handle(): int
    {
        $updated = DB::transaction(fn (): int => ProductVariant::query()
            ->where('available_quantity', '!=', 50)
            ->update(['available_quantity' => 50]));

        $this->info("Updated {$updated} variants to an available quantity of 50.");

        return self::SUCCESS;
    }
}
