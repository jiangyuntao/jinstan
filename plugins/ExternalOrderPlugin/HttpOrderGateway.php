<?php

declare(strict_types=1);

namespace Jinstan\Plugins\ExternalOrderPlugin;

final class HttpOrderGateway implements OrderGateway
{
    private const MAX_RESPONSE_BYTES = 1_048_576;

    /** @param array<string, string> $parameters */
    public function __construct(
        private readonly string $url,
        private readonly array $parameters,
        private readonly ?OutboundUrlPolicy $urlPolicy = null,
    ) {
    }

    public function submit(int $productId, string $email): string
    {
        $payload = json_encode($this->payload([
            'product_id' => $productId,
            'email' => $email,
        ]), JSON_THROW_ON_ERROR);
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('外部订单服务需要 PHP cURL 扩展。');
        }
        $target = ($this->urlPolicy ?? new OutboundUrlPolicy())->validate($this->url);
        $response = '';
        $handle = curl_init($this->url);
        if ($handle === false) {
            throw new \RuntimeException('无法初始化外部订单请求。');
        }
        $resolve = array_map(
            static fn (string $address): string => sprintf(
                '%s:%d:%s',
                $target['host'],
                $target['port'],
                str_contains($address, ':') ? '[' . $address . ']' : $address,
            ),
            $target['addresses'],
        );
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    return 0;
                }
                $response .= $chunk;

                return strlen($chunk);
            },
        ]);
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS_STR, 'http,https');
        } else {
            curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        try {
            $succeeded = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }
        if ($succeeded !== true || $status < 200 || $status >= 300) {
            throw new \RuntimeException('外部订单服务提交失败。');
        }
        $data = json_decode($response, true);
        if (is_array($data) && is_scalar($data['reference'] ?? null)) {
            return (string) $data['reference'];
        }

        return 'REMOTE-' . substr(hash('sha256', $response), 0, 12);
    }

    /**
     * @param array{product_id: int, email: string} $order
     * @return array<string, int|string>
     */
    private function payload(array $order): array
    {
        $payload = [];
        foreach ($this->parameters as $target => $source) {
            if (!is_string($target) || $target === '' || !is_string($source) || !array_key_exists($source, $order)) {
                throw new \RuntimeException('外部订单接收参数配置无效。');
            }
            $payload[$target] = $order[$source];
        }

        return $payload;
    }
}
