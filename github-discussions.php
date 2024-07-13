<?php
/*
Plugin Name: GitHub Discussions Fetcher
Description: Fetches and stores discussions from specified repositories within a GitHub organization into custom post types, categorized by repository. Configure settings under Settings -> GitHub Discussions and dynamically generate a GraphQL query based on settings.
Version: 1.2
Author: Courtney Robertson
Author URI: https://courtneyr.dev
*/

// Register hooks for activation and deactivation
register_activation_hook(__FILE__, 'github_discussion_activation');
register_deactivation_hook(__FILE__, 'github_discussion_deactivation');

// Hook into WordPress to add the admin menu, initialize settings, and register custom post types and taxonomies
add_action('admin_menu', 'github_discussions_admin_menu');
add_action('admin_init', 'github_discussions_settings_init');
add_action('init', 'github_create_post_type_and_taxonomy');
add_action('template_redirect', 'redirect_github_discussion_posts');
add_action('admin_init', 'handle_run_now_action');

function github_discussion_activation()
{
    github_create_post_type_and_taxonomy();
    flush_rewrite_rules();
}

function github_discussion_deactivation()
{
    flush_rewrite_rules();
}

function github_create_post_type_and_taxonomy()
{
    register_post_type('github_discussion', array(
        'labels' => array(
            'name' => __('GitHub Discussions'),
            'singular_name' => __('GitHub Discussion')
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'github-discussions', 'feeds' => true),
        'supports' => array('title', 'editor', 'comments'),
        'show_in_rest' => true,
        'show_in_graphql' => true,
        'graphql_single_name' => 'GitHubDiscussion',
        'graphql_plural_name' => 'GitHubDiscussions'
    ));

    // Your taxonomy registration remains the same
    register_taxonomy('github_repo', ['github_discussion'], array(
        'label' => __('GitHub Repositories'),
        'rewrite' => array('slug' => 'github-repositories'),
        'hierarchical' => true,
        'show_in_rest' => true,
        'show_in_graphql' => true,
        'graphql_single_name' => 'GitHubRepository',
        'graphql_plural_name' => 'GitHubRepositories'
    ));
}


function github_discussions_admin_menu()
{
    add_options_page('GitHub Discussions Settings', 'GitHub Discussions', 'manage_options', 'github-discussions-settings', 'github_discussions_settings_page');
}

function github_discussions_settings_init()
{
    register_setting('github_discussions', 'github_discussions_options', 'github_discussions_options_sanitize');

    add_settings_section(
        'github_discussions_section',
        __('GitHub Discussions Settings', 'github-discussions'),
        'github_discussions_section_callback',
        'github_discussions'
    );

    add_settings_field('github_access_token', __('GitHub Access Token', 'github-discussions'), 'github_discussions_access_token_render', 'github_discussions', 'github_discussions_section');
    add_settings_field('github_organization', __('GitHub Organization', 'github-discussions'), 'github_discussions_organization_render', 'github_discussions', 'github_discussions_section');
    add_settings_field('github_repositories', __('Specific Repositories (comma separated)', 'github-discussions'), 'github_discussions_repositories_render', 'github_discussions', 'github_discussions_section');
    add_settings_field('github_fetch_schedule', __('Fetch Schedule', 'github-discussions'), 'github_discussions_fetch_schedule_render', 'github_discussions', 'github_discussions_section');
    add_settings_field('github_fetch_count', __('Number of Items to Fetch', 'github-discussions'), 'github_discussions_fetch_count_render', 'github_discussions', 'github_discussions_section');
    add_settings_field('github_enable_redirect', __('Enable Redirect to GitHub', 'github-discussions'), 'github_discussions_enable_redirect_render', 'github_discussions', 'github_discussions_section');
}

function github_discussions_section_callback()
{
    echo __('Configure the settings for the GitHub Discussions plugin.', 'github-discussions');
}

function github_discussions_access_token_render()
{
    $options = get_option('github_discussions_options');
    echo '<input type="text" name="github_discussions_options[github_access_token]" value="' . esc_attr($options['github_access_token'] ?? '') . '">';
}

function github_discussions_organization_render()
{
    $options = get_option('github_discussions_options');
    echo '<input type="text" name="github_discussions_options[github_organization]" value="' . esc_attr($options['github_organization'] ?? '') . '">';
}

function github_discussions_repositories_render()
{
    $options = get_option('github_discussions_options');
    echo '<input type="text" name="github_discussions_options[github_repositories]" value="' . esc_attr($options['github_repositories'] ?? '') . '">';
}

