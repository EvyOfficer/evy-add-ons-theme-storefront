<?php
/**
 * Plugin Name: Evy - Add-Ons Storefront
 * Plugin URI:  https://github.com/EvyOfficer
 * Description: Add-Ons for the Storefront theme, product visibility and related products.
 * Version:     1.0.0
 * Author:      EvyOfficer
 * Author URI:  https://github.com/EvyOfficer
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: evy-add-ons-storefront
 * Domain Path: /languages
 */

// ตรวจสอบให้แน่ใจว่าไม่ได้เรียกใช้ไฟล์นี้โดยตรง
defined('ABSPATH') || exit;

// กำหนดค่าคงที่ (Constants) สำหรับปลั๊กอินของคุณ
define( 'EVY_ADDONS_VERSION', '1.0.0' );
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
    return ['shop_manager'];
}

/**
 * ฟังก์ชันระบุหมวดหมู่สินค้าที่ต้องการจำกัดการเข้าถึง
 * เพิ่ม slug ของหมวดหมู่ที่ต้องการจำกัดสิทธิ์ที่นี่
 * เช่น ['short_term_accommodation', 'special_offers']
 */
function evy_get_restricted_categories_slugs() {
    return ['short_term_accommodation'];
}

/**
 * ฟังก์ชันเช็ก Role ที่ได้รับอนุญาตให้เข้าถึงหมวดหมู่ที่ถูกจำกัด (Restricted Category)
 * เพิ่ม Role ที่นี่ หากต้องการให้ Role อื่นๆ เห็นเฉพาะหมวดที่ถูกจำกัด
 * เช่น ['tenant', 'member']
 */
function evy_get_restricted_category_access_roles() {
    return ['tenant'];
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

/**
 * ตรวจสอบว่าผู้ใช้ปัจจุบันมีบทบาทที่สามารถเข้าถึงหมวดหมู่ที่ถูกจำกัดได้หรือไม่
 */
function evy_user_has_restricted_category_access() {
    if (!is_user_logged_in()) return false;
    $user = wp_get_current_user();
    return count(array_intersect($user->roles, evy_get_restricted_category_access_roles())) > 0;
}


// =================================================================================================
// ⚙️ ส่วนที่ 3: ฟังก์ชันการทำงานหลักของปลั๊กอิน (ไม่ต้องแก้ไข)
// =================================================================================================

// --- Start: Product Visibility Functions (จาก OM SAB White House Add-Ons Hide Product slug) ---

/**
 * ปรับ query สำหรับหน้าเว็บไซต์เพื่อควบคุมการมองเห็นสินค้า
 */
add_action('woocommerce_product_query', 'evy_filter_product_visibility_frontend');
function evy_filter_product_visibility_frontend($q) {
    if (is_admin()) return;

    // ถ้าผู้ใช้มีสิทธิ์เข้าถึงเต็มรูปแบบ ไม่ต้องกรองอะไรเลย
    if (evy_user_has_full_access()) return;

    $tax_query = $q->get('tax_query') ?: [];
    $restricted_categories = evy_get_restricted_categories_slugs(); // ดึงค่าจากฟังก์ชันด้านบน

    if (evy_user_has_restricted_category_access()) {
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

    $restricted_categories = evy_get_restricted_categories_slugs(); // ดึงค่าจากฟังก์ชันด้านบน

    if (evy_user_has_restricted_category_access()) {
        // ผู้ใช้มีสิทธิ์เข้าถึงหมวดหมู่ที่ถูกจำกัด: แสดงเฉพาะสินค้าในหมวดหมู่นั้น
        $args['tax_query'][] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $restricted_categories,
            'operator' => 'IN',
        ];
    } else {
        // ผู้ใช้ทั่วไป: ซ่อนสินค้าในหมวดหมู่ที่ถูกจำกัด
        $args['tax_query'][] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $restricted_categories,
            'operator' => 'NOT IN',
        ];
    }

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
