<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('Digital_Store_Shortcodes')) {
    class Digital_Store_Shortcodes {


    public static function init() {
        // Standard shortcodes
        add_shortcode('digital_store', [__CLASS__, 'render_store']);
        add_shortcode('digital_store_order', [__CLASS__, 'render_order_tracking']);
        add_shortcode('digital_store_policy', [__CLASS__, 'render_store_policy']);

        // Legacy aliases
        add_shortcode('chippapay_digital_store', [__CLASS__, 'render_store']);
        add_shortcode('chippapay_order_tracking', [__CLASS__, 'render_order_tracking']);
        add_shortcode('chippapay_store_policy', [__CLASS__, 'render_store_policy']);
    }

    public static function render_store() {
        global $wpdb;
        $products_table = $wpdb->prefix . 'chippapay_ds_products';
        $products = $wpdb->get_results("SELECT * FROM $products_table WHERE status = 'available' ORDER BY id DESC");

        ob_start();
        ?>
        <div class="chippapay-ds-container">
            <!-- Header section -->
            <div class="ds-header">
                <h2>🛒 Digital Store</h2>
                <p>Buy verified social media accounts &amp; digital assets. Manual delivery: 30 mins – 6 hours.</p>

                <!-- Category Filter Tabs -->
                <div class="filter-tabs">
                    <button class="tab-btn active" data-category="all">All</button>
                    <button class="tab-btn" data-category="subscriptions">Subscriptions</button>
                    <button class="tab-btn" data-category="facebook">Facebook</button>
                    <button class="tab-btn" data-category="youtube">YouTube</button>
                    <button class="tab-btn" data-category="tiktok">TikTok</button>
                    <button class="tab-btn" data-category="instagram">Instagram</button>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="product-grid">
                <?php if (empty($products)): ?>
                    <p class="no-products">No digital assets currently in stock. Check back shortly!</p>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <div class="product-card" data-category="<?php echo esc_attr($p->category); ?>">
                            <div class="card-header">
                                <span class="platform-badge <?php echo esc_attr($p->category); ?>"><?php echo $p->category === 'subscriptions' ? 'Premium Sub' : ucfirst($p->category); ?></span>
                                <span class="stock-badge">Available</span>
                            </div>
                            <h3><?php echo esc_html($p->title); ?></h3>
                            <div class="specs">
                                <span>👥 <?php echo esc_html($p->followers); ?></span>
                                <span>⏳ <?php echo esc_html($p->account_age); ?></span>
                                <span>💰 <?php echo esc_html($p->monetization_status); ?></span>
                            </div>
                            <div class="card-footer">
                                <div class="price">₦<?php echo number_format($p->price, 2); ?></div>
                                <button class="btn-buy-now" onclick="chippapay_open_purchase_modal(<?php echo $p->id; ?>, '<?php echo esc_js($p->title); ?>', '<?php echo number_format($p->price, 2); ?>', '<?php echo esc_js($p->delivery_time); ?>')">BUY NOW</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Terms Acceptance & Purchase Modal -->
        <div id="chippapay-purchase-modal" class="cp-modal">
            <div class="cp-modal-content">
                <span class="cp-close" onclick="chippapay_close_modal()">&times;</span>
                <h3 id="modal-product-title">Product Name</h3>
                <div class="modal-body">
                    <p>Price: <strong id="modal-product-price" style="color:#0073aa;">₦0.00</strong></p>
                    <p class="delivery-notice">⚡ <strong>Manual Delivery:</strong> <span id="modal-delivery-time">30 minutes – 6 hours</span></p>

                    <!-- Policy Checkbox -->
                    <div class="policy-box">
                        <label>
                            <input type="checkbox" id="accept-policy-chk"> I agree to the <a href="<?php echo site_url('/digital-store-policy'); ?>" target="_blank">Digital Store Customer Policy</a>.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-confirm-purchase" onclick="chippapay_submit_purchase()">Confirm & Pay from Wallet</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_order_tracking() {
        $order_id = sanitize_text_field($_GET['order_id'] ?? '');
        if (empty($order_id)) {
            return '<p>No Order ID provided.</p>';
        }

        global $wpdb;
        $orders_table = $wpdb->prefix . 'chippapay_ds_orders';
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE order_id = %s", $order_id));

        if (!$order) {
            return '<p>Order not found.</p>';
        }

        $whatsapp_number = digital_store_get_whatsapp_number();
        $wa_msg = rawurlencode("Hello Support, I have purchased a Digital Store product.\nOrder ID: {$order->order_id}\nProduct: {$order->product_name}\nAmount: ₦" . number_format($order->amount, 2) . "\nStatus: PAID.");
        $wa_url = "https://wa.me/{$whatsapp_number}?text={$wa_msg}";

        ob_start();
        ?>
        <div class="chippapay-tracking-box">
            <div class="order-header">
                <div class="icon-success">✓</div>
                <h2>Order Placed Successfully</h2>
                <div class="order-id">Order ID: <strong><?php echo esc_html($order->order_id); ?></strong></div>
            </div>

            <div class="order-summary">
                <div class="row"><span>Product:</span> <strong><?php echo esc_html($order->product_name); ?></strong></div>
                <div class="row"><span>Amount:</span> <strong>₦<?php echo number_format($order->amount, 2); ?></strong></div>
                <div class="row"><span>Payment Status:</span> <span class="badge-paid">PAID</span></div>
                <div class="row"><span>Order Status:</span> <span class="badge-status <?php echo esc_attr($order->order_status); ?>"><?php echo strtoupper($order->order_status); ?></span></div>
            </div>

            <!-- WhatsApp Direct Button -->
            <div class="support-action">
                <a href="<?php echo $wa_url; ?>" target="_blank" class="btn-whatsapp">
                    <span>💬 Chat with us on WhatsApp</span>
                </a>
                <p class="sub-text">Tap to open WhatsApp and send order details directly to admin. No payment screenshot required.</p>
            </div>

            <?php if (!empty($order->delivery_details)): ?>
                <div class="delivery-credentials-box">
                    <h3>Your Digital Account is Ready!</h3>
                    <div class="credentials-content">
                        <?php echo nl2br(esc_html($order->delivery_details)); ?>
                    </div>
                    <?php if ($order->order_status !== 'completed'): ?>
                        <button class="btn-confirm-received" onclick="chippapay_confirm_received('<?php echo esc_js($order->order_id); ?>')">I HAVE RECEIVED MY ACCOUNT</button>
                    <?php else: ?>
                        <p class="status-completed-text">✓ Order Completed and Receipt Confirmed.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_store_policy() {
        ob_start();
        ?>
        <div class="chippapay-policy-container">
            <h2>Digital Store Customer Policy &amp; Agreement</h2>
            <hr>
            <ol>
                <li><strong>Manual Delivery Timeframe:</strong> Accounts are manually verified and transferred within 30 minutes to 6 hours after successful payment.</li>
                <li><strong>All Sales Final:</strong> Due to the nature of digital assets and social media accounts, all purchases are non-refundable once account access credentials have been delivered.</li>
                <li><strong>Account Transfer Protocol:</strong> For Facebook pages/TikTok/Instagram, admin rights or login credentials will be provided via your order tracking page. Please change passwords immediately upon receipt.</li>
                <li><strong>Auto-Refund Protection:</strong> If an order cannot be fulfilled within 24 hours, the order status will be marked as Refunded and the exact purchase amount will be automatically credited back to your user wallet balance.</li>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }
}
}

if (!class_exists('ChippaPay_DS_Shortcodes')) {
    class_alias('Digital_Store_Shortcodes', 'ChippaPay_DS_Shortcodes');
}
Digital_Store_Shortcodes::init();



