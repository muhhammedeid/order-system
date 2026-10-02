<?php

namespace App\Models;

use RuntimeException;

/**
 * Pending-order item edit failures (payload ownership, invalid selection,
 * invalid quantities). Messages are safe for Admin UI.
 */
class OrderItemException extends RuntimeException {}
