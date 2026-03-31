<?php

if (!defined('ABSPATH')) exit;

class WCLM_License_Core {

    public function __construct() {
        // 1. Process new orders when they are completed
        add_action('woocommerce_order_status_completed', [$this, 'handle_new_order_completion']);
        
        // 2. Hook for the daily automated task
        add_action('wclm_daily_license_check', [$this, 'run_daily_license_process']);
		
		add_filter('woocommerce_hidden_order_itemmeta', function($hidden_meta_keys) {
			$hidden_meta_keys[] = '_license_expiry_date';
			$hidden_meta_keys[] = '_license_reminder_sent';
			return $hidden_meta_keys;
		});
    }	
	
    /**
     * Handler for new orders being completed
     */
    public function handle_new_order_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        foreach ($order->get_items() as $item_id => $item) {
            if (self::is_license_product($item)) {
                $this->calculate_and_save_expiry($order, $item_id, $item);
            }
        }
    }

    /**
     * The Core Logic: Calculates 1 year from completion and saves meta
     */
    public function calculate_and_save_expiry($order, $item_id, $item) {
	    $order_completed = $order->get_date_created();
        if (!$order_completed) return false;

        $order_date = $order_completed->getTimestamp();

        // Set expiry to 1 year after the order was actually completed
        $expire_date = date('Y-m-d', strtotime('+1 year', $order_date));

        wc_update_order_item_meta($item_id, '_license_expiry_date', $expire_date);
        wc_update_order_item_meta($item_id, '_license_reminder_sent', 'no');
		
		// Save meta key at ORDER level
		update_post_meta($order->get_id(), '_license_expiry_date', $expire_date);
        
        $order->add_order_note(sprintf(__('License expiry initialized: %1$s for %2$s', 'WCLM'), $expire_date, $item->get_name()));
        
        return $expire_date;
    }

    /**
     * Daily Cron Job: 
     * 1. Finds old orders from the last year that missed the expiry setup.
     * 2. Checks who needs a reminder email (14 days before expiry).
     */
    public function run_daily_license_process() {
        // Fetch completed orders from the last 400 days
        $orders = wc_get_orders([
            'status'        => ['completed'],
            'date_completed' => '>' . date('Y-m-d', strtotime('-400 days')), 
            'limit'         => -1,
            'return'        => 'ids',
        ]);
        
        if (!$orders) return;

        $today = current_time('Y-m-d');

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            foreach ($order->get_items() as $item_id => $item) {
                if (!self::is_license_product($item)) continue;

                $expiry_date   = wc_get_order_item_meta($item_id, '_license_expiry_date', true);
                $reminder_sent = wc_get_order_item_meta($item_id, '_license_reminder_sent', true);

                // STEP 1: RETROACTIVE SETUP
                // If an old order exists but never had an expiry date set, set it now.
                if (empty($expiry_date)) {
                    $expiry_date = $this->calculate_and_save_expiry($order, $item_id, $item);
                }

                if (!$expiry_date || $reminder_sent === 'yes') continue;

                // STEP 2: REMINDER CHECK
                $expiry_timestamp = strtotime($expiry_date);
                $reminder_date    = date('Y-m-d', strtotime('-14 days', $expiry_timestamp));

                // If today is within the 14-day window before expiry
                if ($today >= $reminder_date && $today < $expiry_date) {
					WCLM_License_Email::send_customer_email($order, $item, $expiry_date);
					WCLM_License_Email::send_admin_email($order, $item, $expiry_date);

                    wc_update_order_item_meta($item_id, '_license_reminder_sent', 'yes');
                    $order->add_order_note( __('License reminder email sent to customer and admin.', 'WCLM'));
                }
            }
        }
    }
	
    /**
     * Filter: Determine if a product qualifies for a license
     */
    public static function is_license_product($item) {
        // Check Products (Category: services)
        $product_id = $item->get_product_id();

        if(has_term('website-service-packages', 'product_cat', $product_id)){
			return true;
		}
		
		return false;
    }

	/**
	 * Get prodcut variation description
	 */
	public static function get_product_variation_desc($item) {
		// Get variation description
		$variation_id = $item->get_variation_id();
		$variation_desc = '';
		
		if($variation_id > 0){
			$variation = wc_get_product($variation_id);
			if($variation){
				$full_desc = $variation->get_description();
				
				// If there's a colon, take everything before it.
				if (str_contains($full_desc, ':')) {
					$parts = explode(':', $full_desc);
					$variation_desc = trim($parts[0]);
				} else {
					$variation_desc = $full_desc;
				}
				
				//Change "Storage only" to just "Storage" in service type
            	$variation_desc = str_ireplace('Storage only', 'Storage', $variation_desc);
				$variation_desc = str_ireplace('אחסון בלבד', 'אחסון', $variation_desc);
			}
		}

		return $variation_desc;
	}
}