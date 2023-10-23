<?php
/**
 * Plugin Name: Redirect Analytics
 * Plugin URI: https://github.com/cnoid/redirect-analytics-wp
 * Description: Redirects users and collects analytics on link clicks.
 * Version: 0.9.4
 * Author: Mimmikk
 * Author URI: https://github.com/cnoid
 * License: GPLv3 or later
 */

// Create Custom Post Type for Redirect Links
function ra_create_post_type() {
    register_post_type('ra_redirects',
        array(
            'labels' => array(
                'name' => __('Redirects'), // Keep it as "Redirects"
                'singular_name' => __('Redirect')
            ),
            'public' => true,
            'rewrite' => false, // We handle the rewrite rules ourselves
            'show_ui' => true,
            'menu_icon' => 'dashicons-randomize', // Set a custom icon for the top-level menu
        )
    );
}
add_action('init', 'ra_create_post_type');

// Handle redirect logic with delay
function ra_redirect_logic() {
    if (is_singular('ra_redirects')) {
        $target_url = get_post_meta(get_the_ID(), 'ra_target_url', true);
        if ($target_url) {
            // Output the HTML head section with analytics scripts
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Redirecting...</title>";

            // Check if Google Analytics 4 checkbox is selected
            if (get_option('ra_use_google_analytics')) {
                $google_analytics_code = get_option('ra_google_analytics_code');
                if (!empty($google_analytics_code)) {
                    echo "<!-- Google Analytics 4 -->
                          <script async src='https://www.googletagmanager.com/gtag/js?id={$google_analytics_code}'></script>
                          <script>
                            window.dataLayer = window.dataLayer || [];
                            function gtag(){dataLayer.push(arguments);}
                            gtag('js', new Date());
                            gtag('config', '{$google_analytics_code}');
                          </script>";
                }
            }

            // Check if Umami checkbox is selected and tracker URL and Website ID are provided
            if (get_option('ra_use_umami_analytics')) {
                $umami_tracker_url = get_option('ra_umami_tracker_url');
                $umami_website_id = get_option('ra_umami_website_id');
                if (!empty($umami_tracker_url) && !empty($umami_website_id)) {
                    echo "<!-- Umami Analytics -->
                          <script async src='{$umami_tracker_url}/script.js' data-website-id='{$umami_website_id}'></script>";
                }
            }

            // Check if Custom checkbox is selected and custom script is provided
            if (get_option('ra_use_custom_analytics')) {
                $custom_script = get_option('ra_custom_analytics_script');
                if (!empty($custom_script)) {
                    echo "<!-- Custom Analytics -->
                          {$custom_script}";
                }
            }

            echo "</head><body>";
            
            echo "<script>
                    setTimeout(function(){
                        window.location.href = '{$target_url}';
                    }, 2000);
                  </script>";
            echo "<p>Redirecting in 2 seconds...</p>";
            echo "</body></html>";
            exit;
        }
    }
}
add_action('template_redirect', 'ra_redirect_logic');

// Custom rewrite rules for our desired redirect structure
function ra_rewrite_rules() {
    add_rewrite_rule(
        '^redirect/([^/]+)/([^/]+)/([^/]+)?$',
        'index.php?post_type=ra_redirects&name=$matches[3]',
        'top'
    );
}
add_action('init', 'ra_rewrite_rules');

//Admin menu start

// Modify the admin menu item to change the submenu label
function ra_redirect_menu() {
    add_submenu_page('edit.php?post_type=ra_redirects', 'Add Redirect', 'Add Redirect', 'manage_options', 'ra_add_redirect', 'ra_redirect_form');
    remove_submenu_page('edit.php?post_type=ra_redirects', 'post-new.php?post_type=ra_redirects');
}
add_action('admin_menu', 'ra_redirect_menu');
// Admin menu end

