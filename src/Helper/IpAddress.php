<?php

namespace HiPay\Payment\Helper;

use Symfony\Component\HttpFoundation\Request;

/**
 * Helper class to detect the real customer IP address behind proxies.
 */
class IpAddress
{
    /**
     * Headers to check for real client IP.
     */
    private const IP_HEADERS = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];

    /**
     * Private IP ranges that should never be returned as customer IP.
     */
    private const PRIVATE_IP_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        'fd00::/8',
        '::1/128',
        'fe80::/10',
    ];

    
    public static function getClientIp(Request $request): ?string
    {
        $ip = $request->getClientIp();

        if ($ip && !self::isPrivateIp($ip)) {
            return $ip;
        }

        return self::getIpFromHeaders();
    }

   
    private static function getIpFromHeaders(): ?string
    {
        foreach (self::IP_HEADERS as $header) {
            if (!isset($_SERVER[$header]) || empty($_SERVER[$header])) {
                continue;
            }

            $value = $_SERVER[$header];

            $ips = array_map('trim', explode(',', $value));

            foreach ($ips as $ip) {
                $ip = self::cleanIp($ip);

                if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    
    private static function isPrivateIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        return false;
    }

    
    private static function cleanIp(string $ip): ?string
    {
        $ip = preg_replace('/:\d+$/', '', $ip);

        $ip = trim($ip, '[]');

        $ip = trim($ip);

        return $ip ?: null;
    }

}