function github_discussions_fetch_schedule_render()
{
    $options = get_option('github_discussions_options');
    echo '<select name="github_discussions_options[github_fetch_schedule]">';
    $schedules = ['hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
    foreach ($schedules as $key => $label) {
        $selected = isset($options['github_fetch_schedule']) && $options['github_fetch_schedule'] === $key ? 'selected' : '';
        echo "<option value='$key' $selected>$label</option>";
    }
    echo '</select>';
}

function github_discussions_fetch_count_render()
{
    $options = get_option('github_discussions_options');
    echo '<input type="number" name="github_discussions_options[github_fetch_count]" value="' . esc_attr($options['github_fetch_count'] ?? '10') . '">';
}

function github_discussions_enable_redirect_render()
{
    $options = get_option('github_discussions_options');
    $checked = isset($options['github_enable_redirect']) && $options['github_enable_redirect'] ? 'checked' : '';
    echo '<input type="checkbox" name="github_discussions_options[github_enable_redirect]" ' . $checked . ' value="1">';
}

function github_discussions_settings_page()
{
    $graphqlQuery = generate_graphql_query();
    echo '<div class="wrap">';
    echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
    echo '<form action="options.php" method="post">';
    settings_fields('github_discussions');
    do_settings_sections('github_discussions');
    submit_button('Save Settings');
    echo '</form>';
    echo '<form method="post">';
    wp_nonce_field('run_now_action', 'run_now_nonce');
    submit_button('Run Now', 'primary', 'run_now');
    echo '</form>';
    echo '<h2>GraphQL Query Based on Your Settings</h2>';
    echo '<textarea readonly rows="5" cols="70" id="graphqlQuery">' . esc_textarea($graphqlQuery) . '</textarea>';
    echo '<button onclick="copyQuery()">Copy Query</button>';
    echo '<script>';
    echo 'function copyQuery() {';
    echo 'var copyText = document.getElementById("graphqlQuery");';
    echo 'copyText.select();';
    echo 'document.execCommand("copy");';
    echo 'alert("Copied the query to the clipboard.");';
    echo '}';
    echo '</script>';
    echo '</div>';
}

function generate_graphql_query()
{
    $options = get_option('github_discussions_options');
    $fetchCount = min($options['github_fetch_count'] ?? 10, 100); // Limiting to 100 as per API restriction

    return <<<QUERY
query GetGitHubDiscussions {
  gitHubDiscussions(first: $fetchCount) {
    edges {
      node {
        id
        title
        content
        gitHubRepositories {  
          edges {
            node {
              name
            }
          }
        }
      }
    }
  }
}
QUERY;
}

function fetch_and_store_discussions()
{
    $options = get_option('github_discussions_options');
    $token = $options['github_access_token'];
    $organization = $options['github_organization'];
    $repos = explode(',', $options['github_repositories']);

    foreach ($repos as $repo) {
        $repo = trim($repo);
        if (empty($repo)) continue;

        $query = <<<QUERY
query {
  repository(owner: "{$organization}", name: "{$repo}") {
    discussions(first: 10) {
      nodes {
        title
        url
        bodyText
      }
    }
  }
}
QUERY;

        $response = wp_remote_post('https://api.github.com/graphql', [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode(['query' => $query])
        ]);

        if (is_wp_error($response)) {
            error_log('Error fetching GitHub discussions: ' . $response->get_error_message());
            continue;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode != 200) {
            error_log("Failed to fetch discussions, GitHub API returned status code: {$statusCode}");
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            error_log('Error decoding the GitHub response: ' . $body);
            continue;
        }

        if (isset($data['data']['repository']['discussions']['nodes'])) {
            foreach ($data['data']['repository']['discussions']['nodes'] as $discussion) {
                $post_data = [
                    'post_title'   => sanitize_text_field($discussion['title']),
                    'post_content' => sanitize_textarea_field($discussion['bodyText']),
                    'post_status'  => 'publish',
                    'post_type'    => 'github_discussion',
                ];

                $post_id = wp_insert_post($post_data);

                if (!is_wp_error($post_id)) {
                    update_post_meta($post_id, 'github_url', esc_url_raw($discussion['url']));
                } else {
                    error_log('Failed to insert post: ' . $post_id->get_error_message());
                }
            }
        } else {
            error_log('No discussions found or unexpected data structure.');
        }
    }
}

function redirect_github_discussion_posts()
{
    if (is_singular('github_discussion') && !empty(get_option('github_discussions_options')['github_enable_redirect'])) {
        $github_url = get_post_meta(get_the_ID(), 'github_discussion_url', true);
        if (!empty($github_url)) {
            wp_redirect($github_url);
            exit;
        }
    }
}

function handle_run_now_action()
{
    // Check if the 'run now' button was pressed
    if (isset($_POST['run_now']) && check_admin_referer('run_now_action', 'run_now_nonce')) {
        // Perform the action, for example, fetch and store discussions
        fetch_and_store_discussions();

        // Optionally add an admin notice
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>GitHub Discussions fetched and stored successfully.</p></div>';
        });
    }
}
add_action('admin_init', 'handle_run_now_action');

