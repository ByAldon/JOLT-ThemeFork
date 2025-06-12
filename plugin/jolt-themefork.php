<?php
/*
Plugin Name: JOLT™ ThemeFork
Plugin URI: https://github.com/johnoltmans/JOLT-ThemeFork
Description: Instantly create a clean, functional child theme from your currently active WordPress theme — with one click.
Version: 1.1
Author: John Oltmans
Author URI: https://www.johnoltmans.nl
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined('ABSPATH') || exit;

// Voeg settings menu toe
add_action('admin_menu', 'jolt_themefork_add_settings_menu');

function jolt_themefork_add_settings_menu() {
    add_options_page(
        'JOLT ThemeFork Settings',
        'JOLT ThemeFork',
        'manage_options',
        'jolt-themefork',
        'jolt_themefork_settings_page'
    );
}

// "Instellingen" link bij plugin activeren
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'jolt_themefork_settings_link');

function jolt_themefork_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=jolt-themefork') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}



function jolt_themefork_settings_page() {
    ?>
    <div class="wrap">
        <h1>JOLT ThemeFork Settings</h1>
        <p>Generate a child theme based on your currently active WordPress theme.</p>
<div style="border-left: 4px solid #2271b1; background: #f0f8ff; padding: 15px 20px; margin-bottom: 25px; border-radius: 4px;">
    <p style="margin: 0; font-size: 14px;">
        💡 <strong>How to use JOLT ThemeFork</strong><br>
        Select a theme below and click <strong>“Generate Child Theme”</strong> to instantly create a child theme. <br>
        Once it’s created, you can choose to activate it right away.
    </p>
</div>

        <?php
        if (isset($_POST['jolt_themefork_submit']) && check_admin_referer('jolt_themefork_nonce_action', 'jolt_themefork_nonce_field')) {
    $selected_parent = sanitize_text_field($_POST['jolt_themefork_parent']);
    $result = jolt_themefork_create_child_theme($selected_parent);

    if (is_wp_error($result)) {
        echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
    } else {
        $child_slug = esc_html($result);
        $activate_url = wp_nonce_url(admin_url("themes.php?action=activate&stylesheet=$child_slug"), "switch-theme_$child_slug");
        echo '<div class="notice notice-success"><p>Child theme created in <code>/wp-content/themes/' . $child_slug . '</code>.</p>';
        echo '<p><strong>Would you like to activate this child theme now?</strong></p>';
        echo '<p><a href="' . $activate_url . '" class="button button-secondary">Activate Child Theme</a></p></div>';
    }
}

        ?>

<form method="post">
    <?php wp_nonce_field('jolt_themefork_nonce_action', 'jolt_themefork_nonce_field'); ?>

    <style>
        .jolt-theme-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .jolt-theme-item {
            border: 2px solid transparent;
            padding: 10px;
            width: 180px;
            text-align: center;
            cursor: pointer;
            background: #fff;
            transition: 0.3s;
        }
        .jolt-theme-item input[type="radio"] {
            display: none;
        }
        .jolt-theme-item img {
            width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .jolt-theme-item.selected {
            border-color: #2271b1;
            background: #f0f8ff;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const items = document.querySelectorAll('.jolt-theme-item');
            items.forEach(item => {
                item.addEventListener('click', function () {
                    items.forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
        });
    </script>

    <div class="jolt-theme-grid">
        <?php
        $themes = wp_get_themes();
        $active_theme = wp_get_theme();
$active_slug = $active_theme->get_stylesheet();

foreach ($themes as $slug => $theme) {
    $is_active = ($slug === $active_slug);
    $screenshot = $theme->get_screenshot();
    $name = $theme->get('Name');

    echo '<label class="jolt-theme-item' . ($is_active ? ' selected' : '') . '">';
    echo '<input type="radio" name="jolt_themefork_parent" value="' . esc_attr($slug) . '"' . ($is_active ? ' checked' : '') . '>';
    echo '<img src="' . esc_url($screenshot ?: get_theme_root_uri() . '/' . $slug . '/screenshot.png') . '" alt="' . esc_attr($name) . '">';
    echo '<div>' . esc_html($name) . ($is_active ? ' <span style="color: #2271b1;"><hr>(Active Theme)</span>' : '') . '</div>';
    echo '</label>';
}

        ?>
    </div>

    <p><input type="submit" name="jolt_themefork_submit" class="button button-primary" value="Generate Child Theme"></p>
</form>

    </div>
    <?php
}


// Child theme aanmaken
function jolt_themefork_create_child_theme() {
    $parent = wp_get_theme();
    $parent_slug = $parent->get_stylesheet();
    $child_slug = $parent_slug . '-child';
    $child_dir = WP_CONTENT_DIR . '/themes/' . $child_slug;

    if (file_exists($child_dir)) {
        return new WP_Error('child_exists', 'Child theme already exists.');
    }

    if (!wp_mkdir_p($child_dir)) {
        return new WP_Error('mkdir_failed', 'Could not create child theme directory.');
    }

    // style.css
    $style = "/*
Theme Name: " . $parent->get('Name') . " Child
Theme URI: " . $parent->get('ThemeURI') . "
Description: Child theme of " . $parent->get('Name') . "
Author: " . $parent->get('Author') . "
Template: $parent_slug
Version: 1.0.0
*/";

    file_put_contents($child_dir . '/style.css', $style);

    // functions.php
    $functions = "<?php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
});
";

    file_put_contents($child_dir . '/functions.php', $functions);

    return $child_slug;
}
