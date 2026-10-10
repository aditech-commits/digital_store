<?php
/**
 * Plugin Name: Digital Store
 * Plugin URI: https://agency.aditechgadgets.com.ng
 * Description: Digital Store Addon for Vtupress. Buy verified social media accounts & premium subscriptions. Integrates natively with VTUPress wallet & dashboard service menu.
 * Version: 1.2.0
 * Author: Aditech
 * Author URI: https://agency.aditechgadgets.com.ng
 * License: GPLv2 or later
 * Text Domain: digital-store
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DIGITAL_STORE_VERSION', '1.2.0');
define('DIGITAL_STORE_PATH', plugin_dir_path(__FILE__));
define('DIGITAL_STORE_URL', plugin_dir_url(__FILE__));

// Backward compatibility constants
if (!defined('CHIPPAPAY_DS_VERSION')) define('CHIPPAPAY_DS_VERSION', DIGITAL_STORE_VERSION);
if (!defined('CHIPPAPAY_DS_PATH'))    define('CHIPPAPAY_DS_PATH', DIGITAL_STORE_PATH);
if (!defined('CHIPPAPAY_DS_URL'))     define('CHIPPAPAY_DS_URL', DIGITAL_STORE_URL);

/**
 * Helper: Retrieve configured admin WhatsApp number (digits only).
 * Defaults to +2349032868984 if not explicitly set by admin.
 */
if (!function_exists('digital_store_get_whatsapp_number')) {
    function digital_store_get_whatsapp_number()
    {
        $raw = get_option('digital_store_admin_whatsapp', get_option('chippapay_ds_admin_whatsapp', '+2349032868984'));
        $clean = preg_replace('/[^0-9]/', '', $raw);
        return !empty($clean) ? $clean : '2349032868984';
    }
}

if (!function_exists('chippapay_ds_get_whatsapp_number')) {
    function chippapay_ds_get_whatsapp_number() {
        return digital_store_get_whatsapp_number();
    }
}

// ─── Plugin Activation: Create Database Tables ─────────────────────────────
register_activation_hook(__FILE__, 'digital_store_activate');

if (!function_exists('digital_store_activate')) {
    function digital_store_activate()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // 1. Products Inventory Table
        $table_products = $wpdb->prefix . 'chippapay_ds_products';
        $sql_products = "CREATE TABLE IF NOT EXISTS $table_products (
            id INT NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL,
            description TEXT,
            followers VARCHAR(100) DEFAULT '',
            account_age VARCHAR(100) DEFAULT '',
            monetization_status VARCHAR(100) DEFAULT 'Not Monetized',
            audience_country VARCHAR(100) DEFAULT 'Nigeria',
            price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(50) NOT NULL DEFAULT 'available',
            delivery_time VARCHAR(100) DEFAULT '30 minutes – 6 hours',
            verified_badge TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category_status (category, status)
        ) ENGINE=InnoDB $charset_collate;";
        dbDelta($sql_products);

        // 2. Orders Table
        $table_orders = $wpdb->prefix . 'chippapay_ds_orders';
        $sql_orders = "CREATE TABLE IF NOT EXISTS $table_orders (
            id INT NOT NULL AUTO_INCREMENT,
            order_id VARCHAR(50) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            vtupress_user_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            payment_status VARCHAR(50) DEFAULT 'paid',
            order_status VARCHAR(50) DEFAULT 'processing',
            delivery_details TEXT DEFAULT NULL,
            confirmed_by_user TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_orders (user_id),
            KEY idx_order_status (order_status)
        ) ENGINE=InnoDB $charset_collate;";
        dbDelta($sql_orders);

        // 3. Admin-Only Private Notes Table
        $table_notes = $wpdb->prefix . 'chippapay_ds_admin_notes';
        $sql_notes = "CREATE TABLE IF NOT EXISTS $table_notes (
            id INT NOT NULL AUTO_INCREMENT,
            order_id VARCHAR(50) NOT NULL,
            admin_id INT NOT NULL,
            note_content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_order_notes (order_id)
        ) ENGINE=InnoDB $charset_collate;";
        dbDelta($sql_notes);

        // ── Seed sample inventory if empty ──────────────────────────────────────
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_products");
        if ($count == 0) {
            $samples = [
                [
                    'title'               => 'Facebook Page – Entertainment Hub',
                    'category'            => 'facebook',
                    'description'         => 'Well-engaged Facebook page with active followers. Perfect for brands or personal use.',
                    'followers'           => '12.5K Followers',
                    'account_age'         => '2 Years',
                    'monetization_status' => 'Not Monetized',
                    'audience_country'    => 'Nigeria',
                    'price'               => 50000.00,
                    'status'              => 'available',
                    'delivery_time'       => '30 minutes – 6 hours',
                ],
                [
                    'title'               => 'YouTube Channel – Gaming & Tech',
                    'category'            => 'youtube',
                    'description'         => 'Monetized YouTube channel with high organic watch hours.',
                    'followers'           => '5.2K Subscribers',
                    'account_age'         => '1 Year',
                    'monetization_status' => 'Monetized',
                    'audience_country'    => 'Global',
                    'price'               => 80000.00,
                    'status'              => 'available',
                    'delivery_time'       => '30 minutes – 6 hours',
                ],
                [
                    'title'               => 'TikTok Account – Lifestyle & Comedy',
                    'category'            => 'tiktok',
                    'description'         => 'Active TikTok account with viral video history.',
                    'followers'           => '15.8K Followers',
                    'account_age'         => '1.5 Years',
                    'monetization_status' => 'Not Monetized',
                    'audience_country'    => 'Nigeria',
                    'price'               => 70000.00,
                    'status'              => 'available',
                    'delivery_time'       => '30 minutes – 6 hours',
                ],
                [
                    'title'               => 'Instagram Account – Fashion & Beauty',
                    'category'            => 'instagram',
                    'description'         => 'Clean Instagram page focused on fashion and lifestyle.',
                    'followers'           => '10.2K Followers',
                    'account_age'         => '2 Years',
                    'monetization_status' => 'Not Monetized',
                    'audience_country'    => 'Nigeria',
                    'price'               => 60000.00,
                    'status'              => 'available',
                    'delivery_time'       => '30 minutes – 6 hours',
                ],
            ];
            foreach ($samples as $s) {
                $wpdb->insert($table_products, $s);
            }
        }

        // Seed Premium Subscriptions category if missing
        digital_store_seed_subscription_products();

        // ── Auto-create required WordPress pages ────────────────────────────────
        digital_store_create_required_pages();

        flush_rewrite_rules();
    }
}