function ra_redirect_form() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['partner_name'], $_POST['redirect_url'], $_POST['link_id'], $_POST['note'])) {
        $partner_name = sanitize_text_field($_POST['partner_name']);
        $redirect_url = esc_url_raw($_POST['redirect_url']);
        $link_id = sanitize_key($_POST['link_id']);
        $note = sanitize_text_field($_POST['note']);

        $post_id = wp_insert_post([
            'post_title'  => $partner_name,
            'post_name'   => $link_id,
            'post_type'   => 'ra_redirects',
            'post_status' => 'publish',
        ]);

        if ($post_id) {
            update_post_meta($post_id, 'ra_target_url', $redirect_url);
            update_post_meta($post_id, 'ra_link_id', $link_id);
            update_post_meta($post_id, 'ra_note', $note);

            $parsed_url = parse_url($redirect_url);
            $host = $parsed_url['host'];
            $redirect_link = home_url("/redirect/{$partner_name}/{$host}/{$link_id}");
            echo "<div class='updated'><p>Generated Redirect Link: <a href='{$redirect_link}'>{$redirect_link}</a></p></div>";
        } else {
            echo "<div class='error'><p>Failed to generate the redirect link. Please try again.</p></div>";
        }
    }

    ?>
    <div class="wrap">
        <h2>Add Redirect</h2>
        <form method="post" action="">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="partner_name">Partner Name:</label></th>
                        <td><input name="partner_name" type="text" id="partner_name" value="" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="redirect_url">Redirect URL:</label></th>
                        <td><input name="redirect_url" type="text" id="redirect_url" value="" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="link_id">Link ID:</label></th>
                        <td><input name="link_id" type="text" id="link_id" value="" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="note">Note:</label></th>
                        <td><input name="note" type="text" id="note" value="" class="regular-text"></td>
                    </tr>
                </tbody>
            </table>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Generate Redirect Link"></p>
            <p>Usage: <br><b>Partner Name</b> is the name of the partner/website it's being used on. <br><b>Redirect URL</b> is the URL to which the link is redirecting.<br><b>Link ID</b> is a unique identifier for the redirect link, such as a news article name or shortened partner URL.</p>
        </form>
    </div>
    <?php
}

// Add a new "Note" and "Redirect URL" column to the admin overview of our custom post type
function ra_add_redirect_columns($columns) {
    $new_columns = array(
        'ra_redirect_note' => 'Note',
        'ra_redirect_link' => 'Redirect Link',
        'ra_redirect_url'  => 'Redirect URL', // New column for Redirect URL
    );

    return array_merge($columns, $new_columns);
}
add_filter('manage_ra_redirects_posts_columns', 'ra_add_redirect_columns');

// Populate the new "Redirect Link" and "Note" columns with the correct data
function ra_redirect_custom_column($column, $post_id) {
    if ($column == 'ra_redirect_link') {
        $partner_name = get_the_title($post_id);
        $redirect_url = get_post_meta($post_id, 'ra_target_url', true);
        $link_id = get_post_meta($post_id, 'ra_link_id', true);

        $parsed_url = parse_url($redirect_url);
        $host = $parsed_url['host'];
        $redirect_link = home_url("/redirect/{$partner_name}/{$host}/{$link_id}");

        echo "<a href='{$redirect_link}' target='_blank'>{$redirect_link}</a>";
    } elseif ($column == 'ra_redirect_note') {
        $note = get_post_meta($post_id, 'ra_note', true);
        echo $note;
    } elseif ($column == 'ra_redirect_url') { // Populate Redirect URL
        $redirect_url = get_post_meta($post_id, 'ra_target_url', true);
        echo "<a href='{$redirect_url}' target='_blank'>{$redirect_url}</a>";
    }
}
add_action('manage_ra_redirects_posts_custom_column', 'ra_redirect_custom_column', 10, 2);

