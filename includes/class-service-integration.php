<?php
if (!defined('ABSPATH')) exit;

/**
 * Digital Store — VTUPress Service Integration
 *
 * This class integrates the Digital Store as a native VTUPress service by:
 * 1. Extending Vtupress_services so the service container recognises it.
 * 2. Hooking into 'vtupress_service_registry' so the dashboard card appears.
 * 3. Providing a render_frontend() that outputs the full store UI natively
 *    inside the VTUPress dashboard (no separate page needed, no iframe).
 *
 * HOW THE DASHBOARD CARD APPEARS:
 * VTUPress template loops over Vtupress_Service_Container::all() which reads
 * from vtupress_service_registry(). We add our key 'digital_store' here.
 * The template then renders an icon card using $service->name, $service->image,
 * $service->icon and links to the service URL derived from $service->id.
 */

// ─── 1. Register our service key into the VTUPress registry ─────────────────
add_filter('vtupress_service_registry', function (array $registry): array {
    $registry['digital_store'] = 'Digital_Store_Service';
    return $registry;
});

// ─── 2. Service Class ────────────────────────────────────────────────────────
class Digital_Store_Service extends Vtupress_services
{
    public function __construct(wpdb $db, $service_type = false, ?int $user_id = null)
    {
        // Service identity — these control the dashboard card appearance
        $this->id          = 'digital_store';
        $this->name        = 'Digital Store';
        $this->description = 'Buy verified social media accounts & digital assets';
        $this->icon        = 'fas fa-store menu-icon';
        $this->image       = DIGITAL_STORE_URL . 'assets/images/digital-store-icon.png';
        $this->status      = 'on';     // CRITICAL: Required by vtupress-templates to display tile

        // Availability
        $this->countries   = ['all'];   // Show in all countries
        $this->uses_api    = false;     // No external API needed
        $this->uses_plans  = false;
        $this->uses_provider = false;

        // Do NOT set custom_id — that would require a VTUPress admin toggle to enable it.
        // Leaving custom_id empty means this service is always visible once registered.

        parent::__construct($db, $service_type, $user_id);
        $this->status = 'on';

    }

