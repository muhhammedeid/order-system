<?php

return [
    'title' => 'WhatsApp delivery', 'kind' => 'Purpose', 'order' => 'Order', 'customer' => 'Customer',
    'template' => 'Template', 'status' => 'Status', 'attempts' => 'Send attempts', 'resolution_attempts' => 'Contact failures',
    'reason' => 'Failure reason', 'sent_at' => 'Sent at', 'created_at' => 'Created at', 'retry' => 'Retry before sending',
    'kinds' => ['order_customer' => 'Customer order update', 'order_owner' => 'Owner notification', 'campaign' => 'Product campaign'],
    'statuses' => ['pending' => 'Pending', 'processing' => 'Sending', 'sent' => 'Sent', 'failed' => 'Failed before send',
        'unknown' => 'Outcome unknown — do not resend', 'skipped' => 'Skipped'],
];
