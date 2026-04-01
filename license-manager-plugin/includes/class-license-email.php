<?php 
if (!defined('ABSPATH')) exit;

class WCLM_License_Email {

    /**
     * Common Mail Wrapper
     */
    private static function send_mail($to, $subject, $message) {
        $mailer = WC()->mailer();
        $wrapped = $mailer->wrap_message($subject, $message);

        wc_mail($to, $subject, $wrapped);
    }

    /**
     * Send customer email for license expiration reminder
     */
    public static function send_customer_email($order, $item, $expiry_date) {
		$to      = $order->get_billing_email();
		$customer_name  = $order->get_formatted_billing_full_name();
		$product = $item->get_name();
		$variation_desc = WCLM_License_Core::get_product_variation_desc($item);
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

            <?php if ( ! empty( $variation_desc ) ) : ?>
			<tr>
				<th style="text-align:right; background:#f3f2ff; border:1px solid #e5e5e5;"> <?php esc_html_e('סוג שירות', 'WCLM'); ?> </th>
				<td style="border:1px solid #e5e5e5; text-align:right;"><?php echo esc_html($variation_desc); ?></td>
			</tr>
			<?php endif; ?>

			<tr>
				<th style="text-align:right; background:#f3f2ff; border:1px solid #e5e5e5;"> <?php esc_html_e('תאריך פקיעת הרישיון', 'WCLM'); ?> </th>
				<td style="border:1px solid #e5e5e5; font-weight:bold; color:#d63638; text-align:right;">
					<?php echo esc_html($expiry_date); ?>
				</td>
			</tr>
   		</table>

		<p style="margin-top:20px; direction:rtl; text-align:right;">
			<?php esc_html_e('אי פנייה אלינו בתוך פרק זמן זה תיחשב כהסכמה לחידוש השירותים, באותם תנאים.', 'WCLM'); ?> <br>
			<?php esc_html_e('במהלך 14 הימים ממועד קבלת הודעה זו ניתן לפנות אלינו בכתב לצורך שינוי חבילת השירותים - הוספה או הסרה של שירותים, או בקשה להפסקת כלל השירותים.'); ?> <br>
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
        self::send_mail($to, $subject, $message);
	}

    /**
     * Send admin email for license expiration reminder
     */
    public static function send_admin_email($order, $item, $expiry_date) {
        $admin_email    = get_option('admin_email');
		$customer_name  = $order->get_formatted_billing_full_name();
        $product        = $item->get_name();
        $order_id       = $order->get_id();
		$variation_desc = WCLM_License_Core::get_product_variation_desc($item);
        $subject = sprintf(__('נדרשת פעולה: התראת פקיעת תוקף רישיון (הזמנה #%d)', 'WCLM'), $order_id);		

		ob_start();
		
		?>
			 <h3 style="color:#cc0000; text-align:right;">
				<?php echo esc_html(sprintf('%s - תוקף המנוי של לקוח מסתיים', $customer_name)); ?>
			 </h3>
			
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

				<?php if ( ! empty( $variation_desc ) ) : ?>
				<tr>
					<th style="text-align:right; border:1px solid #e5e5e5; background:#f7f7f7;"> <?php esc_html_e('סוג שירות', 'WCLM'); ?> </th>
					<td style="border:1px solid #e5e5e5; text-align:right;"><?php echo esc_html($variation_desc); ?></td>
				</tr>
				<?php endif; ?>
				
				<tr>
					<th style="text-align:right; border:1px solid #e5e5e5; background:#f7f7f7;"> <?php esc_html_e('תאריך פקיעת תוקף', 'WCLM'); ?></th>
					<td style="border:1px solid #e5e5e5; font-weight:bold; color:#d63638; text-align:right;"><?php echo esc_html($expiry_date); ?></td>
				</tr>
			</table>																						
		<?php
		$message = ob_get_clean();
		self::send_mail($admin_email, $subject, $message);
	}

    /**
     * Send customer email when the license has renewed
     */
    public static function send_license_renewal_email_to_customer($order, $item, $new_expiry){
        $to      = $order->get_billing_email();
		$customer_name  = $order->get_formatted_billing_full_name();
		$product = $item->get_name();
		$variation_desc = WCLM_License_Core::get_product_variation_desc($item);
		$subject = esc_html__('הודעה על חידוש המנוי לשירותי האתר');

		ob_start();
		?>
		<p style="direction:rtl; text-align:right;"> <?php echo esc_html__('שלום רב,', 'WCLM'); ?> </p>
		
		<p style="direction:rtl; text-align:right;">
            <?php echo esc_html__('המנוי שלך לשירותי האתר חודש לשנה נוספת. אין צורך בפעולה נוספת מצידך.', 'WCLM'); ?>
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

            <?php if ( ! empty( $variation_desc ) ) : ?>
			<tr>
				<th style="text-align:right; background:#f3f2ff; border:1px solid #e5e5e5;"> <?php esc_html_e('סוג שירות', 'WCLM'); ?> </th>
				<td style="border:1px solid #e5e5e5; text-align:right;"><?php echo esc_html($variation_desc); ?></td>
			</tr>
			<?php endif; ?>

			<tr>
				<th style="text-align:right; background:#f3f2ff; border:1px solid #e5e5e5;"> <?php esc_html_e('תוקף חדש', 'WCLM'); ?> </th>
				<td style="border:1px solid #e5e5e5; font-weight:bold; color:#d63638; text-align:right;">
					<?php echo esc_html($new_expiry); ?>
				</td>
			</tr>
   		</table>

        <p style="direction:rtl; text-align:right;">
            <?php esc_html_e('תודה שבחרת בנו שוב', 'WCLM'); ?>
        </p>

		<p style="direction:rtl; text-align:right;">
            <?php esc_html_e('בברכה,', 'WCLM'); ?><br>
            <?php esc_html_e('מירב', 'WCLM'); ?>
   		</p>

		<?php

		$message = ob_get_clean();
        self::send_mail($to, $subject, $message);
    } 
}