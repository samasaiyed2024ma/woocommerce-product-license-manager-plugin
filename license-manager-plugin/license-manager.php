<?php
/**
 * Plugin Name: Woocommerce License Manager
 * Description: Adds license expiry and reminder system for services products.
 * Version: 1.0
 * Author: Mervan Agency
 * Text Domain: woocommerce-license-manager
 */

if (!defined('ABSPATH')) exit;

define('WCLM_PATH', plugin_dir_path(__FILE__));
define('WCLM', 'woocommerce-license-manager');

require_once WCLM_PATH . 'includes/class-license-email.php';
require_once WCLM_PATH . 'includes/class-license-core.php';
require_once WCLM_PATH . 'includes/class-license-admin.php';


/**
 * Plugin activation
 */ 
register_activation_hook(__FILE__, 'wclm_activate_plugin');
function wclm_activate_plugin(){
	if (!wp_next_scheduled('wclm_daily_license_check')) {
        wp_schedule_event(time(), 'daily', 'wclm_daily_license_check');
   	}
}

/**
 * Plugin deactivation
 */ 
register_deactivation_hook(__FILE__, 'wclm_deactivate_plugin');
function wclm_deactivate_plugin(){
	wp_clear_scheduled_hook('wclm_daily_license_check');
}

/**
 * Initialize plugin
 */
function wclm_init_plugin(){
    if(class_exists('WooCommerce')){
        new WCLM_License_Core();
        if(is_admin()){
            new WCLM_License_Admin();
        }
    }
}

add_action('plugins_loaded', 'wclm_init_plugin');