// Modify the post type labels for a cleaner admin interface
function ra_change_post_type_labels($labels) {
    $labels->name = 'Redirects';
    $labels->singular_name = 'Redirect';
    $labels->add_new = 'Add Redirect';
    $labels->add_new_item = 'Add Redirect';
    $labels->edit_item = 'Edit Redirect';
    $labels->new_item = 'New Redirect';
    $labels->view_item = 'View Redirect';
    $labels->search_items = 'Search Redirects';
    $labels->not_found = 'No redirects found';
    $labels->not_found_in_trash = 'No redirects found in Trash';
    $labels->all_items = 'All Redirects';

    return $labels;
}
add_filter('post_type_labels_ra_redirects', 'ra_change_post_type_labels');

// Add options panel for analytics settings
function ra_analytics_settings() {
    add_submenu_page('edit.php?post_type=ra_redirects', 'Analytics Provider Settings', 'Analytics Provider', 'manage_options', 'ra_analytics_provider', 'ra_analytics_provider_settings');
}
add_action('admin_menu', 'ra_analytics_settings');

// Render analytics settings page
function ra_analytics_provider_settings() {
    if (isset($_POST['submit'])) {
        update_option('ra_use_google_analytics', isset($_POST['analytics_google']));
        update_option('ra_google_analytics_code', sanitize_text_field($_POST['google_analytics_code']));

        update_option('ra_use_umami_analytics', isset($_POST['analytics_umami']));
        update_option('ra_umami_tracker_url', esc_url($_POST['umami_tracker_url']));
        update_option('ra_umami_website_id', sanitize_text_field($_POST['umami_website_id']));

        update_option('ra_use_custom_analytics', isset($_POST['analytics_custom']));
        update_option('ra_custom_analytics_script', wp_kses_post($_POST['custom_script']));

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    ?>
    <div class="wrap">
        <h2>Analytics Provider Settings</h2>
        <form method="post" action="">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="analytics_google">Google Analytics:</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="analytics_google" id="analytics_google" value="1" <?php checked(get_option('ra_use_google_analytics'), true); ?>>
                                Activate
                            </label>
                            <br>
                            <label for="google_analytics_code">Google Analytics Code:</label><br>
                            <input name="google_analytics_code" type="text" id="google_analytics_code" placeholder="G-123AD456" value="<?php echo esc_attr(get_option('ra_google_analytics_code')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="analytics_umami">Umami Analytics:</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="analytics_umami" id="analytics_umami" value="1" <?php checked(get_option('ra_use_umami_analytics'), true); ?>>
                                Activate
                            </label>
                            <br>
                            <label for="umami_tracker_url">Tracker URL:</label><br>
                            <input name="umami_tracker_url" type="text" id="umami_tracker_url" placeholder="https://analytics.umami.com" value="<?php echo esc_url(get_option('ra_umami_tracker_url')); ?>" class="regular-text"><br>
                            <label for="umami_website_id">Website ID:</label><br>
                            <input name="umami_website_id" type="text" id="umami_website_id" placeholder="53484c4f-l991-12qw-adb3-c3a6c10b0be0" value="<?php echo esc_attr(get_option('ra_umami_website_id')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="analytics_custom">Custom Analytics:</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="analytics_custom" id="analytics_custom" value="1" <?php checked(get_option('ra_use_custom_analytics'), true); ?>>
                                Activate
                            </label>
                            <br>
                            <label for="custom_script">Custom Script:</label><br>
                            <textarea name="custom_script" id="custom_script" rows="4" cols="50" placeholder="<script>Pendo.. Semrush..</script>"><?php echo esc_textarea(get_option('ra_custom_analytics_script')); ?></textarea>
                            <p>Note: You can add any custom tracker script here. Include your script tags.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings"></p>
        </form>
    </div>
    <?php
}

// Flush rewrite rules when the plugin is activated
function ra_plugin_activation() {
    ra_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ra_plugin_activation');

// Flush rewrite rules when the plugin is deactivated
function ra_plugin_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'ra_plugin_deactivation');
?>
