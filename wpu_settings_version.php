<?php

/*
Plugin Name: WPU Settings Version
Description: Keep a custom DB version of your website
Plugin URI: https://github.com/WordPressUtilities/wpu_settings_version
Version: 0.10.2
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class wpu_settings_version {

    public function __construct() {
        add_action('wp_loaded', array(&$this, 'wp_loaded'));
    }

    public function wp_loaded() {
        if (!get_site_url()) {
            return;
        }
        $new_version = 0;
        $current_version = $this->get_current_version();

        /* Retrieve actions, sorted by version */
        $actions = $this->get_actions();
        foreach ($actions as $v => $action) {
            /* Action has already been launched */
            if ($v <= $current_version) {
                continue;
            }
            /* Launch action */
            call_user_func($action);
            /* Save new version */
            if ($v > $new_version) {
                $new_version = $v;
            }
        }

        /* Save new version */
        if ($new_version > $current_version) {
            update_option('wpusettingsversion_version', $new_version);
        }

        /* Set favicon */
        $this->set_site_icon();

    }

    /**
     * Get current version
     * @return int Version number
     */
    public function get_current_version() {
        $current_version = get_option('wpusettingsversion_version');
        if (!$current_version) {
            $current_version = 0;
        }
        return intval($current_version, 10);
    }

    /**
     * Get actions
     * @return array Array of function callbacks
     */
    public function get_actions() {
        $actions = apply_filters('wpusettingsversion_actions', array());
        ksort($actions);
        if (!is_array($actions)) {
            $actions = array();
        }
        return $actions;
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    public function upload_asset_by_path($imagepath) {

        if (!file_exists($imagepath)) {
            return false;
        }

        /* get image infos */
        $imageinfos = pathinfo($imagepath);

        /* Copy image to a tmp file */
        $wp_upload_dir = wp_upload_dir();
        $newfile = md5($imagepath) . uniqid();
        if (isset($imageinfos['extension'])) {
            $newfile .= '.' . $imageinfos['extension'];
        }
        $image = $wp_upload_dir['path'] . '/' . $newfile;
        copy($imagepath, $image);

        /* Required methods */
        require_once ABSPATH . '/wp-admin/includes/file.php';
        require_once ABSPATH . '/wp-admin/includes/media.php';
        require_once ABSPATH . '/wp-admin/includes/image.php';

        /* Upload image */
        $att = media_handle_sideload(array(
            'name' => basename($image),
            'type' => wp_check_filetype($image),
            'tmp_name' => $image,
            'error' => 0,
            'size' => filesize($image)
        ), 0);

        /* Return upload info */
        if (!is_numeric($att)) {
            $att = false;
        }
        return $att;

    }

    /* ----------------------------------------------------------
      Plugins
    ---------------------------------------------------------- */

    public function activate_plugins($plugins = array()) {
        if (!is_array($plugins)) {
            return false;
        }
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ($plugins as $plugin) {
            if (!is_plugin_active($plugin)) {
                activate_plugin($plugin);
            }
        }
    }

    /* ----------------------------------------------------------
      Create menus
    ---------------------------------------------------------- */

    public function set_menus($pages = array(), $menus = array(), $theme_id = '') {
        $opt_id = 'theme_mods_' . $theme_id;
        $theme_mods = get_option($opt_id);
        foreach ($menus as $pos => $menu_name) {

            /* Delete menu if it exists */
            $menu_exists = wp_get_nav_menu_object($menu_name);
            if ($menu_exists) {
                wp_delete_nav_menu($menu_name);
            }
            // If it doesn't exist, let's create it.
            $menu_id = wp_create_nav_menu($menu_name);

            foreach ($pages as $p) {

                if (!is_numeric($p) && !is_array($p)) {
                    continue;
                }

                if (is_numeric($p)) {
                    $p = array(
                        'type' => 'post_type',
                        'post_type' => get_post_type($p),
                        'id' => $p
                    );
                }

                if (!isset($p['id']) || !is_numeric($p['id'])) {
                    $p['id'] = -20;
                }

                if (!isset($p['type'])) {
                    $p['type'] = 'custom';
                }
                if (!isset($p['url'])) {
                    $p['url'] = site_url();
                }
                if (!isset($p['title'])) {
                    $p['title'] = 'Link';
                }
                if (!isset($p['post_type'])) {
                    $p['post_type'] = 'page';
                }

                $page_item = array(
                    'menu-item-db-id' => 0,
                    'menu-item-parent-id' => 0,
                    'menu-item-status' => 'publish'
                );

                $page_item['menu-item-object-id'] = $p['id'];
                $page_item['menu-item-type'] = $p['type'];
                $page_item['menu-item-title'] = $p['title'];
                $page_item['menu-item-url'] = $p['url'];

                switch ($p['type']) {
                case 'taxonomy':
                    $term = get_term($p['id']);
                    $page_item['menu-item-title'] = $term->name;
                    $page_item['menu-item-object-id'] = $term->term_id;
                    $page_item['menu-item-object'] = $term->taxonomy;
                    $page_item['menu-item-url'] = get_term_link($term->term_id);
                    break;

                case 'custom':
                    $page_item['menu-item-object'] = 'custom';
                    break;

                case 'post_type_archive':
                    $post_type = get_post_type_object($p['post_type']);
                    $page_item['menu-item-title'] = $post_type->label;
                    $page_item['menu-item-object'] = $p['post_type'];
                    $page_item['menu-item-url'] = get_post_type_archive_link($p['post_type']);
                    break;

                default:
                    $page_item['menu-item-title'] = get_the_title($p['id']);
                    $page_item['menu-item-object'] = $p['post_type'];
                    $page_item['menu-item-url'] = get_page_link($p['id']);
                }

                // Set up default menu items
                wp_update_nav_menu_item($menu_id, 0, $page_item);
            }

            if (!is_array($theme_mods)) {
                $theme_mods = array();
            }
            if (!isset($theme_mods['nav_menu_locations']) || !is_array($theme_mods['nav_menu_locations'])) {
                $theme_mods['nav_menu_locations'] = array();
            }
            $theme_mods['nav_menu_locations'][$pos] = $menu_id;

        }

        update_option($opt_id, $theme_mods);

    }

    /* ----------------------------------------------------------
      Favicon
    ---------------------------------------------------------- */

    public function set_site_icon() {
        $icon = get_option('site_icon');

        /* Stop if icon exists */
        if ($icon) {
            return false;
        }

        /* Stop if no favicon */
        $img_favicon = get_stylesheet_directory() . '/favicon.ico';
        if (!file_exists($img_favicon)) {
            return false;
        }

        /* Stop if invalid upload */
        $att = $this->upload_asset_by_path($img_favicon);
        if (!is_numeric($att)) {
            return false;
        }

        /* Update site value */
        update_option('site_icon', $att);
    }

    /* ----------------------------------------------------------
      Home page
    ---------------------------------------------------------- */

    public function force_home_page_id() {
        $home__page_id = get_option('home__page_id');
        $page_on_front = get_option('page_on_front');
        $show_on_front = get_option('show_on_front');
        if (is_numeric($home__page_id)) {
            if ($show_on_front != 'page' || $page_on_front != $home__page_id) {
                update_option('page_on_front', $home__page_id);
                update_option('show_on_front', 'page');
            }
        }
    }

}

$wpu_settings_version = new wpu_settings_version();

if (defined('WP_CLI')) {
    WP_CLI::add_command('wpu-settings-version', function ($args) {
        global $wpu_settings_version;
        $wpu_settings_version = new wpu_settings_version();
        $actions = $wpu_settings_version->get_actions();
        foreach ($actions as $v => $action) {
            WP_CLI::log('Running: ' . $action[1]);
            call_user_func($action);
        }
        WP_CLI::success('Success: all actions have been triggered.');
    }, array(
        'when' => 'muplugins_loaded'
    ));
}
