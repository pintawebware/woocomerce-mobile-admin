<?php

function filterNull($param, $default = 0)
{
    return (isset($param) && $param) ? $param : $default;
}


function get_order_statuses()
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


function changeOrderStatus($orderID = 0, $statusID = 0, $comment = '', $inform = false)
{
    if (!$_REQUEST['route']) return;
    $post_object = get_post( $orderID);
    $old_status = $post_object->post_status;

//    if ($old_status == $statusID  && !$comment) return false;

        if ($old_status !== $statusID  || $comment) :
        $post = [
            'ID' => $orderID,
            'post_status' => $statusID,
        ];
        wp_update_post($post);

        $post2 = [
            'post_status' => $statusID,
            'comment_post_ID' => $orderID,
            'comment_author' => 'WooCommerce',
            'comment_approved' => 1,
            'comment_agent' =>'WooCommerce',
            'comment_type' => 'order_note',
            'comment_content' => $comment . ' ' . sprintf('Статус заказа изменен с %s на %s.', get_order_statuses()[$old_status],
                    get_order_statuses()[$statusID])
        ];
        wp_insert_comment($post2);
        endif;

        if($inform) {

//            $orderonfo = (new FunctionsClass())->getOrders($orderID);
//            $email = $orderonfo['email'];
//            if ($email) {
//                pinta_push_change_status($post_object, $orderonfo, get_order_statuses()[$old_status], get_order_statuses()[$statusID]);
//
//            }

        }
        return true;
}

//add_action('init', 'changeOrderStatus');


function pinta_push_change_status($post_order, $orderonfo, $oldstatus, $newstatus)
{
    $type = 'order_changed';
    $data = array("type" => $type);

    $data['status'] = $post_order->post_status;

    $url = get_site_url();
    $url = str_replace("http://", "", $url);
    $url = str_replace("https://", "", $url);

            $currency_code = get_woocommerce_currency();

//            if (function_exists('wc_get_order_status_name')) {
                $order_status_code = $post_order->post_status;
//            }

            $message = array(
                "push_notif_type" => $type,
                "order_id" => $post_order->ID,
                "customer_name" => $orderonfo["fio"],
                "email" => $orderonfo['email'],
                "old_status" => $oldstatus,
                "new_status_code" => $newstatus,
                "total" => $orderonfo["total"] . ' ' . $orderonfo["currency_code"],
                "store_url" => $url,
//                "app_connection_id" => $push_device['app_connection_id']
            );

//            sendPush2Google($push_device['setting_id'], $push_device['registration_id'], $message);

//    var_dump($message); exit;
    return true;
}

