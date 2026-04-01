<?php
if (!defined('ABSPATH')) exit;

class WCLM_License_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_wclm_send_customer_email', [$this, 'handle_send_customer_email']);
    	add_action('admin_post_wclm_send_admin_email', [$this, 'handle_send_admin_email']);
        
        // Enqueue style
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            esc_html__('License Reports', 'WCLM'),
            esc_html__('License Reports', 'WCLM'),
            'manage_woocommerce',
            'wclm-license-reports',
            [$this, 'render_page']
        );
    }

    public function enqueue_styles($hook) {
       	if (strpos($hook, 'wclm-license-reports') === false) {
			return;
		}

        wp_enqueue_style(
            'wclm-admin-style',
           plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
            [],
            time() // prevent caching
        );
    }


	public function render_page() {
		// PERMISSION CHECK: Ensure only authorized users can see this data
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'WCLM'));
		}

		$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		$per_page     = 20;

		// STEP 1: Fetch a batch of orders
		$orders = wc_get_orders([
			'status'        => 'completed',
			'limit'         => -1,
		]);

		$filtered_items = [];

		// STEP 2: Filter valid license items
		foreach ($orders as $order) {
			foreach ($order->get_items('line_item') as $item_id => $item) {

				if (!is_a($item, 'WC_Order_Item_Product')) continue;

                // Filter by category 'services'
				if (!WCLM_License_Core::is_license_product($item)) continue;

                // Get expiry date from Item Meta (preferred) or Order Meta (fallback)
                $expiry_date = wc_get_order_item_meta($item_id, '_license_expiry_date', true);
                if (empty($expiry_date)) {
                    $expiry_date = $order->get_meta('_license_expiry_date', true);
                }

				$filtered_items[] = [
					'order'     => $order,
					'item'      => $item,
					'item_id'   => $item_id,
					'expiry'    => !empty($expiry_date) ? $expiry_date : 'N/A',
				];
			}
		}
		
		usort($filtered_items, function($a, $b){
			$a_id = $a['order']->get_id();
			$b_id = $b['order']->get_id();
			
			return $b_id <=> $a_id;
		});

		// STEP 3: Manual pagination on filtered items
		$total_items = count($filtered_items);
		$total_pages = max(1, ceil($total_items / $per_page));
		$offset      = ($current_page - 1) * $per_page;
		$paged_items = array_slice($filtered_items, $offset, $per_page);

		?>

		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html__('License Reports', 'WCLM'); ?></h1>

			<div class="tablenav top">
				<?php echo $this->render_pagination($current_page, $total_pages); ?>
			</div>

			<table id="wclm-license-table" class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('הזמנה', 'WCLM'); // Order ?></th>
						<th><?php esc_html_e('שם לקוח', 'WCLM'); // Customer name ?></th>
						<th><?php esc_html_e('שם חברה', 'WCLM'); // Company name ?></th>
						<th><?php esc_html_e('מוצר', 'WCLM'); //product ?></th>
						<th><?php esc_html_e('תיאור מוצר', 'WCLM'); //product description ?></th>
						<th><?php esc_html_e('תאריך הזמנה', 'WCLM'); //order created ?></th>
						<th><?php esc_html_e('תוקף', 'WCLM'); // expiration ?></th>
						<th><?php esc_html_e('תאריך התראה', 'WCLM'); //notification date ?></th>
						<th><?php esc_html_e('פעולות', 'WCLM'); // actions ?></th>
					</tr>
				</thead>

				<tbody>
				<?php if (!empty($paged_items)) : ?>

					<?php foreach ($paged_items as $row) :

						$order       = $row['order'];
						$item        = $row['item'];
						$item_id     = $row['item_id'];
						$expiry_date = $row['expiry'];
						$variation_desc = WCLM_License_Core::get_product_variation_desc($item);
					?>

					<tr>
						<td>
							<a href="<?php echo esc_url(get_edit_post_link($order->get_id())); ?>">
								#<?php echo esc_html($order->get_id()); ?>
							</a>
						<td>
                            <?php echo esc_html($order->get_formatted_billing_full_name()); ?><br>
                            <small><?php echo esc_html($order->get_billing_email()); ?></small>
                        </td>			
						<td>
							<a href="<?php echo esc_url($order->get_billing_company()); ?>" target="_blank">
								<?php echo esc_html($order->get_billing_company()); ?>
							</a>
						</td>
						<td><?php echo esc_html($item->get_name()); ?></td>
						<td><?php echo esc_html($variation_desc); ?></td>
						<td><?php echo esc_html($order->get_date_created()->date('Y-m-d')); ?></td>
						<td><?php echo esc_html($expiry_date); ?></td>
						<td>
                            <?php 
							$expiry_timestamp = strtotime($expiry_date);
							echo ($expiry_timestamp) ? esc_html(date('Y-m-d', strtotime('-14 days', $expiry_timestamp))) : '—';
                        	?>
                        </td>
						<td>
							<!-- Customer Email -->
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-bottom:5px;">
								<input type="hidden" name="action" value="wclm_send_customer_email">
								<input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
								<input type="hidden" name="item_id" value="<?php echo esc_attr($item_id); ?>">
								<?php wp_nonce_field('wclm_send_email_nonce'); ?>
								<button class="button button-small">
									<?php esc_html_e('לקוח דוא"ל', 'WCLM'); ?>
								</button>
							</form>

							<!-- Admin Email -->
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
								<input type="hidden" name="action" value="wclm_send_admin_email">
								<input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
								<input type="hidden" name="item_id" value="<?php echo esc_attr($item_id); ?>">
								<?php wp_nonce_field('wclm_send_email_nonce'); ?>
								<button class="button button-small button-secondary">
									<?php esc_html_e('מנהל דוא"ל', 'WCLM'); ?>
								</button>
							</form>
						</td>
					</tr>

					<?php endforeach; ?>

				<?php else : ?>
					<tr>
						<td colspan="8"><?php esc_html__('No License record found.', 'WCLM'); ?></td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>

			<div class="tablenav bottom">
				<?php echo $this->render_pagination($current_page, $total_pages); ?>
			</div>

		</div>

		<?php
	}
	
    private function render_pagination($current_page, $total_pages){
        if($total_pages <= 1) return '';

        $page_links = paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'prev_text' => __('&laquo; Previous'),
            'next_text' => __('Next &raquo;'),
            'total'     => $total_pages,
            'current'   => $current_page,
            'type'      => 'plain',
        ]);

        if ($page_links) {
            return '<div class="tablenav-pages wclm-pagination">' . $page_links . '</div>';
        }
    }

    public function handle_send_customer_email() {
		$this->verify_and_send('customer');
	}

	public function handle_send_admin_email() {
		$this->verify_and_send('admin');
	}

	private function verify_and_send($type) {
		// 1. Nonce Check
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wclm_send_email_nonce')) {
			wp_die('Security check failed');
		}

		// 2. Permission Check
		if (!current_user_can('manage_woocommerce')) {
			wp_die('You do not have permission to trigger license emails.');
		}

		$order_id = intval($_POST['order_id']);
		$item_id  = intval($_POST['item_id']);
		$order    = wc_get_order($order_id);
		$item     = $order->get_item($item_id);
		$expiry   = $item->get_meta('_license_expiry_date', true);

		if (!$order) wp_die('Invalid Order');
		
		// Check if the item actually belongs to this order and is a license product
		if (!$item || !WCLM_License_Core::is_license_product($item)) {
			wp_die('Invalid Item for this Order');
		}

		if ($type === 'customer') {
			WCLM_License_Email::send_customer_email($order, $item, $expiry);
			$order->add_order_note( __('Manual license email sent to customer.', 'WCLM'));
		} else {
			WCLM_License_Email::send_admin_email($order, $item, $expiry);
			$order->add_order_note( __('Manual license alert sent to admin.', 'WCLM' ));
		}

		wp_redirect(admin_url('admin.php?page=wclm-license-reports&email_sent=1'));
		exit;
	}
}