<?php
/**
 * Plugin Name:         Insider
 * Description:         AI-powered content management assistant.
 * Version:             1.0.0
 * Requires PHP:        8.3
 * Author:              Aleksey Filatev
 * License:             GPL v2 or later
 */

function runAsAdministrator($callback): mixed
{
    $originalUserId = get_current_user_id();

    $administrators = get_users(['role' => 'administrator', 'number' => 1]);
    if (empty($administrators)) {
        throw new Exception("No administrators found.");
    }
    $administratorUserId = current($administrators)->ID;

    wp_set_current_user($administratorUserId);
    $result = $callback();
    wp_set_current_user($originalUserId);

    return $result;
}

function createPost(): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/wp/v2/posts');
    $request->set_body_params([
        'title'   => 'My test',
        'content' => 'My test content',
        'excerpt' => 'My test excerpt',
        'status'  => 'draft',
    ]);
    return rest_do_request($request);
}

function prefix_get_endpoint_phrase(): WP_REST_Response|WP_Error
{
    try {
        $response = runAsAdministrator(fn() => createPost());
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
    } catch (Exception $exception) {
        return rest_ensure_response([
            'message' => "Cannot create post: {$exception->getMessage()}",
            'data' => time(),
        ]);
    }

    return rest_ensure_response([
        'message' => "The post created successfully",
        'data' => time(),
    ]);
}

function prefix_register_example_routes(): void
{
    register_rest_route( 'insider/v1', '/hello', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'prefix_get_endpoint_phrase',
    ) );
}

function insider_options_page_html(): void
{
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    echo "<div>Hello, Insider!</div>";
}

function insider_options_page(): void
{
    add_submenu_page(
        'tools.php',
        'Insider',
        'Insider',
        'manage_options',
        'insider',
        'insider_options_page_html'
    );
}

add_action('admin_menu', 'insider_options_page');
add_action('rest_api_init', 'prefix_register_example_routes');
