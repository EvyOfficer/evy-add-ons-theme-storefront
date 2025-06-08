<?php
/**
 * Plugin Name: Evy - Add-Ons Theme Storefront
 * Plugin URI:  https://github.com/EvyOfficer
 * Description: Add-Ons for the Storefront theme, product visibility and related products.
 * Version:     1.4.0
 * Author:      EvyOfficer
 * Author URI:  https://github.com/EvyOfficer
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: evy-add-ons-theme-storefront
 * Domain Path: /languages
 */

// ตรวจสอบให้แน่ใจว่าไม่ได้เรียกใช้ไฟล์นี้โดยตรง
defined('ABSPATH') || exit;

// กำหนดค่าคงที่ (Constants) สำหรับปลั๊กอินของคุณ
define( 'EVY_ADDONS_VERSION', '1.4.0' );
define( 'EVY_ADDONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVY_ADDONS_URL', plugin_dir_url( __FILE__ ) );

// =================================================================================================
// 🔧 ส่วนที่ 1: การกำหนดค่า User Roles และ Product Categories (ปรับเปลี่ยนได้ที่นี่เท่านั้น)
// =================================================================================================

/**
 * ฟังก์ชันเช็ก Role ที่เห็นสินค้าทุกหมวดหมู่ (รวม Short term accommodation)
 * เพิ่ม Role ที่นี่ หากต้องการให้ Role อื่นๆ เห็นสินค้าทุกหมวด
 * เช่น ['administrator', 'shop_manager']
 */
function evy_get_full_access_roles() {
    $roles = get_option('evy_full_access_roles', ['shop_manager']);
    if (!is_array($roles)) {
        $roles = array_filter(array_map('sanitize_key', explode(',', $roles)));
    }
    return $roles;
}

/**
 * รายชื่อผู้ใช้ที่ควรถูกซ่อนเมนู Dashboard และ Appearance
 */
function evy_get_hide_menu_user_logins() {
    $users = get_option('evy_hide_menu_user_logins', []);
    if (!is_array($users)) {
        $users = array_filter(array_map('sanitize_user', explode(',', $users)));
    }
    return $users;
}

/**
 * รายการเมนูที่ต้องซ่อนตามชื่อผู้ใช้
 * ค่าที่คืนเป็นรูปแบบ [ 'user_login' => [ 'menu_slug1', 'menu_slug2' ] ]
 */
function evy_get_hidden_menus_by_user() {
    $data = get_option('evy_hidden_menus_by_user', []);
    return is_array($data) ? $data : [];
}

/**
 * ดึงหมวดหมู่สินค้าที่อนุญาตให้ผู้ใช้เข้าถึง
 *
 * @param string|null $user_login ชื่อผู้ใช้ (ถ้าไม่ระบุจะใช้ผู้ใช้ปัจจุบัน)
 * @return array  รายการ slug ของหมวดหมู่
 */
