<?php

namespace Mublo\Core\Report\Audit;

class ReportAuditLogger
{
    public function log(string $event, array $context = []): void
    {
        $payload = [
            'event' => $event,
            'at' => date('c'),
            'context' => $context,
        ];

        error_log('[Report] ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}

