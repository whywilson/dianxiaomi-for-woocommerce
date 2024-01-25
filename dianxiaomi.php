<?php
/*
	Plugin Name: Dianxiaomi - WooCommerce ERP
	Plugin URI: http://dianxiaomi.com/
	Description: Add tracking number and carrier name to WooCommerce, display tracking info at order history page, auto import tracking numbers to Dianxiaomi.
	Version: 1.0.5
	Author: Dianxiaomi(Wilson Modified)
    Updated: 2024-01-24
	Author URI: https://github.com/whywilson/dianxiaomi-for-woocommerce/releases
	Copyright: © Dianxiaomi
*/

/**
 * Security Note
 */
defined('ABSPATH') or die("No script kiddies please!");

/**
 * Required functions
 */
if (!function_exists('is_woocommerce_active'))
    require_once('dianxiaomi-functions.php');


/**
 * Plugin updates
 */

if (is_woocommerce_active()) {

    /**
     * Dianxiaomi class
     */
    if (!class_exists('Dianxiaomi')) {

        final class Dianxiaomi
        {

            protected static $_instance = null;

            public static function instance()
            {
                if (is_null(self::$_instance)) {
                    self::$_instance = new self();
                }
                return self::$_instance;
            }


            /**
             * Constructor
             */
            public function __construct()
            {
                $this->includes();

                $this->api = new Dianxiaomi_API();

                $options = get_option('dianxiaomi_option_name');
                if ($options) {

                    if (isset($options['plugin'])) {
                        $plugin = $options['plugin'];
                        if ($plugin == 'dianxiaomi') {
                            add_action('admin_print_scripts', array(&$this, 'library_scripts'));
                            add_action('in_admin_footer', array(&$this, 'include_footer_script'));
                            add_action('admin_print_styles', array(&$this, 'admin_styles'));
                            add_action('add_meta_boxes', array(&$this, 'add_meta_box'));
                            add_action('woocommerce_process_shop_order_meta', array(&$this, 'save_meta_box'), 0, 2);
                            add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

                            $this->couriers = $options['couriers'];
                        }

                        // View Order Page
                        $this->plugin = $plugin;
                    } else {
                        $this->plugin = '';
                    }

                    if (isset($options['use_track_button'])) {
                        $this->use_track_button = $options['use_track_button'];
                    } else {
                        $this->use_track_button = false;
                    }

                    if (isset($options['custom_domain'])) {
                        $this->custom_domain = $options['custom_domain'];
                    } else {
                        $this->custom_domain = '';
                    }

                    add_action('woocommerce_view_order', array(&$this, 'display_tracking_info'));
                    add_action('woocommerce_email_before_order_table', array(&$this, 'email_display'));
                }

                // user profile api key
                add_action('show_user_profile', array($this, 'add_api_key_field'));
                add_action('edit_user_profile', array($this, 'add_api_key_field'));
                add_action('personal_options_update', array($this, 'generate_api_key'));
                add_action('edit_user_profile_update', array($this, 'generate_api_key'));

                register_activation_hook(__FILE__, array($this, 'install'));
            }

            public function install()
            {
                global $wp_roles;

                if (class_exists('WP_Roles')) {
                    if (!isset($wp_roles)) {
                        $wp_roles = new WP_Roles();
                    }
                }

                if (is_object($wp_roles)) {
                    $wp_roles->add_cap('administrator', 'manage_dianxiaomi');
                }
            }

            private function includes()
            {
                include_once('dianxiaomi-fields.php');
                $this->dianxiaomi_fields = $dianxiaomi_fields;

                include_once('class-dianxiaomi-api.php');
                include_once('class-dianxiaomi-settings.php');
            }

            /**
             * Localisation
             */
            public function load_plugin_textdomain()
            {
                load_plugin_textdomain('dianxiaomi', false, dirname(plugin_basename(__FILE__)) . '/languages/');
            }

            public function admin_styles()
            {
                wp_enqueue_style('dianxiaomi_styles_chosen', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.min.css');
                wp_enqueue_style('dianxiaomi_styles', plugins_url(basename(dirname(__FILE__))) . '/assets/css/admin.css');
            }

            public function library_scripts()
            {
                wp_enqueue_script('dianxiaomi_styles_chosen_jquery', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.jquery.min.js');
                wp_enqueue_script('dianxiaomi_styles_chosen_proto', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.proto.min.js');
                wp_enqueue_script('dianxiaomi_script_util', plugins_url(basename(dirname(__FILE__))) . '/assets/js/util.js');
                wp_enqueue_script('dianxiaomi_script_couriers', plugins_url(basename(dirname(__FILE__))) . '/assets/js/couriers.js');
                wp_enqueue_script('dianxiaomi_script_admin', plugins_url(basename(dirname(__FILE__))) . '/assets/js/admin.js');
            }
            public function include_footer_script()
            {
                wp_enqueue_script('dianxiaomi_script_footer', plugins_url(basename(dirname(__FILE__))) . '/assets/js/footer.js', true);
            }

            /**
             * Add the meta box for shipment info on the order page
             *
             * @access public
             */
            public function add_meta_box()
            {
                add_meta_box('woocommerce-dianxiaomi', __('Dianxiaomi', 'wc_dianxiaomi'), array(&$this, 'meta_box'), 'shop_order', 'side', 'high');
            }

            /**
             * Show the meta box for shipment info on the order page
             *
             * @access public
             */
            public function meta_box()
            {

                // just draw the layout, no data
                global $post;

                $selected_provider = get_post_meta($post->ID, '_dianxiaomi_tracking_provider', true);

                echo '<div id="dianxiaomi_wrapper">';

                echo '<p class="form-field"><label for="dianxiaomi_tracking_provider">' . __('Carrier:', 'wc_dianxiaomi') . '</label><br/><select id="dianxiaomi_tracking_provider" name="dianxiaomi_tracking_provider" class="chosen_select" style="width:100%">';
                if ($selected_provider == '') {
                    $selected_text = 'selected="selected"';
                } else {
                    $selected_text = '';
                }
                echo '<option disabled ' . $selected_text . ' value="">Please Select</option>';
                echo '</select>';
                echo '<br><a href="options-general.php?page=dianxiaomi-setting-admin">Update carrier list</a>';
                echo '<input type="hidden" id="dianxiaomi_tracking_provider_hidden" value="' . $selected_provider . '"/>';
                echo '<input type="hidden" id="dianxiaomi_couriers_selected" value="' . $this->couriers . '"/>';

                foreach ($this->dianxiaomi_fields as $field) {
                    if ($field['type'] == 'date') {
                        woocommerce_wp_text_input(array(
                            'id' => $field['id'],
                            'label' => __($field['label'], 'wc_dianxiaomi'),
                            'placeholder' => $field['placeholder'],
                            'description' => $field['description'],
                            'class' => $field['class'],
                            'value' => ($date = get_post_meta($post->ID, '_' . $field['id'], true)) ? date('Y-m-d', $date) : ''
                        ));
                    } else {
                        woocommerce_wp_text_input(array(
                            'id' => $field['id'],
                            'label' => __($field['label'], 'wc_dianxiaomi'),
                            'placeholder' => $field['placeholder'],
                            'description' => $field['description'],
                            'class' => $field['class'],
                            'value' => get_post_meta($post->ID, '_' . $field['id'], true),
                        ));
                    }
                }

                //
                //				woocommerce_wp_text_input(array(
                //					'id' => 'dianxiaomi_tracking_provider_name',
                //					'label' => __('', 'wc_dianxiaomi'),
                //					'placeholder' => '',
                //					'description' => '',
                //					'class' => 'hidden',
                //					'value' => get_post_meta($post->ID, '_dianxiaomi_tracking_provider_name', true),
                //				));
                //
                //				woocommerce_wp_text_input(array(
                //					'id' => 'dianxiaomi_tracking_required_fields',
                //					'label' => __('', 'wc_dianxiaomi'),
                //					'placeholder' => '',
                //					'description' => '',
                //					'class' => 'hidden',
                //					'value' => get_post_meta($post->ID, '_dianxiaomi_tracking_required_fields', true),
                //				));
                //
                //				woocommerce_wp_text_input(array(
                //					'id' => 'dianxiaomi_tracking_number',
                //					'label' => __('Tracking number:', 'wc_dianxiaomi'),
                //					'placeholder' => '',
                //					'description' => '',
                //					'value' => get_post_meta($post->ID, '_dianxiaomi_tracking_number', true),
                //				));
                //
                //				woocommerce_wp_text_input(array(
                //					'id' => 'dianxiaomi_tracking_shipdate',
                //					'label' => __('Date shipped:', 'wc_dianxiaomi'),
                //					'placeholder' => 'YYYY-MM-DD',
                //					'description' => '',
                //					'class' => 'date-picker-field hidden-field',
                //					'value' => ($date = get_post_meta($post->ID, '_dianxiaomi_tracking_shipdate', true)) ? date('Y-m-d', $date) : ''
                //				));
                //
                //				woocommerce_wp_text_input(array(
                //					'id' => 'dianxiaomi_tracking_postal',
                //					'label' => __('Postal Code:', 'wc_dianxiaomi'),
                //					'placeholder' => '',
                //					'description' => '',
                //					'class' => 'hidden-field',
                //					'value' => get_post_meta($post->ID, '_dianxiaomi_tracking_postal', true),
                //				));
                //
                //				woocommerce_wp_text_input(array(
                //					'id' => 'dianxiaomi_tracking_account',
                //					'label' => __('Account name:', 'wc_dianxiaomi'),
                //					'placeholder' => '',
                //					'description' => '',
                //					'class' => 'hidden-field',
                //					'value' => get_post_meta($post->ID, '_dianxiaomi_tracking_account', true),
                //				));
                //
                //                woocommerce_wp_text_input(array(
                //                    'id' => 'dianxiaomi_tracking_key',
                //                    'label' => __('Tracking key:', 'wc_dianxiaomi'),
                //                    'placeholder' => '',
                //                    'description' => '',
                //                    'class' => 'hidden-field',
                //                    'value' => get_post_meta($post->ID, '_dianxiaomi_tracking_key', true),
                //                ));
                //
                //                woocommerce_wp_text_input(array(
                //                    'id' => 'dianxiaomi_tracking_destination_country',
                //                    'label' => __('Destination Country:', 'wc_dianxiaomi'),
                //                    'placeholder' => '',
                //                    'description' => '',
                //                    'class' => 'hidden-field',
                //                    'value' => get_post_meta($post->ID, '_dianxiaomi_tracking_destination_country', true),
                //                ));
                echo '</div>';
            }

            /**
             * Order Downloads Save
             *
             * Function for processing and storing all order downloads.
             */
            public function save_meta_box($post_id, $post)
            {
                if (isset($_POST['dianxiaomi_tracking_number'])) {
                    //
                    //                    // Download data
                    $tracking_provider = woocommerce_clean($_POST['dianxiaomi_tracking_provider']);
                    //                    $tracking_number = woocommerce_clean($_POST['dianxiaomi_tracking_number']);
                    //                    $tracking_provider_name = woocommerce_clean($_POST['dianxiaomi_tracking_provider_name']);
                    //                    $tracking_required_fields = woocommerce_clean($_POST['dianxiaomi_tracking_required_fields']);
                    //                    $shipdate = woocommerce_clean(strtotime($_POST['dianxiaomi_tracking_shipdate']));
                    //                    $postal = woocommerce_clean($_POST['dianxiaomi_tracking_postal']);
                    //                    $account = woocommerce_clean($_POST['dianxiaomi_tracking_account']);
                    //                    $tracking_key = woocommerce_clean($_POST['dianxiaomi_tracking_key']);
                    //                    $tracking_destination_country = woocommerce_clean($_POST['dianxiaomi_tracking_destination_country']);
                    //
                    //                    // Update order data
                    update_post_meta($post_id, '_dianxiaomi_tracking_provider', $tracking_provider);
                    //                    update_post_meta($post_id, '_dianxiaomi_tracking_number', $tracking_number);
                    //                    update_post_meta($post_id, '_dianxiaomi_tracking_provider_name', $tracking_provider_name);
                    //                    update_post_meta($post_id, '_dianxiaomi_tracking_required_fields', $tracking_required_fields);
                    //                    update_post_meta($post_id, '_dianxiaomi_tracking_shipdate', $shipdate);
                    //                    update_post_meta($post_id, '_dianxiaomi_tracking_postal', $postal);
                    //                    update_post_meta($post_id, '_dianxiaomi_tracking_account', $account);
                    //                    update_post_meta($post_id, '_dianxiaomi_tracking_key', $tracking_key);
                    //                    update_post_meta($post_id, '_dianxiaomi_tracking_destination_country', $tracking_destination_country);


                    foreach ($this->dianxiaomi_fields as $field) {
                        if ($field['type'] == 'date') {
                            update_post_meta($post_id, '_' . $field['id'], woocommerce_clean(strtotime($_POST[$field['id']])));
                        } else {
                            update_post_meta($post_id, '_' . $field['id'], woocommerce_clean($_POST[$field['id']]));
                        }
                    }
                }
            }

            /**
             * Display the API key info for a user
             *
             * @since 2.1
             * @param WP_User $user
             */
            public function add_api_key_field($user)
            {

                if (!current_user_can('manage_dianxiaomi'))
                    return;

                if (current_user_can('edit_user', $user->ID)) {
?>
                    <h3>Dianxiaomi</h3>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="dianxiaomi_wp_api_key"><?php _e('Dianxiaomi\'s WordPress API Key', 'dianxiaomi'); ?></label>
                                </th>
                                <td>
                                    <?php if (empty($user->dianxiaomi_wp_api_key)) : ?>
                                        <input name="dianxiaomi_wp_generate_api_key" type="checkbox" id="dianxiaomi_wp_generate_api_key" value="0" />
                                        <span class="description"><?php _e('Generate API Key', 'dianxiaomi'); ?></span>
                                    <?php else : ?>
                                        <code id="dianxiaomi_wp_api_key"><?php echo $user->dianxiaomi_wp_api_key ?></code>
                                        <br />
                                        <input name="dianxiaomi_wp_generate_api_key" type="checkbox" id="dianxiaomi_wp_generate_api_key" value="0" />
                                        <span class="description"><?php _e('Revoke API Key', 'dianxiaomi'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
<?php
                }
            }

            /**
             * Generate and save (or delete) the API keys for a user
             *
             * @since 2.1
             * @param int $user_id
             */
            public function generate_api_key($user_id)
            {

                if (current_user_can('edit_user', $user_id)) {

                    $user = get_userdata($user_id);

                    // creating/deleting key
                    if (isset($_POST['dianxiaomi_wp_generate_api_key'])) {

                        // consumer key
                        if (empty($user->dianxiaomi_wp_api_key)) {

                            $api_key = 'ck_' . hash('md5', $user->user_login . date('U') . mt_rand());

                            update_user_meta($user_id, 'dianxiaomi_wp_api_key', $api_key);
                        } else {

                            delete_user_meta($user_id, 'dianxiaomi_wp_api_key');
                        }
                    }
                }
            }

            /**
             * Display Shipment info in the frontend (order view/tracking page).
             *
             * @access public
             */
            function display_tracking_info($order_id, $for_email = false)
            {
                if ($this->plugin == 'dianxiaomi') {
                    $this->display_order_dianxiaomi($order_id, $for_email);
                } else if ($this->plugin == 'wc-shipment-tracking') { //$49
                    $this->display_order_wc_shipment_tracking($order_id, $for_email);
                }
            }

            private function display_order_dianxiaomi($order_id, $for_email)
            {
                //print_r($this->dianxiaomi_fields);
                $values = array();
                foreach ($this->dianxiaomi_fields as $field) {
                    $values[$field['id']] = get_post_meta($order_id, '_' . $field['id'], true);
                    if ($field['type'] == 'date' && $values[$field['id']]) {
                        $values[$field['id']] = date_i18n(__('l jS F Y', 'wc_shipment_tracking'), $values[$field['id']]);
                    }
                }
                $values['dianxiaomi_tracking_provider'] = get_post_meta($order_id, '_dianxiaomi_tracking_provider', true);

                if (!$values['dianxiaomi_tracking_provider'])
                    return;

                if (!$values['dianxiaomi_tracking_number'])
                    return;


                $options = get_option('dianxiaomi_option_name');
                if (array_key_exists('track_message_1', $options) && array_key_exists('track_message_2', $options)) {
                    $track_message_1 = $options['track_message_1'];
                    $track_message_2 = $options['track_message_2'];
                } else {
                    $track_message_1 = 'Your order was shipped via ';
                    $track_message_2 = 'Tracking number is ';
                }

                $required_fields_values = array();
                $provider_required_fields = explode(",", $values['dianxiaomi_tracking_required_fields']);

                for ($i = 0; $i < count($provider_required_fields); $i++) {
                    $field = $provider_required_fields[$i];
                    foreach ($this->dianxiaomi_fields as $dianxiaomi_field) {
                        if (array_key_exists('key', $dianxiaomi_field) && $field == $dianxiaomi_field['key']) {
                            array_unshift($required_fields_values, $values[$dianxiaomi_field['id']]);
                        }
                    }
                }

                if (count($required_fields_values)) {
                    $required_fields_msg = ' (' . join(', ', $required_fields_values) . ')';
                } else {
                    $required_fields_msg = '';
                }

                //print_r($values);

                // echo $track_message_1 . $values['dianxiaomi_tracking_provider_name'] . '<br/>' . $track_message_2 . $values['dianxiaomi_tracking_number'] . $required_fields_msg;


                $dianxiaomi_tracking_provider_name = get_post_meta($order_id, '_dianxiaomi_tracking_provider_name', true);
                $custom_domain = $this->custom_domain;
                if (strpos($custom_domain, "http") === false) {
                    $custom_domain = 'https://t.17track.net/en#nums=';
                }

                $dianxiaomi_tracking_number = $values['dianxiaomi_tracking_number'];
                $dianxiaomi_tracking_provider = $values['dianxiaomi_tracking_provider'];

                if ($dianxiaomi_tracking_provider_name == '4PX Express' || $dianxiaomi_tracking_provider_name == '4PX') {
                    echo $track_message_1 . $dianxiaomi_tracking_provider_name . '<br/>' . $track_message_2 . '<a target="_blank" href="http://track.4px.com/#/result/0/' . $dianxiaomi_tracking_number . '">' . $dianxiaomi_tracking_number . '</a>.' . $required_fields_msg;
                } else if ($dianxiaomi_tracking_provider_name == 'DHL') {
                    echo $track_message_1 . $dianxiaomi_tracking_provider_name . '<br/>' . $track_message_2 . '<a target="_blank" href="http://www.dhl.com/en/express/tracking.html?AWB=' . $dianxiaomi_tracking_number . '&brand=DHL">' . $dianxiaomi_tracking_number . '</a>.' . $required_fields_msg;
                } else if ($dianxiaomi_tracking_provider_name == 'FedEx') {
                    echo $track_message_1 . $dianxiaomi_tracking_provider_name . '<br/>' . $track_message_2 . '<a target="_blank" href="https://www.fedex.com/apps/fedextrack/?action=track&tracknumbers=' . $dianxiaomi_tracking_number . '">' . $dianxiaomi_tracking_number . '</a>.' . $required_fields_msg;
                } else if ($dianxiaomi_tracking_provider_name == 'UPS') {
                    echo $track_message_1 . $dianxiaomi_tracking_provider_name . '<br/>' . $track_message_2 . '<a target="_blank" href="https://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=' . $dianxiaomi_tracking_number . '">' . $dianxiaomi_tracking_number . '</a>.' . $required_fields_msg;
                }  else if (!empty($dianxiaomi_tracking_provider_name)) {
                    echo $track_message_1 . $dianxiaomi_tracking_provider_name . '.<br/>' . $track_message_2 . '<a target="_blank" href="' . $custom_domain . $dianxiaomi_tracking_number . '">' .  $dianxiaomi_tracking_number . '</a>.' . $required_fields_msg;
                } else {
                    echo $track_message_2 . '<a target="_blank" href="' . $custom_domain . $dianxiaomi_tracking_number . '">' . $dianxiaomi_tracking_number . '</a>.' . $required_fields_msg;
                }

                if (!$for_email && $this->use_track_button) {
                    $this->display_track_button($values['dianxiaomi_tracking_provider'], $values['dianxiaomi_tracking_number'], $required_fields_values);
                }

                //-------------------------------------------------------------------------------------
                /*
                                $tracking_provider = get_post_meta($order_id, '_dianxiaomi_tracking_provider', true);
                                $tracking_number = get_post_meta($order_id, '_dianxiaomi_tracking_number', true);
                                $tracking_provider_name = get_post_meta($order_id, '_dianxiaomi_tracking_provider_name', true);
                                $tracking_required_fields = get_post_meta($order_id, '_dianxiaomi_tracking_required_fields', true);
                                $date_shipped = get_post_meta($order_id, '_dianxiaomi_tracking_shipdate', true);
                                $postcode = get_post_meta($order_id, '_dianxiaomi_tracking_postal', true);
                                $account = get_post_meta($order_id, '_dianxiaomi_tracking_account', true);

                                if (!$tracking_provider)
                                    return;

                                if (!$tracking_number)
                                    return;

                                $provider_name = $tracking_provider_name;
                                $provider_required_fields = explode(",", $tracking_required_fields);

                                $date_shipped_str = '';
                                $postcode_str = '';
                                $account_str = '';

                                foreach ($provider_required_fields as $field) {
                                    if ($field == 'tracking_ship_date') {
                                        if ($date_shipped) {
                                            $date_shipped_str = '&nbsp;' . sprintf(__('on %s', 'wc_shipment_tracking'), date_i18n(__('l jS F Y', 'wc_shipment_tracking'), $date_shipped));
                                        }
                                    } else if ($field == 'tracking_postal_code') {
                                        if ($postcode) {
                                            $postcode_str = '&nbsp;' . sprintf('The postal code is %s.', $postcode);
                                        }
                                    } else if ($field == 'tracking_account_number') {
                                        if ($account) {
                                            $account_str = '&nbsp;' . sprintf('The account is %s.', $account);
                                        }
                                    }
                                }

                                $provider_name = '&nbsp;' . __('via', 'wc_shipment_tracking') . ' <strong>' . $provider_name . '</strong>';

                                echo wpautop(sprintf(__('Your order was shipped%s%s. Tracking number is %s.%s%s', 'wc_shipment_tracking'), $date_shipped_str, $provider_name, $tracking_number, $postcode_str, $account_str));

                                if (!$for_email && $this->use_track_button) {
                                    $this->display_track_button($tracking_provider, $tracking_number);
                                }
                */
            }

            private function display_order_wc_shipment_tracking($order_id, $for_email)
            {
                if ($for_email || !$this->use_track_button) {
                    return;
                }

                $tracking = get_post_meta($order_id, '_tracking_number', true);
                $sharp = strpos($tracking, '#');
                $colon = strpos($tracking, ':');
                $required_fields = array();
                if ($sharp && $colon && $sharp >= $colon) {
                    return;
                } else if (!$sharp && $colon) {
                    return;
                } else if ($sharp) {
                    $tracking_provider = substr($tracking, 0, $sharp);
                    if ($colon) {
                        $tracking_number = substr($tracking, $sharp + 1, $colon - $sharp - 1);
                        $temp = substr($tracking, $sharp + 1, strlen($tracking));
                        $required_fields = explode(':', $temp);
                    } else {
                        $tracking_number = substr($tracking, $sharp + 1, strlen($tracking));
                    }
                } else {
                    $tracking_provider = '';
                    $tracking_number = $tracking;
                }
                if ($tracking_number) {
                    $this->display_track_button($tracking_provider, $tracking_number, $required_fields);
                }
            }

            /**
             * Display shipment info in customer emails.
             *
             * @access public
             * @return void
             */
            function email_display($order)
            {
                $this->display_tracking_info($order->id, true);
            }

            private function display_track_button($tracking_provider, $tracking_number, $required_fields_values)
            {

                // $js = '(function(e,t,n){var r,i=e.getElementsByTagName(t)[0];if(e.getElementById(n))return;r=e.createElement(t);r.id=n;r.src="/wp-content/plugins/dianxiaomi/assets/js/track-button.js";i.parentNode.insertBefore(r,i)})(document,"script","trackdog-jssdk")';

                $js = '(function(e,t,n){})(document,"script","trackdog-jssdk")';

                if (function_exists('wc_enqueue_js')) {
                    wc_enqueue_js($js);
                } else {
                    global $woocommerce;
                    $woocommerce->add_inline_js($js);
                }

                if (count($required_fields_values)) {
                    $tracking_number = $tracking_number . ':' . join(':', $required_fields_values);
                }

                $temp_url = '';
                $temp_slug = ' data-slug="' . $tracking_provider . '"';
                if ($this->custom_domain != '') {
                    $temp_url = '" data-domain="' . $this->custom_domain;
                    $temp_slug = '';
                }

                $this->display_track_button_html($this->custom_domain, $tracking_number, $tracking_provider);

                // $track_button = '<div id="as-root"></div><div class="as-track-button"' . $temp_slug . ' data-tracking-number="' . $tracking_number . $temp_url .'" data-support="true" data-width="400" data-size="normal" data-hide-tracking-number="true"></div>';
                // echo wpautop(sprintf('%s', $track_button));
                echo "<br><br>";
            }


            private function display_track_button_html($custom_domain, $tracking_number, $tracking_provider)
            {
                $css = '<style>.btn{position:relative; border-radius: 4px;text-decoration: none !important; border:2px solid #1e88e5;text-align:left;background-color:#1e88e5;color:#fff !important;font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;} .btn:hover{border-color: #1c95ff;background-color: #1c95ff;} .btn span{font-size: 16px;vertical-align: middle;}.btn.a:focus,.btn.a:hover{border-color:#1c95ff;background-color:#1c95ff;}.btn.a{padding:10px 6px 12px;border-radius:4px;outline:0} .btn.a{background-color:transparent}.btn.a:active,.btn.a:hover{outline:0}*,:after,:before{box-sizing:border-box}.tracking-widget .fluid-input-wrapper{display:block;overflow:hidden}.-has-tracking-number .fluid-input-wrapper{float:left}.tracking-widget input{padding:2px 6px 3px;width:100%}.tracking-widget .btn{float:right;padding:4px 10px 3px 36px;margin-left:7px}.tracking-widget .-has-tracking-number .btn,.tracking-widget .-hidden-tracking-number .btn{float:none}.tracking-widget .text-large{font-size:17.5px;padding:10px 6px 12px}.tracking-widget .btn-large{font-size:17.5px;padding:10px 20px 12px 58px}.tracking-widget .text-small{padding:2px 6px 3px;font-size:12px}.tracking-widget .btn-small{padding:2px 10px 3px 32px;font-size:12px}.icon-trackdog{left:9px;top:7px;width:17px;height:19px}.tracking-widget .btn-small .icon-trackdog{left:9px;top:7px;height:19px;width:16px}.icon-trackdog,.icon-trackdog.-large{height:28px;width:24px}.tracking-widget .btn-large .icon-trackdog{left:20px;top:7px;height:28px;width:24px}.ie9 .tracking-widget .btn-small .icon-trackdog{top:0}.-hidden-tracking-number .btn{margin-left:0}.tracking-widget+.tracking-widget{margin-top:20px}.icon-trackdog{position:absolute;display:inline-block;background-repeat:no-repeat;background-position:0 0}.tracking-widget .icon-trackdog{height:21px}.tracking-copyright{font-size:12px;padding:3px 3px 0;text-align:left}.tracking-preset{line-height:28px}.tracking-preset.large{line-height:47px}.tracking-preset.small{font-size:14px;line-height:24px} .tracking-widget .btn{padding: 1px 20px;}</style>';

                echo $css;

                $go_url = $custom_domain;

                //check is 17track add params
                if (strpos($custom_domain, "17track") > -1) {
                    $go_url = $custom_domain . $tracking_number . "&pf=wc_d&pf_c=" . urlencode($tracking_provider);
                }

                //show track button
                $html = '<div class="tracking-widget"><div class="tracking-widget -has-tracking-number"><a class="btn" href="//' . $go_url . '" target="_blank">
                             <span class="btn_text">Track</span>
                         </a></div></div>';
                echo $html;
            }
        }

        if (!function_exists('getDianxiaomiInstance')) {
            function getDianxiaomiInstance()
            {
                return Dianxiaomi::Instance();
            }
        }
    }

    /**
     * Register this class globally
     */
    $GLOBALS['dianxiaomi'] = getDianxiaomiInstance();
}
