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
            if ($this->is_license_product($item)) {
                $this->calculate_and_save_expiry($order, $item_id, $item);
            }
        }
    }

    /**
     * The Core Logic: Calculates 1 year from completion and saves meta
     */
    public function calculate_and_save_expiry($order, $item_id, $item) {
	    $order_created = $order->get_date_created();
        if (!$order_created) return false;

        $order_date = $order_created->getTimestamp();

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
                if (!$this->is_license_product($item)) continue;

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
                $reminder_date    = date('Y-m-d', strtotime('-2 days', $expiry_timestamp));

                // If today is within the 14-day window before expiry
                if ($today >= $reminder_date && $today < $expiry_date) {
                    $this->send_customer_email($order, $item, $expiry_date);
                    $this->send_admin_email($order, $item, $expiry_date);

                    wc_update_order_item_meta($item_id, '_license_reminder_sent', 'yes');
                    $order->add_order_note( __('License reminder email sent to customer and admin.', 'WCLM'));
                }
            }
        }
    }
	
    /**
     * Filter: Determine if a product qualifies for a license
     */
    public function is_license_product($item) {
        // Check Products (Category: services)
        $product_id = $item->get_product_id();

        if(has_term('services', 'product_cat', $product_id)){
			return true;
		}
		
		return false;
    }

    /**
     * Email Templates
     */
	public function send_customer_email($order, $item, $expiry_date) {
		$to      = $order->get_billing_email();
		$customer_name  = $order->get_formatted_billing_full_name();
		$product = $item->get_name();
		$subject = esc_html__('הגיע זמן החידוש של המנוי השנתי לשירותי האתר!');

		ob_start();
		?>

		<h2 style="color:#cc0000; text-align:right;"> <?php echo esc_html__('הודעה על חידוש אוטומטי של שירותים לאתר', 'WCLM'); ?> </h2>

		<p style="direction:rtl; text-align:right;"> <?php echo esc_html__('שלום רב,', 'WCLM'); ?> </p>
		
		<p style="direction:rtl; text-align:right;">
            <?php echo esc_html__('ברצוננו לעדכנך כי בעוד 14 ימים יחודשו שירותי האתר שלך אוטומטית לשנה נוספת, בהתאם לתנאי ההתקשרות:', 'WCLM'); ?>
		</p>

		<table cellspacing="0" cellpadding="10" style="width:100%; border:1px solid #e5e5e5; border-collapse:collapse; margin:20px 0; direction:rtl;">
			<tr>
				<th style="text-align:right; background:#f3f2ff; border:1px solid #e5e5e5;"> <?php esc_html_e('לקוח', 'WCLM'); ?> </th>
				<td style="border:1px solid #e5e5e5; text-align:right;"><?php echo esc_html($customer_name); ?></td>
			</tr>
			
			<tr>
				<th style="text-align:right; background:#f3f2ff; border:1px solid #e5e5e5;"> <?php esc_html_e('מוצר', 'WCLM'); ?> </th>
				<td style="border:1px solid #e5e5e5; text-align:right;"><?php echo esc_html($product); ?></td>
			</tr>

			<tr>
				<th style="text-align:right; background:#f3f2ff; border:1px solid #e5e5e5;"> <?php esc_html_e('תאריך פקיעת הרישיון', 'WCLM'); ?> </th>
				<td style="border:1px solid #e5e5e5; font-weight:bold; color:#d63638; text-align:right;">
					<?php echo esc_html($expiry_date); ?>
				</td>
			</tr>
   		</table>

		<p style="margin-top:20px; direction:rtl; text-align:right;">
			<?php esc_html_e('אי פנייה אלינו בתוך פרק זמן זה תיחשב כהסכמה לחידוש השירותים, באותם תנאים.', 'WCLM'); ?> <br>
			<?php esc_html_e('במהלך 14 הימים ממועד קבלת הודעה זו ניתן לפנות אלינו בכתב לצורך שינוי חבילת השירותים - הוספה או הסרה של שירותים, או בקשה להפסקת כלל השירותים. אי פנייה אלינו בתקופה זו תחשב כהסכמתך לחידוש השירותים לשנה נוספת.'); ?> <br>
			<?php esc_html_e('במקרה של בקשה להפסקת כלל השירותים - האתר יימחק משרתי החברה וירד מהאוויר.'); ?>
		</p>

		<p style="direction:rtl; text-align:right;">
			<?php esc_html_e('לכל שאלה או בקשה - ניתן להשיב למייל זה.'); ?>
		</p>

		<p style="direction:rtl; text-align:right;">
            <?php esc_html_e('בברכה,', 'WCLM'); ?><br>
            <?php esc_html_e('מירב', 'WCLM'); ?>
   		</p>

		<?php

		$message = ob_get_clean();

		$mailer = WC()->mailer();
		$wrapped_message = $mailer->wrap_message($subject, $message);

		wc_mail($to, $subject, $wrapped_message);
	}
	
	public function send_admin_email($order, $item, $expiry_date) {
        $admin_email    = get_option('admin_email');
		$customer_name  = $order->get_formatted_billing_full_name();
        $product        = $item->get_name();
        $order_id       = $order->get_id();
        $subject        = sprintf(__('נדרשת פעולה: התראת פקיעת תוקף רישיון (הזמנה #%d)', 'WCLM'), $order_id);;
		
		ob_start();
		
		?>
			<h2 style="color:#cc0000; text-align:right;"> <?php esc_html_e('התראת פקיעת תוקף רישיון', 'WCLM'); ?> </h2>
			<p style="direction:rtl; text-align:right;"> <?php esc_html_e('הרישיון הבא עומד פוג בקרוב:', 'WCLM'); ?> </p>
			
			<table cellspacing="0" cellpadding="10" style="width:100%; border:1px solid #e5e5e5; border-collapse:collapse; direction:rtl;">
				<tr>
					<th style="text-align:right; border:1px solid #e5e5e5; background:#f7f7f7;"><?php esc_html_e('מזהה הזמנה', 'WCLM'); ?> </th>
					<td style="border:1px solid #e5e5e5; text-align:right;">#<?php echo esc_html($order_id); ?></td>
				</tr>

				<tr>
					<th style="text-align:right; border:1px solid #e5e5e5; background:#f7f7f7;"> <?php esc_html_e('לקוח', 'WCLM'); ?> </th>
					<td style="border:1px solid #e5e5e5; text-align:right;"><?php echo esc_html($customer_name); ?></td>
				</tr>

				<tr>
					<th style="text-align:right; border:1px solid #e5e5e5; background:#f7f7f7;"> <?php esc_html_e('מוצר', 'WCLM'); ?> </th>
					<td style="border:1px solid #e5e5e5; text-align:right;"><?php echo esc_html($product); ?></td>
				</tr>

				<tr>
					<th style="text-align:right; border:1px solid #e5e5e5; background:#f7f7f7;"> <?php esc_html_e('תאריך פקיעת תוקף', 'WCLM'); ?></th>
					<td style="border:1px solid #e5e5e5; font-weight:bold; color:#d63638; text-align:right;"><?php echo esc_html($expiry_date); ?></td>
				</tr>
			</table>																						
		<?php
		$message = ob_get_clean();
		$mailer = WC()->mailer();
		$wrapped_message = $mailer->wrap_message($subject, $message);
		
		wc_mail($admin_email, $subject, $wrapped_message);
	}
}