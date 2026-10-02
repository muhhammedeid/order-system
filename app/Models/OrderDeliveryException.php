<?php

namespace App\Models;

use RuntimeException;

/**
 * Delivery-operation failures (stale delivery state, over-delivery,
 * zero-only submissions, unknown items). Messages are safe for Admin UI.
 */
class OrderDeliveryException extends RuntimeException {}
