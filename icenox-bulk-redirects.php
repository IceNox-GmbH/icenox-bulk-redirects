<?php
/**
 * Plugin Name: IceNox Bulk Redirects
 * Description: Redirect multiple pages to a single page
 * Version: 2.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: IceNox GmbH
 * Author URI: https://icenox.com
 * Licence: None
 */

class IceNoxBulkRedirects
{
    private $enabled;
    private $redirectUrl;
    private $pathList;

    public function __construct()
    {
        $this->enabled = get_option('icenox_bulk_redirects_enabled') === "on";
        $this->redirectUrl = get_option('icenox_bulk_redirects_url');
        $this->pathList = get_option('icenox_bulk_redirects_path_list') ?: [];

        if (is_admin()) {
            add_action('admin_enqueue_scripts', function () {
                wp_register_style('icenox-bulk-redirects-admin-styles', plugins_url('admin.css', __FILE__));
                wp_register_script('icenox-bulk-redirects-admin-scripts', plugins_url('admin.js', __FILE__));
            });

            add_action('admin_menu', [$this, 'addSettingsPage']);
            add_action('admin_init', [$this, 'settingsPageInit']);

            add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'icenox_bulk_redirects_settings_link');

            function icenox_bulk_redirects_settings_link(array $links)
            {
                $url = get_admin_url() . "admin.php?page=icenox-bulk-redirects";
                $settings_link = '<a href="' . $url . '">' . __('Settings', 'icenox-bulk-redirects') . '</a>';
                array_unshift($links, $settings_link);
                return $links;
            }
        }

