<?php
if (!defined('ABSPATH')) exit;

/**
 * Return array of normalized removal patterns from settings.
 * Examples supported:
 *   - query param keys: utm_*, gclid, ref
 *   - raw substrings: e.g., "tracking123" (will be removed anywhere in the URL)
 */
function ups_get_patterns()
{
    $raw = get_option(UPS_OPTION_KEY, 'utm_*,gclid,fbclid');
    $parts = array_filter(array_map('trim', explode(',', (string)$raw)));
    return array_unique($parts);
}

/**
 * True if $str looks like a URL.
 */
function ups_looks_like_url($str)
{
    return is_string($str) && preg_match('#^https?://#i', $str);
}

/**
 * Remove matching substrings & query params from a single URL.
 */
function ups_strip_url($url)
{
    if (!ups_looks_like_url($url)) return $url;

    $patterns = ups_get_patterns();
    if (empty($patterns)) return $url;

    // Remove raw substrings first (non-param)
    foreach ($patterns as $p) {
        // Treat entries without wildcard and not looking like a param name as raw substrings
        if (strpos($p, '=') !== false) {
            // Not supported: we operate by query key, not full key=value literals
            continue;
        }
        // If it contains * or letters/digits and not purely "param-name" we treat as raw substring
        if (!preg_match('/^[a-z0-9_\-]+(\*)?$/i', $p)) {
            $url = str_replace($p, '', $url);
        }
    }

    // Parse and rebuild to handle param removal safely
    $parts = wp_parse_url($url);
    if (!$parts) return $url;

    // Validate required components exist (scheme and host are mandatory for valid URLs)
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }

    $scheme   = $parts['scheme'];
    $host     = $parts['host'];
    $port     = $parts['port']    ?? '';
    $user     = $parts['user']    ?? '';
    $pass     = $parts['pass']    ?? '';
    $path     = $parts['path']    ?? '';
    $query    = $parts['query']   ?? '';
    $fragment = $parts['fragment'] ?? '';

    // Build blacklist regexes for param KEYS (support * wildcard at end)
    $blacklist = [];
    foreach ($patterns as $p) {
        // Only letters/digits/_/- and optional trailing *
        if (preg_match('/^[a-z0-9_\-]+(\*)?$/i', $p)) {
            $re = '#^' . str_replace('\*', '.*', preg_quote($p, '#')) . '$#i';
            $blacklist[] = $re;
        }
    }

    if ($query) {
        parse_str($query, $args);
        foreach (array_keys($args) as $k) {
            foreach ($blacklist as $re) {
                if (preg_match($re, $k)) {
                    unset($args[$k]);
                    break;
                }
            }
        }
        $query = http_build_query($args, '', '&', PHP_QUERY_RFC3986);
    }

    // Rebuild URL with validated components
    $auth = $user ? $user . ($pass ? ':' . $pass : '') . '@' : '';
    $hostport = $host . ($port ? ':' . $port : '');
    $rebuilt = $scheme . '://' . $auth . $hostport . $path;
    if ($query !== '') $rebuilt .= '?' . $query;
    if ($fragment) $rebuilt .= '#' . $fragment;

    return $rebuilt;
}

/**
 * Find all URLs in a blob of text and sanitize them.
 */
function ups_sanitize_text_urls($text)
{
    if (!is_string($text) || $text === '') return $text;
    return preg_replace_callback(
        '#https?://[^\s<>"\'\)\]]+#i',
        function ($m) {
            return ups_strip_url($m[0]);
        },
        $text
    );
}

/**
 * Sanitize strings, arrays, or objects recursively.
 */
function ups_sanitize_mixed($value)
{
    if (is_string($value)) {
        if (ups_looks_like_url($value)) return ups_strip_url($value);
        // If not purely a URL, still scan text for embedded URLs
        return ups_sanitize_text_urls($value);
    }
    if (is_array($value)) {
        foreach ($value as $k => $v) $value[$k] = ups_sanitize_mixed($v);
        return $value;
    }
    if (is_object($value)) {
        foreach ($value as $k => $v) $value->$k = ups_sanitize_mixed($v);
        return $value;
    }
    return $value;
}
