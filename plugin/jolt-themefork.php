<?php
/*
Plugin Name: JOLT™ ThemeFork
Plugin URI: https://github.com/johnoltmans/JOLT-ThemeFork
Description: Instantly create a clean, functional child theme from your currently active WordPress theme — with one click.
Version: 1.0
Author: John Oltmans
Author URI: https://www.johnoltmans.nl
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined('ABSPATH') || exit;

// Admin menu toevoegen
add_action('admin_menu', 'jolt_themefork_register_menu');

function jolt_themefork_register_menu() {
    add_theme_page(
        'ThemeFork',
        'ThemeFork',
        'switch_themes',
        'jolt-themefork',
        'jolt_themefork_admin_page'
    );
}

// Adminpagina tonen
function jolt_themefork_admin_page() {
    ?>
    <div class="wrap">
        <h1>JOLT ThemeFork</h1>
        <p>Click the button below to generate a child theme based on your currently active theme.</p>

        <?php
        if (isset($_POST['jolt_themefork_submit']) && check_admin_referer('jolt_themefork_nonce_action', 'jolt_themefork_nonce_field')) {
            $result = jolt_themefork_create_child_theme();
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Child theme created successfully in <code>/wp-content/themes/' . esc_html($result) . '</code>.</p></div>';
            }
        }
        ?>

        <form method="post">
            <?php wp_nonce_field('jolt_themefork_nonce_action', 'jolt_themefork_nonce_field'); ?>
            <p><input type="submit" name="jolt_themefork_submit" class="button button-primary" value="Fork Active Theme"></p>
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
