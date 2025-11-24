<?php
if (!defined('ABSPATH')) exit;

/**
 * Return array of normalized removal patterns from settings.
 * Examples supported:
 *   - query param keys: utm_*, gclid, ref
 *   - key=value: utm_source=chatgpt.com
 */
function ups_get_patterns()
{
    $raw = get_option(UPS_OPTION_KEY, 'utm_*,gclid,fbclid');
    $parts = array_filter(array_map('trim', explode(',', (string)$raw)));
    return array_unique($parts);
}

/**
 * Return array of fragment removal patterns.
 */
function ups_get_fragment_patterns()
{
    $raw = get_option('ups_fragment_patterns', '');
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
 * Remove matching query params from a single URL.
 */
function ups_strip_url($url)
{
    // We allow partial URLs in hrefs (like /foo/bar), so strict ups_looks_like_url check 
    // might be too restrictive if we want to strip params from relative URLs too.
    // However, wp_parse_url handles relative URLs.
    // But existing code enforced ups_looks_like_url. The user request implies full URLs "https://...", 
    // but stripping params from "/page?utm_..." is also good. 
    // For now, I'll stick to preserving the existing behavior for "looks like url" if it was strict, 
    // BUT `ups_sanitize_text_urls` will call this with whatever is in href.
    // If href="/foo?utm=1", ups_looks_like_url("/foo...") is false.
    // So I should relax or remove ups_looks_like_url check inside ups_strip_url 
    // and let the caller decide, OR update ups_looks_like_url.
    // Given the user's example was a full URL, and the previous code strictly checked for http(s),
    // I'll keep the http(s) check for now to be safe, unless I see a reason to change.
    // actually, for hrefs, we definitely want to strip from relative URLs too.
    // So I will remove the strict http check at the start of this function, 
    // relying on wp_parse_url to fail if it's garbage.
    
    $patterns = ups_get_patterns();
    $fragPatterns = ups_get_fragment_patterns();
    
    if (empty($patterns) && empty($fragPatterns)) return $url;

    // Decode HTML entities (e.g. &amp; -> &) to prevent parse_str from seeing "amp;key"
    // This fixes the issue where "url?a=1&amp;b=2" becomes "url?a=1&amp%3Bb=2"
    $url = html_entity_decode($url);

    // Parse URL
    $parts = wp_parse_url($url);
    if (!$parts) return $url;

    // Separate patterns into Key-Only (regex supported) and Key-Value
    $keyRules = [];
    $keyValueRules = [];

    foreach ($patterns as $p) {
        if (strpos($p, '=') !== false) {
            list($k, $v) = explode('=', $p, 2);
            $keyValueRules[] = ['key' => $k, 'val' => $v];
        } else {
            // Only letters/digits/_/- and optional trailing *
            // We convert "utm_*" to regex "^utm_.*$"
            if (preg_match('/^[a-z0-9_\-]+(\*)?$/i', $p)) {
                $re = '#^' . str_replace('\*', '.*', preg_quote($p, '#')) . '$#i';
                $keyRules[] = $re;
            }
        }
    }

    // Handle Query Params
    $query = $parts['query'] ?? '';
    if ($query) {
        parse_str($query, $args);
        $modified = false;
        
        foreach (array_keys($args) as $k) {
            $shouldRemove = false;

            // Check Key Rules (Regex)
            foreach ($keyRules as $re) {
                if (preg_match($re, $k)) {
                    $shouldRemove = true;
                    break;
                }
            }

            // Check Key-Value Rules
            if (!$shouldRemove) {
                foreach ($keyValueRules as $rule) {
                    if ($rule['key'] === $k && isset($args[$k]) && $args[$k] == $rule['val']) {
                        $shouldRemove = true;
                        break;
                    }
                }
            }

            if ($shouldRemove) {
                unset($args[$k]);
                $modified = true;
            }
        }

        if ($modified) {
            $query = http_build_query($args, '', '&', PHP_QUERY_RFC3986);
            // Update parts
            if ($query === '') {
                unset($parts['query']);
            } else {
                $parts['query'] = $query;
            }
        }
    }

    // Handle Fragments
    $fragment = $parts['fragment'] ?? '';
    if ($fragment !== '' && !empty($fragPatterns)) {
        foreach ($fragPatterns as $p) {
            // If pattern is "*", remove everything
            if ($p === '*') {
                $fragment = '';
                unset($parts['fragment']);
                break;
            }
            // Pattern matching (simple wildcard support)
            // e.g. ":~:text=*"
            $re = '#^' . str_replace('\*', '.*', preg_quote($p, '#')) . '$#i';
            if (preg_match($re, $fragment)) {
                $fragment = '';
                unset($parts['fragment']);
                break;
            }
        }
    }

    // Rebuild URL
    // wp_parse_url returns: scheme, host, port, user, pass, path, query, fragment
    // We can't use http_build_url (PECL), so we rebuild manually.
    
    $scheme   = isset($parts['scheme'])   ? $parts['scheme'] . '://' : '';
    $host     = $parts['host']     ?? '';
    $port     = isset($parts['port'])     ? ':' . $parts['port'] : '';
    $user     = $parts['user']     ?? '';
    $pass     = isset($parts['pass'])     ? ':' . $parts['pass']  : '';
    $pass     = ($user || $pass)          ? "$pass@" : '';
    $path     = $parts['path']     ?? '';
    $query    = isset($parts['query'])    ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    // Handle relative URLs (no scheme/host)
    // If scheme is present, host is likely present.
    // If no scheme, it might be just path.
    
    $auth = $user . $pass;
    
    // If we have scheme/host, rebuild fully. 
    // If strict relative path, just path+query+frag.
    
    if ($scheme || $host) {
        return "$scheme$user$pass$host$port$path$query$fragment";
    } else {
        return "$path$query$fragment";
    }
}

/**
 * Find all URLs in HREF attributes and sanitize them.
 */
function ups_sanitize_text_urls($text)
{
    if (!is_string($text) || $text === '') return $text;
    
    // Regex matches href= followed by a quote (possibly escaped with backslash).
    // Group 1: href= prefix
    // Group 2: Opening quote (e.g. " or ' or \" or \')
    // Group 3: The URL content
    // \2 backreference ensures the closing quote matches the opening one.
    return preg_replace_callback(
        '/(href\s*=\s*)(\\\?["\'])(.*?)\2/i',
        function ($m) {
            $prefix = $m[1];
            $quote  = $m[2];
            $url    = $m[3];
            
            // Detect if we are in a slashed context (quote starts with backslash)
            // e.g. content_save_pre filter usually passes slashed data.
            $is_slashed = (strpos($quote, '\\') === 0);
            
            if ($is_slashed) {
                $url = wp_unslash($url);
            }
            
            $clean = ups_strip_url($url);
            
            if ($is_slashed) {
                $clean = wp_slash($clean);
            }
            
            return $prefix . $quote . $clean . $quote;
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
        // If it's a standalone URL string (e.g. a meta field that is just a URL), strip it.
        if (ups_looks_like_url($value)) {
            return ups_strip_url($value);
        }
        // Otherwise scan for links in the text
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