function schedule_github_fetch()
{
    if (!wp_next_scheduled('github_fetch_discussions')) {
        wp_schedule_event(time(), 'daily', 'github_fetch_discussions');  // Modify as needed based on settings
    }
}

function clear_scheduled_fetch()
{
    $timestamp = wp_next_scheduled('github_fetch_discussions');
    wp_unschedule_event($timestamp, 'github_fetch_discussions');
}

function github_discussions_options_sanitize($input)
{
    return array_map('sanitize_text_field', $input);
}

function github_discussions_shortcode($atts)
{
    $options = get_option('github_discussions_options');
    $fetchCount = $options['github_fetch_count'] ?? 10;

    $query = <<<QUERY
query GetGitHubDiscussions {
  gitHubDiscussions(first: $fetchCount) {
    edges {
      node {
        title
        url
      }
    }
  }
}
QUERY;

    $response = wp_remote_post('https://api.github.com/graphql', [
        'headers' => [
            'Authorization' => 'Bearer ' . $options['github_access_token'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/vnd.github.v3+json'
        ],
        'body' => json_encode(['query' => $query]),
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        return 'Error fetching GitHub discussions: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['data']) || !isset($data['data']['gitHubDiscussions']['edges'])) {
        return 'No discussions found or unexpected data structure: ' . esc_html($body);
    }

    $discussions = $data['data']['gitHubDiscussions']['edges'];
    $output = '<ul>';
    foreach ($discussions as $discussion) {
        if (!isset($discussion['node']['url']) || !isset($discussion['node']['title'])) {
            continue;  // Skip incomplete data
        }
        $url = esc_url($discussion['node']['url']);
        $title = esc_html($discussion['node']['title']);
        $output .= "<li><a href='{$url}'>{$title}</a></li>";
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode('github_discussions', 'github_discussions_shortcode');

function github_discussions_plugin_icon()
{
    echo '<style>.icon16.icon-plugins { background: url(' . plugins_url('dashicons-format-chat.svg', __FILE__) . ') no-repeat 0 0 !important; }</style>';
}
add_action('admin_head', 'github_discussions_plugin_icon');

function github_discussions_block_icon()
{
    wp_enqueue_style('dashicons');
    wp_add_inline_style('dashicons', '.wp-block-github-discussions-github-discussions-block .wp-block-icon svg { fill: #0073aa; }');
}
add_action('enqueue_block_editor_assets', 'github_discussions_block_icon');

function github_discussions_block_render($attributes)
{
    $category = isset($attributes['category']) ? $attributes['category'] : '';
    $args = [
        'post_type' => 'github_discussion',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'github_repo',
                'field' => 'slug',
                'terms' => $category,
            ],
        ],
    ];
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        $output = '<ul>';
        while ($query->have_posts()) {
            $query->the_post();
            $output .= '<li><a href="' . get_post_meta(get_the_ID(), 'github_discussion_url', true) . '">' . get_the_title() . '</a></li>';
        }
        $output .= '</ul>';
        wp_reset_postdata();
    } else {
        $output = 'No GitHub discussions found.';
    }
    return $output;
}
register_block_type('github-discussions/github-discussions-block', ['render_callback' => 'github_discussions_block_render']);

function github_discussions_dashboard_widget()
{
    $options = get_option('github_discussions_options');
    $fetchCount = $options['github_fetch_count'] ?? 10;
    $query = <<<QUERY
query GetGitHubDiscussions {
  gitHubDiscussions(first: $fetchCount) {
    edges {
      node {
        title
        url
      }
    }
  }
}
QUERY;

    $response = wp_remote_post('https://api.github.com/graphql', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $options['github_access_token'],
            'Accept' => 'application/vnd.github.v3+json'
        ],
        'body' => json_encode(['query' => $query]),
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        echo 'Error fetching GitHub discussions: ' . $response->get_error_message();
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['data']) || !isset($data['data']['gitHubDiscussions']['edges'])) {
        echo 'No discussions found or data structure is incorrect.';
        return;
    }

    $discussions = $data['data']['gitHubDiscussions']['edges'];
    if (empty($discussions)) {
        echo 'No discussions available.';
        return;
    }

    echo '<ul>';
    foreach ($discussions as $discussion) {
        $url = esc_url($discussion['node']['url']);
        $title = esc_html($discussion['node']['title']);
        echo "<li><a href='{$url}'>{$title}</a></li>";
    }
    echo '</ul>';
}

function github_discussions_add_dashboard_widget()
{
    wp_add_dashboard_widget('github_discussions_dashboard_widget', 'GitHub Discussions', 'github_discussions_dashboard_widget');
}
add_action('wp_dashboard_setup', 'github_discussions_add_dashboard_widget');
