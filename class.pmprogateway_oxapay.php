<?php
/*
Plugin Name:A PMPro OxaPay Gateway Customized
Plugin URI: https://OxaPay.com
Description: OxaPay Payment Gateway integration for Paid Memberships Pro.
Version: 1.0
Author: ethanwong.online
Author URI: https://OxaPay.com
*/
// 確保 PMP 相關的 class 和 function 存在
if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!class_exists('PMProGateway')) {
    exit('Paid Memberships Pro is required for this plugin to work. Please install and activate Paid Memberships Pro first.');
}

DEFINE('OXAPAYPMPETHAN', "oxapay-paidmembershipspro-ethan");
// paid memberships pro required

add_action('init', array('PMProGateway_oxapay', 'init'));
add_action("pmpro_checkout_boxes", array('PaidMembershipsPro_CheckoutBoxs_OxaPay', 'checkout_boxes'));

add_filter('pmpro_valid_gateways', 'pmpro_oxapay_valid_gateways');

class PMProGateway_oxapay extends PMProGateway
{
    function __construct($gateway = NULL)
    {
        $this->gateway = $gateway;
        return $this->gateway;
    }
    static function init()
    {

        add_filter('pmpro_gateways', array('PMProGateway_oxapay', 'pmpro_gateways'));

        add_filter('pmpro_payment_options', array('PMProGateway_oxapay', 'pmpro_payment_options'));

        //background
        add_filter('pmpro_payment_option_fields', array('PMProGateway_oxapay', 'pmpro_payment_option_fields'), 10, 2);

        $gateway = pmpro_getGateway();


        if ($gateway == "oxapay") {
            add_filter('pmpro_include_billing_address_fields', '__return_false');
            add_filter('pmpro_include_payment_information_fields', '__return_false');
            add_filter('pmpro_required_billing_fields', array('PMProGateway_oxapay', 'pmpro_required_billing_fields'));
        }
        if (isset($_GET['oxapaygateway']) && $_GET['oxapaygateway'] === 'oxapay') {
            self::process_ipn();
            exit;
        }
    }
    static function pmpro_gateways($gateways)
    {
        if (empty($gateways['oxapay'])) {
            $gateways['oxapay'] = __('OxaPay', 'paid-memberships-pro');
        }
        return $gateways;
    }
    static function getGatewayOptions()
    {
        $options = array(
            'oxapay_merchant_id',
            'oxapay_lifetime'
        );

        return $options;
    }
    static function pmpro_payment_options($options)
    {
        $oxapay_options = PMProGateway_oxapay::getGatewayOptions();
        $options = array_merge($oxapay_options, $options);

        return $options;
    }

    public function pmpro_oxapay_payment_options($options)
    {
        $oxapay_options = array(
            'oxapay_merchant_id',
            'oxapay_lifetime'
        );
        return array_merge($options, $oxapay_options);
    }

