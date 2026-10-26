<?php
/**
 * Bantay Aso — Passive Security & Vulnerability Scanner Engine
 * Deterministic, non-intrusive security posture assessment.
 * Evaluates HTTP response headers, SSL certificates, cookie security flags,
 * DOM content hygiene, information leakage, and CORS configurations.
 * 
 * Strict ECC zero-shell & zero-external-dependency compliance.
 */

declare(strict_types=1);

class SecurityScanner
{
    /**
     * Conduct a comprehensive passive security posture audit on a fetched target.
     *
     * @param string $targetUrl The evaluated website URL
     * @param array $fetchResult Data payload returned by WebFetcher::fetch()
     * @param array $sslResult Data payload returned by WebFetcher::inspectSsl()
     * @return array Standardized security report with score, grade, and findings
     */
    public static function auditTarget(string $targetUrl, array $fetchResult, array $sslResult = []): array
    {
        $findings = [];
        $rawHeaders = (string)($fetchResult['raw_headers'] ?? '');
        $parsedHeaders = (array)($fetchResult['parsed_headers'] ?? []);
        $rawBody = (string)($fetchResult['raw_body'] ?? '');
        $statusCode = (int)($fetchResult['status_code'] ?? 0);
        $isHttps = (parse_url($targetUrl, PHP_URL_SCHEME) === 'https');

        // =========================================================================
        // 1. HTTP SECURITY HEADERS AUDIT
        // =========================================================================

        // Content-Security-Policy (CSP)
        if (isset($parsedHeaders['content-security-policy'])) {
            $csp = $parsedHeaders['content-security-policy'];
            if (str_contains($csp, "'unsafe-inline'") && !str_contains($csp, 'nonce-') && !str_contains($csp, 'sha256-')) {
                $findings[] = [
                    'id' => 'CSP_UNSAFE_INLINE',
                    'category' => 'HEADER',
                    'severity' => 'MEDIUM',
                    'title' => 'Permissive Content-Security-Policy (Unsafe Inline)',
                    'evidence' => "CSP header contains 'unsafe-inline' without cryptographic nonces: " . substr($csp, 0, 120) . "...",
                    'remediation' => 'Replace unsafe-inline with cryptographically random per-request nonces or hashes.'
                ];
            } else {
                $findings[] = [
                    'id' => 'CSP_PRESENT',
                    'category' => 'HEADER',
                    'severity' => 'PASS',
                    'title' => 'Content-Security-Policy Enforced',
                    'evidence' => 'Valid CSP policy header detected (' . substr($csp, 0, 80) . '...).',
                    'remediation' => 'Maintain strict CSP directives as new routes and components evolve.'
                ];
            }
        } else {
            $findings[] = [
                'id' => 'MISSING_CSP',
                'category' => 'HEADER',
                'severity' => 'HIGH',
                'title' => 'Missing Content-Security-Policy (CSP) Header',
                'evidence' => 'No Content-Security-Policy header received in HTTP response.',
                'remediation' => 'Deploy a Content-Security-Policy header restricting script-src, object-src, and frame-ancestors.'
            ];
        }

        // Strict-Transport-Security (HSTS)
        if ($isHttps) {
            if (isset($parsedHeaders['strict-transport-security'])) {
                $hsts = $parsedHeaders['strict-transport-security'];
                $hasIncludeSubDomains = str_contains(strtolower($hsts), 'includesubdomains');
                $hasPreload = str_contains(strtolower($hsts), 'preload');
                $maxAge = 0;
                if (preg_match('/max-age=(\d+)/i', $hsts, $m)) {
                    $maxAge = (int)$m[1];
                }

                if ($maxAge < 15552000) { // < 180 days
                    $findings[] = [
                        'id' => 'HSTS_SHORT_MAX_AGE',
                        'category' => 'HEADER',
                        'severity' => 'LOW',
                        'title' => 'HSTS Duration Below Recommended Baseline',
                        'evidence' => "HSTS max-age is {$maxAge} seconds (recommended >= 31536000).",
                        'remediation' => 'Increase HSTS max-age to 31536000 (1 year) and include includeSubDomains.'
                    ];
                } else {
                    $findings[] = [
                        'id' => 'HSTS_PRESENT',
                        'category' => 'HEADER',
                        'severity' => 'PASS',
                        'title' => 'Strict-Transport-Security (HSTS) Active',
                        'evidence' => "HSTS max-age={$maxAge}" . ($hasIncludeSubDomains ? ' with includeSubDomains' : ''),
                        'remediation' => 'Keep HSTS active to defend against SSL stripping attacks.'
                    ];
                }
            } else {
                $findings[] = [
                    'id' => 'MISSING_HSTS',
                    'category' => 'HEADER',
                    'severity' => 'HIGH',
                    'title' => 'Missing Strict-Transport-Security (HSTS) Header',
                    'evidence' => 'Target is served over HTTPS but lacks the Strict-Transport-Security header.',
                    'remediation' => 'Add Strict-Transport-Security: max-age=31536000; includeSubDomains to web server configuration.'
                ];
            }
        }

        // X-Frame-Options (Clickjacking Protection)
        if (isset($parsedHeaders['x-frame-options'])) {
            $xfo = strtoupper(trim($parsedHeaders['x-frame-options']));
            if ($xfo === 'DENY' || $xfo === 'SAMEORIGIN') {
                $findings[] = [
                    'id' => 'XFO_PRESENT',
                    'category' => 'HEADER',
                    'severity' => 'PASS',
                    'title' => 'Clickjacking Protection (X-Frame-Options)',
                    'evidence' => "X-Frame-Options set to {$xfo}.",
                    'remediation' => 'Preserve frame protection against UI redressing attacks.'
                ];
            } else {
                $findings[] = [
                    'id' => 'XFO_WEAK',
                    'category' => 'HEADER',
                    'severity' => 'MEDIUM',
                    'title' => 'Non-Standard X-Frame-Options Directive',
                    'evidence' => "X-Frame-Options configured with '{$xfo}', which may be deprecated or unsupported.",
                    'remediation' => 'Use DENY or SAMEORIGIN, or employ CSP frame-ancestors directive.'
                ];
            }
        } elseif (!isset($parsedHeaders['content-security-policy']) || !str_contains($parsedHeaders['content-security-policy'], 'frame-ancestors')) {
            $findings[] = [
                'id' => 'MISSING_XFO',
                'category' => 'HEADER',
                'severity' => 'MEDIUM',
                'title' => 'Missing X-Frame-Options / Clickjacking Guard',
                'evidence' => 'Neither X-Frame-Options nor CSP frame-ancestors directive is defined.',
                'remediation' => 'Set X-Frame-Options: SAMEORIGIN or CSP frame-ancestors \'self\'.'
            ];
        }

        // X-Content-Type-Options (MIME Sniffing)
        if (isset($parsedHeaders['x-content-type-options'])) {
            $xcto = strtolower(trim($parsedHeaders['x-content-type-options']));
            if ($xcto === 'nosniff') {
                $findings[] = [
                    'id' => 'XCTO_PRESENT',
                    'category' => 'HEADER',
                    'severity' => 'PASS',
                    'title' => 'MIME Sniffing Protection Enabled',
                    'evidence' => 'X-Content-Type-Options: nosniff present.',
                    'remediation' => 'Continue enforcing nosniff on all static and dynamic responses.'
                ];
            } else {
                $findings[] = [
                    'id' => 'XCTO_INVALID',
                    'category' => 'HEADER',
                    'severity' => 'LOW',
                    'title' => 'Invalid X-Content-Type-Options Value',
                    'evidence' => "Received '{$xcto}' instead of expected 'nosniff'.",
                    'remediation' => 'Set X-Content-Type-Options: nosniff.'
                ];
            }
        } else {
            $findings[] = [
                'id' => 'MISSING_XCTO',
                'category' => 'HEADER',
                'severity' => 'LOW',
                'title' => 'Missing X-Content-Type-Options Header',
                'evidence' => 'X-Content-Type-Options header not found in server response.',
                'remediation' => 'Add X-Content-Type-Options: nosniff to prevent browsers from interpreting non-script MIME types as executable.'
            ];
        }

        // Referrer-Policy
        if (isset($parsedHeaders['referrer-policy'])) {
            $findings[] = [
                'id' => 'REFERRER_POLICY_PRESENT',
                'category' => 'HEADER',
                'severity' => 'PASS',
                'title' => 'Referrer-Policy Defined',
                'evidence' => 'Referrer-Policy set to: ' . $parsedHeaders['referrer-policy'],
                'remediation' => 'Maintain strict referrer policies such as strict-origin-when-cross-origin.'
            ];
        } else {
            $findings[] = [
                'id' => 'MISSING_REFERRER_POLICY',
                'category' => 'HEADER',
                'severity' => 'LOW',
                'title' => 'Missing Referrer-Policy Header',
                'evidence' => 'No Referrer-Policy header declared in HTTP response.',
                'remediation' => 'Set Referrer-Policy: strict-origin-when-cross-origin to avoid leaking query parameters in referrers.'
            ];
        }

        // Permissions-Policy
        if (isset($parsedHeaders['permissions-policy'])) {
            $findings[] = [
                'id' => 'PERMISSIONS_POLICY_PRESENT',
                'category' => 'HEADER',
                'severity' => 'PASS',
                'title' => 'Permissions-Policy Enforced',
                'evidence' => 'Permissions-Policy configured: ' . substr($parsedHeaders['permissions-policy'], 0, 80),
                'remediation' => 'Regularly review restricted browser hardware APIs (geolocation, camera, microphone).'
            ];
        } else {
            $findings[] = [
                'id' => 'MISSING_PERMISSIONS_POLICY',
                'category' => 'HEADER',
                'severity' => 'LOW',
                'title' => 'Missing Permissions-Policy Header',
                'evidence' => 'Permissions-Policy header not provided.',
                'remediation' => 'Declare Permissions-Policy (e.g. camera=(), microphone=(), geolocation=()) to limit browser features.'
            ];
        }

        // =========================================================================
        // 2. INFORMATION DISCLOSURE HEADERS
        // =========================================================================

        if (isset($parsedHeaders['server'])) {
            $serverVal = $parsedHeaders['server'];
            if (preg_match('/\d+\.\d+/', $serverVal)) {
                $findings[] = [
                    'id' => 'INFO_LEAK_SERVER_VERSION',
                    'category' => 'INFO_LEAK',
                    'severity' => 'MEDIUM',
                    'title' => 'Web Server Version Disclosure',
                    'evidence' => "Server header leaks exact software version: '{$serverVal}'.",
                    'remediation' => 'Configure ServerTokens Prod (Apache) or server_tokens off (Nginx) to suppress version details.'
                ];
            } else {
                $findings[] = [
                    'id' => 'SERVER_HEADER_GENERIC',
                    'category' => 'INFO_LEAK',
                    'severity' => 'PASS',
                    'title' => 'Server Header Minimal / Generic',
                    'evidence' => "Server header presents generic banner ('{$serverVal}') without granular version leaks.",
                    'remediation' => 'Consider suppressing the Server header entirely if possible.'
                ];
            }
        }

        if (isset($parsedHeaders['x-powered-by'])) {
            $xpb = $parsedHeaders['x-powered-by'];
            $findings[] = [
                'id' => 'INFO_LEAK_X_POWERED_BY',
                'category' => 'INFO_LEAK',
                'severity' => 'MEDIUM',
                'title' => 'Technology Stack Fingerprinting (X-Powered-By)',
                'evidence' => "X-Powered-By header discloses underlying backend runtime: '{$xpb}'.",
                'remediation' => 'Disable expose_php in php.ini or remove X-Powered-By header in reverse proxy.'
            ];
        }

        if (isset($parsedHeaders['x-aspnet-version']) || isset($parsedHeaders['x-aspnetmvc-version'])) {
            $findings[] = [
                'id' => 'INFO_LEAK_ASPNET',
                'category' => 'INFO_LEAK',
                'severity' => 'MEDIUM',
                'title' => 'Framework Version Disclosure (ASP.NET)',
                'evidence' => 'Response headers disclose Microsoft ASP.NET framework version.',
                'remediation' => 'Disable enableVersionHeader in web.config.'
            ];
        }

        // =========================================================================
        // 3. COOKIE SECURITY FLAGS AUDIT
        // =========================================================================

        $cookieLines = [];
        if (!empty($parsedHeaders['set-cookie-list']) && is_array($parsedHeaders['set-cookie-list'])) {
            $cookieLines = $parsedHeaders['set-cookie-list'];
        } elseif (isset($parsedHeaders['set-cookie'])) {
            $cookieLines = [$parsedHeaders['set-cookie']];
        } else {
            // Extract all Set-Cookie lines from raw headers
            if (preg_match_all('/^Set-Cookie:\s*(.*?)$/im', $rawHeaders, $cookieMatches)) {
                $cookieLines = $cookieMatches[1];
            }
        }

        if (!empty($cookieLines)) {
            foreach ($cookieLines as $cLine) {
                $parts = explode(';', $cLine);
                $cookieNameVal = trim($parts[0] ?? '');
                $cookieName = explode('=', $cookieNameVal)[0] ?? 'Cookie';
                $lowerLine = strtolower($cLine);

                $hasHttpOnly = str_contains($lowerLine, 'httponly');
                $hasSecure = str_contains($lowerLine, 'secure');
                $hasSameSite = (bool)preg_match('/samesite\s*=\s*(strict|lax|none)/i', $lowerLine);

                if (!$hasHttpOnly) {
                    $findings[] = [
                        'id' => 'COOKIE_MISSING_HTTPONLY',
                        'category' => 'COOKIE',
                        'severity' => 'HIGH',
                        'title' => "Cookie '{$cookieName}' Missing HttpOnly Flag",
                        'evidence' => "Set-Cookie: {$cookieNameVal} lacks HttpOnly; accessible via document.cookie in JavaScript.",
                        'remediation' => "Add 'HttpOnly' flag to '{$cookieName}' to mitigate session theft via XSS."
                    ];
                }

                if ($isHttps && !$hasSecure) {
                    $findings[] = [
                        'id' => 'COOKIE_MISSING_SECURE',
                        'category' => 'COOKIE',
                        'severity' => 'HIGH',
                        'title' => "Cookie '{$cookieName}' Missing Secure Flag",
                        'evidence' => "Set-Cookie: {$cookieNameVal} lacks the Secure attribute on an HTTPS deployment.",
                        'remediation' => "Ensure 'Secure' flag is set so the cookie is only transmitted over encrypted TLS channels."
                    ];
                }

                if (!$hasSameSite) {
                    $findings[] = [
                        'id' => 'COOKIE_MISSING_SAMESITE',
                        'category' => 'COOKIE',
                        'severity' => 'MEDIUM',
                        'title' => "Cookie '{$cookieName}' Missing SameSite Attribute",
                        'evidence' => "Set-Cookie: {$cookieNameVal} does not specify SameSite=Strict or SameSite=Lax.",
                        'remediation' => "Configure SameSite=Lax or SameSite=Strict to protect against cross-site request forgery."
                    ];
                }

                if ($hasHttpOnly && (!$isHttps || $hasSecure) && $hasSameSite) {
                    $findings[] = [
                        'id' => 'COOKIE_SECURE_HARBOR',
                        'category' => 'COOKIE',
                        'severity' => 'PASS',
                        'title' => "Cookie '{$cookieName}' Hardened",
                        'evidence' => "Cookie '{$cookieName}' properly configured with HttpOnly, Secure, and SameSite.",
                        'remediation' => 'Maintain hardened cookie flags on all stateful tokens.'
                    ];
                }
            }
        }

        // =========================================================================
        // 4. CORS MISCONFIGURATION AUDIT
        // =========================================================================

        if (isset($parsedHeaders['access-control-allow-origin'])) {
            $corsOrigin = trim($parsedHeaders['access-control-allow-origin']);
            $corsCreds = strtolower(trim($parsedHeaders['access-control-allow-credentials'] ?? ''));

            if ($corsOrigin === '*' && $corsCreds === 'true') {
                $findings[] = [
                    'id' => 'CORS_WILDCARD_CREDENTIALS',
                    'category' => 'CORS',
                    'severity' => 'CRITICAL',
                    'title' => 'Critical CORS Misconfiguration (Wildcard with Credentials)',
                    'evidence' => 'Access-Control-Allow-Origin: * combined with Access-Control-Allow-Credentials: true violates browser security models.',
                    'remediation' => 'Specify exact trusted origin domain instead of wildcard when credentials are exchanged.'
                ];
            } elseif ($corsOrigin === '*') {
                $findings[] = [
                    'id' => 'CORS_WILDCARD',
                    'category' => 'CORS',
                    'severity' => 'LOW',
                    'title' => 'Public CORS Access-Control-Allow-Origin: *',
                    'evidence' => 'Target exposes public wildcard CORS response header.',
                    'remediation' => 'Verify this endpoint is intended for unauthenticated public consumption.'
                ];
            } elseif ($corsOrigin === 'null') {
                $findings[] = [
                    'id' => 'CORS_ORIGIN_NULL',
                    'category' => 'CORS',
                    'severity' => 'MEDIUM',
                    'title' => 'Insecure CORS Origin: null',
                    'evidence' => 'Access-Control-Allow-Origin: null can be abused by sandboxed iframes and local file URIs.',
                    'remediation' => 'Reject origin: null and validate incoming Origin against a strict whitelist.'
                ];
            } else {
                $findings[] = [
                    'id' => 'CORS_SPECIFIC_ORIGIN',
                    'category' => 'CORS',
                    'severity' => 'PASS',
                    'title' => 'CORS Restricted to Specific Origin',
                    'evidence' => "Access-Control-Allow-Origin explicitly restricted to '{$corsOrigin}'.",
                    'remediation' => 'Continue validating origin header dynamically against an authorized registry.'
                ];
            }
        }

        // =========================================================================
        // 5. DOM & HTML CONTENT HYGIENE AUDIT
        // =========================================================================

        if (!empty($rawBody)) {
            // Mixed Content Inspection on HTTPS pages
            if ($isHttps) {
                $mixedMatches = [];
                // Look for unencrypted active scripts, iframes, and stylesheets
                if (preg_match_all('/<(script|iframe|link)\s+[^>]*(src|href)=["\']http:\/\/([^"\'>]+)["\']/i', $rawBody, $mMatches)) {
                    $mixedMatches = array_slice($mMatches[0], 0, 3);
                }

                if (!empty($mixedMatches)) {
                    $findings[] = [
                        'id' => 'DOM_MIXED_CONTENT',
                        'category' => 'DOM',
                        'severity' => 'HIGH',
                        'title' => 'Active Mixed Content Detected',
                        'evidence' => 'Target references unencrypted HTTP active resources: ' . implode('; ', $mixedMatches),
                        'remediation' => 'Upgrade all script, iframe, and stylesheet URLs to HTTPS or use protocol-relative paths.'
                    ];
                } else {
                    $findings[] = [
                        'id' => 'DOM_NO_MIXED_CONTENT',
                        'category' => 'DOM',
                        'severity' => 'PASS',
                        'title' => 'Clean HTTPS DOM (Zero Mixed Content)',
                        'evidence' => 'No unencrypted active resources detected within DOM.',
                        'remediation' => 'Maintain HTTPS asset loading across all dynamic subcomponents.'
                    ];
                }
            }

            // Open Directory Listing Signatures
            if (preg_match('/<title>\s*Index of\s*\/[^<]*<\/title>/i', $rawBody) || 
                (str_contains($rawBody, 'Index of /') && str_contains($rawBody, 'Parent Directory'))) {
                $findings[] = [
                    'id' => 'OPEN_DIRECTORY_LISTING',
                    'category' => 'DOM',
                    'severity' => 'CRITICAL',
                    'title' => 'Open Directory Listing Signature Detected',
                    'evidence' => "Page body contains 'Index of /' server file browser index table.",
                    'remediation' => 'Disable directory indexation (e.g. Options -Indexes in Apache or autoindex off in Nginx).'
                ];
            }

            // Exposed Sensitive Comments Inspection
            if (preg_match_all('/<!--(.*?)-->/s', $rawBody, $commentMatches)) {
                $sensitiveKeywords = ['API_KEY', 'SECRET', 'PASSWORD', 'PASSWD', 'PRIVATE_KEY', 'ADMIN_PASS', 'AUTH_TOKEN', 'BEARER '];
                $exposedSnippets = [];
                foreach ($commentMatches[1] as $cmt) {
                    $upperCmt = strtoupper($cmt);
                    foreach ($sensitiveKeywords as $kw) {
                        if (str_contains($upperCmt, $kw)) {
                            $snippet = trim(preg_replace('/\s+/', ' ', $cmt));
                            $exposedSnippets[] = substr($snippet, 0, 80);
                            break;
                        }
                    }
                    if (count($exposedSnippets) >= 2) break;
                }

                if (!empty($exposedSnippets)) {
                    $findings[] = [
                        'id' => 'EXPOSED_SENSITIVE_COMMENT',
                        'category' => 'DOM',
                        'severity' => 'HIGH',
                        'title' => 'Sensitive Keywords in Public HTML Comments',
                        'evidence' => 'HTML source contains comments leaking potential credentials: <!-- ' . implode(' -->, <!-- ', $exposedSnippets) . ' -->',
                        'remediation' => 'Purge developmental comments, credentials, and authentication notes from client-facing templates.'
                    ];
                }
            }

            // Inline Event Handlers
            if (preg_match_all('/\s(onload|onerror|onclick|onmouseover|onfocus)\s*=\s*["\'][^"\']+["\']/i', $rawBody, $handlerMatches)) {
                $handlerCount = count($handlerMatches[0]);
                if ($handlerCount > 10) {
                    $findings[] = [
                        'id' => 'DOM_INLINE_EVENT_HANDLERS',
                        'category' => 'DOM',
                        'severity' => 'LOW',
                        'title' => 'Extensive Inline Event Handlers in DOM',
                        'evidence' => "Found {$handlerCount} inline DOM event handlers (e.g. " . htmlspecialchars($handlerMatches[0][0]) . ").",
                        'remediation' => 'Refactor inline event handlers into addEventListener calls to enable strict CSP script-src without unsafe-inline.'
                    ];
                }
            }
        }

        // =========================================================================
        // 6. SSL / TLS HEALTH AUDIT
        // =========================================================================

        if ($isHttps && !empty($sslResult)) {
            $sslStatus = strtoupper((string)($sslResult['status'] ?? 'UNKNOWN'));
            $daysLeft = $sslResult['days_left'] ?? null;
            $issuer = $sslResult['issuer'] ?? 'Unknown CA';

            if ($sslStatus === 'EXPIRED') {
                $findings[] = [
                    'id' => 'SSL_EXPIRED',
                    'category' => 'SSL',
                    'severity' => 'CRITICAL',
                    'title' => 'SSL/TLS Certificate Has Expired',
                    'evidence' => "Certificate expired on {$sslResult['valid_to']} (Issuer: {$issuer}).",
                    'remediation' => 'Immediately renew TLS certificate using Certbot or VPS certificate manager.'
                ];
            } elseif ($sslStatus === 'EXPIRING_SOON') {
                $findings[] = [
                    'id' => 'SSL_EXPIRING_SOON',
                    'category' => 'SSL',
                    'severity' => 'HIGH',
                    'title' => "SSL/TLS Certificate Expiring Soon ({$daysLeft} days)",
                    'evidence' => "Certificate will expire in {$daysLeft} days on {$sslResult['valid_to']} (Issuer: {$issuer}).",
                    'remediation' => 'Trigger automated ACME certificate renewal prior to expiration window.'
                ];
            } elseif ($sslStatus === 'INVALID') {
                $findings[] = [
                    'id' => 'SSL_INVALID',
                    'category' => 'SSL',
                    'severity' => 'CRITICAL',
                    'title' => 'SSL/TLS Handshake / Validation Error',
                    'evidence' => 'SSL validation error: ' . ($sslResult['error'] ?? 'Handshake failed or untrusted root CA.'),
                    'remediation' => 'Inspect server TLS configuration and ensure complete intermediate CA chain is installed.'
                ];
            } elseif ($sslStatus === 'VALID') {
                $findings[] = [
                    'id' => 'SSL_VALID_HEALTHY',
                    'category' => 'SSL',
                    'severity' => 'PASS',
                    'title' => 'SSL/TLS Certificate Healthy',
                    'evidence' => "Valid TLS certificate issued by {$issuer} ({$daysLeft} days remaining).",
                    'remediation' => 'Monitor automated renewal triggers as expiry window approaches.'
                ];
            }
        } elseif (!$isHttps) {
            $findings[] = [
                'id' => 'SSL_NOT_HTTPS',
                'category' => 'SSL',
                'severity' => 'HIGH',
                'title' => 'Target Website Not Served via HTTPS',
                'evidence' => "Target URL '{$targetUrl}' utilizes unencrypted plain HTTP transport.",
                'remediation' => 'Issue a TLS certificate and enforce HTTP to HTTPS automatic redirection.'
            ];
        }

        // =========================================================================
        // 7. SECURITY SCORE & GRADE COMPUTATION
        // =========================================================================

        $score = 100;
        $summary = [
            'total_checks' => count($findings),
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'pass' => 0,
        ];

        foreach ($findings as $f) {
            $sev = $f['severity'];
            switch ($sev) {
                case 'CRITICAL':
                    $summary['critical']++;
                    $score -= 25;
                    break;
                case 'HIGH':
                    $summary['high']++;
                    $score -= 15;
                    break;
                case 'MEDIUM':
                    $summary['medium']++;
                    $score -= 8;
                    break;
                case 'LOW':
                    $summary['low']++;
                    $score -= 3;
                    break;
                case 'PASS':
                    $summary['pass']++;
                    break;
            }
        }

        $score = max(0, min(100, $score));

        // Determine letter grade
        if ($score >= 95) {
            $grade = 'A+';
        } elseif ($score >= 85) {
            $grade = 'A';
        } elseif ($score >= 70) {
            $grade = 'B';
        } elseif ($score >= 55) {
            $grade = 'C';
        } elseif ($score >= 40) {
            $grade = 'D';
        } else {
            $grade = 'F';
        }

        // Severe findings cap the maximum achievable grade
        if ($summary['critical'] > 0) {
            $grade = 'F';
            $score = min($score, 39);
        } elseif ($summary['high'] >= 2 && in_array($grade, ['A+', 'A', 'B'], true)) {
            $grade = 'C';
            $score = min($score, 68);
        }

        return [
            'security_score' => $score,
            'security_grade' => $grade,
            'scanned_at' => date('Y-m-d H:i:s T'),
            'target_url' => $targetUrl,
            'status_code' => $statusCode,
            'summary' => $summary,
            'vulnerabilities' => $findings,
        ];
    }
}
