# Digital Store — Development Architecture & Master Guide

This document serves as the **authoritative architectural guide** for the **Digital Store** WordPress plugin developed by **Aditech** ([agency.aditechgadgets.com.ng](https://agency.aditechgadgets.com.ng)) for VTUPress.

---

## 📌 1. Project Context & Objectives

The **Digital Store** is a standalone WordPress add-on plugin for VTUPress that allows users to manually browse and purchase verified social media accounts (Instagram, TikTok, YouTube, Facebook) and premium AI/Software subscriptions (CapCut Pro, Gemini Advanced, Canva Pro, Surfshark VPN, NordVPN, LinkedIn Premium) directly using their **VTUPress wallet balance**.

### Key Features:
1. **Native VTUPress Dashboard Integration**: Displays as a service card/tile inside the user dashboard service grid alongside Airtime, Data, Cable, Virtual Number, etc.
2. **In-Dashboard Experience**: Clicking the Digital Store tile opens the full store catalog inline inside the VTUPress layout (header, sidebar, bottom navigation preserved).
3. **Wallet Billing & Automated Refund**: Debits the user's VTUPress wallet (`Users::deduct()`). If an order is cancelled or marked as refunded by an admin, the system automatically refunds the user's wallet (`Users::refund()`).
4. **Order Tracking & WhatsApp Support Redirect**: Real-time order status tracking with configurable WhatsApp support line redirect.
5. **Admin Management**: Full WP Admin dashboard under `vtupanel` menu for processing orders, adding private admin operational notes, publishing new products, configuring support contact numbers, and inline editing of existing inventory items.

---

## 🏗️ 2. Architectural Findings & Key Learnings

### Rule 1: Extending `Vtupress_services` & Required Abstract Methods
- Any custom service class **MUST** extend `Vtupress_services`.
- Mandatory Abstract Method: `Vtupress_services` declares `abstract public function form_fields(): array;`.

### Rule 2: Dynamic Service Registry
- The plugin hooks into `vtupress_service_registry` to register the service ID and class name:
  ```php
  add_filter('vtupress_service_registry', function (array $registry): array {
      $registry['digital_store'] = 'Digital_Store_Service';
      return $registry;
  });
  ```

### Rule 3: The `$this->status = 'on'` Requirement
- The VTUPress dashboard template (`vtupress-templates`) filters services during rendering:
  ```php
  foreach ($services as $s) {
      if ($s->status != 'on') continue;
  ```
- The constructor of `Digital_Store_Service` MUST explicitly set `$this->status = 'on';`.

### Rule 4: Avoiding Admin Gating & Country Locks
- Do **NOT** set `$this->custom_id`. Leaving `$custom_id = ''` makes the service always active.
- Set `$this->countries = ['all'];` so the service is available across all user regions.

### Rule 5: Template Navigation Routing
- When a user clicks the Digital Store tile on the dashboard, VTUPress routes to `?vend=digital_store`.
- Implementing `render_frontend($user, $runcode)` on `Digital_Store_Service` renders the full store catalog directly inside the active VTUPress theme/template wrapper.

---

## 🗄️ 3. Database Schema

1. **`wp_chippapay_ds_products`**:
   - `id` (INT, PRIMARY KEY)
   - `title` (VARCHAR 255)
   - `category` (VARCHAR 50) — `facebook`, `youtube`, `tiktok`, `instagram`, `subscriptions`
   - `description` (TEXT)
   - `followers` (VARCHAR 100)
   - `account_age` (VARCHAR 100)
   - `monetization_status` (VARCHAR 100)
   - `audience_country` (VARCHAR 100)
   - `price` (DECIMAL 20,2)
   - `status` (VARCHAR 50) — `available`, `reserved`, `sold`
   - `delivery_time` (VARCHAR 100)
   - `created_at`, `updated_at`

2. **`wp_chippapay_ds_orders`**:
   - `id` (INT, PRIMARY KEY)
   - `order_id` (VARCHAR 50, UNIQUE) — e.g. `CPDS-892347`
   - `user_id` (INT)
   - `vtupress_user_id` (INT)
   - `product_id` (INT)
   - `product_name` (VARCHAR 255)
   - `amount` (DECIMAL 20,2)
   - `payment_status` (VARCHAR 50) — `paid`, `refunded`
   - `order_status` (VARCHAR 50) — `processing`, `delivered`, `completed`, `refunded`, `cancelled`
   - `delivery_details` (TEXT)
   - `confirmed_by_user` (TINYINT)
   - `created_at`, `updated_at`

3. **`wp_chippapay_ds_admin_notes`**:
   - `id` (INT, PRIMARY KEY)
   - `order_id` (VARCHAR 50)
   - `admin_id` (INT)
   - `note_content` (TEXT)
   - `created_at`

---

## 📂 4. File Structure & Component Breakdown

```
wp-content/plugins/digital-store/
├── assets/
│   ├── css/
│   │   └── digital-store.css        # Premium CSS design system, responsive grid, modal & dark mode support
│   ├── js/
│   │   └── digital-store.js         # Frontend filter logic, AJAX purchase handler, tracking modal controller
│   └── images/
│       └── digital-store-icon.png   # Dashboard service tile icon (High-res 3D store icon)
├── includes/
│   ├── class-admin-dashboard.php    # WP Admin page under VTUPress ('vtupanel'), order processing & inventory editing
│   ├── class-frontend-shortcodes.php # [digital_store] shortcode for standalone pages
│   ├── class-service-integration.php # Digital_Store_Service extends Vtupress_services
│   └── class-store-engine.php       # AJAX endpoints: purchase, order tracking, user delivery confirmation
├── digital-store.php                # Main plugin loader, activation hooks, DB setup, page generator
└── DEVELOPMENT_GUIDE.md             # Development master guide
```

---

## 🛠️ 5. How to Package & Deploy Updates

Whenever you make updates to the plugin files in `wp-content/plugins/digital-store/`:

1. Run the PowerShell command to package the plugin into a standalone ZIP file:
   ```powershell
   Remove-Item -Path "c:\Users\hp\Downloads\digital-store.zip" -ErrorAction SilentlyContinue
   Compress-Archive -Path "c:\Users\hp\Downloads\vtupress\vtupress\wp-content\plugins\digital-store\*" -DestinationPath "c:\Users\hp\Downloads\digital-store.zip" -Force
   ```
2. Upload `c:\Users\hp\Downloads\digital-store.zip` directly in WordPress Admin via **Plugins > Add New > Upload Plugin**, or copy the folder to your host via FTP/cPanel File Manager.