        /* Handle Redirects if Redirects are enabled */
        if ($this->enabled) {
            $this->handleRedirects();
        }
    }

    private function handleRedirects()
    {
        if (empty($this->redirectUrl) || empty($this->pathList)) {
            return;
        }

        $currentPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        if (in_array($currentPath, $this->pathList) || in_array($currentPath . "/", $this->pathList)) {
            header("Location: " . $this->redirectUrl, true, 302);
            exit();
        }
    }

    public function addSettingsPage()
    {
        add_menu_page(
            'Bulk Redirects', // page_title
            'Bulk Redirects', // menu_title
            'manage_options', // capability
            'icenox-bulk-redirects', // menu_slug,
            [$this, 'createSettingsPage'],
            'dashicons-admin-links', //path
            null //position
        );
    }

    public function createSettingsPage()
    {
        wp_enqueue_style('icenox-bulk-redirects-admin-styles');
        wp_enqueue_script('icenox-bulk-redirects-admin-scripts');
        ?>

        <div class="wrap icenox-bulk-redirects">
            <h2>Bulk Redirects</h2>
            <p></p>
            <?php settings_errors(); ?>
            <table class="added-paths">
                <thead>
                <tr>
                    <td class="path">Path</td>
                    <td class="remove-row">Remove</td>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($this->pathList as $key => $path): ?>
                    <tr>
                        <td class="path-row"><?= $path ?></td>
                        <td class="remove-row">
                            <button class="remove-button button-secondary" data-path-key="<?= $key ?>" data-wpnonce="<?=wp_create_nonce("icenox_redirects_option_group-options")?>">Remove</button>
                        </td>
                    </tr>
                <?php endforeach;
                if (empty($this->pathList)):?>
                    <tr>
                        <td class="path-row">Please first add a new Path below.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <form class="bulk-redirects-form" method="post" action="options.php">
                <?php
                settings_fields('icenox_redirects_option_group');
                do_settings_sections('icenox-redirects-admin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function settingsPageInit()
    {
        add_settings_section(
            'icenox_redirects_settings_section', // id
            'Settings', // title
            '', // callback
            'icenox-redirects-admin', // page
            [
                'before_section' => '<section class="%s">',
                'after_section' => '</section>',
                'section_class' => 'settings-section',
            ]
        );

        register_setting(
            'icenox_redirects_option_group', // option_group
            'icenox_bulk_redirects_enabled', // option_name
            [
                'type' => 'string',
                'sanitize_callback' => function ($value) {
                    if(isset($value)) {
                        return $value;
                    } else {
                        return $this->enabled ? "on" : "off";
                    }
                },
                'default' => 'no'
            ]
        );

        add_settings_field(
            "enabled-status",
            "Enabled",
            [$this, 'enabledStatusCheckbox'],
            'icenox-redirects-admin', // page
            'icenox_redirects_settings_section', //section
        );

        register_setting(
            'icenox_redirects_option_group', // option_group
            'icenox_bulk_redirects_url', // option_name
            [
                'type' => 'string',
                'sanitize_callback' => [$this, "redirectUrlCallback"],
                'default' => ''
            ]
        );

        add_settings_field(
            "redirect-url",
            "Redirect URL",
            [$this, 'redirectUrlInput'],
            'icenox-redirects-admin', // page
            'icenox_redirects_settings_section', //section
        );

        register_setting(
            'icenox_redirects_option_group', // option_group
            'icenox_bulk_redirects_path_list', // option_name
            [
                'type' => 'array',
                'sanitize_callback' => [$this, "addNewPathCallback"],
                'default' => []
            ]
        );

        add_settings_field(
            "add-new-path",
            "Add new Path",
            [$this, 'addNewPathInput'],
            'icenox-redirects-admin', // page
            'icenox_redirects_settings_section', //section
        );

        register_setting(
            'icenox_redirects_option_group', // option_group
            'icenox_bulk_redirects_remove_path', // option_name
            [
                'type' => 'int',
                'sanitize_callback' => [$this, "removePathCallback"],
                'default' => null
            ]
        );
    }

    public function enabledStatusCheckbox()
    {
        $enabled = $this->enabled; ?>
        <label class="input-label sr-only" for="icenox-bulk-redirects-enabled">URL</label>
        <input id="icenox-bulk-redirects-enabled" name="icenox_bulk_redirects_enabled" type="checkbox" <?= $enabled ? "checked" : "" ?>>
        <?php
    }

    public function redirectUrlInput()
    {
        $redirectUrl = $this->redirectUrl; ?>
        <label class="input-label sr-only" for="icenox-bulk-redirects-url">URL</label>
        <input id="icenox-bulk-redirects-url" name="icenox_bulk_redirects_url" type="url" value="<?= $redirectUrl ?>">
        <?php
    }

    public function addNewPathInput()
    {
        ?>
        <label class="input-label sr-only" for="icenox-bulk-redirects-add-path">Path</label>
        <input id="icenox-bulk-redirects-add-path" name="icenox_bulk_redirects_path_list" type="text"
               placeholder="/new-path/" value="">
        <div class="add-path-description">
            Please note, the Path is matched exactly.<br>The path needs to start with a slash (/) and a trailing slash
            is often required too. Don't add any query strings or fragments.
        </div>
        <?php
    }

    public function redirectUrlCallback($value)
    {
        if(isset($value)) {
            return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
        } else {
            return $this->redirectUrl;
        }
    }

    public function addNewPathCallback($value)
    {
        $pathList = $this->pathList;

        if (is_string($value) && str_starts_with($value, "/")) {
            $pathList[] = $value;
        }

        if(isset($_POST["icenox_bulk_redirects_remove_path"])) {
            $removeIndex = $_POST["icenox_bulk_redirects_remove_path"];
            if (is_numeric($removeIndex) && isset($pathList[$removeIndex])) {
                unset($pathList[$removeIndex]);
            }
        }

        return $pathList;
    }

    public function removePathCallback($value)
    {
        $pathList = $this->pathList;
        if (is_numeric($value) && isset($pathList[$value])) {
            unset($pathList[$value]);
            update_option("icenox_bulk_redirects_path_list", $pathList);
            $this->pathList = $pathList;
        }

        return "";
    }

}

$icenoxBulkRedirects = new IceNoxBulkRedirects();

/*
function doCnDRedirects() {
    $redirectUrl = "https://criminalmodz.com/cnd-notice/";
    
    $pathList = [
        "/xbox-series-x-modded-accounts/",
        "/xbox-xs-account-boost/",
        "/account-boost/",
        "/account-boost-ps5/",
        "/account-boost-ps4/",
        "/modded-accounts-pc/",
        "/modded-outfits-pc/",
        "/modded-outfits-ps4/",
        "/modded-packages/",
        "/ps4-modded-accounts/",
        "/ps4-modded-combos/",
        "/ps4-modded-packages/",
        "/ps5-modded-accounts/",
        "/xbox-one-modded-packages/",
        "/xbox-one-cash-drops/",
        "/xbox-one-modded-accounts/"
    ];

    $currentPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    if(in_array($currentPath, $pathList)) {
        http_response_code(302);
        header("Location: " . $redirectUrl);
    }
}

doCnDRedirects();
*/
