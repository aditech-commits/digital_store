<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('Digital_Store_Engine')) {
    class Digital_Store_Engine {


    public static function init() {
        add_action('wp_ajax_digital_store_buy_product', [__CLASS__, 'handle_purchase']);
        add_action('wp_ajax_digital_store_confirm_delivery', [__CLASS__, 'handle_delivery_confirmation']);

        // Legacy aliases
        add_action('wp_ajax_chippapay_ds_buy_product', [__CLASS__, 'handle_purchase']);
        add_action('wp_ajax_chippapay_ds_confirm_delivery', [__CLASS__, 'handle_delivery_confirmation']);
    }

    public static function handle_purchase() {
        check_ajax_referer('chippapay_ds_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please login to continue purchase']);
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        $accepted_policy = sanitize_text_field($_POST['accept_policy'] ?? 'no');

        if ($accepted_policy !== 'yes') {
            wp_send_json_error(['message' => 'You must accept the Digital Store Terms & Policy before purchasing.']);
        }

        global $wpdb;
        $products_table = $wpdb->prefix . 'chippapay_ds_products';
        
        // 1. Initial product check
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $products_table WHERE id = %d AND status = 'available'", $product_id));

        if (!$product) {
            wp_send_json_error(['message' => 'Selected product is no longer available or in stock.']);
        }

        $wp_user_id = get_current_user_id();
        $users = new Users();
        $vp_user = $users->getUser($wp_user_id, false, true);

        if (!$vp_user) {
            wp_send_json_error(['message' => 'User Wallet profile not found.']);
        }

        $price = floatval($product->price);
        $vtupress_user_id = $vp_user->id;

        // 2. KYC Verification Check (using VTUPress core KYC rules)
        if (function_exists('vtupress_kyc_check')) {
            $kyc = vtupress_kyc_check($vtupress_user_id, $price);
            if (isset($kyc['status']) && $kyc['status'] === false) {
                wp_send_json_error(['message' => $kyc['message']]);
            }
        }

        // 3. Wallet Balance Check
        $current_balance = $users->getBalance($vtupress_user_id);
        if ($current_balance < $price) {
            wp_send_json_error(['message' => 'Insufficient wallet balance. Please fund your user wallet.']);
        }

        // 4. Atomic Concurrency Lock: Reserve product in database first
        $reserved = $wpdb->query($wpdb->prepare(
            "UPDATE $products_table SET status = 'reserved' WHERE id = %d AND status = 'available'",
            $product_id
        ));

        if (!$reserved) {
            wp_send_json_error(['message' => 'This product was just purchased by another customer. Please select another item.']);
        }

        // 5. Generate unique Order ID: CPDS-XXXXXX
        $order_id = 'CPDS-' . rand(100000, 999999);

        // 6. Atomic Debit using VTUPress Wallet Engine
        $debit_details = [
            'service' => 'digital_store',
            'order_id' => $order_id,
            'product_id' => $product_id,
            'product_name' => $product->title,
            'category' => $product->category,
            'amount' => $price,
            'delivery_time' => $product->delivery_time
        ];

        $debit_result = $users->debit(
            $vtupress_user_id,
            $price,
            'digital_store',
            "Digital Store Purchase: {$product->title} (Order {$order_id})",
            $debit_details
        );

        // 7. Rollback Protection: If debit fails, restore product status to 'available'
        if (!$debit_result || $debit_result === false) {
            $wpdb->update($products_table, ['status' => 'available'], ['id' => $product_id]);
            wp_send_json_error(['message' => 'Wallet debit failed. Your wallet was not charged.']);
        }

        // 8. Create Order Record
        $orders_table = $wpdb->prefix . 'chippapay_ds_orders';
        $wpdb->insert($orders_table, [
            'order_id' => $order_id,
            'user_id' => $wp_user_id,
            'vtupress_user_id' => $vtupress_user_id,
            'product_id' => $product_id,
            'product_name' => $product->title,
            'amount' => $price,
            'payment_status' => 'paid',
            'order_status' => 'processing'
        ]);

        wp_send_json_success([
            'message' => 'Purchase successful!',
            'order_id' => $order_id,
            'product_name' => $product->title,
            'amount' => number_format($price, 2),
            'redirect_url' => site_url("/digital-store-order/?order_id={$order_id}")
        ]);
    }

    public static function handle_delivery_confirmation() {
        check_ajax_referer('chippapay_ds_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        $wp_user_id = get_current_user_id();

        global $wpdb;
        $orders_table = $wpdb->prefix . 'chippapay_ds_orders';
        $products_table = $wpdb->prefix . 'chippapay_ds_products';

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE order_id = %s AND user_id = %d", $order_id, $wp_user_id));

        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        if ($order->order_status === 'completed') {
            wp_send_json_success(['message' => 'Order is already marked as COMPLETED.']);
        }

        $wpdb->update($orders_table, [
            'order_status' => 'completed',
            'confirmed_by_user' => 1
        ], ['id' => $order->id]);

        $wpdb->update($products_table, [
            'status' => 'sold'
        ], ['id' => $order->product_id]);

        wp_send_json_success(['message' => 'Delivery confirmed! Order status marked as COMPLETED. Thank you!']);
    }
}
}

if (!class_exists('ChippaPay_DS_Engine')) {
    class_alias('Digital_Store_Engine', 'ChippaPay_DS_Engine');
}
Digital_Store_Engine::init();


