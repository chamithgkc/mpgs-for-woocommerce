<?php
/**
 * Plugin Name: MPGS for WooCommerce
 * Description: Extends WooCommerce with MasterCard Payment Gateway Services (MPGS).
 * Version: 1.5.3
 * WC requires at least: 5.0.0
 * WC tested up to: 9.8.5
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Tested up to: 6.9
 * Text Domain: mpgs-for-woocommerce
 * Domain Path: /languages
 * Author: Chamith Koralage
 * Author URI: https://github.com/chamithgkc
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom link (i.e., "Configure")
 */
function woo_mpgs_gateway_plugin_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=woo_mpgs') . '">' . __('Configure', 'mpgs-for-woocommerce') . '</a>'
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woo_mpgs_gateway_plugin_links');

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + Woo MPGS gateway
 */
function woo_mpgs_add_to_gateways($gateways)
{
    $gateways[] = 'WOO_MPGS';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'woo_mpgs_add_to_gateways');

/**
 * WooCommerce MPGS
 *
 * Extends WooCommerce with MasterCard Payment Gateway Services (MPGS).
 *
 * @class 		WOO_MPGS
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Ali Basheer
 */
function woo_mpgs_init()
{

    /**
     * Make sure WooCommerce is active
     */
    if (!class_exists('WooCommerce')) {
        $wc_link = wp_kses(
            sprintf(
                /* translators: %s: WooCommerce link */
                __('MPGS requires WooCommerce to be installed and active. You can download %s here.', 'mpgs-for-woocommerce'),
                '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
            ),
            array('a' => array('href' => array(), 'target' => array()))
        );
        echo '<div class="error"><p><strong>' . $wc_link . '</strong></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses above
        return;
    }

    class WOO_MPGS extends WC_Payment_Gateway
    {

        public $mpgs_icon;
        public $service_host;
        public $api_version;
        public $merchant_id;
        public $auth_pass;
        public $merchant_name;
        public $merchant_address1;
        public $merchant_address2;
        public $checkout_interaction;

        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {

            $this->id = 'woo_mpgs';
            $this->mpgs_icon = $this->get_option('mpgs_icon');
            $this->icon = (!empty($this->mpgs_icon)) ? $this->mpgs_icon : apply_filters('woo_mpgs_icon', plugins_url('assets/images/mastercard.png', __FILE__));
            $this->has_fields = false;
            $this->method_title = __('MPGS', 'mpgs-for-woocommerce');
            $this->method_description = __('Allows MasterCard Payment Gateway Services (MPGS)', 'mpgs-for-woocommerce');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->service_host = $this->get_option('service_host');
            $this->api_version = $this->get_option('api_version');
            $this->merchant_id = $this->get_option('merchant_id');
            $this->auth_pass = $this->get_option('authentication_password');
            $this->merchant_name = $this->get_option('merchant_name');
            $this->merchant_address1 = $this->get_option('merchant_address1');
            $this->merchant_address2 = $this->get_option('merchant_address2');
            $this->checkout_interaction = $this->get_option('checkout_interaction');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_woo_mpgs', array($this, 'receipt_page'));
            add_action('woocommerce_api_woo_mpgs', array($this, 'process_response'));
            add_action('wp_enqueue_scripts', array($this, 'add_checkout_script'));
            add_filter('script_loader_tag', array($this, 'add_mpgs_checkout_data_attributes'), 10, 3);
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields()
        {

            $this->form_fields = apply_filters('woo_mpgs_form_fields', array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'mpgs-for-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable MPGS Payment Module.', 'mpgs-for-woocommerce'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Title', 'mpgs-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'mpgs-for-woocommerce'),
                    'default' => __('Credit Card', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => __('Description', 'mpgs-for-woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'mpgs-for-woocommerce'),
                    'default' => __('Pay securely by Credit/Debit Card.', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'mpgs_icon' => array(
                    'title' => __('Icon', 'mpgs-for-woocommerce'),
                    'type' => 'text',
                    'css' => 'width:100%',
                    'description' => __('Enter an image URL to change the icon.', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'service_host' => array(
                    'title' => __('MPGS URL', 'mpgs-for-woocommerce'),
                    'type' => 'text',
                    'css' => 'width:100%',
                    'description' => __('MPGS URL, given by the Bank. This is an example: https://ap-gateway.mastercard.com/', 'mpgs-for-woocommerce'),
                    'placeholder' => __('MPGS URL', 'mpgs-for-woocommerce'),
                    'default' => __('https://ap-gateway.mastercard.com/', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'api_version' => array(
                    'title' => __('API Version', 'mpgs-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('API version, given by the Bank', 'mpgs-for-woocommerce'),
                    'placeholder' => __('MPGS API Version (66 is recommended)', 'mpgs-for-woocommerce'),
                    'default' => 49,
                    'desc_tip' => true
                ),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'mpgs-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('Merchant ID, given by the Bank', 'mpgs-for-woocommerce'),
                    'placeholder' => __('Merchant ID', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'authentication_password' => array(
                    'title' => __('Authentication Password', 'mpgs-for-woocommerce'),
                    'type' => 'password',
                    'description' => __('Authentication Password, given by the Bank', 'mpgs-for-woocommerce'),
                    'placeholder' => __('Authentication Password', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'merchant_name' => array(
                    'title' => __('Name', 'mpgs-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('Merchant name that will appear in the gateway page or popup', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'merchant_address1' => array(
                    'title' => __('Merchant Address Line 1', 'mpgs-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('Merchant Address Line 1 that will appear in the gateway page or popup', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'merchant_address2' => array(
                    'title' => __('Merchant Address Line 2', 'mpgs-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('Merchant Address Line 2 that will appear in the gateway page or popup', 'mpgs-for-woocommerce'),
                    'desc_tip' => true
                ),
                'checkout_interaction' => array(
                    'title' => __('Checkout Interaction', 'mpgs-for-woocommerce'),
                    'type' => 'select',
                    'description' => __('Choose checkout interaction type. Please note that Lightbox option is not supported in API version 63 or above.', 'mpgs-for-woocommerce'),
                    'options' => array('lightbox' => 'Lightbox (for API versions < 63)', 'paymentpage' => 'Payment Page'),
                    'default' => '1',
                )
            ));
        }

        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            // Prepare session request
            $session_request = array();

            if ((int) $this->api_version >= 62) {
                $session_request['initiator']['userId'] = $order->get_user_id();
            } else {
                $session_request['userId'] = $order->get_user_id();
            }

            $session_request['order']['id'] = $order_id;
            $session_request['order']['amount'] = $order->get_total();
            $session_request['order']['currency'] = get_woocommerce_currency();
            $session_request['interaction']['returnUrl'] = add_query_arg(array('order_id' => $order_id, 'wc-api' => 'woo_mpgs'), home_url('/'));

            if ((int) $this->api_version >= 63) {
                $session_request['apiOperation'] = "INITIATE_CHECKOUT";
            } else {
                $session_request['apiOperation'] = "CREATE_CHECKOUT_SESSION";
            }

            if ((int) $this->api_version >= 52) {
                $session_request['interaction']['operation'] = "PURCHASE";
            }

            /**
             * Filters the session request.
             *
             * @since 1.3.1
             *
             * @param array   $session_request The array that will be sent with the request.
             * @param WC_ORDER $order  Order object.
             */
            $session_request = apply_filters('woo_mpgs_session_request', $session_request, $order);

            $request_url = trailingslashit($this->service_host) . "api/rest/version/" . $this->api_version . "/merchant/" . $this->merchant_id . "/session";

            // Request the session
            $response_json = wp_remote_post($request_url, array(
                'body' => json_encode($session_request),
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode("merchant." . $this->merchant_id . ":" . $this->auth_pass),
                ),
                'timeout' => 30,
            ));

            if (is_wp_error($response_json)) {

                wc_add_notice(__('Payment error: Failed to communicate with MPGS server. Make sure MPGS URL looks like `https://example.mastercard.com/` by removing `checkout/version/*/checkout.js` and end the URL with a slash "/".', 'mpgs-for-woocommerce'), 'error');

                return array(
                    'result' => 'fail',
                    'redirect' => '',
                );
            }

            $response = json_decode($response_json['body'], true);

            if ($response['result'] == 'SUCCESS' && !empty($response['successIndicator'])) {

                $order->update_meta_data('woo_mpgs_successIndicator', $response['successIndicator']);
                $order->update_meta_data('woo_mpgs_sessionVersion', $response['session']['version']);
                $order->save();

                $pay_url = add_query_arg(array(
                    'sessionId' => $response['session']['id'],
                    'key' => $order->get_order_key(),
                    'pay_for_order' => false,
                ), $order->get_checkout_payment_url());

                return array(
                    'result' => 'success',
                    'redirect' => $pay_url
                );

            } else {
                wc_add_notice(__('Payment error: ', 'mpgs-for-woocommerce') . $response['error']['explanation'], 'error');
            }
        }

        /**
         * Print payment buttons in the receipt page
         *
         * @param int $order_id
         */
        public function receipt_page($order_id)
        {

            // Sanitize session ID from request before any use.
            $session_id = isset($_REQUEST['sessionId']) ? sanitize_text_field(wp_unslash($_REQUEST['sessionId'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if (!empty($session_id)) {

                $order = wc_get_order($order_id);
                if (!$order) {
                    return;
                }
                ?>
                <script type="text/javascript">
                    function errorCallback(error) {
                        alert("Error: " + JSON.stringify(error));
                        window.location.href = "<?php echo esc_url(wc_get_checkout_url()); ?>";
                    }
                    Checkout.configure({
                        <?php if ((int) $this->api_version <= 62) { ?>
                                            merchant: "<?php echo esc_js($this->merchant_id); ?>",
                        <?php } ?>
                                        order: {
                            id: "<?php echo esc_js((string) $order_id); ?>",
                            <?php if ((int) $this->api_version <= 62) { ?>
                                                amount: "<?php echo esc_js((string) $order->get_total()); ?>",
                                currency: "<?php echo esc_js(get_woocommerce_currency()); ?>",
                            <?php } ?>
                                            description: "<?php 
                                                /* translators: 1: Order ID, 2: Gateway Title */
                                                printf(esc_html__('Pay for order #%1$d via %2$s', 'mpgs-for-woocommerce'), absint($order_id), esc_html($this->title)); 
                                            ?>",
                            customerOrderDate: "<?php echo esc_js(gmdate('Y-m-d')); ?>",
                            customerReference: "<?php echo esc_js((string) $order->get_user_id()); ?>",
                            reference: "<?php echo esc_js((string) $order_id); ?>"
                        },
                        session: {
                            id: "<?php echo esc_js($session_id); ?>"
                        },
                        transaction: {
                            reference: "TRF" + "<?php echo esc_js((string) $order_id); ?>"
                        },
                        billing: {
                            address: {
                                city: "<?php echo esc_js($order->get_billing_city()); ?>",
                                country: "<?php echo esc_js($this->kia_convert_country_code($order->get_billing_country())); ?>",
                                postcodeZip: "<?php echo esc_js($order->get_billing_postcode()); ?>",
                                stateProvince: "<?php echo esc_js($order->get_billing_state()); ?>",
                                street: "<?php echo esc_js($order->get_billing_address_1()); ?>",
                                street2: "<?php echo esc_js($order->get_billing_address_2()); ?>"
                            }
                        },
                        <?php if (!empty($order->get_billing_email()) && !empty($order->get_billing_first_name()) && !empty($order->get_billing_last_name()) && !empty($order->get_billing_phone())) { ?>
                                            customer: {
                                email: "<?php echo esc_js($order->get_billing_email()); ?>",
                                firstName: "<?php echo esc_js($order->get_billing_first_name()); ?>",
                                lastName: "<?php echo esc_js($order->get_billing_last_name()); ?>",
                                phone: "<?php echo esc_js($order->get_billing_phone()); ?>"
                            },
                        <?php } ?>
                                        interaction: {
                            <?php if ((int) $this->api_version >= 52) { ?>
                                                operation: "PURCHASE",
                            <?php } ?>
                                            merchant: {
                                name: "<?php echo esc_js(!empty($this->merchant_name) ? $this->merchant_name : 'MPGS'); ?>",
                                address: {
                                    line1: "<?php echo esc_js($this->merchant_address1); ?>",
                                    line2: "<?php echo esc_js($this->merchant_address2); ?>"
                                }
                            },
                            displayControl: {
                                billingAddress: "HIDE",
                                customerEmail: "HIDE",
                                <?php if ((int) $this->api_version <= 62) { ?>
                                                    orderSummary: "HIDE",
                                <?php } ?>
                                                shipping: "HIDE"
                            }
                        }
                    });
                </script>
                <p class="loading-payment-text">
                    <?php esc_html_e('Loading payment method, please wait. This may take up to 30 seconds.', 'mpgs-for-woocommerce'); ?></p>
                <script type="text/javascript">
                    <?php if ((int) $this->api_version >= 63) {
                        echo 'Checkout.showPaymentPage();';
                    } else {
                        echo ($this->checkout_interaction === 'paymentpage') ? 'Checkout.showPaymentPage();' : 'Checkout.showLightbox()';
                    } ?>
                </script>
                <?php
            } else {
                wc_add_notice(__('Payment error: Session not found.', 'mpgs-for-woocommerce'), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
        }

        /**
         * Handle MPGS response
         */
        public function process_response()
        {

            global $woocommerce;
            $order_id = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order = wc_get_order($order_id);

            if (!$order) {
                wp_die(esc_html__('Invalid order ID or order not found.', 'mpgs-for-woocommerce'), esc_html__('Error', 'mpgs-for-woocommerce'), array('response' => 400));
            }

            $resultIndicator = isset($_REQUEST['resultIndicator']) ? sanitize_text_field(wp_unslash($_REQUEST['resultIndicator'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $mpgs_successIndicator = $order->get_meta('woo_mpgs_successIndicator');

            if ($resultIndicator === $mpgs_successIndicator && !empty($resultIndicator)) {

                $request_url = trailingslashit($this->service_host) . 'api/rest/version/' . $this->api_version . '/merchant/' . $this->merchant_id . '/order/' . $order_id;

                // Request the order payment details.
                $response_json = wp_remote_get($request_url, array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode('merchant.' . $this->merchant_id . ':' . $this->auth_pass), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                    ),
                    'timeout' => 30,
                ));

                // Guard against WP_Error (network failure).
                if (is_wp_error($response_json)) {
                    $order->add_order_note(__('Payment error: Failed to retrieve order details from MPGS.', 'mpgs-for-woocommerce'));
                    wc_add_notice(__('Payment error: Failed to communicate with payment server.', 'mpgs-for-woocommerce'), 'error');
                    wp_safe_redirect(wc_get_checkout_url());
                    exit;
                }

                // utf8_decode() is deprecated in PHP 8.2; use mb_convert_encoding() instead.
                $body = wp_remote_retrieve_body($response_json);
                $response = json_decode(mb_convert_encoding($body, 'UTF-8', 'UTF-8'), true);

                // Guard: ensure transaction data exists before accessing.
                if (empty($response['transaction']) || !is_array($response['transaction'])) {
                    $order->add_order_note(__('Payment error: No transaction data returned by MPGS.', 'mpgs-for-woocommerce'));
                    wc_add_notice(__('Payment error: Something went wrong.', 'mpgs-for-woocommerce'), 'error');
                    wp_safe_redirect(wc_get_checkout_url());
                    exit;
                }

                $transaction_index = count($response['transaction']) - 1;
                $transaction_result = isset($response['transaction'][$transaction_index]['result']) ? $response['transaction'][$transaction_index]['result'] : '';
                $transaction_receipt = isset($response['transaction'][$transaction_index]['transaction']['receipt']) ? $response['transaction'][$transaction_index]['transaction']['receipt'] : '';

                if ('SUCCESS' === $transaction_result && !empty($transaction_receipt)) {
                    $woocommerce->cart->empty_cart();
                    $order->add_order_note(sprintf(
                        /* translators: %s: payment receipt number */
                        __('MPGS Payment completed with Transaction Receipt: %s.', 'mpgs-for-woocommerce'),
                        sanitize_text_field($transaction_receipt)
                    ));
                    $order->payment_complete($transaction_receipt);

                    wp_safe_redirect($this->get_return_url($order));
                    exit;
                } else {
                    $order->add_order_note(__('Payment error: Something went wrong.', 'mpgs-for-woocommerce'));
                    wc_add_notice(__('Payment error: Something went wrong.', 'mpgs-for-woocommerce'), 'error');
                }

            } else {
                if (!empty($resultIndicator)) {
                    $order->add_order_note(esc_html__('Payment error: Invalid transaction.', 'mpgs-for-woocommerce'));
                }
                wc_add_notice(esc_html__('Payment error: Invalid transaction.', 'mpgs-for-woocommerce'), 'error');
            }

            // Reaching this line means there is an error; redirect back to checkout page.
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        /**
         * Load checkout script on the receipt page.
         */
        public function add_checkout_script()
        {
            // Sanitize before checking so no raw superglobal is read.
            $session_id = isset($_REQUEST['sessionId']) ? sanitize_text_field(wp_unslash($_REQUEST['sessionId'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if (!empty($session_id)) {

                if ((int) $this->api_version >= 63) {
                    $src = trailingslashit($this->service_host) . 'static/checkout/checkout.min.js';
                } else {
                    $src = trailingslashit($this->service_host) . 'checkout/version/' . $this->api_version . '/checkout.js';
                }

                wp_enqueue_script('mpgs-checkout', $src, array(), '1.5.3', false);
            }
        }

        /**
         * Filter script tag to add required data attributes.
         *
         * @param string $tag    The `<script>` tag for the enqueued script.
         * @param string $handle The script's registered handle.
         * @param string $src    The script's source URL.
         * @return string Modified script tag.
         */
        public function add_mpgs_checkout_data_attributes($tag, $handle, $src)
        {
            if ('mpgs-checkout' === $handle) {
                $cancel_url = esc_url(wc_get_checkout_url());
                $tag = str_replace(' src', ' data-error="errorCallback" data-cancel="' . $cancel_url . '" src', $tag);
            }
            return $tag;
        }

        /**
         * Converts the WooCommerce country codes to 3-letter ISO codes
         * https://en.wikipedia.org/wiki/ISO_3166-1_alpha-3
         * @param string WooCommerce's 2 letter country code
         * @return string ISO 3-letter country code
         */
        function kia_convert_country_code($country)
        {
            $countries = array(
                'AF' => 'AFG', //Afghanistan
                'AX' => 'ALA', //&#197;land Islands
                'AL' => 'ALB', //Albania
                'DZ' => 'DZA', //Algeria
                'AS' => 'ASM', //American Samoa
                'AD' => 'AND', //Andorra
                'AO' => 'AGO', //Angola
                'AI' => 'AIA', //Anguilla
                'AQ' => 'ATA', //Antarctica
                'AG' => 'ATG', //Antigua and Barbuda
                'AR' => 'ARG', //Argentina
                'AM' => 'ARM', //Armenia
                'AW' => 'ABW', //Aruba
                'AU' => 'AUS', //Australia
                'AT' => 'AUT', //Austria
                'AZ' => 'AZE', //Azerbaijan
                'BS' => 'BHS', //Bahamas
                'BH' => 'BHR', //Bahrain
                'BD' => 'BGD', //Bangladesh
                'BB' => 'BRB', //Barbados
                'BY' => 'BLR', //Belarus
                'BE' => 'BEL', //Belgium
                'BZ' => 'BLZ', //Belize
                'BJ' => 'BEN', //Benin
                'BM' => 'BMU', //Bermuda
                'BT' => 'BTN', //Bhutan
                'BO' => 'BOL', //Bolivia
                'BQ' => 'BES', //Bonaire, Saint Estatius and Saba
                'BA' => 'BIH', //Bosnia and Herzegovina
                'BW' => 'BWA', //Botswana
                'BV' => 'BVT', //Bouvet Islands
                'BR' => 'BRA', //Brazil
                'IO' => 'IOT', //British Indian Ocean Territory
                'BN' => 'BRN', //Brunei
                'BG' => 'BGR', //Bulgaria
                'BF' => 'BFA', //Burkina Faso
                'BI' => 'BDI', //Burundi
                'KH' => 'KHM', //Cambodia
                'CM' => 'CMR', //Cameroon
                'CA' => 'CAN', //Canada
                'CV' => 'CPV', //Cape Verde
                'KY' => 'CYM', //Cayman Islands
                'CF' => 'CAF', //Central African Republic
                'TD' => 'TCD', //Chad
                'CL' => 'CHL', //Chile
                'CN' => 'CHN', //China
                'CX' => 'CXR', //Christmas Island
                'CC' => 'CCK', //Cocos (Keeling) Islands
                'CO' => 'COL', //Colombia
                'KM' => 'COM', //Comoros
                'CG' => 'COG', //Congo
                'CD' => 'COD', //Congo, Democratic Republic of the
                'CK' => 'COK', //Cook Islands
                'CR' => 'CRI', //Costa Rica
                'CI' => 'CIV', //Côte d\'Ivoire
                'HR' => 'HRV', //Croatia
                'CU' => 'CUB', //Cuba
                'CW' => 'CUW', //Curaçao
                'CY' => 'CYP', //Cyprus
                'CZ' => 'CZE', //Czech Republic
                'DK' => 'DNK', //Denmark
                'DJ' => 'DJI', //Djibouti
                'DM' => 'DMA', //Dominica
                'DO' => 'DOM', //Dominican Republic
                'EC' => 'ECU', //Ecuador
                'EG' => 'EGY', //Egypt
                'SV' => 'SLV', //El Salvador
                'GQ' => 'GNQ', //Equatorial Guinea
                'ER' => 'ERI', //Eritrea
                'EE' => 'EST', //Estonia
                'ET' => 'ETH', //Ethiopia
                'FK' => 'FLK', //Falkland Islands
                'FO' => 'FRO', //Faroe Islands
                'FJ' => 'FIJ', //Fiji
                'FI' => 'FIN', //Finland
                'FR' => 'FRA', //France
                'GF' => 'GUF', //French Guiana
                'PF' => 'PYF', //French Polynesia
                'TF' => 'ATF', //French Southern Territories
                'GA' => 'GAB', //Gabon
                'GM' => 'GMB', //Gambia
                'GE' => 'GEO', //Georgia
                'DE' => 'DEU', //Germany
                'GH' => 'GHA', //Ghana
                'GI' => 'GIB', //Gibraltar
                'GR' => 'GRC', //Greece
                'GL' => 'GRL', //Greenland
                'GD' => 'GRD', //Grenada
                'GP' => 'GLP', //Guadeloupe
                'GU' => 'GUM', //Guam
                'GT' => 'GTM', //Guatemala
                'GG' => 'GGY', //Guernsey
                'GN' => 'GIN', //Guinea
                'GW' => 'GNB', //Guinea-Bissau
                'GY' => 'GUY', //Guyana
                'HT' => 'HTI', //Haiti
                'HM' => 'HMD', //Heard Island and McDonald Islands
                'VA' => 'VAT', //Holy See (Vatican City State)
                'HN' => 'HND', //Honduras
                'HK' => 'HKG', //Hong Kong
                'HU' => 'HUN', //Hungary
                'IS' => 'ISL', //Iceland
                'IN' => 'IND', //India
                'ID' => 'IDN', //Indonesia
                'IR' => 'IRN', //Iran
                'IQ' => 'IRQ', //Iraq
                'IE' => 'IRL', //Republic of Ireland
                'IM' => 'IMN', //Isle of Man
                'IL' => 'ISR', //Israel
                'IT' => 'ITA', //Italy
                'JM' => 'JAM', //Jamaica
                'JP' => 'JPN', //Japan
                'JE' => 'JEY', //Jersey
                'JO' => 'JOR', //Jordan
                'KZ' => 'KAZ', //Kazakhstan
                'KE' => 'KEN', //Kenya
                'KI' => 'KIR', //Kiribati
                'KP' => 'PRK', //Korea, Democratic People\'s Republic of
                'KR' => 'KOR', //Korea, Republic of (South)
                'KW' => 'KWT', //Kuwait
                'KG' => 'KGZ', //Kyrgyzstan
                'LA' => 'LAO', //Laos
                'LV' => 'LVA', //Latvia
                'LB' => 'LBN', //Lebanon
                'LS' => 'LSO', //Lesotho
                'LR' => 'LBR', //Liberia
                'LY' => 'LBY', //Libya
                'LI' => 'LIE', //Liechtenstein
                'LT' => 'LTU', //Lithuania
                'LU' => 'LUX', //Luxembourg
                'MO' => 'MAC', //Macao S.A.R., China
                'MK' => 'MKD', //Macedonia
                'MG' => 'MDG', //Madagascar
                'MW' => 'MWI', //Malawi
                'MY' => 'MYS', //Malaysia
                'MV' => 'MDV', //Maldives
                'ML' => 'MLI', //Mali
                'MT' => 'MLT', //Malta
                'MH' => 'MHL', //Marshall Islands
                'MQ' => 'MTQ', //Martinique
                'MR' => 'MRT', //Mauritania
                'MU' => 'MUS', //Mauritius
                'YT' => 'MYT', //Mayotte
                'MX' => 'MEX', //Mexico
                'FM' => 'FSM', //Micronesia
                'MD' => 'MDA', //Moldova
                'MC' => 'MCO', //Monaco
                'MN' => 'MNG', //Mongolia
                'ME' => 'MNE', //Montenegro
                'MS' => 'MSR', //Montserrat
                'MA' => 'MAR', //Morocco
                'MZ' => 'MOZ', //Mozambique
                'MM' => 'MMR', //Myanmar
                'NA' => 'NAM', //Namibia
                'NR' => 'NRU', //Nauru
                'NP' => 'NPL', //Nepal
                'NL' => 'NLD', //Netherlands
                'AN' => 'ANT', //Netherlands Antilles
                'NC' => 'NCL', //New Caledonia
                'NZ' => 'NZL', //New Zealand
                'NI' => 'NIC', //Nicaragua
                'NE' => 'NER', //Niger
                'NG' => 'NGA', //Nigeria
                'NU' => 'NIU', //Niue
                'NF' => 'NFK', //Norfolk Island
                'MP' => 'MNP', //Northern Mariana Islands
                'NO' => 'NOR', //Norway
                'OM' => 'OMN', //Oman
                'PK' => 'PAK', //Pakistan
                'PW' => 'PLW', //Palau
                'PS' => 'PSE', //Palestinian Territory
                'PA' => 'PAN', //Panama
                'PG' => 'PNG', //Papua New Guinea
                'PY' => 'PRY', //Paraguay
                'PE' => 'PER', //Peru
                'PH' => 'PHL', //Philippines
                'PN' => 'PCN', //Pitcairn
                'PL' => 'POL', //Poland
                'PT' => 'PRT', //Portugal
                'PR' => 'PRI', //Puerto Rico
                'QA' => 'QAT', //Qatar
                'RE' => 'REU', //Reunion
                'RO' => 'ROU', //Romania
                'RU' => 'RUS', //Russia
                'RW' => 'RWA', //Rwanda
                'BL' => 'BLM', //Saint Barth&eacute;lemy
                'SH' => 'SHN', //Saint Helena
                'KN' => 'KNA', //Saint Kitts and Nevis
                'LC' => 'LCA', //Saint Lucia
                'MF' => 'MAF', //Saint Martin (French part)
                'SX' => 'SXM', //Sint Maarten / Saint Matin (Dutch part)
                'PM' => 'SPM', //Saint Pierre and Miquelon
                'VC' => 'VCT', //Saint Vincent and the Grenadines
                'WS' => 'WSM', //Samoa
                'SM' => 'SMR', //San Marino
                'ST' => 'STP', //S&atilde;o Tom&eacute; and Pr&iacute;ncipe
                'SA' => 'SAU', //Saudi Arabia
                'SN' => 'SEN', //Senegal
                'RS' => 'SRB', //Serbia
                'SC' => 'SYC', //Seychelles
                'SL' => 'SLE', //Sierra Leone
                'SG' => 'SGP', //Singapore
                'SK' => 'SVK', //Slovakia
                'SI' => 'SVN', //Slovenia
                'SB' => 'SLB', //Solomon Islands
                'SO' => 'SOM', //Somalia
                'ZA' => 'ZAF', //South Africa
                'GS' => 'SGS', //South Georgia/Sandwich Islands
                'SS' => 'SSD', //South Sudan
                'ES' => 'ESP', //Spain
                'LK' => 'LKA', //Sri Lanka
                'SD' => 'SDN', //Sudan
                'SR' => 'SUR', //Suriname
                'SJ' => 'SJM', //Svalbard and Jan Mayen
                'SZ' => 'SWZ', //Swaziland
                'SE' => 'SWE', //Sweden
                'CH' => 'CHE', //Switzerland
                'SY' => 'SYR', //Syria
                'TW' => 'TWN', //Taiwan
                'TJ' => 'TJK', //Tajikistan
                'TZ' => 'TZA', //Tanzania
                'TH' => 'THA', //Thailand
                'TL' => 'TLS', //Timor-Leste
                'TG' => 'TGO', //Togo
                'TK' => 'TKL', //Tokelau
                'TO' => 'TON', //Tonga
                'TT' => 'TTO', //Trinidad and Tobago
                'TN' => 'TUN', //Tunisia
                'TR' => 'TUR', //Turkey
                'TM' => 'TKM', //Turkmenistan
                'TC' => 'TCA', //Turks and Caicos Islands
                'TV' => 'TUV', //Tuvalu
                'UG' => 'UGA', //Uganda
                'UA' => 'UKR', //Ukraine
                'AE' => 'ARE', //United Arab Emirates
                'GB' => 'GBR', //United Kingdom
                'US' => 'USA', //United States
                'UM' => 'UMI', //United States Minor Outlying Islands
                'UY' => 'URY', //Uruguay
                'UZ' => 'UZB', //Uzbekistan
                'VU' => 'VUT', //Vanuatu
                'VE' => 'VEN', //Venezuela
                'VN' => 'VNM', //Vietnam
                'VG' => 'VGB', //Virgin Islands, British
                'VI' => 'VIR', //Virgin Island, U.S.
                'WF' => 'WLF', //Wallis and Futuna
                'EH' => 'ESH', //Western Sahara
                'YE' => 'YEM', //Yemen
                'ZM' => 'ZMB', //Zambia
                'ZW' => 'ZWE', //Zimbabwe

            );

            $iso_code = isset($countries[$country]) ? $countries[$country] : $country;
            return $iso_code;

        }
    }
}
add_action('plugins_loaded', 'woo_mpgs_init');