function evy_get_user_restricted_categories($user_login = null) {
    if ($user_login === null) {
        if (!is_user_logged_in()) {
            return [];
        }
        $user = wp_get_current_user();
        $user_login = $user->user_login;
    } else {
        $user_login = sanitize_user($user_login);
    }

    $map = get_option('evy_restricted_categories_by_user', []);
    if (!is_array($map) || empty($map[$user_login])) {
        return [];
    }

    $cats = $map[$user_login];
    if (!is_array($cats)) {
        $cats = explode(',', $cats);
    }

    return array_filter(array_map('sanitize_title', array_map('trim', $cats)));

 /**
 * รายการเมนูที่ต้องซ่อนตามชื่อผู้ใช้
 * ค่าที่คืนเป็นรูปแบบ [ 'user_login' => [ 'menu_slug1', 'menu_slug2' ] ]
 */
function evy_get_hidden_menus_by_user() {
    $data = get_option('evy_hidden_menus_by_user', []);
    return is_array($data) ? $data : [];
}

/**
 * หมวดหมู่สินค้าที่ถูกจำกัดตามชื่อผู้ใช้
 * รูปแบบค่าที่คืน: [ 'user_login' => [ 'slug1', 'slug2' ] ]
 */
function evy_get_restricted_categories_by_user() {
    $data = get_option('evy_restricted_categories_by_user', []);
    return is_array($data) ? $data : [];
}

/**
 * Return restricted categories for the current user.
 * If no specific categories are set for the user, fall back to the global list.
 */
function evy_get_user_restricted_categories() {
    $user = wp_get_current_user();
    $map  = evy_get_restricted_categories_by_user();

    if ($user && isset($map[$user->user_login])) {
        return $map[$user->user_login];
    }

    return evy_get_restricted_categories_slugs();
}

// =============================================================================
// ⚙️ ส่วนเสริม: หน้าการตั้งค่าในแผงควบคุม
// =============================================================================

add_action('admin_menu', 'evy_add_options_page');
function evy_add_options_page() {
    add_options_page(
        __('Evy Add-Ons Settings', 'evy-add-ons-storefront'),
        __('Evy Add-Ons', 'evy-add-ons-storefront'),
        'manage_options',
        'evy-addons-settings',
        'evy_render_settings_page'
    );
}

add_action('admin_init', 'evy_register_settings');
function evy_register_settings() {
    register_setting('evy_addons_settings', 'evy_full_access_roles', [
        'type' => 'array',
        'sanitize_callback' => 'evy_sanitize_csv',
        'default' => ['shop_manager'],
    ]);

    register_setting('evy_addons_settings', 'evy_restricted_categories_slugs', [
@@ -119,78 +143,76 @@ function evy_register_settings() {
    ]);

    register_setting('evy_addons_settings', 'evy_disable_nickname_fields', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);

    register_setting('evy_addons_settings', 'evy_hide_menu_for_shop_manager', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);

    register_setting('evy_addons_settings', 'evy_hide_menu_user_logins', [
        'type' => 'array',
        'sanitize_callback' => 'evy_sanitize_csv',
        'default' => [],
    ]);

    register_setting('evy_addons_settings', 'evy_hidden_menus_by_user', [
        'type' => 'array',
        'sanitize_callback' => 'evy_sanitize_hidden_menus_by_user',
        'default' => [],
    ]);

    register_setting('evy_addons_settings', 'evy_restricted_categories_by_user', [
        'type' => 'array',
        'sanitize_callback' => 'evy_sanitize_restricted_categories_by_user',
        'default' => [],
    ]);

    add_settings_section(
        'evy_settings_section',
        __('User Roles & Categories', 'evy-add-ons-storefront'),
        '__return_false',
        'evy_addons_settings'
    );

    add_settings_field(
        'evy_full_access_roles',
        __('Full Access Roles', 'evy-add-ons-storefront'),
        'evy_field_full_access_roles',
        'evy_addons_settings',
        'evy_settings_section'
    );

    add_settings_field(
        'evy_restricted_categories_by_user',
        __('Restricted Categories by User', 'evy-add-ons-storefront'),
        'evy_field_restricted_categories_by_user',
        'evy_addons_settings',
        'evy_settings_section'
    );

    add_settings_field(
        'evy_disable_nickname_fields',
        __('Disable Nickname Editing', 'evy-add-ons-storefront'),
        'evy_field_disable_nickname',
        'evy_addons_settings',
        'evy_settings_section',
        ['label_for' => 'evy_disable_nickname_fields']
    );

    add_settings_field(
        'evy_hide_menu_for_shop_manager',
        __('Hide Menus for Shop Manager', 'evy-add-ons-storefront'),
        'evy_field_hide_menu_shop_manager',
        'evy_addons_settings',
        'evy_settings_section',
        ['label_for' => 'evy_hide_menu_for_shop_manager']
    );

    add_settings_field(
        'evy_hide_menu_user_logins',
        __('Hide Menus for Users', 'evy-add-ons-storefront'),
@@ -215,50 +237,72 @@ function evy_sanitize_csv($value) {
    $value = array_filter(array_map('sanitize_key', array_map('trim', $value)));
    return $value;
}

function evy_sanitize_hidden_menus_by_user($value) {
    if (is_array($value)) {
        $lines = $value;
    } else {
        $lines = explode("\n", $value);
    }
    $result = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line || strpos($line, ':') === false) {
            continue;
        }
        list($login, $menus) = array_map('trim', explode(':', $line, 2));
        $login = sanitize_user($login);
        $slugs = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $menus))));
        if ($login && !empty($slugs)) {
            $result[$login] = $slugs;
        }
    }
    return $result;
}

