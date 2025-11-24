<?php
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

// Define global constants
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}
if (!defined('UPS_OPTION_KEY')) {
    define('UPS_OPTION_KEY', 'ups_remove_patterns');
}

// Mock global state storage
class UpsTestState {
    public static $options = [];
}

// Mock WP functions
if (!function_exists('get_option')) {
    function get_option($key, $default = '') {
        return UpsTestState::$options[$key] ?? $default;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url) {
        return parse_url($url);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim($str);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($s) { return $s; }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($s) { return $s; }
}

if (!function_exists('__')) {
    function __($s, $d) { return $s; }
}

if (!function_exists('esc_html__')) {
    function esc_html__($s, $d) { return $s; }
}

if (!function_exists('wp_slash')) {
    function wp_slash($value) {
        return addslashes($value);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return stripslashes($value);
    }
}

