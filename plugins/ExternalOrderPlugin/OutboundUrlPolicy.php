<?php

declare(strict_types=1);

namespace Jinstan\Plugins\ExternalOrderPlugin;

final class OutboundUrlPolicy
{
    private readonly \Closure $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver === null
            ? static fn (string $host): array => self::resolve($host)
            : $resolver(...);
    }

    /** @return array{host: string, port: int, addresses: list<string>} */
    public function validate(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('外部订单 URL 无效。');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($host === '' || !in_array($port, [80, 443], true)) {
            throw new \InvalidArgumentException('外部订单 URL 只允许使用 80 或 443 端口。');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : ($this->resolver)($host);
        $addresses = array_values(array_unique(array_filter($addresses, 'is_string')));
        if ($addresses === []) {
            throw new \InvalidArgumentException('外部订单 URL 无法解析。');
        }
        foreach ($addresses as $address) {
            if (filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                throw new \InvalidArgumentException('外部订单 URL 不得指向本机、内网或保留地址。');
            }
        }

        return ['host' => $host, 'port' => $port, 'addresses' => $addresses];
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (preg_match('/^[a-z0-9.-]+$/D', $host) !== 1
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return [];
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records)) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