function evy_sanitize_restricted_categories_by_user($value) {
    if (is_array($value)) {
        $lines = $value;
    } else {
        $lines = explode("\n", $value);
    }
    $result = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line || strpos($line, ':') === false) {
            continue;
        }
        list($login, $cats) = array_map('trim', explode(':', $line, 2));
        $login = sanitize_user($login);
        $slugs = array_filter(array_map('sanitize_title', array_map('trim', explode(',', $cats))));
        if ($login && !empty($slugs)) {
            $result[$login] = $slugs;
        }
    }
    return $result;
}

function evy_field_full_access_roles() {
    $value = get_option('evy_full_access_roles', ['shop_manager']);
    if (is_array($value)) {
        $value = implode(',', $value);
    }
    echo '<input type="text" name="evy_full_access_roles" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Comma separated role slugs', 'evy-add-ons-storefront') . '</p>';
}

function evy_field_restricted_categories() {
    $value = get_option('evy_restricted_categories_slugs', ['short_term_accommodation']);
    if (is_array($value)) {
        $value = implode(',', $value);
    }
    echo '<input type="text" name="evy_restricted_categories_slugs" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Comma separated category slugs', 'evy-add-ons-storefront') . '</p>';
}

function evy_field_restricted_roles() {
    $value = get_option('evy_restricted_category_access_roles', ['tenant']);
    if (is_array($value)) {
        $value = implode(',', $value);
    }
    echo '<input type="text" name="evy_restricted_category_access_roles" value="' . esc_attr($value) . '" class="regular-text" />';
@@ -284,137 +328,154 @@ function evy_field_hide_menu_users() {
    }
    echo '<input type="text" name="evy_hide_menu_user_logins" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Comma separated user logins', 'evy-add-ons-storefront') . '</p>';
}

function evy_field_hidden_menus_by_user() {
    $value = get_option('evy_hidden_menus_by_user', []);
    $lines = [];
    foreach ($value as $login => $menus) {
        $lines[] = $login . ':' . implode(',', $menus);
    }
    $value = implode("\n", $lines);
    echo '<textarea name="evy_hidden_menus_by_user" rows="5" cols="40">' . esc_textarea($value) . '</textarea>';
    global $menu;
    $slugs = [];
    foreach ((array) $menu as $m) {
        if (!empty($m[2])) {
            $slugs[] = esc_html($m[2]);
        }
    }
    if ($slugs) {
        echo '<p class="description">' . esc_html__('Format: user_login:menu_slug1,menu_slug2', 'evy-add-ons-storefront') . '</p>';
        echo '<pre>' . implode("\n", $slugs) . '</pre>';
    }
}

function evy_field_restricted_categories_by_user() {
    $value = get_option('evy_restricted_categories_by_user', []);
    $lines = [];
    foreach ($value as $login => $cats) {
        $lines[] = $login . ':' . implode(',', $cats);
    }
    $value = implode("\n", $lines);
    echo '<textarea name="evy_restricted_categories_by_user" rows="5" cols="40">' . esc_textarea($value) . '</textarea>';
    $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    if (!is_wp_error($terms)) {
        $slugs = wp_list_pluck($terms, 'slug');
        sort($slugs);
        echo '<p class="description">' . esc_html__('Format: user_login:slug1,slug2', 'evy-add-ons-storefront') . '</p>';
        echo '<pre>' . esc_html(implode("\n", $slugs)) . '</pre>';
    }
}