    static function pmpro_required_billing_fields($fields)
    {
        unset($fields['bfirstname']);
        unset($fields['blastname']);
        unset($fields['baddress1']);
        unset($fields['bcity']);
        unset($fields['bstate']);
        unset($fields['bzipcode']);
        unset($fields['bphone']);
        unset($fields['bemail']);
        unset($fields['bcountry']);
        unset($fields['CardType']);
        unset($fields['AccountNumber']);
        unset($fields['ExpirationMonth']);
        unset($fields['ExpirationYear']);
        unset($fields['CVV']);
        return $fields;
    }
    static function pmpro_payment_option_fields($values, $gateway)
    {
?>
        <tr class="oxapay_gateway" <?php if ($gateway != "oxapay") { ?>style="display: none;" <?php } ?>>
            <td colspan="2">
                <hr />
                <h2 class="title" style="font-size: 18px; font-weight: 500; margin-bottom: 0; margin-left: -10px;">
                    <?php esc_html_e('OxaPay Settings', 'paid-memberships-pro'); ?>
                </h2>
            </td>
        </tr>

        <tr class="oxapay_merchant_id" <?php if ($gateway != "oxapay") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="oxapay_merchant_id"><?php esc_html_e('OxaPay Merchant Key:', 'paid-memberships-pro'); ?></label>
            </th>
            <td>
                <input type="text" id="oxapay_merchant_id" name="oxapay_merchant_id" value="<?php echo esc_attr($values['oxapay_merchant_id']); ?>" class="regular-text code" />
            </td>
        </tr>

        <tr class="oxapay_gateway" <?php if ($gateway != "oxapay") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="oxapay_lifetime"><?php esc_html_e('Lifetime', 'paid-memberships-pro'); ?></label>
            </th>
            <td>
                <select id="oxapay_lifetime" name="oxapay_lifetime">
                    <option value="30" <?php selected($values['oxapay_lifetime'], '30'); ?>>30 Min</option>
                    <option value="60" <?php selected($values['oxapay_lifetime'], '60'); ?>>60 Min</option>
                    <option value="90" <?php selected($values['oxapay_lifetime'], '90'); ?>>90 Min</option>
                    <option value="120" <?php selected($values['oxapay_lifetime'], '120'); ?>>120 Min</option>
                </select>
            </td>
        </tr>

        <?php
    }
    /**
     * Summary of process
     * @param mixed $order
     * @return bool
     */
    function process(&$order)
    {
        $code = $order->code;
        $initial_payment = $order->InitialPayment;
        $initial_payment_tax = $order->getTaxForPrice($initial_payment);
        $initial_payment = round((float)$initial_payment + (float)$initial_payment_tax, 2);

        $data = array(
            'merchant'    => pmpro_getOption("oxapay_merchant_id"),
            'amount'      => floatval($initial_payment),
            'currency'    => 'USD',
            'orderId'     => $code,
            'email'       => $order->Email,
            'callbackUrl' => trailingslashit(home_url()) . '?oxapaygateway=oxapay',
            'lifeTime' => pmpro_getOption("oxapay_lifetime"),
            'returnUrl' => trailingslashit(home_url()) . "membership-account/membership-invoice/?invoice=$code&"
        );
        $url = 'https://api.oxapay.com/merchants/request';
        $options = array(
            'http' => array(
                'header' => 'Content-Type: application/json',
                'method'  => 'POST',
                'content' => json_encode($data),
            ),
        );
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response);
        if (is_wp_error($response)) {
            $message = __('Erorr', 'oxapay-payment-for-pmmp') . ' : ' . $result->get_error_message();
            $order->error = $message;
            return false;
        }
        if ($result->result == 100) {
            $order->code = $code;
            $order->payment_type = "OxaPay";
            $order->status = "review";
            $order->saveOrder();

            wp_redirect($result->payLink);
            exit;
        } else {
            $message = __('Erorr', 'oxapay-payment-for-pmmp') . ' : ' . $result->message;
            $order->error = $message;
            return false;
        }
    }
    public static function convertToWPStatus($status)
    {
        switch ($status) {
            case 'Waiting':
            case 'Confirming':
                $result = 'pending';
                break;

            case 'Failed':
            case 'Expired':
                $result = 'error';
                break;

            case 'Paid':
                $result = 'success';
                break;
        }
        return $result;
    }

    public static function process_ipn()
    {
        $postData = file_get_contents('php://input');
        $data = json_decode($postData, true);
        $apiSecretKey = pmpro_getOption("oxapay_merchant_id");
        $hmacHeader = $_SERVER['HTTP_HMAC'];
        $calculatedHmac = hash_hmac('sha512', $postData, $apiSecretKey);
        $code = $data['orderId'];
        if ($calculatedHmac === $hmacHeader) {
            $morder = new MemberOrder();
            $morder->getMemberOrderByCode($code);
            $status = self::convertToWPStatus($data['status']);
            $morder->status = $status;
            $morder->saveOrder();
            if ($status == 'success') {
                //过期shijian
                $user_id = $morder->user_id;
                $level_id = $morder->membership_id;
                pmpro_changeMembershipLevel($level_id, $user_id);
            }
            http_response_code(200);
            echo 'OK';
        } else {
            http_response_code(400);
            echo 'Invalid HMAC signature';
        }
    }
}
function pmpro_oxapay_valid_gateways($valid_gateways)
{
    if (isset($_REQUEST['gateway']) && $_REQUEST['gateway'] == 'oxapay') {
        // 如果已經選擇了 OxaPay 作為支付網關，則不需要再次添加
        // 確保 OxaPay 網關已經被添加到有效網關列表中
        $valid_gateways[] = 'oxapay';
        return $valid_gateways;
    } else {
        return $valid_gateways;
    }
}

class PaidMembershipsPro_CheckoutBoxs_OxaPay extends PMProGateway
{

    public function  __construct()
    {
        register_activation_hook(__FUNCTION__, array($this, "register_activation_hook_for_oxapay"));
        register_deactivation_hook(__FUNCTION__, array($this, "register_deactivation_hook_for_oxapay"));
    }
    public function register_activation_hook_for_oxapay()
    {
        //检测OxaPay是否已經安裝
        if (!function_exists('pmpro_getGateway')) {
            exit('Paid Memberships Pro is required for this plugin to work. Please install and activate Paid Memberships Pro first.');
        } else {
            // 在這裡可以添加激活時的邏輯
            // 例如，初始化選項或設置默認值等
            // pmpro_setOption('oxapay_merchant_id', 'your_merchant_id');
            // pmpro_setOption('oxapay_lifetime', '1');
            update_option('PaidMembershipsPro_CheckoutBoxs_OxaPay_enabled', 1);
        }
        // 在這裡可以添加激活時的邏輯
    }
    public function register_deactivation_hook_for_oxapay()
    {
        update_option('PaidMembershipsPro_CheckoutBoxs_OxaPay_enabled', 0);
    }

    // public static function checkout_boxes()
    // {
    //   //OxaPay是否激活
    //   $checkPlugin = "pmpro-oxapay/class.pmprogateway_oxapay.php";
    //   if (is_plugin_active($checkPlugin)) {
    //     echo "The OxaPay plugin is active.";
    //   } else {
    //     echo "The OxaPay plugin is not active. Please activate it to use OxaPay payment gateway.";
    //     return; // 如果插件未激活，則不顯示支付選項
    //   }


