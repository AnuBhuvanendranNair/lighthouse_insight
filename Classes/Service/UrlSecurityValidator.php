<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Service;

final readonly class UrlSecurityValidator
{
    public function isAllowedPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = trim(strtolower((string)($parts['host'] ?? '')), '[]');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        $addresses = $this->resolveAddresses($host);
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = (string)$record['ip'];
            }
            if (isset($record['ipv6'])) {
                $addresses[] = (string)$record['ipv6'];
            }
        }

        return $addresses;
    }
}
