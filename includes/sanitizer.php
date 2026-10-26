<?php
/**
 * Bantay Aso — Content Sanitizer & Canonicalizer
 * Strips volatile dynamic tags (nonces, dynamic tokens, ad trackers, timestamps) as specified in ECC.
 * Normalizes HTML/Text to allow deterministic diffing and content-hash tracking.
 */

declare(strict_types=1);

class ContentSanitizer
{
    /**
     * Clean and normalize raw HTML for semantic diffing.
     *
     * @param string $html Raw HTML content
     * @return array [
     *   'cleaned_html' => string,
     *   'canonical_text' => string,
     *   'content_hash' => string,
     *   'page_title' => ?string,
     *   'meta_description' => ?string,
     *   'metrics' => array
     * ]
     */
    public static function process(string $html): array
    {
        $metrics = self::extractMetrics($html);
        $title = self::extractTitle($html);
        $description = self::extractMetaDescription($html);

        // 1. Strip dynamic script/style nonces (e.g., nonce="abc123xyz")
        $clean = preg_replace('/\s+nonce=["\'][^"\']*["\']/i', ' nonce="[STRIPPED_NONCE]"', $html);

        // 2. Normalize CSRF / anti-forgery tokens in hidden inputs
        $clean = preg_replace(
            '/(<input[^>]+(?:name|id)=["\'](?:_token|csrf_token|csrf|_csrf|authenticity_token)["\'][^>]+value=)["\'][^"\']*["\']/i',
            '$1"[NORMALIZED_TOKEN]"',
            $clean
        );

        // 3. Strip tracking and ad scripts (GTM, Google Analytics, Facebook Pixel, Cloudflare)
        $clean = preg_replace(
            '/<script\b[^>]*>(?:(?!<\/script>).)*(?:googletagmanager\.com|google-analytics\.com|connect\.facebook\.net|clarity\.ms|beacon\.min\.js|cf-beacon).*?<\/script>/is',
            '<!-- [STRIPPED_TRACKING_SCRIPT] -->',
            $clean
        );

        // 4. Strip timestamp & cache comments (e.g., <!-- Generated at ... -->, <!-- Dynamic Page Cache ... -->)
        $clean = preg_replace(
            '/<!--\s*(?:Generated|Cache|Rendered|Built|Server\s*Time|Page\s*created|WP\s*Super\s*Cache).*?-->/is',
            '<!-- [NORMALIZED_TIMESTAMP_COMMENT] -->',
            $clean
        );

        // 5. Normalize cache-busting timestamp query parameters in asset URLs (e.g., ?v=1729329182 or ?t=1690000000)
        $clean = preg_replace('/(\.(?:css|js|png|jpg|jpeg|svg|webp)\?[tv]=)\d{9,13}/i', '$1[TIMESTAMP_REMOVED]', $clean);

        // 6. Normalize volatile whitespace (consecutive spaces/tabs, uniform line breaks)
        $clean = preg_replace("/\r\n|\r/", "\n", $clean);
        $clean = preg_replace("/[ \t]+$/m", '', $clean);

        // 7. Extract readable text for content comparison
        $canonicalText = self::extractCleanText($clean);

        // 8. Compute SHA-256 hash of cleaned text + DOM structure
        $contentHash = hash('sha256', $canonicalText);

        // 9. Compute structural DOM skeleton hash (tag sequence only, stripping text/attrs)
        $domSkeletonHash = self::extractDomSkeletonHash($clean);

        return [
            'cleaned_html' => $clean,
            'canonical_text' => $canonicalText,
            'content_hash' => $contentHash,
            'dom_skeleton_hash' => $domSkeletonHash,
            'page_title' => $title,
            'meta_description' => $description,
            'metrics' => $metrics,
        ];
    }

    /**
     * Compute a structural DOM skeleton hash based on ordered tag hierarchy only.
     */
    public static function extractDomSkeletonHash(string $html): string
    {
        if (preg_match_all('/<\/?([a-zA-Z0-9]+)\b[^>]*>/', $html, $matches)) {
            $tagSequence = '';
            foreach ($matches[0] as $i => $fullTag) {
                $isClosing = str_starts_with($fullTag, '</');
                $tagName = strtolower($matches[1][$i]);
                $tagSequence .= ($isClosing ? "/{$tagName}>" : "<{$tagName}>");
            }
            return hash('sha256', $tagSequence);
        }
        return hash('sha256', '');
    }

    /**
     * Extract title from HTML using DOMDocument or regex fallback.
     */
    private static function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return mb_substr($title, 0, 255);
        }
        return null;
    }

    /**
     * Extract meta description.
     */
    private static function extractMetaDescription(string $html): ?string
    {
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $html, $matches)) {
            $desc = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return mb_substr($desc, 0, 500);
        }
        return null;
    }

    /**
     * Extract DOM element counts and structural metrics.
     */
    private static function extractMetrics(string $html): array
    {
        $metrics = [
            'scripts_count' => preg_match_all('/<script\b/i', $html),
            'styles_count' => preg_match_all('/<link[^>]+rel=["\']stylesheet["\']|<style\b/i', $html),
            'images_count' => preg_match_all('/<img\b/i', $html),
            'forms_count' => preg_match_all('/<form\b/i', $html),
            'links_count' => preg_match_all('/<a\b[^>]+href=/i', $html),
            'h1_count' => preg_match_all('/<h1\b/i', $html),
            'h2_count' => preg_match_all('/<h2\b/i', $html),
            'h3_count' => preg_match_all('/<h3\b/i', $html),
            'total_size_bytes' => strlen($html),
        ];
        return $metrics;
    }

    /**
     * Extract structured, readable body text from HTML.
     */
    private static function extractCleanText(string $html): string
    {
        // Strip head, scripts, styles, noscript, svg, iframes
        $text = preg_replace('/<(?:head|script|style|noscript|svg|iframe)\b[^>]*>.*?<\/(?:head|script|style|noscript|svg|iframe)>/is', '', $html);

        // Convert common block elements to newlines
        $text = preg_replace('/<\/(?:p|div|h[1-6]|li|tr|section|article|header|footer|nav|aside)>/i', "\n", $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse multiple empty lines
        $text = preg_replace("/\n\s*\n+/", "\n\n", $text);
        return trim($text);
    }
}
