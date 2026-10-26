<?php
/**
 * Bantay Aso — Web Fetcher Engine
 * Pure native PHP cURL implementation for live snapshot retrieval.
 * Captures status code, latency, headers, security posture, and raw body.
 * Zero shell or CLI dependencies (Virtualmin VPS native).
 */

declare(strict_types=1);

class WebFetcher
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; BantayAso-QA-Bot/1.0; +https://github.com/bantayaso/qa)';
    private const DEFAULT_TIMEOUT = 25; // seconds

    /**
     * Fetch a live target website and extract diagnostic metadata.
     *
     * @param string $url The target website URL
     * @param int $timeout Connection timeout in seconds
     * @return array Diagnostic results including body, headers, latency, security audit
     */
    /**
     * Validate target URL against SSRF vulnerabilities.
     * Rejects non-HTTP/HTTPS schemes, loopbacks, link-locals, and private IP blocks.
     */
    public static function validateTargetUrl(string $url): string
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid target URL format.');
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Target URL must contain a valid scheme and host.');
        }

        $scheme = strtolower((string)$parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidArgumentException("Unsupported URL scheme '{$scheme}'. Only HTTP and HTTPS are permitted.");
        }

        $host = strtolower((string)$parts['host']);
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $host === '0.0.0.0') {
            throw new InvalidArgumentException("Access to loopback target '{$host}' is prohibited.");
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolvedV4 = @gethostbynamel($host);
            if (is_array($resolvedV4)) {
                $ips = array_merge($ips, $resolvedV4);
            }
            if (function_exists('dns_get_record')) {
                $recA = @dns_get_record($host, DNS_A);
                if (is_array($recA)) {
                    foreach ($recA as $r) {
                        if (!empty($r['ip'])) $ips[] = $r['ip'];
                    }
                }
                $recAaaa = @dns_get_record($host, DNS_AAAA);
                if (is_array($recAaaa)) {
                    foreach ($recAaaa as $r) {
                        if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
                    }
                }
            }
        }

        $ips = array_unique($ips);
        if (empty($ips)) {
            throw new InvalidArgumentException("Unable to resolve DNS records for target host '{$host}'.");
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException("Target host '{$host}' resolves to restricted/private IP address: {$ip}");
            }
            if (str_starts_with($ip, '169.254.') || str_starts_with($ip, '127.') || $ip === '::1') {
                throw new InvalidArgumentException("Target host '{$host}' resolves to forbidden link-local address: {$ip}");
            }
        }

        return $url;
    }

    /**
     * Resolve relative redirect location against base URL.
     */
    private static function resolveRelativeUrl(string $base, string $rel): string
    {
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }
        if (str_starts_with($rel, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return "{$scheme}:{$rel}";
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        if (str_starts_with($rel, '/')) {
            return "{$scheme}://{$host}{$port}{$rel}";
        }
        $path = $parts['path'] ?? '/';
        $dir = preg_replace('#/[^/]*$#', '', $path);
        return "{$scheme}://{$host}{$port}{$dir}/{$rel}";
    }

    /**
     * Fetch a live target website and extract diagnostic metadata.
     * Enforces SSRF validation and safe manual redirect verification.
     *
     * @param string $url The target website URL
     * @param int $timeout Connection timeout in seconds
     * @return array Diagnostic results including body, headers, latency, security audit
     */
    public static function fetch(string $url, int $timeout = self::DEFAULT_TIMEOUT): array
    {
        $currentUrl = trim($url);
        $redirectCount = 0;
        $maxRedirects = 5;
        $totalDuration = 0;

        while (true) {
            try {
                self::validateTargetUrl($currentUrl);
            } catch (InvalidArgumentException $e) {
                return [
                    'success' => false,
                    'error' => 'SSRF Guard Block: ' . $e->getMessage(),
                    'status_code' => 0,
                    'response_time_ms' => $totalDuration,
                    'content_type' => 'text/html',
                    'raw_headers' => '',
                    'security_headers' => [],
                    'raw_body' => '',
                ];
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $currentUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false, // SSRF Guard: manual validated redirect loop
                CURLOPT_TIMEOUT => max(4, $timeout - (int)floor($totalDuration / 1000)),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Cache-Control: no-cache',
                ],
            ]);

            $rawResponse = curl_exec($ch);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $info = curl_getinfo($ch);
            $httpCode = (int)($info['http_code'] ?? 0);
            $hopTime = (int)round(($info['total_time'] ?? 0) * 1000);
            $totalDuration += $hopTime;
            $headerSize = (int)($info['header_size'] ?? 0);
            $contentType = (string)($info['content_type'] ?? 'text/html');
            curl_close($ch);

            if ($curlErrno !== 0 || $rawResponse === false) {
                return [
                    'success' => false,
                    'error' => 'cURL error (' . $curlErrno . '): ' . $curlError,
                    'status_code' => $httpCode ?: 504,
                    'response_time_ms' => $totalDuration,
                    'content_type' => $contentType,
                    'raw_headers' => '',
                    'security_headers' => [],
                    'raw_body' => '',
                ];
            }

            $rawHeaders = substr((string)$rawResponse, 0, $headerSize);
            $rawBody = substr((string)$rawResponse, $headerSize);

            // Handle HTTP redirects securely with SSRF re-validation
            if (in_array($httpCode, [301, 302, 303, 307, 308], true)) {
                if ($redirectCount >= $maxRedirects) {
                    return [
                        'success' => false,
                        'error' => "Exceeded maximum redirect limit ({$maxRedirects}).",
                        'status_code' => $httpCode,
                        'response_time_ms' => $totalDuration,
                        'content_type' => $contentType,
                        'raw_headers' => trim($rawHeaders),
                        'security_headers' => [],
                        'raw_body' => $rawBody,
                    ];
                }

                if (preg_match('/^Location:\s*(.*?)$/im', $rawHeaders, $matches)) {
                    $location = trim($matches[1]);
                    $nextUrl = self::resolveRelativeUrl($currentUrl, $location);
                    $currentUrl = $nextUrl;
                    $redirectCount++;
                    continue;
                }
            }

            // Terminal response reached
            $parsedHeaders = self::parseHeaders($rawHeaders);
            $securityHeaders = self::auditSecurityHeaders($parsedHeaders);

            return [
                'success' => true,
                'status_code' => $httpCode,
                'response_time_ms' => $totalDuration,
                'content_type' => $contentType,
                'effective_url' => $currentUrl,
                'redirect_count' => $redirectCount,
                'raw_headers' => trim($rawHeaders),
                'parsed_headers' => $parsedHeaders,
                'security_headers' => $securityHeaders,
                'raw_body' => $rawBody,
            ];
        }
    }

    /**
     * Inspect live SSL/TLS certificate posture.
     *
     * @param string $url Target URL
     * @return array [
     *   'status' => 'VALID' | 'EXPIRING_SOON' | 'EXPIRED' | 'INVALID' | 'NOT_HTTPS',
     *   'issuer' => ?string,
     *   'subject' => ?string,
     *   'valid_from' => ?string,
     *   'valid_to' => ?string,
     *   'days_left' => ?int,
     *   'error' => ?string
     * ]
     */
    public static function inspectSsl(string $url): array
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'https') {
            return [
                'status' => 'NOT_HTTPS',
                'issuer' => null,
                'subject' => null,
                'valid_from' => null,
                'valid_to' => null,
                'days_left' => null,
                'error' => 'Target does not use HTTPS encryption.',
            ];
        }

        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? (int)$parts['port'] : 443;

        try {
            self::validateTargetUrl($url);
        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'INVALID',
                'issuer' => null,
                'subject' => null,
                'valid_from' => null,
                'valid_to' => null,
                'days_left' => null,
                'error' => 'SSRF policy blocked host: ' . $e->getMessage(),
            ];
        }

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$client) {
            return [
                'status' => 'INVALID',
                'issuer' => null,
                'subject' => null,
                'valid_from' => null,
                'valid_to' => null,
                'days_left' => null,
                'error' => "SSL Handshake failure ({$errno}): {$errstr}",
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        if (empty($params['options']['ssl']['peer_certificate'])) {
            return [
                'status' => 'INVALID',
                'issuer' => null,
                'subject' => null,
                'valid_from' => null,
                'valid_to' => null,
                'days_left' => null,
                'error' => 'No peer certificate presented by host.',
            ];
        }

        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if (!$cert || empty($cert['validTo_time_t'])) {
            return [
                'status' => 'INVALID',
                'issuer' => null,
                'subject' => null,
                'valid_from' => null,
                'valid_to' => null,
                'days_left' => null,
                'error' => 'Failed to parse peer X.509 certificate.',
            ];
        }

        $validTo = (int)$cert['validTo_time_t'];
        $validFrom = (int)($cert['validFrom_time_t'] ?? 0);
        $daysLeft = (int)floor(($validTo - time()) / 86400);

        $issuer = $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unknown Issuer';
        if (is_array($issuer)) $issuer = implode(', ', $issuer);
        $subject = $cert['subject']['CN'] ?? $cert['subject']['O'] ?? $host;
        if (is_array($subject)) $subject = implode(', ', $subject);

        $status = 'VALID';
        if ($daysLeft <= 0) {
            $status = 'EXPIRED';
        } elseif ($daysLeft <= 14) {
            $status = 'EXPIRING_SOON';
        }

        return [
            'status' => $status,
            'issuer' => (string)$issuer,
            'subject' => (string)$subject,
            'valid_from' => date('Y-m-d H:i:s', $validFrom),
            'valid_to' => date('Y-m-d H:i:s', $validTo),
            'days_left' => $daysLeft,
            'error' => null,
        ];
    }

    /**
     * Parse raw HTTP response headers into an associative array.
     */
    private static function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        // Split header sections in case of redirects
        $sections = explode("\r\n\r\n", trim($rawHeaders));
        $lastSection = end($sections);

        $lines = explode("\r\n", (string)$lastSection);
        $cookies = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $headers['HTTP_STATUS_LINE'] = $line;
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                if ($name === 'set-cookie') {
                    $cookies[] = $value;
                }
                $headers[$name] = $value;
            }
        }
        if (!empty($cookies)) {
            $headers['set-cookie-list'] = $cookies;
        }
        return $headers;
    }

    /**
     * Audit critical HTTP security headers according to ECC rules (rules/web/security.md).
     */
    private static function auditSecurityHeaders(array $headers): array
    {
        $keys = [
            'content-security-policy' => [
                'title' => 'Content Security Policy (CSP)',
                'present' => false,
                'value' => null,
                'grade' => 'warning',
            ],
            'strict-transport-security' => [
                'title' => 'Strict-Transport-Security (HSTS)',
                'present' => false,
                'value' => null,
                'grade' => 'warning',
            ],
            'x-frame-options' => [
                'title' => 'X-Frame-Options (Clickjacking)',
                'present' => false,
                'value' => null,
                'grade' => 'warning',
            ],
            'x-content-type-options' => [
                'title' => 'X-Content-Type-Options (MIME Sniffing)',
                'present' => false,
                'value' => null,
                'grade' => 'warning',
            ],
            'referrer-policy' => [
                'title' => 'Referrer-Policy',
                'present' => false,
                'value' => null,
                'grade' => 'info',
            ],
            'permissions-policy' => [
                'title' => 'Permissions-Policy',
                'present' => false,
                'value' => null,
                'grade' => 'info',
            ],
        ];

        foreach ($keys as $headerKey => &$audit) {
            if (isset($headers[$headerKey])) {
                $audit['present'] = true;
                $audit['value'] = $headers[$headerKey];
                $audit['grade'] = 'pass';
            }
        }

        return $keys;
    }
}
