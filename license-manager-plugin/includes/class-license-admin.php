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
		$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		$per_page     = 20;
		$core = new WCLM_License_Core();

		// STEP 1: Fetch a batch of orders
		$orders = wc_get_orders([
			'status'        => 'completed',
			'limit'         => -1,
		]);

		$filtered_items = [];
        $today_timestamp = strtotime(current_time('Y-m-d'));

		// STEP 2: Filter valid license items
		foreach ($orders as $order) {
			foreach ($order->get_items('line_item') as $item_id => $item) {

				if (!is_a($item, 'WC_Order_Item_Product')) continue;

                // Filter by category 'services'
				if (!$core->is_license_product($item)) continue;

                // Get expiry date from Item Meta (preferred) or Order Meta (fallback)
                $expiry_date = wc_get_order_item_meta($item_id, '_license_expiry_date', true);
                if (empty($expiry_date)) {
                    $expiry_date = $order->get_meta('_license_expiry_date', true);
                }

                // Determine Status
                $status_label = __('No Date Set', 'WCLM');
                $status_color = '#999';

                if (!empty($expiry_date) && $expiry_date !== 'N/A') {
                    $expiry_timestamp = strtotime($expiry_date);
                    if ($expiry_timestamp < $today_timestamp) {
                        $status_label = __('Expired', 'WCLM');
                        $status_color = '#d63638'; // Red
                    } else {
                        $status_label = __('Active', 'WCLM');
                        $status_color = '#00a32a'; // Green
                    }
                }

				$filtered_items[] = [
					'order'     => $order,
					'item'      => $item,
					'item_id'   => $item_id,
					'expiry'    => !empty($expiry_date) ? $expiry_date : 'N/A',
                    'status_label' => $status_label,
                    'status_color' => $status_color
				];
			}
		}

		// STEP 3: Manual pagination on filtered items
		$total_items = count($filtered_items);
		$total_pages = max(1, ceil($total_items / $per_page));
		$offset      = ($current_page - 1) * $per_page;
		$paged_items = array_slice($filtered_items, $offset, $per_page);

		?>

		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e('License Reports', 'WCLM'); ?></h1>

			<div class="tablenav top">
				<?php echo $this->render_pagination($current_page, $total_pages); ?>
			</div>

			<table id="wclm-license-table" class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Order', 'WCLM'); ?></th>
						<th><?php esc_html_e('Customer Name', 'WCLM'); ?></th>
						<th><?php esc_html_e('Product', 'WCLM'); ?></th>
						<th><?php esc_html_e('Expiry', 'WCLM'); ?></th>
						<th><?php esc_html_e('Status', 'WCLM'); ?></th>
						<th><?php esc_html_e('Actions', 'WCLM'); ?></th>
					</tr>
				</thead>

				<tbody>
				<?php if (!empty($paged_items)) : ?>

					<?php foreach ($paged_items as $row) :

						$order       = $row['order'];
						$item        = $row['item'];
						$item_id     = $row['item_id'];
						$expiry_date = $row['expiry'];
	
					?>

					<tr>
						<td>#<?php echo esc_html($order->get_id()); ?></td>
						<td>
                            <?php echo esc_html($order->get_formatted_billing_full_name()); ?><br>
                            <small><?php echo esc_html($order->get_billing_email()); ?></small>
                        </td>			
						<td><?php echo esc_html($item->get_name()); ?></td>
						<td><?php echo esc_html($expiry_date); ?></td>
						<td>
                            <span class="badge expiration_status" style="background:<?php echo $row['status_color']; ?>;">
                                <?php echo esc_html($row['status_label']); ?>
                            </span>  
                        </td>
						<td>
							<!-- Customer Email -->
							<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block; margin-bottom:5px;">
								<input type="hidden" name="action" value="wclm_send_customer_email">
								<input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
								<input type="hidden" name="item_id" value="<?php echo esc_attr($item_id); ?>">
								<?php wp_nonce_field('wclm_send_email_nonce'); ?>
								<button class="button button-small">
									<?php esc_html_e('Email Customer', 'WCLM'); ?>
								</button>
							</form>

							<!-- Admin Email -->
							<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block;">
								<input type="hidden" name="action" value="wclm_send_admin_email">
								<input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
								<input type="hidden" name="item_id" value="<?php echo esc_attr($item_id); ?>">
								<?php wp_nonce_field('wclm_send_email_nonce'); ?>
								<button class="button button-small button-secondary">
									<?php esc_html_e('Email Admin', 'WCLM'); ?>
								</button>
							</form>
						</td>
					</tr>

					<?php endforeach; ?>

				<?php else : ?>
					<tr>
						<td colspan="8"><?php esc_html_e('No License record found.', 'WCLM'); ?></td>
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
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wclm_send_email_nonce')) {
			wp_die('Security check failed');
		}

		$order_id = intval($_POST['order_id']);
		$item_id  = intval($_POST['item_id']);
		$order    = wc_get_order($order_id);
		$item     = $order->get_item($item_id);
		$expiry   = $item->get_meta('_license_expiry_date', true);
		$core     = new WCLM_License_Core();

		if ($type === 'customer') {
			$core->send_customer_email($order, $item, $expiry);
			$order->add_order_note( __('Manual license email sent to customer.', 'WCLM'));
		} else {
			$core->send_admin_email($order, $item, $expiry);
			$order->add_order_note( __('Manual license alert sent to admin.', 'WCLM' ));
		}

		wp_redirect(admin_url('admin.php?page=wclm-license-reports&email_sent=1'));
		exit;
	}
}