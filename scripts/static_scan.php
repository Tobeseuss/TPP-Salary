<?php
/**
 * اسکن استاتیک پلاگین: پیدا کردن فراخوانی توابعی که نه در PHP هستند،
 * نه در وردپرس (لیست رسمی)، نه در خود پلاگین تعریف شده‌اند.
 */
$plugin_dir = '/home/z/my-project/build/tpp_salary';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_dir));
$php_files = [];
foreach ($rii as $f) {
    if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
        $php_files[] = $f->getPathname();
    }
}
sort($php_files);

// ---------- 1. توابع تعریف‌شده در خود پلاگین ----------
$own_functions = [];
foreach ($php_files as $file) {
    $src = file_get_contents($file);
    if (preg_match_all('/\bfunction\s+([a-zA-Z_]\w*)\s*\(/', $src, $m)) {
        foreach ($m[1] as $fn) {
            $own_functions[strtolower($fn)] = $file;
        }
    }
}

// ---------- 2. لیست توابع وردپرس (پرکاربردها) ----------
$wp_funcs = explode("\n", strtolower(trim(<<<'WPF'
__
_n
_nx
_x
_e
absint
add_action
add_editor_style
add_filter
add_management_page
add_menu_page
add_meta_box
add_meta_boxes
add_network_option
add_option
add_query_arg
add_role
add_settings_error
add_settings_field
add_settings_section
add_shortcode
add_site_option
add_submenu_page
add_theme_support
add_user_meta
add_users_page
admin_url
allowed_tags
apply_filters
array_key_first
array_key_last
author_can
bloginfo
build_query
cache_javascript_headers
cancel_comment_reply_link
capital_P_dangit
check_admin_referer
check_ajax_referer
check_upload_size
checked
clean_pre
comment_author
comment_class
comment_date
comment_form
comment_id_fields
comment_reply_link
comment_text
comments_number
comments_popup_link
comments_popup_script
comments_rss_link
convert_chars
convert_smilies
current_action
current_filter
current_theme_supports
current_time
current_user_can
current_user_can_for_blog
date_i18n
date_i18n_jalali
dbDelta
delete_blog_option
delete_meta
delete_network_option
delete_option
delete_post_meta
delete_site_option
delete_site_transient
delete_transient
delete_user_meta
disabled
do_action
do_action_ref_array
do_robots
do_settings_sections
do_shortcode
doing_action
doing_filter
doing_shortcode
download_url
drop_index
edit_bookmark_link
edit_comment_link
edit_post_link
edit_tag_link
esc_attr
esc_attr__
esc_attr_e
esc_attr_x
esc_html
esc_html__
esc_html_e
esc_html_x
esc_js
esc_sql
esc_textarea
esc_url
esc_url_raw
get_adjacent_post
get_all_category_ids
get_archives_link
get_attached_file
get_avatar
get_bloginfo
get_bloginfo_rss
get_bookmark
get_bookmark_field
get_bookmarks
get_calendar
get_categories
get_category
get_category_by_path
get_category_link
get_category_parents
get_cat_ID
get_cat_name
get_comment
get_comment_author
get_comment_date
get_comment_link
get_comment_meta
get_comment_text
get_comments
get_current_blog_id
get_current_network_id
get_current_site
get_current_user_id
get_date_from_gmt
get_date_template
get_day_link
get_delete_post_link
get_edit_post_link
get_footer
get_gmt_from_date
get_header
get_home_path
get_home_url
get_locale
get_meta
get_month_link
get_network_option
get_num_queries
get_option
get_page
get_page_by_title
get_page_link
get_page_template
get_pages
get_permalink
get_post
get_post_ancestors
get_post_field
get_post_format
get_post_meta
get_post_parent
get_post_status
get_post_thumbnail_id
get_post_type
get_posts
get_query_var
get_role
get_search_form
get_search_query
get_shortcode_regex
get_sidebar
get_site
get_site_option
get_site_transient
get_site_url
get_stylesheet
get_stylesheet_directory
get_stylesheet_uri
get_tag
get_tag_link
get_tags
get_template
get_template_directory
get_template_directory_uri
get_temp_dir
get_term
get_term_by
get_term_children
get_term_link
get_term_meta
get_terms
get_theme_file_path
get_theme_file_uri
get_the_author
get_the_author_meta
get_the_category
get_the_content
get_the_date
get_the_excerpt
get_the_ID
get_the_permalink
get_the_post_thumbnail
get_the_post_thumbnail_url
get_the_tag_list
get_the_tags
get_the_terms
get_the_time
get_the_title
get_transient
get_udata
get_upload_dir
get_userdata
get_user_by
get_user_meta
get_users
get_year_link
has_category
has_excerpt
has_filter
has_post_thumbnail
has_shortcode
has_tag
has_term
hash_equals
header_image
home_url
includes_url
is_admin
is_admin_bar_showing
is_archive
is_attachment
is_blog_installed
is_category
is_date
is_day
is_email
is_feed
is_front_page
is_home
is_main_network
is_main_site
is_month
is_multisite
is_network_admin
is_page
is_page_template
is_paged
is_plugin_active
is_post_type_archive
is_preview
is_rtl
is_search
is_single
is_singular
is_ssl
is_sticky
is_tag
is_tax
is_taxonomy
is_time
is_user_logged_in
is_wp_error
is_year
load_plugin_textdomain
load_textdomain
load_template
locale_stylesheet
mb_substr
media_handle_upload
network_admin_url
network_home_url
network_site_url
next_image_link
next_posts_link
number_format_i18n
paginate_comments_links
paginate_links
permalink_anchor
plugins_url
post_type_archive_title
post_password_required
posts_nav_link
prepend_attachment
previous_image_link
previous_posts_link
register_activation_hook
register_deactivation_hook
register_nav_menu
register_nav_menus
register_post_type
register_rest_field
register_setting
register_sidebar
register_taxonomy
register_taxonomy_for_object_type
register_widget
remove_action
remove_filter
remove_menu_page
remove_meta_box
remove_post_type_support
remove_query_arg
remove_role
remove_shortcode
remove_theme_support
remove_user_meta
rest_url
sanitize_email
sanitize_file_name
sanitize_html_class
sanitize_key
sanitize_mime_type
sanitize_option
sanitize_sql_orderby
sanitize_term
sanitize_text_field
sanitize_textarea_field
sanitize_title
sanitize_title_for_query
sanitize_title_with_dashes
sanitize_user
set_post_thumbnail
set_post_type
set_screen_option
set_site_transient
set_transient
settings_fields
shortcode_atts
shortcode_exists
shortcode_parse_atts
shortcode_unautop
shortcode_empty
single_cat_title
single_month_title
single_post_title
single_tag_title
single_term_title
site_url
status_header
submit_button
the_ID
the_archive_description
the_archive_title
the_attachment_link
the_author
the_author_link
the_author_meta
the_author_posts_link
the_category
the_content
the_content_feed
the_date
the_date_xml
the_excerpt
the_feed_link
the_footer
the_header_image_tag
the_header_video_url
the_meta
the_permalink
the_post
the_post_thumbnail
the_post_thumbnail_url
the_posts_pagination
the_posts_navigation
the_search_query
the_shortlink
the_tags
the_terms
the_time
the_title
the_title_attribute
the_title_rss
the_widget
trailingslashit
unescape_invalid_shortcodes
unregister_nav_menu
unregister_sidebar
unregister_taxonomy
unregister_taxonomy_for_object_type
unregister_widget
untrailingslashit
update_blog_option
update_home_siteurl
update_meta
update_network_option
update_option
update_post_meta
update_post_thumbnail_cache
update_site_option
update_user_meta
update_user_option
update_usermeta
user_can
user_trailingslashit
validate_current_theme
validate_file
wp_add_inline_script
wp_add_inline_style
wp_admin_css
wp_ajax_
wp_basename
wp_cache_add
wp_cache_delete
wp_cache_flush
wp_cache_get
wp_cache_set
wp_check_filetype
wp_clear_scheduled_hook
wp_create_nonce
wp_create_user
wp_cron
wp_delete_file
wp_delete_post
wp_deregister_script
wp_deregister_style
wp_die
wp_dropdown_categories
wp_dropdown_pages
wp_dropdown_users
wp_enqueue_media
wp_enqueue_script
wp_enqueue_style
wp_generate_password
wp_get_archives
wp_get_attachment_image
wp_get_attachment_image_src
wp_get_attachment_image_url
wp_get_attachment_url
wp_get_current_user
wp_get_post_categories
wp_get_post_tags
wp_get_post_terms
wp_get_referer
wp_get_theme
wp_get_upload_dir
wp_handle_sideload
wp_handle_upload
wp_head
wp_insert_category
wp_insert_post
wp_insert_term
wp_insert_user
wp_is_mobile
wp_json_encode
wp_link_pages
wp_list_authors
wp_list_bookmarks
wp_list_categories
wp_list_comments
wp_list_pages
wp_list_pluck
wp_localize_script
wp_login_url
wp_loginout
wp_mail
wp_mime_type_icon
wp_nav_menu
wp_next_scheduled
wp_nonce_field
wp_nonce_url
wp_normalize_path
wp_parse_args
wp_parse_url
wp_redirect
wp_register_script
wp_register_style
wp_reset_postdata
wp_reset_query
wp_schedule_event
wp_schedule_single_event
wp_script_is
wp_send_json
wp_send_json_error
wp_send_json_success
wp_set_auth_cookie
wp_set_current_user
wp_set_password
wp_set_post_categories
wp_set_post_tags
wp_set_post_terms
wp_shortlink_header
wp_shortlink_wp_head
wp_style_is
wp_style_loader_src
wp_title
wp_trim_excerpt
wp_trim_words
wp_unschedule_event
wp_unslash
wp_upload_dir
wp_verify_nonce
wptexturize
WP_Error
WP_User
WP_Query
get_charset_collate
WPF
)));
$wp_funcs = array_filter($wp_funcs);

