<?php

return [
    // Comma-separated proxy addresses/CIDRs; direct nginx ingress trusts none.
    'proxies' => env('TRUSTED_PROXIES') ?: [],
];
