<?php

if (!defined('ABSPATH')) {
    exit;
}

class AICA_REST
{
    private $analyzer;

    public function __construct($analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function register_hooks()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        register_rest_route('aica/v1', '/analyze/(?P<post_id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_post'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function analyze_post($request)
    {
        $post_id = (int) $request['post_id'];
        $result = $this->analyzer->analyze_post_comments($post_id, true);

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 200);
    }
}
