<?php

/**
 * Plugin Name:       URL Parameter Stripper
 * Description:       Removes specified substrings or query parameters from URLs before saving them to the DB (posts, comments, options, meta).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.0
 * Author:            Saulo Tauil
 * Author URI:        https://saulotauil.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       url-parameter-stripper
 * Domain Path:       /languages
 *
 * @package           Url_Parameter_Stripper
 */

// Your code starts here.

if (!defined('ABSPATH')) exit;

define('UPS_OPTION_KEY', 'ups_remove_patterns'); // comma-separated patterns
define('UPS_CAP', 'manage_options');

require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/admin/settings.php';

// ---- Hook into common save paths ----

// Posts (content & excerpt)
add_filter('content_save_pre', 'url_parameter_stripper_sanitize_text_urls');
add_filter('excerpt_save_pre', 'url_parameter_stripper_sanitize_text_urls');

// Comments (content + author URL)
add_filter('preprocess_comment', function ($comment) {
    if (!empty($comment['comment_content'])) {
        $comment['comment_content'] = url_parameter_stripper_sanitize_text_urls($comment['comment_content']);
    }
    if (!empty($comment['comment_author_url'])) {
        $comment['comment_author_url'] = url_parameter_stripper_strip_url($comment['comment_author_url']);
    }
    return $comment;
});

// User profile URL
add_filter('pre_user_url', 'url_parameter_stripper_strip_url');

// Generic options (catches many plugin/theme settings)
add_filter('pre_update_option', function ($value, $old_value, $option) {
    return url_parameter_stripper_sanitize_mixed($value);
}, 10, 3);

// Generic meta (works best when meta keys are registered, but we try anyway)
add_filter('sanitize_meta', function ($meta_value, $meta_key, $meta_type) {
    return url_parameter_stripper_sanitize_mixed($meta_value);
}, 10, 3);
