<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_options_page(
        __('URL Parameter Stripper', 'url-parameter-stripper'),
        __('URL Stripper', 'url-parameter-stripper'),
        UPS_CAP,
        'url-parameter-stripper',
        'url_parameter_stripper_render_settings_page'
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

    register_setting('ups_settings', 'ups_fragment_patterns', [
        'type' => 'string',
        'sanitize_callback' => function ($val) {
            return sanitize_text_field($val);
        },
        'default' => ''
    ]);

    add_settings_section('ups_main', __('Removal Rules', 'url-parameter-stripper'), function () {
        echo wp_kses_post(
            sprintf(
                /* translators: 1: example list, 2: wildcard indicator, 3: example parameter */
                __('Enter a comma-separated list. Examples: %1$s. Keys with %2$s remove matching query parameters (e.g., %3$s); any other text is stripped as a raw substring from URLs.<br>For strict value matching use <code>key=value</code> format.', 'url-parameter-stripper'),
                '<code>utm_*,gclid,ref</code>',
                '<code>*</code>',
                '<code>utm_source</code>'
            )
        );
    }, 'url-parameter-stripper');

    add_settings_field('ups_patterns', __('Query Parameters', 'url-parameter-stripper'), function () {
        $val = get_option(UPS_OPTION_KEY, 'utm_*,gclid,fbclid');
        printf(
            '<input type="text" name="%1$s" value="%2$s" class="regular-text" />',
            esc_attr(UPS_OPTION_KEY),
            esc_attr($val)
        );
    }, 'url-parameter-stripper', 'ups_main');

    add_settings_field('ups_fragment_patterns', __('Fragment Rules', 'url-parameter-stripper'), function () {
        $val = get_option('ups_fragment_patterns', '');
        printf(
            '<input type="text" name="ups_fragment_patterns" value="%1$s" class="regular-text" /><p class="description">%2$s</p>',
            esc_attr($val),
            esc_html__('Comma-separated list. Use * to strip all fragments, or specific patterns like ":~:text=*".', 'url-parameter-stripper')
        );
    }, 'url-parameter-stripper', 'ups_main');
});

function url_parameter_stripper_render_settings_page()
{
    if (!current_user_can(UPS_CAP)) return;
    echo '<div class="wrap"><h1>' . esc_html__('URL Parameter Stripper', 'url-parameter-stripper') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('ups_settings');
    do_settings_sections('url-parameter-stripper');
    submit_button();
    echo '</form></div>';
}