    /**
     * render_frontend() is called by the VTUPress shortcode handler
     * [vtupress service="digital_store"] when the user visits the service page.
     *
     * It returns the complete Digital Store catalog HTML.
     */
    public function render_frontend($user, $runcode = '')
    {
        global $wpdb;
        $products_table = $wpdb->prefix . 'chippapay_ds_products';
        $products = $wpdb->get_results(
            "SELECT * FROM $products_table WHERE status = 'available' ORDER BY id DESC"
        );

        // Detect the order tracking page for post-purchase redirect
        $tracking_page = self::find_page_with_shortcode('chippapay_order_tracking');
        $tracking_url  = $tracking_page ? get_permalink($tracking_page) : site_url('/digital-store-order/');

        // Policy page URL
        $policy_page = self::find_page_with_shortcode('chippapay_store_policy');
        $policy_url  = $policy_page ? get_permalink($policy_page) : site_url('/digital-store-policy/');

        ob_start();
        ?>
        <!-- Digital Store — Native Dashboard UI -->
        <div id="chippapay-ds-native" class="chippapay-ds-container">

            <!-- Store Header -->
            <div class="store-header">
                <h2>🛒 Digital Store</h2>
                <p>Buy verified social media accounts &amp; digital assets. Manual delivery: 30 mins – 6 hours.</p>

                <!-- Category Filter Tabs -->
                <div class="filter-tabs">
                    <button class="tab-btn active" data-category="all">All</button>
                    <button class="tab-btn" data-category="subscriptions">
                        <i class="fas fa-crown"></i> Subscriptions
                    </button>
                    <button class="tab-btn" data-category="facebook">
                        <i class="fab fa-facebook"></i> Facebook
                    </button>
                    <button class="tab-btn" data-category="youtube">
                        <i class="fab fa-youtube"></i> YouTube
                    </button>
                    <button class="tab-btn" data-category="tiktok">
                        <i class="fab fa-tiktok"></i> TikTok
                    </button>
                    <button class="tab-btn" data-category="instagram">
                        <i class="fab fa-instagram"></i> Instagram
                    </button>
                    <button class="tab-btn" data-category="twitter">
                        <i class="fab fa-twitter"></i> Twitter/X
                    </button>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="product-grid">
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <i class="fas fa-box-open" style="font-size:3rem; color:#ccc;"></i>
                        <p>No digital assets currently in stock. Check back shortly!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <div class="product-card" data-category="<?php echo esc_attr($p->category); ?>">
                            <div class="card-header">
                                <span class="platform-badge <?php echo esc_attr($p->category); ?>">
                                    <?php
                                    $icons = [
                                        'subscriptions' => '<i class="fas fa-crown"></i>',
                                        'facebook'      => '<i class="fab fa-facebook"></i>',
                                        'youtube'       => '<i class="fab fa-youtube"></i>',
                                        'tiktok'        => '<i class="fab fa-tiktok"></i>',
                                        'instagram'     => '<i class="fab fa-instagram"></i>',
                                        'twitter'       => '<i class="fab fa-twitter"></i>',
                                    ];
                                    echo $icons[$p->category] ?? '<i class="fas fa-globe"></i>';
                                    echo ' ' . ($p->category === 'subscriptions' ? 'Premium Sub' : ucfirst($p->category));
                                    ?>
                                </span>
                                <span class="stock-badge">In Stock</span>
                            </div>


                            <h3><?php echo esc_html($p->title); ?></h3>
                            <?php if (!empty($p->description)): ?>
                                <p class="card-desc"><?php echo esc_html($p->description); ?></p>
                            <?php endif; ?>

                            <div class="specs">
                                <?php if (!empty($p->followers)): ?>
                                    <span><i class="fas fa-users"></i> <?php echo esc_html($p->followers); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($p->account_age)): ?>
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo esc_html($p->account_age); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($p->monetization_status)): ?>
                                    <span><i class="fas fa-dollar-sign"></i> <?php echo esc_html($p->monetization_status); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($p->audience_country)): ?>
                                    <span><i class="fas fa-flag"></i> <?php echo esc_html($p->audience_country); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="card-footer">
                                <div class="price">₦<?php echo number_format($p->price, 2); ?></div>
                                <div class="delivery-tag">
                                    <i class="fas fa-clock"></i> <?php echo esc_html($p->delivery_time ?: '30 mins – 6 hrs'); ?>
                                </div>
                                <button
                                    class="btn-buy-now"
                                    onclick="chippapay_ds_open_modal(
                                        <?php echo intval($p->id); ?>,
                                        '<?php echo esc_js($p->title); ?>',
                                        '<?php echo number_format($p->price, 2); ?>',
                                        '<?php echo esc_js($p->delivery_time); ?>',
                                        '<?php echo esc_js($policy_url); ?>'
                                    )">
                                    BUY NOW
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Order Lookup Box -->
            <div class="order-lookup-box">
                <h3><i class="fas fa-search"></i> Track Your Order</h3>
                <p>Already placed an order? Enter your Order ID below to check its status.</p>
                <div class="lookup-form">
                    <input type="text" id="cpds-lookup-input" placeholder="e.g. CPDS-123456" />
                    <button onclick="chippapay_ds_lookup_order('<?php echo esc_js($tracking_url); ?>')">Track Order</button>
                </div>
            </div>
        </div>

        <!-- Purchase Confirmation Modal -->
        <div id="chippapay-ds-modal" class="cp-modal" style="display:none;">
            <div class="cp-modal-content">
                <span class="cp-close" onclick="chippapay_ds_close_modal()">&times;</span>
                <div class="cp-modal-header">
                    <h3 id="cpds-modal-title">Product Name</h3>
                    <div class="cp-modal-price">
                        <span>Price:</span>
                        <strong id="cpds-modal-price" style="color:#6200ea;">₦0.00</strong>
                    </div>
                </div>
                <div class="cp-modal-body">
                    <div class="delivery-notice">
                        <i class="fas fa-truck"></i>
                        <strong>Manual Delivery:</strong>
                        <span id="cpds-modal-delivery">30 minutes – 6 hours</span>
                    </div>

                    <div class="policy-box">
                        <label>
                            <input type="checkbox" id="cpds-policy-chk">
                            I agree to the
                            <a id="cpds-policy-link" href="<?php echo esc_url($policy_url); ?>" target="_blank">
                                Digital Store Customer Policy
                            </a>.
                        </label>
                    </div>
                </div>
                <div class="cp-modal-footer">
                    <button class="btn-cancel" onclick="chippapay_ds_close_modal()">Cancel</button>
                    <button class="btn-confirm-purchase" id="cpds-confirm-btn" onclick="chippapay_ds_submit_purchase()">
                        <i class="fas fa-wallet"></i> Confirm &amp; Pay from Wallet
                    </button>
                </div>
            </div>
        </div>

        <script>
        var chippapay_ds_current_id  = 0;
        var chippapay_ds_ajax_url    = '<?php echo admin_url('admin-ajax.php'); ?>';
        var chippapay_ds_nonce_value = '<?php echo wp_create_nonce('chippapay_ds_nonce'); ?>';
        var chippapay_ds_tracking_url = '<?php echo esc_js($tracking_url); ?>';

        /* ── Category Filter ── */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
                    this.classList.add('active');
                    var cat = this.getAttribute('data-category');
                    document.querySelectorAll('.product-card').forEach(function(card) {
                        if (cat === 'all' || card.getAttribute('data-category') === cat) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });

        /* ── Modal Helpers ── */
        function chippapay_ds_open_modal(id, title, price, deliveryTime, policyUrl) {
            chippapay_ds_current_id = id;
            document.getElementById('cpds-modal-title').textContent    = title;
            document.getElementById('cpds-modal-price').textContent     = '₦' + price;
            document.getElementById('cpds-modal-delivery').textContent  = deliveryTime;
            document.getElementById('cpds-policy-chk').checked          = false;
            if (policyUrl) {
                document.getElementById('cpds-policy-link').href = policyUrl;
            }
            document.getElementById('chippapay-ds-modal').style.display = 'flex';
        }

        function chippapay_ds_close_modal() {
            document.getElementById('chippapay-ds-modal').style.display = 'none';
        }

        /* ── Purchase ── */
        function chippapay_ds_submit_purchase() {
            if (!document.getElementById('cpds-policy-chk').checked) {
                alert('Please accept the Digital Store Customer Policy before proceeding.');
                return;
            }

            var btn = document.getElementById('cpds-confirm-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', chippapay_ds_ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        window.location.href = res.data.redirect_url || chippapay_ds_tracking_url + '?order_id=' + res.data.order_id;
                    } else {
                        alert(res.data.message || 'Purchase failed. Please try again.');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-wallet"></i> Confirm & Pay from Wallet';
                    }
                } catch(e) {
                    alert('Unexpected error. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-wallet"></i> Confirm & Pay from Wallet';
                }
            };
            xhr.onerror = function() {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-wallet"></i> Confirm & Pay from Wallet';
            };
            xhr.send(
                'action=chippapay_ds_buy_product' +
                '&nonce=' + encodeURIComponent(chippapay_ds_nonce_value) +
                '&product_id=' + encodeURIComponent(chippapay_ds_current_id) +
                '&accept_policy=yes'
            );
        }

        /* ── Order Lookup ── */
        function chippapay_ds_lookup_order(trackingBase) {
            var val = document.getElementById('cpds-lookup-input').value.trim();
            if (!val) {
                alert('Please enter your Order ID (e.g. CPDS-123456).');
                return;
            }
            window.location.href = trackingBase + '?order_id=' + encodeURIComponent(val);
        }

        /* Close modal on backdrop click */
        document.addEventListener('click', function(e) {
            var modal = document.getElementById('chippapay-ds-modal');
            if (modal && e.target === modal) {
                chippapay_ds_close_modal();
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Utility: find a WordPress page that contains a given shortcode.
     */
    private static function find_page_with_shortcode(string $shortcode): ?\WP_Post
    {
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            's'              => '[' . $shortcode,
        ]);
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, $shortcode)) {
                return $page;
            }
        }
        return null;
    }

    /**
     * Required by Vtupress_services abstract class.
     * Returns admin form fields configuration (none needed for digital store).
     */
    public function form_fields(): array
    {
        return [];
    }

    /**
     * boot() is called by the template when it needs to fetch plans/providers.
     * For the digital store we have nothing to boot externally.
     */
    public function boot(): void
    {
        $this->booted = true;
    }
}

if (!class_exists('ChippaPay_Digital_Store_Service')) {
    class_alias('Digital_Store_Service', 'ChippaPay_Digital_Store_Service');
}