// ---------- 3. توابع PHP بuiltin ----------
$php_funcs = array_flip(array_map('strtolower', get_defined_functions()['internal']));

// ---------- 4. اسکن فراخوانی‌ها ----------
$whitelist = ['print_r','var_dump']; // هشدار نیست
$issues = [];
foreach ($php_files as $file) {
    if (strpos($file, '/lib/') !== false || strpos($file, '/font/') !== false) continue; // کتابخانه tFPDF خارج از مسئولیت
    $src = file_get_contents($file);
    $tokens = @token_get_all($src);
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_STRING && isset($tokens[$i+1]) && $tokens[$i+1] === '(') {
            $name = strtolower($t[1]);
            // فقط نام‌های ساده (بدون namespace / -> / ::)
            $prev = $tokens[$i-1] ?? null;
            if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NS_SEPARATOR, T_FUNCTION, T_NEW], true)) continue;
            if (is_array($prev) && $prev[0] === T_STRING) continue;
            if (in_array($name, $wp_funcs) || isset($php_funcs[$name]) || isset($own_functions[$name]) || in_array($name, $whitelist)) continue;
            $line = $t[2];
            $issues[] = sprintf("%s:%d  ->  %s()", str_replace($plugin_dir.'/', '', $file), $line, $t[1]);
        }
    }
}

