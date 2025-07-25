<?php

/**
 * Provides methods to be aware of the queue system and its inner workings.
 *
 * @package WPStaging\Framework\BackgroundProcessing
 */

namespace WPStaging\Framework\BackgroundProcessing;

use WP_Error;
use WPStaging\Framework\Facades\Hooks;

use function WPStaging\functions\debug_log;

/**
 * Trait WithQueueAwareness
 *
 * @package WPStaging\Framework\BackgroundProcessing
 */
trait WithQueueAwareness
{
    /**
     * Whether this Queue instance did fire the AJAX action request or not.
     *
     * @var bool
     */
    private $didFireAjaxAction = false;

    /**
     * Returns the Queue default priority that will be used to schedule actions when the
     * priority is not specified or is specified as an invalid value.
     *
     * @return int The Queue default priority.
     */
    public static function getDefaultPriority()
    {
        return 0;
    }

    /**
     * Fires a non-blocking request to the WordPress admin AJAX endpoint that will,
     * in turn, trigger the processing of more Actions.
     *
     * @param mixed|null $bodyData An optional set of data to customize the processing request
     *                             for. If not provided, then the request will be fired for the
     *                             next available Actions (normal operations).
     *
     * @return bool A value that will indicate whether the request was correctly dispatched
     *              or not.
     */
    public function fireAjaxAction($bodyData = null)
    {
        if ($this->didFireAjaxAction) {
            // Let's not fire the AJAX request more than once per HTTP request, per Queue.
            return false;
        }

        $ajaxUrl = add_query_arg([
            'action'      => QueueProcessor::ACTION_QUEUE_PROCESS,
            '_ajax_nonce' => wp_create_nonce(QueueProcessor::ACTION_QUEUE_PROCESS)
        ], admin_url('admin-ajax.php'));

        $useGetMethod = false;
        $requestSent  = false;
        // If we are in a cron job, check if GET/POST method works and set it in a transient for caching
        $useGetMethod = get_site_transient(QueueProcessor::TRANSIENT_REQUEST_GET_METHOD);
        // Transient return false for non existing or expired values, for type safety we will use string 'Yes' or 'No' for GET method usage
        if ($useGetMethod === false) {
            // By default we use POST method, so if that doesn't work we will use GET method
            $useGetMethod = $this->checkGetRequestNeededForQueue($ajaxUrl, $bodyData);
            // We already sent the POST method request. Let not double sent request if we continue use POST method
            $requestSent  = !$useGetMethod;
            // Let set the transient for 24 hours
            set_site_transient(QueueProcessor::TRANSIENT_REQUEST_GET_METHOD, $useGetMethod ? 'Yes' : 'No', 60 * 60 * 24);
        } else {
            $useGetMethod = $useGetMethod === 'Yes';
        }

        // If request already sent let early bail
        if ($requestSent) {
            $this->didFireAjaxAction = true;

            Hooks::doAction('wpstg_queue_fire_ajax_request', $this);

            return true;
        }

        // If filter is present lets override it!
        $useGetMethod = Hooks::applyFilters(QueueProcessor::FILTER_REQUEST_FORCE_GET_METHOD, $useGetMethod);

        $response = wp_remote_request(esc_url_raw($ajaxUrl), [
            'headers'   => [
                'X-WPSTG-Request' => QueueProcessor::ACTION_QUEUE_PROCESS,
            ],
            'method'    => $useGetMethod ? 'GET' : 'POST',
            'blocking'  => $this->useBlockingRequest(),
            'timeout'   => $this->useBlockingRequest() ? 30 : 0.01, // 0.01 for a non-blocking request
            'cookies'   => !empty($_COOKIE) ? $_COOKIE : [],
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body'      => $this->normalizeAjaxRequestBody($bodyData),
        ]);

        //debug_log('fireAjaxAction: ' . wp_json_encode($response, JSON_PRETTY_PRINT));

        /*
         * A non-blocking request will either return a WP_Error instance, or
         * a mock response. The response is a mock as we cannot really build
         * a good response without waiting for it to be processed from the server.
         */
        if ($response instanceof WP_Error) {
            \WPStaging\functions\debug_log(json_encode([
                'root'    => 'Queue processing admin-ajax request failed.',
                'class'   => get_class($this),
                'code'    => $response->get_error_code(),
                'message' => $response->get_error_message(),
                'data'    => $response->get_error_data()
            ], JSON_PRETTY_PRINT));

            return false;
        }

        $this->didFireAjaxAction = true;

        /**
         * Fires an Action to indicate the Queue did fire the AJAX request that will
         * trigger side-processing in another PHP process.
         *
         * @param Queue $this A reference to the instance of the Queue that actually fired
         *                    the AJAX request.
         */
        do_action('wpstg_queue_fire_ajax_request', $this);

        return true;
    }

    /**
     * Normalizes the data to be sent along the non-blocking AJAX request
     * that will trigger the Queue processing of an Action.
     *
     * @param mixed|null $bodyData The data to normalize to a format suitable for
     *                             the remote request.
     *
     * @return array The normalized body data to be sent along the non-blocking
     *               AJAX request.
     */
    private function normalizeAjaxRequestBody($bodyData)
    {
        $normalized = (array)$bodyData;

        $normalized['_referer'] = __CLASS__;

        return $normalized;
    }

    /**
     * @param string $ajaxUrl
     * @param mixed|null $bodyData
     * @return bool
     */
    private function checkGetRequestNeededForQueue(string $ajaxUrl, $bodyData = null): bool
    {
        // Let send a blocking request to check if POST method works
        $response = wp_remote_post(esc_url_raw($ajaxUrl), [
            'headers'   => [
                'X-WPSTG-Request' => QueueProcessor::ACTION_QUEUE_PROCESS,
            ],
            'blocking'  => true,
            'timeout'   => 10,
            'cookies'   => !empty($_COOKIE) ? $_COOKIE : [],
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body'      => $this->normalizeAjaxRequestBody($bodyData),
        ]);

        debug_log('checkGetRequestNeededForQueue: ' . wp_json_encode($response, JSON_PRETTY_PRINT), 'info', false);

        // If we get WP_Error, then we can assume that POST method doesn't work
        if ($response instanceof WP_Error) {
            return true;
        }

        if (!is_array($response)) {
            return false;
        }

        // If we get 404 response code, then we can assume that POST method doesn't work
        if (
            array_key_exists('response', $response) &&
            array_key_exists('code', $response['response']) &&
            $response['response']['code'] === 404
        ) {
            return true;
        }

        return false;
    }

    private function useBlockingRequest(): bool
    {
        // Early bail if we are doing ajax request
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }

        // Only use blocking request if we are in a local environment
        return function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local';
    }
}