/**
 * Seed demo Premium Subscriptions category products into database if not present.
 */
if (!function_exists('digital_store_seed_subscription_products')) {
    function digital_store_seed_subscription_products()
    {
        global $wpdb;
        $table_products = $wpdb->prefix . 'chippapay_ds_products';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_products'") !== $table_products) {
            return;
        }

        $sub_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_products WHERE category = 'subscriptions'");
        if ($sub_count == 0) {
            $subscriptions = [
                [
                    'title'               => '✂️ CapCut Pro — 1 Month Subscription',
                    'category'            => 'subscriptions',
                    'description'         => 'CapCut Pro 1 Month access. Unlocks AI video editing features, 4K export, pro transitions, and cloud storage.',
                    'followers'           => 'N/A',
                    'account_age'         => '1 Month Duration',
                    'monetization_status' => 'Pro Features Unlocked',
                    'audience_country'    => 'Global',
                    'price'               => 5000.00,
                    'status'              => 'available',
                    'delivery_time'       => '15 mins – 2 hours',
                ],
                [
                    'title'               => '✨ Gemini Advanced AI — 18 Months Plan',
                    'category'            => 'subscriptions',
                    'description'         => 'Google Gemini Advanced AI subscription for 18 Months. Includes 2TB Google One Cloud storage and Gemini 1.5 Pro AI model.',
                    'followers'           => 'N/A',
                    'account_age'         => '18 Months Duration',
                    'monetization_status' => 'Gemini 1.5 Pro Unlocked',
                    'audience_country'    => 'Global',
                    'price'               => 18000.00,
                    'status'              => 'available',
                    'delivery_time'       => '15 mins – 2 hours',
                ],
                [
                    'title'               => '🎨 Canva Pro Edu — Lifetime / Annual Access',
                    'category'            => 'subscriptions',
                    'description'         => 'Full Canva Pro Edu account access on your email. Unlocks 100M+ stock images, background remover, brand kits, and templates.',
                    'followers'           => 'N/A',
                    'account_age'         => 'Lifetime Access',
                    'monetization_status' => 'All Pro Tools Unlocked',
                    'audience_country'    => 'Global',
                    'price'               => 3500.00,
                    'status'              => 'available',
                    'delivery_time'       => '15 mins – 1 hour',
                ],
                [
                    'title'               => '🛡️ Surfshark VPN — 2 Months Premium',
                    'category'            => 'subscriptions',
                    'description'         => 'Surfshark VPN 2 Months subscription. CleanWeb ad blocker, unlimited devices, and high-speed encrypted servers.',
                    'followers'           => 'N/A',
                    'account_age'         => '2 Months Duration',
                    'monetization_status' => 'Unlimited Devices',
                    'audience_country'    => 'Global',
                    'price'               => 4500.00,
                    'status'              => 'available',
                    'delivery_time'       => '15 mins – 2 hours',
                ],
                [
                    'title'               => '🔒 NordVPN — 3 Months Subscription',
                    'category'            => 'subscriptions',
                    'description'         => 'NordVPN 3 Months subscription. Double VPN encryption, Threat Protection, Meshnet, and high-speed NordLynx servers.',
                    'followers'           => 'N/A',
                    'account_age'         => '3 Months Duration',
                    'monetization_status' => 'Ultra Fast NordLynx',
                    'audience_country'    => 'Global',
                    'price'               => 6000.00,
                    'status'              => 'available',
                    'delivery_time'       => '15 mins – 2 hours',
                ],
                [
                    'title'               => '💼 LinkedIn Premium — Business/Career Voucher',
                    'category'            => 'subscriptions',
                    'description'         => 'Upgrade your LinkedIn profile to Premium Business/Career. Includes 5 InMail credits/month, full profile viewers list, and LinkedIn Learning.',
                    'followers'           => 'N/A',
                    'account_age'         => '6 Months Access',
                    'monetization_status' => 'InMail + Profile Viewers',
                    'audience_country'    => 'Global',
                    'price'               => 15000.00,
                    'status'              => 'available',
                    'delivery_time'       => '30 mins – 3 hours',
                ],
            ];
            foreach ($subscriptions as $s) {
                $wpdb->insert($table_products, $s);
            }
        }
    }
}