// ---------- 5. متدهای استاتیک کلاس‌های پلاگین ----------
$own_classes = [];
foreach ($php_files as $file) {
    $src = file_get_contents($file);
    if (preg_match_all('/\bclass\s+(TPP_\w+)/', $src, $m)) {
        foreach ($m[1] as $c) $own_classes[] = $c;
    }
}
// کل تعریف متدهای هر کلاس
$class_methods = [];
foreach ($php_files as $file) {
    $src = file_get_contents($file);
    if (preg_match_all('/class\s+(TPP_\w+)(.*?)\n\}/s', $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            preg_match_all('/function\s+(\w+)\s*\(/', $mm[2], $fm);
            $class_methods[$mm[1]] = $fm[1];
        }
    }
}
// فراخوانی‌های استاتیک TPP_X::method
$static_calls = [];
foreach ($php_files as $file) {
    if (strpos($file, '/lib/') !== false) continue;
    $src = file_get_contents($file);
    if (preg_match_all('/(TPP_\w+)::(\w+)\s*\(/', $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $static_calls["$mm[1]::$mm[2]"][] = str_replace($plugin_dir.'/', '', $file);
        }
    }
}
echo "=== CLASSES ===\n".implode(', ', $own_classes)."\n\n";
echo "=== STATIC CALLS CHECK ===\n";
foreach ($static_calls as $k => $files) {
    list($cls, $mth) = explode('::', $k);
    if (in_array($cls, $own_classes)) {
        if (!isset($class_methods[$cls]) || !in_array($mth, $class_methods[$cls] ?? [], true)) {
            echo "MISSING METHOD: $k  <- ".implode(', ', $files)."\n";
        }
    } else {
        echo "UNKNOWN CLASS: $cls <- ".implode(', ', $files)."\n";
    }
}
echo "\n=== FUNCTION CALLS NOT IN WP/PHP/PLUGIN ===\n";
if (!$issues) echo "(none)\n";
foreach (array_unique($issues) as $iss) echo "$iss\n";
echo "\nDone. Files scanned: ".count($php_files)."\n";
