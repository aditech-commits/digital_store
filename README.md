# Digital Store Addon for VTUPress

![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-green.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPLv2-orange.svg)

**Digital Store** is a native companion WordPress plugin built specifically for the **VTUPress** fintech platform. It allows VTUPress portal owners to sell verified social media accounts (Instagram, TikTok, YouTube, Facebook) and premium software/AI subscriptions directly using the user's **VTUPress wallet balance**.

---

## 🌟 Key Features

- **Native VTUPress Wallet Integration**: Users pay directly with their VTUPress balance with atomic debit and rollback protection.
- **Automated Refund Protection**: When an admin cancels or marks an order as refunded, funds are instantly returned to the user's wallet via `$users->refund()`.
- **Integrated Dashboard Tile**: Appears automatically in the VTUPress user dashboard service grid alongside Airtime, Data, and Bills without editing core files.
- **Product Catalog**: Supports verified social media accounts and software/AI subscriptions (CapCut Pro, Gemini Advanced, Canva Pro, VPNs, etc.).
- **Order Tracking & WhatsApp Support**: Real-time status stepper (`Processing` → `Delivered` → `Completed`) with automatic pre-filled WhatsApp support redirect links.
- **Admin Management Suite**: Full control in WordPress Admin under `vtupanel > Digital Store` for order fulfillment, credential delivery, private internal operational notes, and stock management.

---

## 📁 Directory Structure

```
digital-store/
├── assets/
│   ├── css/
│   │   └── digital-store.css        # Premium responsive design system
│   ├── images/
│   │   └── digital-store-icon.png   # Dashboard 3D service tile icon
│   └── js/
│       └── digital-store.js         # Frontend filter & AJAX purchase logic
├── includes/
│   ├── class-admin-dashboard.php    # WP Admin management under 'vtupanel'
│   ├── class-frontend-shortcodes.php # [digital_store] shortcode handler
│   ├── class-service-integration.php # Service class extending Vtupress_services
│   └── class-store-engine.php       # AJAX purchase, tracking & auto-refunds
├── digital-store.php                # Main plugin loader & DB migration
├── DEVELOPMENT_GUIDE.md             # Developer architecture & master guide
└── README.md                        # Repository overview
```

---

## 🚀 Installation & Activation

1. Download or clone this repository into your WordPress plugins directory:
   ```bash
   wp-content/plugins/digital-store/
   ```
2. Navigate to **WP Admin > Plugins** and click **Activate** under **Digital Store**.
3. The plugin will automatically create the necessary database tables (`wp_chippapay_ds_products`, `wp_chippapay_ds_orders`, `wp_chippapay_ds_admin_notes`).
4. Go to **WP Admin > VTUPress > Digital Store** to configure your support WhatsApp number and add inventory.

---

## 🛠️ Requirements

- WordPress 5.8+
- PHP 8.0+
- VTUPress Ultimate plugin active

---

## 📄 License

GPL-2.0-or-later. Developed by [Aditech](https://agency.aditechgadgets.com.ng).