function evy_render_settings_page() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Evy Add-Ons Settings', 'evy-add-ons-storefront') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('evy_addons_settings');
    do_settings_sections('evy_addons_settings');
    submit_button();
    echo '</form></div>';
}

// =================================================================================================
// 🚀 ส่วนที่ 2: ฟังก์ชันช่วยตรวจสอบบทบาทของผู้ใช้ (ไม่ต้องแก้ไข)
// =================================================================================================

/**
 * ตรวจสอบว่าผู้ใช้ปัจจุบันมีบทบาทที่สามารถเข้าถึงสินค้าทุกหมวดหมู่ได้หรือไม่
 */
function evy_user_has_full_access() {
    if (!is_user_logged_in()) return false;
    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    return count(array_intersect($roles, evy_get_full_access_roles())) > 0;
}


// =================================================================================================
// ⚙️ ส่วนที่ 3: ฟังก์ชันการทำงานหลักของปลั๊กอิน (ไม่ต้องแก้ไข)
// =================================================================================================

// --- Start: Product Visibility Functions (จาก OM SAB White House Add-Ons Hide Product slug) ---

/**
 * ปรับ query สำหรับหน้าเว็บไซต์เพื่อควบคุมการมองเห็นสินค้า
 */
add_action('woocommerce_product_query', 'evy_filter_product_visibility_frontend');
function evy_filter_product_visibility_frontend($q) {␊
    if (is_admin()) return;␊

    // ถ้าผู้ใช้มีสิทธิ์เข้าถึงเต็มรูปแบบ ไม่ต้องกรองอะไรเลย␊
    if (evy_user_has_full_access()) return;␊

    $tax_query = $q->get('tax_query') ?: [];␊
    $restricted_categories = evy_get_user_restricted_categories();

    if (evy_user_has_restricted_category_access()) {␊
        // ผู้ใช้มีสิทธิ์เข้าถึงหมวดหมู่ที่ถูกจำกัด: แสดงเฉพาะสินค้าในหมวดหมู่นั้น
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $restricted_categories,
            'operator' => 'IN',
        ];
    } else {
        // ผู้ใช้ทั่วไป: ซ่อนสินค้าในหมวดหมู่ที่ถูกจำกัด
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $restricted_categories,
            'operator' => 'NOT IN',
        ];
    }
                   
function evy_filter_product_visibility_frontend($q) {
    if (is_admin()) return;

    // ถ้าผู้ใช้มีสิทธิ์เข้าถึงเต็มรูปแบบ ไม่ต้องกรองอะไรเลย
    if (evy_user_has_full_access()) return;

    $cats = evy_get_user_restricted_categories();
    if (empty($cats)) return;

    $tax_query   = $q->get('tax_query') ?: [];
    $tax_query[] = [
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => $cats,
        'operator' => 'IN',
    ];

    $q->set('tax_query', $tax_query);
}

/**
 * ปรับ query สำหรับ REST API (เช่น WooCommerce Mobile App) เพื่อควบคุมการมองเห็นสินค้า
 */
add_filter('woocommerce_rest_product_query', 'evy_filter_product_visibility_rest', 10, 2);
add_filter('woocommerce_rest_product_object_query', 'evy_filter_product_visibility_rest', 10, 2);
function evy_filter_product_visibility_rest($args, $request) {
    // ถ้าผู้ใช้มีสิทธิ์เข้าถึงเต็มรูปแบบ ไม่ต้องกรองอะไรเลย
    if (evy_user_has_full_access()) return $args;

    $cats = evy_get_user_restricted_categories();
    if (empty($cats)) return $args;

    if (empty($args['tax_query'])) {
        $args['tax_query'] = [];
    }

    $args['tax_query'][] = [
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => $cats,
        'operator' => 'IN',
    ];

    return $args;
}