    // }
    public static function checkout_boxes()
    {
        global $pmpro_requirebilling, $gateway, $pmpro_review;

        //$gateway = "oxapay"; // Set the gateway to OxaPay
        //if already using gourl, ignore this
        $setting_gateway = get_option("pmpro_gateway");

        if ($setting_gateway == "gourl") {
            echo '<h2>' . __('Payment method', OXAPAYPMPETHAN) . '</h2>';
            echo __('Bitcoin/Altcoin', OXAPAYPMPETHAN) . '<img style="vertical-align:middle" src="' . plugins_url("/images/crypto.png", __FILE__) . '" border="0" vspace="10" hspace="10" height="43" width="143"><br><br>';
            return true;
        }

        $arr = pmpro_gateways();

        $setting_gateway_name = (isset($arr["$setting_gateway"]) && $arr["$setting_gateway"]) ? $arr["$setting_gateway"] : ucwords($setting_gateway);

        $image = $setting_gateway;
        if (in_array($image, array("paypalexpress", "paypal", "payflowpro", "paypalstandard"))) $image = "paypal";
        if (!in_array($image, array("authorizenet", "braintree", "check", "cybersource", "gourl", "paypal", "stripe", "twocheckout"))) $image = "creditcards";

        //only show this if we're not reviewing and the current gateway isn't a gourl gateway
        if (empty($pmpro_review)) {
        ?>
            <div id="pmpro_payment_method" class="pmpro_checkout" <?php if (!$pmpro_requirebilling) { ?>style="display: none;" <?php } ?>>
                <br>
                <h2><?php _e('Choose Your Payment method', OXAPAYPMPETHAN) ?> -</h2>
                <div class="pmpro_checkout-fields">
                    <span class="gateway_gourl">
                        <input type="radio" name="gateway" value="oxapay" <?php if ($gateway == "oxapay") { ?>checked="checked" <?php } ?> />
                        <a href="javascript:void(0);" class="pmpro_radio" style="box-shadow:none"><?php _e('Bitcoin/CryptoCoin', OXAPAYPMPETHAN) ?></a>
                        <img style="vertical-align:middle" src="<?php echo plugins_url("/images/crypto.png", __FILE__); ?>" border="0" vspace="10" hspace="10" height="43" width="143">
                    </span>
                    <br />
                    <span class="gateway_<?php echo esc_attr($setting_gateway); ?>">
                        <input type="radio" name="gateway" value="<?php echo esc_attr($setting_gateway); ?>" <?php if (!$gateway || $gateway == $setting_gateway) { ?>checked="checked" <?php } ?> />
                        <a href="javascript:void(0);" class="pmpro_radio" style="box-shadow:none"><?php _e($setting_gateway_name, OXAPAYPMPETHAN) ?></a>
                        <img style="vertical-align:middle" src="<?php echo plugins_url("/images/" . $image . ".png", __FILE__); ?>" border="0" vspace="10" hspace="10" height="43">
                    </span>
                </div>
            </div> <!--end pmpro_payment_method -->
            <?php //here we draw the gourl Express button, which gets moved in place by JavaScript 
            ?>
            <script>
                var pmpro_require_billing = <?php if ($pmpro_requirebilling) echo "true";
                                            else echo "false"; ?>;

                //choosing payment method
                jQuery(document).ready(function() {
                    //move gourl express button into submit box
                    jQuery('#pmpro_gourl_checkout').appendTo('div.pmpro_submit');

                    function showLiteCheckout() {
                        jQuery('#pmpro_billing_address_fields').hide();
                        jQuery('#pmpro_payment_information_fields').hide();
                        jQuery('#pmpro_paypalexpress_checkout, #pmpro_paypalstandard_checkout, #pmpro_payflowpro_checkout, #pmpro_paypal_checkout').hide();
                        jQuery('#pmpro_submit_span').show();

                        pmpro_require_billing = false;
                    }

                    function showFullCheckout() {
                        jQuery('#pmpro_billing_address_fields').show();
                        jQuery('#pmpro_payment_information_fields').show();

                        pmpro_require_billing = true;
                    }


                    //detect gateway change
                    jQuery('input[name=gateway]').click(function() {
                        if (jQuery(this).val() != 'oxapay') {
                            showFullCheckout();
                        } else {
                            showLiteCheckout();
                        }
                    });

                    //update radio on page load
                    if (jQuery('input[name=gateway]:checked').val() != 'oxapay' && pmpro_require_billing == true) {
                        showFullCheckout();
                    } else {
                        showLiteCheckout();
                    }

                    //select the radio button if the label is clicked on
                    jQuery('a.pmpro_radio').click(function() {
                        jQuery(this).prev().click();
                    });
                });
            </script>
        <?php
        } else {
        ?>
            <script>
                //choosing payment method
                jQuery(document).ready(function() {
                    jQuery('#pmpro_billing_address_fields').hide();
                    jQuery('#pmpro_payment_information_fields').hide();
                });
            </script>
<?php
        }
    }
}
new PaidMembershipsPro_CheckoutBoxs_OxaPay();

?>
