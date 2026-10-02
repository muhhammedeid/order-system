<?php

return [
    'model' => 'Product announcement campaign', 'models' => 'Product campaigns',
    'name' => 'Name', 'product' => 'Product', 'product_url' => 'Public product URL', 'scheduled_at' => 'Scheduled at',
    'audience' => 'Recipients', 'variations' => 'Template variations', 'status' => 'Status',
    'customer' => 'Customer', 'template' => 'Template', 'attempts' => 'Send attempts',
    'sent_at' => 'Sent at', 'failure_reason' => 'Failure reason', 'provider_reference' => 'Provider reference',
    'start' => 'Start', 'pause' => 'Pause', 'resume' => 'Resume', 'retry' => 'Retry before send',
    'invalid_audience' => 'Every selected customer must have marketing consent and a usable number.',
    'invalid_transition' => 'The campaign state changed. Refresh before continuing.',
    'invalid_product' => 'The product must be active and recipients selected.',
    'unsafe_retry' => 'Only failures before any send attempt can be retried. Resume a paused campaign separately.',
    'statuses' => ['draft' => 'Draft', 'running' => 'Running', 'paused' => 'Paused', 'completed' => 'Completed'],
];
