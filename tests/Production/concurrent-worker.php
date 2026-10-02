<?php

use App\Http\Controllers\OrderController;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDeliveryException;
use App\Models\OrderTransitionException;
use App\Support\Cart;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Tests\Production\DatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DatabaseGuard::verify();

file_put_contents($argv[4], 'ready');
echo "READY\n";
flush();

try {
    $action = $argv[1];
    $id = (int) $argv[2];
    if ($action === 'customer') {
        $customer = Customer::matchOrCreate([
            'name' => 'Concurrent customer', 'phone' => $argv[3],
        ]);
        echo 'OK:'.$customer->id;
    } elseif ($action === 'checkout') {
        Cart::add($id, 2);
        $method = new ReflectionMethod(OrderController::class, 'createOrderWithRetry');
        $order = $method->invoke(new OrderController, ['name' => 'Concurrent checkout', 'phone' => $argv[3]]);
        echo 'OK:'.$order->id;
    } elseif ($action === 'delivery') {
        Order::findOrFail($id)->recordDeliveries([
            (int) $argv[3] => ['quantity' => 2, 'expected_delivered' => 0],
        ]);
        echo 'OK';
    } elseif ($action === 'confirm') {
        Order::findOrFail($id)->confirm();
        echo 'OK';
    } elseif ($action === 'number') {
        Order::create([
            'customer_id' => $id, 'order_number' => $argv[3], 'total_quantity' => 0,
        ]);
        echo 'OK';
    } else {
        throw new RuntimeException('Unknown verification action.');
    }
} catch (OrderDeliveryException|OrderTransitionException $exception) {
    echo 'REJECTED';
} catch (QueryException $exception) {
    // Deliberately do not print SQL or bindings containing personal data/secrets.
    echo 'SQL_ERROR:'.($exception->errorInfo[1] ?? 'unknown');
    exit(2);
}
