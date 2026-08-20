<?php

namespace App\Services\Audit;

use Exception;

class SsrfGuard
{
    public function assertUrlAllowed(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new Exception('Некорректный URL.');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http','https'], true)) {
            throw new Exception('Разрешены только http/https.');
        }

        $host = $parts['host'];

        // Block obvious local hosts
        $lowerHost = strtolower($host);
        if (in_array($lowerHost, ['localhost'], true) || str_ends_with($lowerHost, '.local')) {
            throw new Exception('Запрещённый хост.');
        }

        // Restrict ports
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!in_array((int)$port, [80, 443], true)) {
            throw new Exception('Разрешены только порты 80 и 443.');
        }

        // Resolve DNS -> IPs and block private ranges
        $ips = $this->resolveHostToIps($host);
        if (empty($ips)) {
            throw new Exception('Не удалось определить IP адрес хоста.');
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateOrLocalIp($ip)) {
                throw new Exception('Запрещённый IP (локальная/приватная сеть).');
            }
            // block cloud metadata (IPv4)
            if ($ip === '169.254.169.254') {
                throw new Exception('Запрещённый IP.');
            }
        }
    }

    private function resolveHostToIps(string $host): array
    {
        $ips = [];

        // If host is already an IP:
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        // A records
        $recordsA = dns_get_record($host, DNS_A) ?: [];
        foreach ($recordsA as $r) {
            if (!empty($r['ip'])) $ips[] = $r['ip'];
        }

        // AAAA records
        $recordsAAAA = dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($recordsAAAA as $r) {
            if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
        }

        return array_values(array_unique($ips));
    }

    private function isPrivateOrLocalIp(string $ip): bool
    {
        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) return true;

            $ranges = [
                ['0.0.0.0','0.255.255.255'],         // "this" network
                ['10.0.0.0','10.255.255.255'],
                ['127.0.0.0','127.255.255.255'],
                ['169.254.0.0','169.254.255.255'],   // link-local
                ['172.16.0.0','172.31.255.255'],
                ['192.168.0.0','192.168.255.255'],
                ['224.0.0.0','239.255.255.255'],     // multicast
            ];

            foreach ($ranges as [$start, $end]) {
                if ($long >= ip2long($start) && $long <= ip2long($end)) return true;
            }
            return false;
        }

        // IPv6 (basic blocks)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $lower = strtolower($ip);
            return str_starts_with($lower, '::1')
                || str_starts_with($lower, 'fc')
                || str_starts_with($lower, 'fd')
                || str_starts_with($lower, 'fe80');
        }

        return true;
    }
}
