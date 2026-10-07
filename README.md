# Bulk Edit SEO Manager

**Bulk Edit SEO Manager** is a powerful WordPress plugin for bulk‑editing posts, products and custom post types in a single table — with a strong focus on SEO (Yoast, Rank Math, SEOPress) and WooCommerce.

> 🌐 **Language / زبان:** This README is in English. برای مستندات کامل فارسی به [README.fa.md](README.fa.md) مراجعه کنید.

[![version](https://img.shields.io/badge/version-1.5.2-blue.svg)](https://github.com/Yaldaj19/bulk-edit-seo-manager)
[![WordPress](https://img.shields.io/badge/wordpress-5.8%2B-green.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-red.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 🎯 Overview

Editing SEO fields, prices, stock or taxonomies one post at a time is slow. **Bulk Edit SEO Manager** puts hundreds of items in one editable table so you can update them together — directly in the browser or via CSV round‑trips through Excel/Google Sheets.

It is fully translatable and ships with **English** and **Persian (فارسی)** out of the box.

---

## ✨ Key Features

### Bulk table editor
- Edit posts, products and **any custom post type** in one screen
- Pick exactly which fields appear (per post type)
- Inline editing with a **row‑scroll** system and synced sticky header
- **Save only changed rows** (nothing untouched is re‑written)
- Bulk actions: publish, draft, pending, private, move to trash

### Data transfer (CSV)
- **Export** the currently filtered rows to CSV (UTF‑8 with BOM so Excel shows every language correctly)
- Edit in Excel / Google Sheets, then **import** the file back — rows are matched and updated by the `ID` column through the same safe save pipeline
- Taxonomy columns export as `Name A|Name B` and are resolved back to existing terms on import

### Fast column operations
- **Find & Replace** a phrase across one column for all rows
- **Fill‑down** — push a single value into an entire column
- (Both apply in the browser; click **Save changes** to commit.)

### SEO integration
- **Yoast SEO**, **Rank Math** and **SEOPress**: title, meta description, focus keyword, canonical, robots (noindex / nofollow)
- **OpenGraph & Twitter** title/description, mapped automatically to the active SEO plugin

### WooCommerce
- Regular/sale price, SKU, stock status & quantity, manage‑stock, product type, short description
- Gallery management and **variable products** (edit each variation’s price, stock, SKU, image…)
- Product attributes

### Smart filters
- Text search (title, excerpt, content)
- Filter by status, product type, taxonomies and custom meta fields (auto‑detected type: text / number / select)

---

## 📋 Requirements

- WordPress **5.8+**
- PHP **7.4+**
- *(optional)* WooCommerce 5.0+ for product features
- *(optional)* Yoast SEO, Rank Math, or SEOPress for SEO fields

---

## 💾 Installation

1. Download the plugin (Code → **Download ZIP**, or clone the repo).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, **Install**, then **Activate**.
3. Open **ویرایش گروهی پست تایپ ها / Bulk Edit** in the admin menu, go to **Settings**, pick a post type and the fields you want, and save.

Manual install:
```bash
git clone https://github.com/Yaldaj19/bulk-edit-seo-manager.git
cp -r bulk-edit-seo-manager /path/to/wp-content/plugins/
```

---

## 🚀 Quick Start

1. **Settings** → choose a post type (e.g. Product), tick the fields to edit, set rows‑per‑page, save.
2. **Bulk Editor** → use the filters to narrow the list.
3. Edit inline, or use **Export CSV → edit in Excel → Import CSV**, or **Find & Replace** / **Fill‑down**.
4. Click **Save changes**.

---

## 🌐 Languages

The plugin is fully internationalized (text domain `bulk-edit-seo`) and ships with:

| Locale | Status |
|--------|--------|
| English (`en_US`) | ✅ included |
| Persian / فارسی (`fa_IR`) | ✅ source language |

WordPress picks the language automatically from **Settings → General → Site Language**, or per‑user from **Users → Profile → Language**. Set it to English and the whole plugin UI is in English; set it to فارسی and it is in Persian.

**Add another language:** translate `languages/bulk-edit-seo.pot` with a tool like [Poedit](https://poedit.net/) and drop the resulting `bulk-edit-seo-{locale}.po/.mo` into the `languages/` folder. To regenerate the template with WP‑CLI:
```bash
wp i18n make-pot . languages/bulk-edit-seo.pot --domain=bulk-edit-seo
```

---

## 🧩 Hooks for developers

```php
// Add a custom field to the editor
add_filter('besm_available_fields', function ($fields, $post_type) {
    $fields['my_field'] = ['type' => 'text', 'label' => 'My field'];
    return $fields;
}, 10, 2);

// Adjust the query
add_filter('besm_query_args', fn($args, $filters) => $args, 10, 2);

// Adjust a post's data before it loads into the table
add_filter('besm_post_data', fn($data, $post_id) => $data, 10, 2);
```

---

## 📜 License

Released under the **GPL‑2.0‑or‑later** license. See [LICENSE](LICENSE).

Copyright © 2026 YJ19.

---

## 👤 Author

**YJ19** — WordPress & front‑end developer.

- 🌐 Website: [yaldajahanshahi.ir](https://yaldajahanshahi.ir)
- 💻 GitHub: [github.com/Yaldaj19](https://github.com/Yaldaj19)
- 📧 Email: jyalda.619@gmail.com

Found it useful? Please ⭐ the repo. Bug reports and feature requests are welcome in [Issues](https://github.com/Yaldaj19/bulk-edit-seo-manager/issues).
