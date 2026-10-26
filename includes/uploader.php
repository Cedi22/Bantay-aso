<?php
/**
 * Bantay Aso — Enterprise Secure Upload & File Handling Engine
 * Mitigates: File Upload RCE, Unrestricted File Upload, Polyglot Payloads,
 * Path Traversal, and PHP2Shell (CVE-2026-109618 / CVE-2026-109619) vectors.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class SecureUploader
{
    /**
     * Immutable MIME-to-Extension Whitelist.
     * Only explicitly vetted safe MIME types with exact byte-signature mappings are permitted.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg'        => 'jpg',
        'image/png'         => 'png',
        'image/webp'        => 'webp',
        'text/plain'        => 'txt',
        'application/json'  => 'json',
        'application/pdf'   => 'pdf'
    ];

    /**
     * Dangerous signature blacklist checked against raw file content to reject
     * PHP polyglot payloads (e.g. PHP code hidden in EXIF/IDAT tags).
     */
    private const DANGEROUS_PAYLOAD_SIGNATURES = [
        '<?php',
        '<?=',
        '<script language="php"',
        '__halt_compiler'
    ];

    /**
     * Default maximum upload size (8MB).
     */
    private const MAX_FILE_SIZE = 8 * 1024 * 1024;

    /**
     * Resolves the secure storage directory, ensuring it is positioned OUTSIDE
     * the public web root.
     *
     * @param string|null $customPath Optional custom directory
     * @return string Fully qualified and hardened storage path
     */
    public static function getStorageDirectory(?string $customPath = null): string
    {
        $baseDir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        
        // Default target: one level above web root under /storage/uploads
        $targetDir = $customPath ?: (dirname($baseDir) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads');

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0750, true);
        }

        // Deploy protective directory controls (.htaccess & index.html)
        self::hardenDirectory($targetDir);

        return $targetDir;
    }

    /**
     * Inspects incoming request headers and strictly rejects multipart form-data
     * if the application endpoint or configuration does not explicitly allow it.
     */
    public static function blockMultipartIfUnused(): void
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'multipart/form-data') !== false) {
            http_response_code(415);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Multipart form-data processing is administratively disabled to mitigate parser memory exploits (CVE-2026-109618 / CVE-2026-109619).'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit(0);
        }
    }

    /**
     * Securely validates, sanitizes, renames, and persists an uploaded file.
     *
     * @param array $fileEntry An entry from $_FILES (e.g. $_FILES['upload'])
     * @param string|null $destinationDir Optional override for storage path
     * @param int $maxSizeBytes Maximum allowed file size
     * @return array Result array containing success status, safe filename, and storage path
     * @throws RuntimeException On security or validation failure
     */
    public static function processUpload(
        array $fileEntry,
        ?string $destinationDir = null,
        int $maxSizeBytes = self::MAX_FILE_SIZE
    ): array {
        // 1. Basic error code validation
        if (!isset($fileEntry['error']) || is_array($fileEntry['error'])) {
            throw new RuntimeException('Invalid upload parameters structure.');
        }

        if ($fileEntry['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::getErrorMessage((int)$fileEntry['error']));
        }

        // 2. Strict size validation
        $size = (int)($fileEntry['size'] ?? 0);
        if ($size <= 0 || $size > $maxSizeBytes) {
            throw new RuntimeException("File exceeds maximum allowed size ({$maxSizeBytes} bytes).");
        }

        $tmpName = (string)($fileEntry['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpName)) {
            throw new RuntimeException('Security violation: file was not uploaded via standard HTTP POST.');
        }

        // 3. Strict MIME Whitelisting via finfo magic bytes (Client-provided MIME is ignored)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($tmpName);

        if (!array_key_exists($detectedMime, self::ALLOWED_MIME_TYPES)) {
            throw new RuntimeException("Rejected file type. MIME type '{$detectedMime}' is not permitted.");
        }

        $canonicalExt = self::ALLOWED_MIME_TYPES[$detectedMime];

        // 4. Deep Inspection for embedded PHP / Executable tags in payload
        $headBytes = (string)@file_get_contents($tmpName, false, null, 0, 8192);
        foreach (self::DANGEROUS_PAYLOAD_SIGNATURES as $signature) {
            if (stripos($headBytes, $signature) !== false) {
                throw new RuntimeException('Security violation: embedded code signature detected in uploaded file.');
            }
        }

        // 5. Cryptographically secure, collision-free hash-based renaming
        // Strip any user-supplied filename completely to avoid directory traversal / double-extensions
        $safeHash = bin2hex(random_bytes(16));
        $safeFileName = "{$safeHash}.{$canonicalExt}";

        // 6. Resolve destination (outside web root) and move file
        $storageDir = self::getStorageDirectory($destinationDir);
        $destinationPath = $storageDir . DIRECTORY_SEPARATOR . $safeFileName;

        if (!move_uploaded_file($tmpName, $destinationPath)) {
            throw new RuntimeException('Failed to securely store uploaded file on filesystem.');
        }

        // 7. Restrict file permissions (Non-executable read-only for owner/group)
        @chmod($destinationPath, 0640);

        return [
            'success'       => true,
            'file_name'     => $safeFileName,
            'file_path'     => $destinationPath,
            'mime_type'     => $detectedMime,
            'size_bytes'    => $size,
            'sha256'        => hash_file('sha256', $destinationPath)
        ];
    }

    /**
     * Hardens a directory against direct web execution and indexing.
     * Deploys restrictive .htaccess rules and an empty index.html file.
     *
     * @param string $dir Path to directory
     */
    public static function hardenDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        // Apache access denial & PHP execution killswitch
        $htaccessPath = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $rules = <<<'HTACCESS'
# ==============================================================================
# Bantay Aso — Storage Directory Hardening Killswitch
# Strictly denies all web access and disables script execution engines
# ==============================================================================

# Disable directory browsing
Options -Indexes -ExecCGI

# Apache 2.4+
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

# Apache 2.2 fallback
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>

# Hard killswitch against script engines
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>

<FilesMatch "(?i)\.(ph(p[0-9]?|t|tml|ar)|cgi|pl|py|sh|bash|exe|bat|cmd)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Deny from all
    </IfModule>
</FilesMatch>
HTACCESS;
            @file_put_contents($htaccessPath, $rules);
        }

        // Silent empty index file
        $indexPath = $dir . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($indexPath)) {
            @file_put_contents($indexPath, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1></body></html>');
        }
    }

    /**
     * Translates standard PHP upload error codes to user-friendly safe descriptions.
     */
    private static function getErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            default               => 'Unknown upload error occurred.'
        };
    }
}