/**
 * Create required WordPress pages if they don't already exist.
 */
if (!function_exists('digital_store_create_required_pages')) {
    function digital_store_create_required_pages()
    {
        $pages = [
            'digital-store-order' => [
                'title'   => 'Digital Store – Order Tracking',
                'content' => '[digital_store_order]',
            ],
            'digital-store-policy' => [
                'title'   => 'Digital Store Policy',
                'content' => '[digital_store_policy]',
            ],
        ];

        foreach ($pages as $slug => $page_data) {
            $existing = get_page_by_path($slug, OBJECT, 'page');
            if (!$existing) {
                wp_insert_post([
                    'post_title'   => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_name'    => $slug,
                ]);
            }
        }
    }
}

// ─── Load Plugin Classes ────────────────────────────────────────────────────
add_action('plugins_loaded', function () {
    require_once DIGITAL_STORE_PATH . 'includes/class-store-engine.php';
    require_once DIGITAL_STORE_PATH . 'includes/class-frontend-shortcodes.php';
    require_once DIGITAL_STORE_PATH . 'includes/class-admin-dashboard.php';

    // Auto-seed subscription products if table exists and empty
    digital_store_seed_subscription_products();

    if (class_exists('Vtupress_services')) {
        require_once DIGITAL_STORE_PATH . 'includes/class-service-integration.php';
    } else {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning is-dismissible"><p>'
                . '<strong>Digital Store:</strong> '
                . 'VTUPress is not active. The Digital Store will still work via shortcodes, '
                . 'but the dashboard service icon card will not appear until VTUPress is active.'
                . '</p></div>';
        });
    }
}, 20);

// ─── Enqueue Frontend Assets ────────────────────────────────────────────────
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'digital-store-css',
        DIGITAL_STORE_URL . 'assets/css/digital-store.css',
        [],
        DIGITAL_STORE_VERSION
    );
    wp_enqueue_script(
        'digital-store-js',
        DIGITAL_STORE_URL . 'assets/js/digital-store.js',
        ['jquery'],
        DIGITAL_STORE_VERSION,
        true
    );
    wp_localize_script('digital-store-js', 'chippapay_ds_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('chippapay_ds_nonce'),
    ]);
});

// ─── Enqueue Font Awesome (if not already loaded by VTUPress) ───────────────
add_action('wp_enqueue_scripts', function () {
    if (!wp_style_is('font-awesome', 'registered') && !wp_style_is('fontawesome', 'registered')) {
        wp_enqueue_style(
            'digital-store-fa',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
            [],
            '6.5.0'
        );
    }
});
