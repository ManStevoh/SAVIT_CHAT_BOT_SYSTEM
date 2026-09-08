<?php
// Temporary diagnostic + OPcache reset — delete after use
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo json_encode(['opcache_reset' => true, 'ts' => time()]);
} else {
    echo json_encode(['opcache_reset' => false, 'reason' => 'opcache not available']);
}
