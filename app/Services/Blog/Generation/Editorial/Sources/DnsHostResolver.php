<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Sources;

use App\Contracts\Blog\Generation\HostResolver;

/** Resolution par le DNS du systeme : IPv4, puis IPv6. */
final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        $records = @dns_get_record($host, DNS_AAAA) ?: [];

        foreach ($records as $record) {
            if (is_string($record['ipv6'] ?? null)) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
