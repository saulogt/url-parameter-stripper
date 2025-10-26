<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_options_page(
        __('URL Parameter Stripper', 'url-parameter-stripper'),
        __('URL Stripper', 'url-parameter-stripper'),
        UPS_CAP,
        'url-parameter-stripper',
        'ups_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('ups_settings', UPS_OPTION_KEY, [
        'type' => 'string',
        'sanitize_callback' => function ($val) {
            return sanitize_text_field($val);
        },
        'default' => 'utm_*,gclid,fbclid'
    ]);

    add_settings_section('ups_main', __('Removal Rules', 'url-parameter-stripper'), function () {
        echo '<p>Enter a comma-separated list. Examples: <code>utm_*,gclid,ref</code>. 
        Keys with <code>*</code> remove matching query parameters (e.g., <code>utm_source</code>); 
        any other text is stripped as a raw substring from URLs.</p>';
    }, 'url-parameter-stripper');

    add_settings_field('ups_patterns', __('Patterns', 'url-parameter-stripper'), function () {
        $val = esc_attr(get_option(UPS_OPTION_KEY, 'utm_*,gclid,fbclid'));
        echo '<input type="text" name="' . UPS_OPTION_KEY . '" value="' . $val . '" class="regular-text" />';
    }, 'url-parameter-stripper', 'ups_main');
});

function ups_render_settings_page()
{
    if (!current_user_can(UPS_CAP)) return;
    echo '<div class="wrap"><h1>' . esc_html__('URL Parameter Stripper', 'url-parameter-stripper') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('ups_settings');
    do_settings_sections('url-parameter-stripper');
    submit_button();
    echo '</form></div>';
}
