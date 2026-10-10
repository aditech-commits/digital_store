<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('Digital_Store_Admin')) {
    class Digital_Store_Admin {


    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 99);
    }

    public static function register_admin_menu() {
        add_submenu_page(
            'vtupanel',
            'Digital Store Orders',
            'Digital Store',
            'manage_options',
            'digital-store-admin',
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function render_admin_page() {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'chippapay_ds_orders';
        $notes_table = $wpdb->prefix . 'chippapay_ds_admin_notes';
        $products_table = $wpdb->prefix . 'chippapay_ds_products';

        // Handle Store Support Settings
        if (isset($_POST['save_store_settings'])) {
            check_admin_referer('chippapay_ds_admin_action');
            $wa_num = sanitize_text_field($_POST['admin_whatsapp_number']);
            update_option('digital_store_admin_whatsapp', $wa_num);
            update_option('chippapay_ds_admin_whatsapp', $wa_num);
            echo '<div class="notice notice-success"><p>Digital Store Settings saved successfully!</p></div>';
        }

        // Handle Product Creation
        if (isset($_POST['add_new_product'])) {
            check_admin_referer('chippapay_ds_admin_action');
            $wpdb->insert($products_table, [
                'title' => sanitize_text_field($_POST['title']),
                'category' => sanitize_text_field($_POST['category']),
                'description' => sanitize_textarea_field($_POST['description']),
                'followers' => sanitize_text_field($_POST['followers']),
                'account_age' => sanitize_text_field($_POST['account_age']),
                'monetization_status' => sanitize_text_field($_POST['monetization_status']),
                'audience_country' => sanitize_text_field($_POST['audience_country']),
                'price' => floatval($_POST['price']),
                'status' => 'available',
                'delivery_time' => sanitize_text_field($_POST['delivery_time'] ?: '30 minutes – 6 hours')
            ]);
            echo '<div class="notice notice-success"><p>New Product added to Digital Store Inventory!</p></div>';
        }

        // Handle Product Update
        if (isset($_POST['update_product'])) {
            check_admin_referer('chippapay_ds_admin_action');
            $p_id = intval($_POST['edit_product_id']);
            $wpdb->update($products_table, [
                'title'               => sanitize_text_field($_POST['title']),
                'category'            => sanitize_text_field($_POST['category']),
                'description'         => sanitize_textarea_field($_POST['description']),
                'followers'           => sanitize_text_field($_POST['followers']),
                'account_age'         => sanitize_text_field($_POST['account_age']),
                'monetization_status' => sanitize_text_field($_POST['monetization_status']),
                'audience_country'    => sanitize_text_field($_POST['audience_country']),
                'price'               => floatval($_POST['price']),
                'status'              => sanitize_text_field($_POST['status']),
                'delivery_time'       => sanitize_text_field($_POST['delivery_time'] ?: '30 minutes – 6 hours')
            ], ['id' => $p_id]);
            echo '<div class="notice notice-success"><p>Product updated successfully!</p></div>';
        }

        // Handle Product Status Toggle / Delete
        if (isset($_GET['action_type']) && isset($_GET['product_id'])) {
            check_admin_referer('chippapay_ds_admin_action');
            $p_id = intval($_GET['product_id']);
            $action = sanitize_text_field($_GET['action_type']);

            if ($action === 'delete_product') {
                $wpdb->delete($products_table, ['id' => $p_id]);
                echo '<div class="notice notice-success"><p>Product deleted.</p></div>';
            } elseif (in_array($action, ['available', 'reserved', 'sold'])) {
                $wpdb->update($products_table, ['status' => $action], ['id' => $p_id]);
                echo '<div class="notice notice-success"><p>Product status updated to ' . esc_html($action) . '.</p></div>';
            }
        }

        // Handle Order Status Updates, Private Notes & Automatic Refunds
        if (isset($_POST['update_order_status'])) {
            check_admin_referer('chippapay_ds_admin_action');
            $order_db_id = intval($_POST['order_db_id']);
            $new_status = sanitize_text_field($_POST['order_status']);
            $delivery_details = sanitize_textarea_field($_POST['delivery_details']);

            $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_db_id));

            if ($order) {
                // Auto-refund if admin sets status to Refunded / Cancelled
                if (($new_status === 'refunded' || $new_status === 'cancelled') && $order->payment_status === 'paid') {
                    $users = new Users();
                    $refunded = $users->refund(
                        $order->vtupress_user_id,
                        $order->amount,
                        "Digital Store Auto-Refund: Order {$order->order_id}"
                    );

                    if ($refunded) {
                        $wpdb->update($orders_table, ['payment_status' => 'refunded'], ['id' => $order->id]);
                        $wpdb->update($products_table, ['status' => 'available'], ['id' => $order->product_id]);
                    }
                }

                $wpdb->update($orders_table, [
                    'order_status' => $new_status,
                    'delivery_details' => $delivery_details
                ], ['id' => $order->id]);

                // Save Admin Private Notes (Strictly Admin Visible)
                if (!empty($_POST['admin_private_note'])) {
                    $note = sanitize_textarea_field($_POST['admin_private_note']);
                    $wpdb->insert($notes_table, [
                        'order_id' => $order->order_id,
                        'admin_id' => get_current_user_id(),
                        'note_content' => $note
                    ]);
                }
                echo '<div class="notice notice-success"><p>Order updated successfully!</p></div>';
            }
        }

        // Filters and Search
        $status_filter = sanitize_text_field($_GET['status_filter'] ?? 'all');
        $search_query = sanitize_text_field($_GET['s_order'] ?? '');

        $where_clauses = ["1=1"];
        if ($status_filter !== 'all') {
            $where_clauses[] = $wpdb->prepare("order_status = %s", $status_filter);
        }
        if (!empty($search_query)) {
            $where_clauses[] = $wpdb->prepare("(order_id LIKE %s OR product_name LIKE %s)", '%' . $wpdb->esc_like($search_query) . '%', '%' . $wpdb->esc_like($search_query) . '%');
        }

        $where_sql = implode(' AND ', $where_clauses);
        $orders = $wpdb->get_results("SELECT * FROM $orders_table WHERE $where_sql ORDER BY id DESC LIMIT 100");
        $products = $wpdb->get_results("SELECT * FROM $products_table ORDER BY id DESC");
        $admin_page_slug = sanitize_text_field($_GET['page'] ?? 'digital-store-admin');
        ?>
        <div class="wrap">
            <h1>Digital Store Management</h1>
            <hr>

            <!-- Store Settings Section -->
            <div style="background:#fff; padding:15px; border:1px solid #ccd0d4; margin-bottom:20px; border-left:4px solid #0073aa;">
                <h3 style="margin-top:0;">💬 Support &amp; WhatsApp Redirect Settings</h3>
                <form method="post" style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                    <?php wp_nonce_field('chippapay_ds_admin_action'); ?>
                    <div>
                        <label><strong>Admin WhatsApp Number (For Post-Payment Redirect):</strong></label><br>
                        <input type="text" name="admin_whatsapp_number" value="<?php echo esc_attr(get_option('digital_store_admin_whatsapp', get_option('chippapay_ds_admin_whatsapp', '+2349032868984'))); ?>" style="width:280px;" placeholder="+2349032868984">
                    </div>
                    <div style="margin-top:18px;">
                        <button type="submit" name="save_store_settings" class="button button-primary">Save Settings</button>
                    </div>
                </form>
                <p class="description" style="margin-bottom:0; margin-top:5px;">Customers will be redirected to this WhatsApp number when clicking "Chat with us on WhatsApp" on the order tracking page.</p>
            </div>

            <!-- Order Status Filter Navigation -->
            <ul class="subsubsub">
                <li><a href="<?php echo admin_url('admin.php?page=' . $admin_page_slug); ?>" class="<?php echo $status_filter === 'all' ? 'current' : ''; ?>">All Orders</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=' . $admin_page_slug . '&status_filter=processing'); ?>" class="<?php echo $status_filter === 'processing' ? 'current' : ''; ?>">Processing</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=' . $admin_page_slug . '&status_filter=delivered'); ?>" class="<?php echo $status_filter === 'delivered' ? 'current' : ''; ?>">Delivered</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=' . $admin_page_slug . '&status_filter=completed'); ?>" class="<?php echo $status_filter === 'completed' ? 'current' : ''; ?>">Completed</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=' . $admin_page_slug . '&status_filter=refunded'); ?>" class="<?php echo $status_filter === 'refunded' ? 'current' : ''; ?>">Refunded</a></li>
            </ul>

            <div style="clear:both; margin-bottom:15px;"></div>
            
            <div style="display:flex; gap: 20px;">
                <!-- Orders Column -->
                <div style="flex:2;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h2>Digital Store Orders</h2>
                        <form method="get">
                            <input type="hidden" name="page" value="<?php echo esc_attr($admin_page_slug); ?>">
                            <input type="search" name="s_order" value="<?php echo esc_attr($search_query); ?>" placeholder="Search Order ID...">
                            <input type="submit" class="button" value="Search">
                        </form>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="7">No orders found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): 
                                    $notes = $wpdb->get_results($wpdb->prepare("SELECT * FROM $notes_table WHERE order_id = %s ORDER BY id DESC", $o->order_id));
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($o->order_id); ?></strong></td>
                                    <td><?php echo esc_html($o->product_name); ?></td>
                                    <td>₦<?php echo number_format($o->amount, 2); ?></td>
                                    <td><span class="badge"><?php echo strtoupper($o->payment_status); ?></span></td>
                                    <td><strong style="color:#0073aa;"><?php echo strtoupper($o->order_status); ?></strong></td>
                                    <td><?php echo $o->created_at; ?></td>
                                    <td>
                                        <button class="button button-secondary" onclick="document.getElementById('edit-order-<?php echo $o->id; ?>').style.display='block'">Manage</button>
                                    </td>
                                </tr>
                                
                                <tr id="edit-order-<?php echo $o->id; ?>" style="display:none; background:#f9f9f9;">
                                    <td colspan="7">
                                        <form method="post" style="padding:15px; border:1px solid #ccd0d4; background:#fff;">
                                            <?php wp_nonce_field('chippapay_ds_admin_action'); ?>
                                            <input type="hidden" name="order_db_id" value="<?php echo $o->id; ?>">
                                            <h3>Manage Order: <?php echo esc_html($o->order_id); ?></h3>
                                            
                                            <p><label><strong>Order Status:</strong></label><br>
                                            <select name="order_status">
                                                <option value="processing" <?php selected($o->order_status, 'processing'); ?>>Processing</option>
                                                <option value="delivered" <?php selected($o->order_status, 'delivered'); ?>>Delivered (Access Provided)</option>
                                                <option value="completed" <?php selected($o->order_status, 'completed'); ?>>Completed</option>
                                                <option value="refunded" <?php selected($o->order_status, 'refunded'); ?>>Refunded (Auto-Refund Wallet)</option>
                                                <option value="cancelled" <?php selected($o->order_status, 'cancelled'); ?>>Cancelled</option>
                                            </select></p>

                                            <p><label><strong>Delivery Details (Visible to Customer on Order Tracking page):</strong></label><br>
                                            <textarea name="delivery_details" rows="3" style="width:100%;" placeholder="e.g. You have been added as a Page Manager for john@example.com. Check Facebook notifications to accept."><?php echo esc_textarea($o->delivery_details); ?></textarea></p>

                                            <p><label style="color:#d63638;"><strong>Admin Private Notes (Only Visible to Admins):</strong></label><br>
                                            <textarea name="admin_private_note" rows="2" style="width:100%;" placeholder="Add private operational notes here..."></textarea></p>

                                            <?php if (!empty($notes)): ?>
                                                <div style="background:#fff8e5; padding:10px; border-left:4px solid #ffb900; margin-bottom:15px;">
                                                    <strong>Previous Admin Notes:</strong>
                                                    <ul>
                                                    <?php foreach ($notes as $n): ?>
                                                        <li><em><?php echo $n->created_at; ?>:</em> <?php echo esc_html($n->note_content); ?></li>
                                                    <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <button type="submit" name="update_order_status" class="button button-primary">Save Order Updates</button>
                                            <button type="button" class="button" onclick="document.getElementById('edit-order-<?php echo $o->id; ?>').style.display='none'">Close</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Product Inventory Column -->
                <div style="flex:1; background:#fff; padding:15px; border:1px solid #ccd0d4;">
                    <h2>Add New Product</h2>
                    <form method="post">
                        <?php wp_nonce_field('chippapay_ds_admin_action'); ?>
                        <p><label><strong>Product Title:</strong></label><br>
                        <input type="text" name="title" required style="width:100%;" placeholder="e.g. ✂️ CapCut Pro — 1 Month"></p>

                        <p><label><strong>Category:</strong></label><br>
                        <select name="category" style="width:100%;">
                            <option value="subscriptions">🔥 Premium Subscriptions</option>
                            <option value="facebook">Facebook</option>
                            <option value="youtube">YouTube</option>
                            <option value="tiktok">TikTok</option>
                            <option value="instagram">Instagram</option>
                        </select></p>

                        <p><label><strong>Price (NGN):</strong></label><br>
                        <input type="number" step="0.01" name="price" required style="width:100%;" placeholder="5000"></p>

                        <p><label><strong>Followers / Subscribers:</strong></label><br>
                        <input type="text" name="followers" placeholder="e.g. N/A or 12.5K Followers" style="width:100%;"></p>

                        <p><label><strong>Account Age / Duration:</strong></label><br>
                        <input type="text" name="account_age" placeholder="e.g. 1 Month Duration" style="width:100%;"></p>

                        <p><label><strong>Monetization / Feature Status:</strong></label><br>
                        <input type="text" name="monetization_status" placeholder="e.g. Pro Features Unlocked" style="width:100%;"></p>

                        <p><label><strong>Audience / Region:</strong></label><br>
                        <input type="text" name="audience_country" value="Global" style="width:100%;"></p>

                        <p><label><strong>Delivery Timeframe:</strong></label><br>
                        <input type="text" name="delivery_time" value="15 mins – 2 hours" style="width:100%;"></p>

                        <p><label><strong>Description:</strong></label><br>
                        <textarea name="description" rows="3" style="width:100%;" placeholder="Full product description & features included..."></textarea></p>

                        <button type="submit" name="add_new_product" class="button button-primary">Publish Product</button>
                    </form>

                    <hr>
                    <h3>Current Inventory List</h3>
                    <ul style="max-height: 400px; overflow-y: auto; margin:0; padding:0; list-style:none;">
                        <?php foreach ($products as $p): ?>
                            <li style="border-bottom: 1px solid #eee; padding: 10px 0;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <div>
                                        <strong><?php echo esc_html($p->title); ?></strong> (₦<?php echo number_format($p->price, 2); ?>) <br>
                                        <span class="badge" style="font-size:10px; padding:2px 6px; background:<?php echo $p->status === 'available' ? '#46b450' : ($p->status === 'reserved' ? '#ffb900' : '#dc3232'); ?>; color:#fff; border-radius:3px; font-weight:600; text-transform:uppercase;"><?php echo strtoupper($p->status); ?></span>
                                        <span style="font-size:10px; background:#f0f0f1; padding:2px 5px; border-radius:3px; margin-left:4px; font-weight:600;"><?php echo strtoupper($p->category); ?></span>
                                    </div>
                                    <div style="white-space:nowrap;">
                                        <button type="button" onclick="var el=document.getElementById('edit-prod-box-<?php echo $p->id; ?>'); el.style.display=(el.style.display==='none'?'block':'none');" class="button button-small" style="font-size:11px; margin-right:4px;">Edit</button>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=' . $admin_page_slug . '&action_type=delete_product&product_id=' . $p->id), 'chippapay_ds_admin_action'); ?>" onclick="return confirm('Delete product?');" style="color:#d63638; font-size:11px; text-decoration:underline;">Delete</a>
                                    </div>
                                </div>

                                <!-- Inline Edit Box -->
                                <div id="edit-prod-box-<?php echo $p->id; ?>" style="display:none; margin-top:10px; padding:12px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px;">
                                    <h4 style="margin:0 0 10px 0; font-size:13px; color:#1d2327;">Edit Product #<?php echo $p->id; ?></h4>
                                    <form method="post">
                                        <?php wp_nonce_field('chippapay_ds_admin_action'); ?>
                                        <input type="hidden" name="edit_product_id" value="<?php echo $p->id; ?>">

                                        <p style="margin-bottom:8px;"><label><strong>Product Title:</strong></label><br>
                                        <input type="text" name="title" value="<?php echo esc_attr($p->title); ?>" required style="width:100%;"></p>

                                        <p style="margin-bottom:8px;"><label><strong>Category:</strong></label><br>
                                        <select name="category" style="width:100%;">
                                            <option value="subscriptions" <?php selected($p->category, 'subscriptions'); ?>>🔥 Premium Subscriptions</option>
                                            <option value="facebook" <?php selected($p->category, 'facebook'); ?>>Facebook</option>
                                            <option value="youtube" <?php selected($p->category, 'youtube'); ?>>YouTube</option>
                                            <option value="tiktok" <?php selected($p->category, 'tiktok'); ?>>TikTok</option>
                                            <option value="instagram" <?php selected($p->category, 'instagram'); ?>>Instagram</option>
                                        </select></p>

                                        <p style="margin-bottom:8px;"><label><strong>Price (NGN):</strong></label><br>
                                        <input type="number" step="0.01" name="price" value="<?php echo esc_attr($p->price); ?>" required style="width:100%;"></p>

                                        <p style="margin-bottom:8px;"><label><strong>Inventory Status:</strong></label><br>
                                        <select name="status" style="width:100%;">
                                            <option value="available" <?php selected($p->status, 'available'); ?>>Available</option>
                                            <option value="reserved" <?php selected($p->status, 'reserved'); ?>>Reserved</option>
                                            <option value="sold" <?php selected($p->status, 'sold'); ?>>Sold</option>
                                        </select></p>

                                        <p style="margin-bottom:8px;"><label><strong>Followers / Subs:</strong></label><br>
                                        <input type="text" name="followers" value="<?php echo esc_attr($p->followers); ?>" style="width:100%;"></p>

                                        <p style="margin-bottom:8px;"><label><strong>Account Age / Duration:</strong></label><br>
                                        <input type="text" name="account_age" value="<?php echo esc_attr($p->account_age); ?>" style="width:100%;"></p>

                                        <p style="margin-bottom:8px;"><label><strong>Monetization / Feature Status:</strong></label><br>
                                        <input type="text" name="monetization_status" value="<?php echo esc_attr($p->monetization_status); ?>" style="width:100%;"></p>

                                        <p style="margin-bottom:8px;"><label><strong>Audience / Region:</strong></label><br>
                                        <input type="text" name="audience_country" value="<?php echo esc_attr($p->audience_country); ?>" style="width:100%;"></p>

                                        <p style="margin-bottom:8px;"><label><strong>Delivery Timeframe:</strong></label><br>
                                        <input type="text" name="delivery_time" value="<?php echo esc_attr($p->delivery_time); ?>" style="width:100%;"></p>

                                        <p style="margin-bottom:10px;"><label><strong>Description:</strong></label><br>
                                        <textarea name="description" rows="3" style="width:100%;"><?php echo esc_textarea($p->description); ?></textarea></p>

                                        <div style="display:flex; gap:8px;">
                                            <button type="submit" name="update_product" class="button button-primary button-small">Save Changes</button>
                                            <button type="button" class="button button-small" onclick="document.getElementById('edit-prod-box-<?php echo $p->id; ?>').style.display='none'">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}
}

if (!class_exists('ChippaPay_DS_Admin')) {
    class_alias('Digital_Store_Admin', 'ChippaPay_DS_Admin');
}
Digital_Store_Admin::init();




