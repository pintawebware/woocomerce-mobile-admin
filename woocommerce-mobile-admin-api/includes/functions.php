<?php
if ( ! function_exists( 'get_home_path' ) ) {
    include_once ABSPATH . '/wp-admin/includes/file.php';
}
require_once(get_home_path() . "wp-includes/pluggable.php");
require_once(get_home_path() . "wp-includes/class-phpmailer.php");
include_once(WOOCOMMERCE_PINTA_DIR . 'includes/FunctionsClass.php');

function wma_filterNull($param, $default = 0)
{
    return (isset($param) && $param) ? $param : $default;
}


function wma_get_order_statuses()
{
    if (function_exists('wc_get_order_statuses')) {
        return wc_get_order_statuses();
    }

    $statuses = (array)get_terms('shop_order_status', array('hide_empty' => 0, 'orderby' => 'id'));

    $statuses_arr = array();
    if ($statuses):
        foreach ($statuses as $status) {
            $statuses_arr[$status->slug] = $status->name;
        }
    endif;
    return $statuses_arr;
}

//add_action('phpmailer_init', 'send_smtp_email');
//function send_smtp_email($phpmailer)
//{
//    // Define that we are sending with SMTP
//    $phpmailer->isSMTP();
//
//    // The hostname of the mail server
//    $phpmailer->Host = "smtp.yandex.ru";
//
//    // Use SMTP authentication (true|false)
//    $phpmailer->SMTPAuth = true;
//
//    // SMTP port number - likely to be 25, 465 or 587
//    $phpmailer->Port = "465";
//
//    // Username to use for SMTP authentication
//    $phpmailer->Username = "vikulya.grishko@yandex.ru";
//    $phpmailer->From = "vikulya.grishko@yandex.ru"; // должен соответствовать  $phpmailer->Username
//
//    $phpmailer->FromName = get_site_option('site_name') ? get_site_option('site_name') : "Victoria";
//
//    // Password to use for SMTP authentication
//    $phpmailer->Password = "05052011r";
//
//    // The encryption system to use - ssl (deprecated) or tls
//    $phpmailer->SMTPSecure = "ssl";
//}

function wma_changeOrderStatus($orderID = 0, $statusID = 0, $comment = '', $inform = false, $lang )
{
    if (!$_REQUEST['route']) return;
    $post_object = get_post($orderID);
    $old_status = $post_object->post_status;

    if ($old_status !== $statusID || $comment) :
        $post = [
            'ID' => $orderID,
            'post_status' => $statusID,
        ];

        $post2 = [
            'post_status' => $statusID,
            'comment_post_ID' => $orderID,
            'comment_author' => 'WooCommerce',
            'comment_approved' => 1,
            'comment_agent' => 'WooCommerce',
            'comment_type' => 'order_note',
            'comment_content' => $comment . ' ' . sprintf('Статус заказа изменен с %s на %s.', get_order_statuses()[$old_status],
                    get_order_statuses()[$statusID])
        ];
        wp_insert_comment($post2);
    endif;

    if ($inform) {
        $orderinfo = (new WMAFunctionsClass())->getOrders($orderID);
        $billingEmail = $orderinfo['email'];
        if ($billingEmail) {
            $from_name = get_site_option('site_name') == '' ? 'WordPress' : esc_html(get_site_option('site_name'));

            $admin_email = get_option('admin_email');
            $message_headers = "From: \"{$from_name}\" <{$admin_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";

          WC_Post_types::register_post_types(); // Necessary for sending emails.
          require_once(WC_ABSPATH . '/includes/wc-template-functions.php'); // Necessary for sending emails.
          wp_templating_constants(); // Necessary for sending emails.
          global $woocommerce; // Necessary for sending emails.
          $woocommerce->init(); // Necessary for sending emails.
          $GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher(); // Necessary for sending emails.
          $GLOBALS['wp_rewrite'] = new WP_Rewrite(); // Necessary for sending emails.
          WC_Emails::init_transactional_emails(); // Necessary for sending emails.
          
          $wc_order = wc_get_order($orderID);
          $wc_order->set_object_read(true);
          $wc_order->update_status($statusID);
        }
    }
    return true;
}


do_action( 'woocommerce_new_order', $order_id );// define the woocommerce_new_order callback
function action_woocommerce_new_order( $order_id ) {

    $funclass = new WMAFunctionsClass();
    $post_object = $funclass->getOrders($order_id);
    if ($post_object):
    $msg['ios'] = [
        'body'       => $post_object['total'],
        'title'      => "http://" . $_SERVER['HTTP_HOST'],
        'vibrate'    => 1,
        'sound'      => 1,
        'badge'      => 1,
        'priority'   => 'high',
        'new_order'  => [
            'order_id'      => $order_id,
            'total'         => $post_object['total'],
            'currency_code' => $post_object['currency_code'],
            'site_url'      => "http://" . $_SERVER['HTTP_HOST'],
        ],
        'event_type' => 'new_order'
    ];

    $msg['android'] = [
        'new_order'  => [
            'order_id'      => $order_id,
            'total'         => $post_object['total'],
            'currency_code' => $post_object['currency_code'],
            'site_url'      => "http://" . $_SERVER['HTTP_HOST'],
        ],
        'event_type' => 'new_order'
    ];

    $devices = $funclass->getAllUserDevices(); # отправляем на все девайсы????

    if ($devices):
        $ids = [];
    foreach ( $devices as  $dev ):
        $ids[$dev['os_type']][] = $dev['device_token'];
    endforeach;
        if ($ids):
        foreach ( $ids as  $key => $dev ):
            if ( $key == 'android' ) {
                if (array_key_exists($key, $msg)):
                    $funclass->sendCurl( [
                        'registration_ids' => $ids[$key],
                        'data'             => $msg[$key],
                    ] );
                endif;;
            } else {
                if (array_key_exists($key, $msg)):
                    $funclass->sendCurl( [
                        'registration_ids' => $ids[$key],
                        'notification'     => $msg[$key],
                    ] );
                endif;;
            }
        endforeach;
        endif;
    endif;
    endif;

};

// add the action
add_action( 'woocommerce_new_order', 'action_woocommerce_new_order', 10, 1 );

