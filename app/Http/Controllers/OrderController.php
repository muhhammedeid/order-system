<?php

namespace App\Http\Controllers;

use App\Support\Cart;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function checkout(): Response
    {
        return Inertia::render('Checkout/Index', Cart::hydrated());
    }
}
