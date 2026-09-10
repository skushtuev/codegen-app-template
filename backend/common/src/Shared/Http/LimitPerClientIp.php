<?php

declare(strict_types=1);

namespace Common\Shared\Http;

use Common\Shared\Http\Exception\InternalException;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\RateLimiter\Policy\LimitPolicyInterface;

/**
 * Buckets rate limiting per client IP and route.
 *
 * Replaces the package's LimitPerIp, which reads REMOTE_ADDR only and falls back to an empty IP when
 * it is missing: the fingerprint then degrades to method + path and every client silently shares one
 * bucket. Here the forwarded headers are consulted as well, and an unresolvable IP throws.
 */
final readonly class LimitPerClientIp implements LimitPolicyInterface
{
    /**
     * Forwarded headers to read the client IP from, in order of preference. `Forwarded` (RFC 7239) is
     * deliberately absent: traefik strips incoming `X-Forwarded-*` but passes `Forwarded` through, so
     * trusting it would let a client choose its own bucket.
     */
    private const FORWARDED_HEADERS = [
        'X-Forwarded-For',
        'X-Real-IP',
    ];

    public function fingerprint(ServerRequestInterface $request): string
    {
        $ip = $this->clientIp($request);

        // Fail loud: an unknown IP would collapse every client into a single bucket.
        if ($ip === null) {
            throw new InternalException('Client IP is unknown, rate limiting cannot be applied.');
        }

        return sha1(strtolower($request->getMethod() . $request->getUri()->getPath()) . $ip);
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        foreach (self::FORWARDED_HEADERS as $header) {
            $value = $request->getHeaderLine($header);
            if ($value === '') {
                continue;
            }

            // Right to left: the rightmost entry is the one our own proxy appended. Everything left
            // of it was supplied by the client and can be forged, so it must never win.
            foreach (array_reverse(explode(',', $value)) as $candidate) {
                $ip = $this->normalize($candidate);
                if ($ip !== null) {
                    return $ip;
                }
            }
        }

        $remoteAddr = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($remoteAddr) ? $this->normalize($remoteAddr) : null;
    }

    private function normalize(string $value): ?string
    {
        $value = trim($value);

        // Some proxies append a port: "1.2.3.4:5678" or "[2001:db8::1]:5678".
        if (preg_match('/^\[(?<ip>.+)](?::\d+)?$/', $value, $matches) === 1) {
            $value = $matches['ip'];
        } elseif (substr_count($value, ':') === 1) {
            $value = strstr($value, ':', true) ?: $value;
        }

        return filter_var($value, FILTER_VALIDATE_IP) === false ? null : $value;
    }
}
