<?php
/*
Plugin Name: JOLT™ ThemeFork
Plugin URI: https://github.com/johnoltmans/JOLT-ThemeFork
Description: Instantly create a clean, functional child theme from your currently active WordPress theme — with one click.
Version: 1.3
Author: John Oltmans
Author URI: https://www.johnoltmans.nl
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Beveiliging: direct toegang blokkeren
defined('ABSPATH') || exit;

// ====== FUNCTIES VOOR CHILD THEME VERWIJDEREN ======
// Deze functies komen bovenaan, buiten andere functies.

// Verwijder een hele map met alle inhoud
function jolt_themefork_rrmdir($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            jolt_themefork_rrmdir($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// Verwijder child theme map (veiligheid: niet actieve theme verwijderen!)
function jolt_themefork_delete_child_theme($child_slug) {
    $child_dir = WP_CONTENT_DIR . '/themes/' . $child_slug;

    if (!file_exists($child_dir)) {
        return new WP_Error('not_found', 'Child theme bestaat niet.');
    }

    // Check dat het child theme niet actief is
    $active_slug = wp_get_theme()->get_stylesheet();
    if ($child_slug === $active_slug) {
        return new WP_Error('active_theme', 'Je kunt het actieve thema niet verwijderen.');
    }

    // Verwijder de map met de recursieve functie
    $delete = jolt_themefork_rrmdir($child_dir);

    if (!$delete) {
        return new WP_Error('delete_failed', 'Verwijderen van child theme is mislukt.');
    }

    return true;
}

// ====== FUNCTIE OM EEN CHILD THEME TE MAKEN ======
function jolt_themefork_create_child_theme($parent_slug) {
    $themes = wp_get_themes();

    if (!isset($themes[$parent_slug])) {
        return new WP_Error('invalid_theme', 'Geselecteerd thema bestaat niet.');
    }

    $parent = $themes[$parent_slug];
    $child_slug = $parent_slug . '-child';
    $child_dir = WP_CONTENT_DIR . '/themes/' . $child_slug;

    if (file_exists($child_dir)) {
        return new WP_Error('child_exists', 'Child theme bestaat al.');
    }

    if (!wp_mkdir_p($child_dir)) {
        return new WP_Error('mkdir_failed', 'Kon child theme map niet aanmaken.');
    }

    // style.css voor het child theme
    $style = "/*
Theme Name: " . $parent->get('Name') . " Child
Theme URI: " . $parent->get('ThemeURI') . "
Description: Child theme van " . $parent->get('Name') . "
Author: " . $parent->get('Author') . "
Template: $parent_slug
Version: 1.0.0
*/";

    file_put_contents($child_dir . '/style.css', $style);

    // functions.php voor het child theme
    $functions = "<?php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css', [], wp_get_theme()->get('Version'));
});
";

    file_put_contents($child_dir . '/functions.php', $functions);

    return $child_slug;
}

// ====== PLUGIN MENU EN INSTELLINGEN PAGINA ======

// Voeg instellingen menu toe in admin
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

// Voeg een "Settings" link toe bij de plugin op plugins-pagina
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'jolt_themefork_settings_link');
function jolt_themefork_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=jolt-themefork') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Instellingenpagina (deze functie toont het scherm in WP admin)
function jolt_themefork_settings_page() {
    // Verwerk DELETE child theme actie
    if (isset($_POST['jolt_themefork_delete']) && check_admin_referer('jolt_themefork_nonce_action', 'jolt_themefork_nonce_field')) {
        $child_slug = sanitize_text_field($_POST['jolt_themefork_delete']);
        $delete_result = jolt_themefork_delete_child_theme($child_slug);

        if (is_wp_error($delete_result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($delete_result->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Child theme <code>' . esc_html($child_slug) . '</code> succesvol verwijderd.</p></div>';
        }
    }

    // Verwerk CREATE child theme actie
    if (isset($_POST['jolt_themefork_submit']) && check_admin_referer('jolt_themefork_nonce_action', 'jolt_themefork_nonce_field')) {
        $selected_parent = sanitize_text_field($_POST['jolt_themefork_parent']);
        $result = jolt_themefork_create_child_theme($selected_parent);

        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            $child_slug = esc_html($result);
            $activate_url = wp_nonce_url(admin_url("themes.php?action=activate&stylesheet=$child_slug"), "switch-theme_$child_slug");
            echo '<div class="notice notice-success"><p>Child theme aangemaakt in <code>/wp-content/themes/' . $child_slug . '</code>.</p>';
            echo '<p><strong>Wil je dit child theme nu activeren?</strong></p>';
            echo '<p><a href="' . $activate_url . '" class="button button-secondary">Activeer Child Theme</a></p></div>';
        }
    }

    // HTML van instellingenpagina begint hier
    ?>
    <div class="wrap">
        <h1>JOLT ThemeFork Instellingen</h1>
        <p>Maak snel een child theme van je actieve thema.</p>

        <form method="post">
            <?php wp_nonce_field('jolt_themefork_nonce_action', 'jolt_themefork_nonce_field'); ?>

            <h2>Selecteer een ouderthema</h2>
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
                    echo '<div>' . esc_html($name) . ($is_active ? ' <span style="color:green;">(Actief)</span>' : '') . '</div>';
                    echo '</label>';
                }
                ?>
            </div>

            <p>
                <input type="submit" name="jolt_themefork_submit" class="button button-primary" value="Maak Child Theme aan">
            </p>
        </form>

        <hr>

        <h2>Verwijder een child theme</h2>
        <form method="post" onsubmit="return confirm('Weet je zeker dat je dit child theme wilt verwijderen?');">
            <?php wp_nonce_field('jolt_themefork_nonce_action', 'jolt_themefork_nonce_field'); ?>

            <select name="jolt_themefork_delete" required>
                <option value="">Selecteer een child theme om te verwijderen</option>
                <?php
                // Toon alleen child themes (met "-child" suffix)
                foreach ($themes as $slug => $theme) {
                    if (str_ends_with($slug, '-child')) {
                        echo '<option value="' . esc_attr($slug) . '">' . esc_html($theme->get('Name')) . ' (' . esc_html($slug) . ')</option>';
                    }
                }
                ?>
            </select>
            <input type="submit" class="button button-secondary" value="Verwijder child theme">
        </form>
    </div>
    <?php
}
