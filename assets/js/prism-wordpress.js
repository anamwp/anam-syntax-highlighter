/**
 * Anam Syntax Highlighter — WordPress custom Prism grammar.
 *
 * Extends Prism's PHP grammar to highlight WordPress-specific
 * functions, classes, hooks, and constants.
 */
(function () {
	'use strict';

	if (typeof Prism === 'undefined' || typeof Prism.languages.php === 'undefined') {
		return;
	}

	/* -----------------------------------------------------------------------
	 * WordPress-specific tokens injected into the PHP grammar.
	 * ----------------------------------------------------------------------- */

	var wpFunctions = /\b(?:add_action|add_filter|remove_action|remove_filter|apply_filters|do_action|do_action_ref_array|apply_filters_ref_array|has_action|has_filter|current_filter|doing_action|doing_filter|add_shortcode|remove_shortcode|do_shortcode|shortcode_atts|wp_enqueue_script|wp_enqueue_style|wp_register_script|wp_register_style|wp_dequeue_script|wp_dequeue_style|wp_localize_script|wp_add_inline_script|wp_add_inline_style|wp_script_is|wp_style_is|get_option|update_option|add_option|delete_option|get_site_option|update_site_option|get_post|get_posts|get_post_meta|update_post_meta|add_post_meta|delete_post_meta|get_post_field|get_post_status|get_post_type|get_post_types|get_the_title|get_the_content|get_the_excerpt|get_the_permalink|get_the_ID|get_the_date|get_the_modified_date|get_the_author|get_the_tags|get_the_category|the_title|the_content|the_excerpt|the_permalink|the_ID|the_date|the_author|the_tags|the_category|have_posts|the_post|in_the_loop|get_template_part|get_header|get_footer|get_sidebar|wp_head|wp_footer|body_class|post_class|language_attributes|bloginfo|get_bloginfo|home_url|site_url|admin_url|plugin_url|plugin_dir_url|plugin_dir_path|get_stylesheet_directory_uri|get_template_directory_uri|get_stylesheet_directory|get_template_directory|wp_nonce_field|wp_nonce_url|wp_verify_nonce|check_admin_referer|is_user_logged_in|current_user_can|wp_get_current_user|get_current_user_id|get_current_blog_id|wp_login_url|wp_logout_url|is_singular|is_single|is_page|is_home|is_front_page|is_archive|is_category|is_tag|is_tax|is_search|is_404|is_admin|is_multisite|get_terms|get_term|get_term_by|get_term_meta|update_term_meta|get_categories|get_tags|get_taxonomies|register_taxonomy|register_post_type|get_permalink|add_rewrite_rule|flush_rewrite_rules|wp_redirect|wp_safe_redirect|wp_die|wp_send_json|wp_send_json_success|wp_send_json_error|wp_remote_get|wp_remote_post|wp_remote_request|wp_safe_remote_get|wp_safe_remote_post|wp_cache_get|wp_cache_set|wp_cache_delete|wp_cache_flush|wp_insert_post|wp_update_post|wp_delete_post|wp_trash_post|wp_insert_user|wp_update_user|wp_delete_user|get_user_by|get_user_meta|update_user_meta|add_user_meta|delete_user_meta|sanitize_text_field|sanitize_textarea_field|sanitize_email|sanitize_url|sanitize_file_name|sanitize_key|sanitize_html_class|sanitize_title|esc_html|esc_attr|esc_url|esc_js|esc_textarea|esc_html_e|esc_attr_e|esc_url_raw|wp_kses|wp_kses_post|wp_strip_all_tags|absint|wp_parse_args|wp_parse_url|wp_array_slice_assoc|maybe_serialize|maybe_unserialize|wp_json_encode|wp_sprintf|number_format_i18n|size_format|__(?=\s*\()|_e(?=\s*\()|_n(?=\s*\()|_x(?=\s*\()|_ex(?=\s*\()|_nx(?=\s*\()|esc_html__(?=\s*\()|esc_attr__(?=\s*\()|esc_html_x(?=\s*\()|esc_attr_x(?=\s*\())\b/;

	var wpClasses = /\b(?:WP_Query|WP_Error|WP_User|WP_Post|WP_Term|WP_Comment|WP_Roles|WP_Role|WP_Taxonomy|WP_Post_Type|WP_Screen|WP_Widget|WP_REST_Request|WP_REST_Response|WP_REST_Server|WP_REST_Controller|WP_Customize_Manager|WP_Customize_Control|WP_Customize_Section|WP_Customize_Panel|WP_Customize_Setting|WP_Hook|WP_Meta_Query|WP_Tax_Query|WP_Date_Query|WP_Comment_Query|WP_User_Query|WP_Network|WP_Site|WP_List_Table|Walker|Walker_Page|Walker_Category|Walker_Nav_Menu|wpdb)\b/;

	var wpConstants = /\b(?:ABSPATH|WPINC|WPMU_PLUGIN_DIR|WPMU_PLUGIN_URL|WP_PLUGIN_DIR|WP_PLUGIN_URL|WP_CONTENT_DIR|WP_CONTENT_URL|WP_ADMIN|TEMPLATEPATH|STYLESHEETPATH|DB_NAME|DB_USER|DB_HOST|DB_CHARSET|DB_COLLATE|WP_DEBUG|WP_DEBUG_LOG|WP_DEBUG_DISPLAY|SCRIPT_DEBUG|WP_CACHE|WP_MEMORY_LIMIT|WP_MAX_MEMORY_LIMIT|FORCE_SSL_ADMIN|FORCE_SSL_LOGIN|WP_POST_REVISIONS|WP_CRON_LOCK_TIMEOUT|COOKIEPATH|SITECOOKIEPATH|COOKIE_DOMAIN|COOKIEHASH|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY|AUTH_SALT|SECURE_AUTH_SALT|LOGGED_IN_SALT|NONCE_SALT|WP_HOME|WP_SITEURL|SUNRISE|MULTISITE|SUBDOMAIN_INSTALL|DOMAIN_CURRENT_SITE|PATH_CURRENT_SITE|SITE_ID_CURRENT_SITE|BLOG_ID_CURRENT_SITE|WP_ALLOW_MULTISITE|WP_CLI)\b/;

	Prism.languages.insertBefore('php', 'keyword', {
		'wp-function': {
			pattern: wpFunctions,
			alias: 'function'
		},
		'wp-class': {
			pattern: wpClasses,
			alias: 'class-name'
		},
		'wp-constant': {
			pattern: wpConstants,
			alias: 'constant'
		}
	});

	/* -----------------------------------------------------------------------
	 * Register "wordpress" as an alias for "php" so that
	 * <code class="language-wordpress"> works out of the box.
	 * ----------------------------------------------------------------------- */
	Prism.languages.wordpress = Prism.languages.extend('php', {});
	Prism.languages.insertBefore('wordpress', 'keyword', {
		'wp-function': {
			pattern: wpFunctions,
			alias: 'function'
		},
		'wp-class': {
			pattern: wpClasses,
			alias: 'class-name'
		},
		'wp-constant': {
			pattern: wpConstants,
			alias: 'constant'
		}
	});

}());
