<?php
/**
 * Plugin Name: Fluent Forms PDF Generator
 * Plugin URI:  https://wpmanageninja.com/downloads/fluentform-pro-add-on/
 * Description: Download entries as pdf with multiple template.
 * Author: WPManageNinja LLC
 * Author URI:  https://wpmanageninja.com
 * Version: 1.1.11
 * Text Domain: fluentforms-pdf
 * Domain Path: /assets/languages
 * License: GPLv2 or later
 */

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright 2019 WPManageNinja LLC. All rights reserved.
 */

defined('ABSPATH') or die;
define('FLUENTFORM_PDF_VERSION', '1.1.11');
define('FLUENTFORM_PDF_PATH', plugin_dir_path(__FILE__));
define('FLUENTFORM_PDF_URL', plugin_dir_url(__FILE__));

if (!defined('FLUENTFORM_FRAMEWORK_UPGRADE')) {
    define('FLUENTFORM_FRAMEWORK_UPGRADE', '4.3.22');
}

class FluentFormPdf
{
    public function boot()
    {
        if (!defined('FLUENTFORM')) {
            return $this->injectDependency();
        }

        $this->includeFiles();

        if (function_exists('wpFluentForm')) {
            return $this->registerHooks(wpFluentForm());
        }
    }

    protected function includeFiles()
    {
        require_once FLUENTFORM_PDF_PATH . 'Classes/Controller/AvailableOptions.php';
        require_once FLUENTFORM_PDF_PATH . 'Classes/Controller/FontManager.php';
        require_once FLUENTFORM_PDF_PATH . 'Classes/Controller/GlobalPdfManager.php';

        require_once FLUENTFORM_PDF_PATH . 'Classes/Templates/TemplateManager.php';
        require_once FLUENTFORM_PDF_PATH . 'Classes/Templates/GeneralTemplate.php';
        require_once FLUENTFORM_PDF_PATH . 'Classes/Templates/InvoiceTemplate.php';
        
        require_once FLUENTFORM_PDF_PATH . 'Classes/Report/ReportPdfGenerator.php';
    }

    protected function registerHooks($fluentForm)
    {
        new \FluentFormPdf\Classes\Controller\GlobalPdfManager($fluentForm);
    }


    /**
     * Notify the user about the FluentForm dependency and instructs to install it.
     */
    protected function injectDependency()
    {
        add_action('admin_notices', function () {
            $pluginInfo = $this->getFluentFormInstallationDetails();

            $class = 'notice notice-error';

            $install_url_text = __('Click Here to Install the Plugin', 'fluentforms-pdf');

            if ($pluginInfo->action == 'activate') {
                $install_url_text = __('Click Here to Activate the Plugin', 'fluentforms-pdf');
            }

            $message = __('FluentForm pdf Add-On Requires Fluent Forms Plugin, ', 'fluentforms-pdf');
            $message .= '<b><a href="' .$pluginInfo->url . '">' . $install_url_text . '</a></b>';

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
        });
    }

    protected function getFluentFormInstallationDetails()
    {
        $activation = (object) [
            'action' => 'install',
            'url'    => ''
        ];

        $allPlugins = get_plugins();

        if (isset($allPlugins['fluentform/fluentform.php'])) {
            $url = wp_nonce_url(
                self_admin_url('plugins.php?action=activate&plugin=fluentform/fluentform.php'),
                'activate-plugin_fluentform/fluentform.php'
            );

            $activation->action = 'activate';
        } else {
            $api = (object) ['slug' => 'fluentform'];

            $url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=' . $api->slug),
                'install-plugin_' . $api->slug
            );
        }
        $activation->url = $url;
        return $activation;
    }
}

add_action('plugins_loaded', function () {
    (new FluentFormPdf())->boot();
});

register_activation_hook(__FILE__, function () {
    require_once FLUENTFORM_PDF_PATH . '/Classes/Controller/Activator.php';
    \FluentFormPdf\Classes\Controller\Activator::activate();
});

register_deactivation_hook( __FILE__, function () {
    require_once FLUENTFORM_PDF_PATH . '/Classes/Controller/Activator.php';
    \FluentFormPdf\Classes\Controller\Activator::deactivate();
});
