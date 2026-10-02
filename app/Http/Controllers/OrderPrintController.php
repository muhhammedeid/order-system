<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;

class OrderPrintController extends Controller
{
    public function __invoke(Order $order): View
    {
        $order->load(['customer', 'items']);

        $panel = Filament::getPanel('admin');

        $publicItemsTotal = '0.00';
        $hasPublicPricedItems = false;

        foreach ($order->items as $item) {
            if ($item->unit_price === null) {
                continue;
            }

            $hasPublicPricedItems = true;
            $publicItemsTotal = bcadd(
                $publicItemsTotal,
                bcmul((string) $item->unit_price, (string) $item->quantity, 2),
                2,
            );
        }

        return view('orders.print', [
            'order' => $order,
            'brandName' => strip_tags((string) $panel->getBrandName()),
            'brandLogo' => $panel->getBrandLogo(),
            'hasPublicPricedItems' => $hasPublicPricedItems,
            'hasRequestPriceItems' => $order->items->contains(fn ($item): bool => $item->unit_price === null),
            'publicItemsTotal' => $publicItemsTotal,
        ]);
    }
}