// --- End: Product Visibility Functions ---


// --- Start: Related Products Functions (จาก OM SAB Add-Ons Related Products on Theme storefront) ---

/**
 * ปรับการแสดงผลสินค้าที่เกี่ยวข้องให้แสดงเฉพาะสินค้าในหมวดหมู่เดียวกัน
 */
add_filter('woocommerce_related_products', 'evy_filter_related_products_by_same_category', 10, 3);

function evy_filter_related_products_by_same_category($related_posts, $product_id, $args) {
    $terms = get_the_terms($product_id, 'product_cat');
    
    if ($terms && !is_wp_error($terms)) {
        $term_ids = wp_list_pluck($terms, 'term_id');
        
        if (empty($term_ids)) {
            return $related_posts;
        }

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 4,
            'post__not_in'   => [$product_id],
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $term_ids,
                    'operator' => 'IN',
                ]
            ]
        ];

        $related_query = new WP_Query($args);
        
        if ($related_query->have_posts()) {
            $related_posts = wp_list_pluck($related_query->posts, 'ID');
        } else {
            $related_posts = [];
        }
    }
    return $related_posts;
}

// --- End: Related Products Functions ---

// --- Start: Admin UX Tweaks ---

if (get_option('evy_disable_nickname_fields', true)) {
    add_filter('user_contactmethods', 'evy_truly_remove_nickname_and_display_name_fields', 100);
    add_action('admin_head-profile.php', 'evy_remove_nickname_with_buffer_start');
    add_action('admin_head-user-edit.php', 'evy_remove_nickname_with_buffer_start');
    add_action('personal_options_update', 'evy_prevent_nickname_update');
    add_action('edit_user_profile_update', 'evy_prevent_nickname_update');
}

if (get_option('evy_hide_menu_for_shop_manager', true) || !empty(evy_get_hide_menu_user_logins())) {
    add_action('admin_menu', 'evy_hide_for_shop_manager', 999);
}

function evy_truly_remove_nickname_and_display_name_fields($methods) {
    remove_action('show_user_profile', 'default_profile_fields');
    remove_action('edit_user_profile', 'default_profile_fields');
    return $methods;
}

function evy_remove_nickname_with_buffer_start() {
    ob_start('evy_remove_nickname_callback');
}

function evy_remove_nickname_callback($buffer) {
    $buffer = preg_replace('/<tr class="user-nickname-wrap.*?<\/tr>/s', '', $buffer);
    $buffer = preg_replace('/<tr class="user-display-name-wrap.*?<\/tr>/s', '', $buffer);
    return $buffer;
}

function evy_prevent_nickname_update($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }
    $current = get_userdata($user_id);
    $_POST['nickname'] = $current->nickname;
    $_POST['display_name'] = $current->display_name;
}

function evy_hide_for_shop_manager() {
    $user = wp_get_current_user();
    $logins = evy_get_hide_menu_user_logins();
    $custom = evy_get_hidden_menus_by_user();
    $user_menus = $custom[$user->user_login] ?? [];

    if (!current_user_can('shop_manager') && !in_array($user->user_login, $logins, true) && empty($user_menus)) {
        return;
    }

    if (current_user_can('shop_manager') || in_array($user->user_login, $logins, true)) {
        remove_menu_page('index.php');
        remove_menu_page('themes.php');
        remove_submenu_page('tools.php', 'import.php');
    }

    foreach ($user_menus as $slug) {
        remove_menu_page($slug);
        global $menu;
        foreach ((array) $menu as $m) {
            if (!empty($m[2])) {
                remove_submenu_page($m[2], $slug);
            }
        }
    }
}

// --- End: Admin UX Tweaks ---
