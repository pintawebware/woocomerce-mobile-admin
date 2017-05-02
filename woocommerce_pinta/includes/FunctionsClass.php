<?php

include_once(WOOCOMMERCE_PINTA_DIR . 'includes/functions.php');

/**
 * Class PintaFunctionsClass
 */
class FunctionsClass
{
    protected $status_list_hide = array("auto-draft", "draft", "trash");

    public function __construct()
    {
        $this->check_is_woo_activated();
//        add_action('woocommerce_checkout_update_order_meta', 'mobassist_push_new_order');

        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_$plugin", array(&$this, 'setting_link'));
        add_action('woocommerce_order_status_changed', 'pinta_push_change_status');
    }

    /**
     * Read order data. Can be overridden by child classes to load other props.
     *
     * @param WC_Order
     * @param object $post_object
     * @since 3.0.0
     */
    protected function read_order_data(&$order, $post_object)
    {
        $id = $order->get_id();

        $order->set_props(array(
            'currency' => get_post_meta($id, '_order_currency', true),
            'discount_total' => get_post_meta($id, '_cart_discount', true),
            'discount_tax' => get_post_meta($id, '_cart_discount_tax', true),
            'shipping_total' => get_post_meta($id, '_order_shipping', true),
            'shipping_tax' => get_post_meta($id, '_order_shipping_tax', true),
            'cart_tax' => get_post_meta($id, '_order_tax', true),
            'total' => get_post_meta($id, '_order_total', true),
            'version' => get_post_meta($id, '_order_version', true),
            'prices_include_tax' => metadata_exists('post', $id, '_prices_include_tax') ? 'yes' === get_post_meta($id, '_prices_include_tax', true) : 'yes' === get_option('woocommerce_prices_include_tax'),
        ));

        // Gets extra data associated with the order if needed.
        var_dump($order->get_extra_data_keys());
        exit;
        foreach ($order->get_extra_data_keys() as $key) {
            $function = 'set_' . $key;
            if (is_callable(array($order, $function))) {
                $order->{$function}(get_post_meta($order->get_id(), '_' . $key, true));
            }
        }
    }

    protected function getOrderHistory($id)
    {
        global $wpdb;
        $sql = "SELECT comment_date, comment_content FROM {$wpdb->comments} WHERE comment_post_ID = %s AND comment_type='order_note'";
        $sql = sprintf($sql, $id);
        $res = $wpdb->get_results($sql, ARRAY_A);

        $response = [];
        foreach ($res as $comment) {
            $result['date_added'] = $comment['comment_date'];
            $result['comment'] = $comment['comment_content'];
            $status = strstr($comment['comment_content'], 'Статус заказа изменен');
            $status = strstr($status, ' на ');

            $status = trim(str_replace([' на ', '.'], "", $status));

            $result['name'] = $status;
            $result['order_status_id'] = $this->getStatusKey($status);

            $response[] = $result;
        }

        return $response;

    }


    protected function getStatusKey($status)
    {
        $statusesArr = get_order_statuses();
        $array_flip = array_flip($statusesArr);
        return array_key_exists($status, $array_flip) ? $array_flip[$status] : 'undefined';
    }

    private function check_is_woo_activated()
    {
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $this->generate_output('module_disabled');
        }
    }

    protected function check_db()
    {
        global $wpdb;
        # Создаем новые таблицы
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}user_token (
            id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(32) NOT NULL )";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}user_device (
            id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            device_token VARCHAR(500) ,
            os_type VARCHAR(20))";
        $wpdb->query($sql);
    }

    protected function updateUserDeviceToken($old, $new)
    {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}user_device  WHERE  device_token = %s";
        $query = $wpdb->prepare($sql, $old);
        $res = $wpdb->get_row($query, ARRAY_A);
        if (!$res)
            return false;

        $sql = "UPDATE {$wpdb->prefix}user_device SET device_token = %s WHERE device_token = %s";
        $wpdb->query($wpdb->prepare(
            $sql, $new, $old
        ));

        return true;
    }

    /**
     * @param $order_id
     */
    protected function getOrdersDeliveryInfo($order_id)
    {
        global $wpdb;

        /**
         *
         * $data['payment_method'] = "Оплата при доставке";
         * $data['shipping_method'] = 'Доставка с фиксированной стоимостью доставки';
         * $data['shipping_address'] = 'проспект Карла Маркса 1, Днепропетровск, Днепропетровская область, Украина.';
         *
         */
        $fields = "SELECT
                    payment_method_title.meta_value AS payment_method,
                    order_item_name as shipping_method,
                    shipping_address_index.meta_value as shipping_address,
                    shipping_address_1.meta_value as shipping_address_house,
                    shipping_address_2.meta_value as shipping_address_flat,
                    shipping_city.meta_value as shipping_city,
                    shipping_country.meta_value as shipping_country
                    
                    ";

        $sql = " FROM {$wpdb->posts} AS posts
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items 
              ON order_items.order_id = posts.ID AND order_items.order_item_type = 'shipping'
            LEFT JOIN {$wpdb->postmeta} AS payment_method_title 
                ON payment_method_title.post_id = posts.ID AND payment_method_title.meta_key = '_payment_method_title'
            LEFT JOIN {$wpdb->postmeta} AS shipping_address_index 
                ON shipping_address_index.post_id = posts.ID AND shipping_address_index.meta_key = '_shipping_address_index'                
            LEFT JOIN {$wpdb->postmeta} AS shipping_address_1 
                ON shipping_address_1.post_id = posts.ID AND shipping_address_1.meta_key = '_shipping_address_1'               
            LEFT JOIN {$wpdb->postmeta} AS shipping_address_2 
                ON shipping_address_2.post_id = posts.ID AND shipping_address_2.meta_key = '_shipping_address_2'              
            LEFT JOIN {$wpdb->postmeta} AS shipping_city 
                ON shipping_city.post_id = posts.ID AND shipping_city.meta_key = '_shipping_city'             
            LEFT JOIN {$wpdb->postmeta} AS shipping_country 
                ON shipping_country.post_id = posts.ID AND shipping_country.meta_key = '_shipping_country'
                ";

        $query = $fields . $sql;

        $query_where_parts[] = " posts.post_type = 'shop_order' ";


        $query_where_parts[] = sprintf(" posts.ID = %s ",
            $order_id);


        if (!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        $result = $wpdb->get_row($query, ARRAY_A);
        $addr = [
            "payment_method" => $result["payment_method"],
            "shipping_method" => $result["shipping_method"],
            "shipping_address" => $result['shipping_address_house']
                . ' ' . $result['shipping_address_flat'] . ', '
                . $result['shipping_city'] . ' ' . $result['shipping_country']
        ];
        return $addr;
    }

    /**
     * @param string $order_id
     * @return array|mixed
     */
    public function getOrders($order_id = "")
    {
        $page = filterNull($_REQUEST['page'], 1);
        $limit = filterNull($_REQUEST['limit'], 10);

        $filter = filterNull($_REQUEST['filter'], []);
        $fio = filterNull($_REQUEST['fio'], '');
        $min_price = filterNull($_REQUEST['min_price'], '0');
        $max_price = filterNull($_REQUEST['max_price'], '');
        $order_status_id = filterNull($_REQUEST['order_status_id'],'');
        $date_min = filterNull($_REQUEST['date_min'], '');
        $date_max = filterNull($_REQUEST['date_max'], '');
        $sort_by = filterNull($_REQUEST['sort_by'], '');

        if ($filter) {
            $fio = filterNull($filter['fio'], '');
            $min_price = filterNull($filter['min_price'], '0');
            $max_price = filterNull($filter['max_price'], '');
            $order_status_id = filterNull($filter['order_status_id'],'');
            $date_min = filterNull($filter['date_min'], '');
            $date_max = filterNull($filter['date_max'], '');
            $sort_by = filterNull($filter['sort_by'], '');
        }

        global $wpdb;
        $sql_total_products = "SELECT SUM(meta_items_qty.meta_value)
            FROM {$wpdb->prefix}woocommerce_order_items AS order_items
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS meta_items_qty 
            ON meta_items_qty.order_item_id = order_items.order_item_id 
            AND meta_items_qty.meta_key = '_qty'
            WHERE order_items.order_item_type = 'line_item' 
            AND order_items.order_id = posts.ID";
        if (function_exists('wc_get_order_status_name')) {
            $status_code_field = "posts.post_status";
        } else {
            $status_code_field = "status_terms.slug";
        }

        $fields = "SELECT
                    posts.ID AS id_order,
                    posts.post_date AS date_add,
                    posts.post_date_gmt AS date_add_gmt,
                    meta_order_total.meta_value AS total_paid,
                    meta_order_currency.meta_value AS currency_code,
                    $status_code_field AS status_code,
                    first_name.meta_value AS first_name,
                    last_name.meta_value AS last_name,
                    CONCAT(first_name.meta_value, ' ', last_name.meta_value) AS customer,
                    users.display_name,
                    customer_id.meta_value AS customer_id,
                    ( $sql_total_products ) AS count_prods,
                    billing_first_name.meta_value AS billing_first_name,
                    billing_last_name.meta_value AS billing_last_name,
                    billing_phone.meta_value AS billing_phone,
                    customer_email.meta_value AS customer_email";

        $total_fields = "SELECT COUNT(DISTINCT(posts.ID)) AS total_orders, SUM(meta_order_total.meta_value) AS total_sales";

        $sql = " FROM {$wpdb->posts} AS posts
            LEFT JOIN {$wpdb->postmeta} AS meta_order_total ON meta_order_total.post_id = posts.ID AND meta_order_total.meta_key = '_order_total'
            LEFT JOIN {$wpdb->postmeta} AS meta_order_currency ON meta_order_currency.post_id = posts.ID AND meta_order_currency.meta_key = '_order_currency'
            LEFT JOIN {$wpdb->postmeta} AS customer_id ON customer_id.post_id = posts.ID AND customer_id.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->usermeta} AS first_name ON first_name.user_id = customer_id.meta_value AND first_name.meta_key = 'first_name'
            LEFT JOIN {$wpdb->usermeta} AS last_name ON last_name.user_id = customer_id.meta_value AND last_name.meta_key = 'last_name'
            LEFT JOIN {$wpdb->users} AS users ON users.ID = customer_id.meta_value
            LEFT JOIN {$wpdb->postmeta} AS billing_first_name ON billing_first_name.post_id = posts.ID AND billing_first_name.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} AS billing_last_name ON billing_last_name.post_id = posts.ID AND billing_last_name.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->postmeta} AS billing_phone ON billing_phone.post_id = posts.ID AND billing_phone.meta_key = '_billing_phone'
            LEFT JOIN {$wpdb->postmeta} AS customer_email ON customer_email.post_id = posts.ID AND customer_email.meta_key = '_billing_email'
        ";

        if (!function_exists('wc_get_order_status_name')) {
            $sql .= " LEFT JOIN {$wpdb->term_relationships} AS order_status_terms ON order_status_terms.object_id = posts.ID
                    AND order_status_terms.term_taxonomy_id IN (SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'shop_order_status')
                LEFT JOIN {$wpdb->terms} AS status_terms ON status_terms.term_id = order_status_terms.term_taxonomy_id";
        }

        $query = $fields . $sql;

        $query_totals = $total_fields . $sql;

        $query_where_parts[] = " posts.post_type = 'shop_order' ";

        if (!empty($this->status_list_hide)) {
            $query_where_parts[] = " posts.post_status NOT IN ( '" . implode($this->status_list_hide, "', '") . "' ) ";
        }

        /**
         * ЗАГЛУШКА!!!
         */
//        $date_max = '2017-04-28 15:49:59 +0000';
//        $date_min = '2017-04-24 15:49:59 +0000';

        /**
         * ЗАГЛУШКА!!!
         */
        if ($date_min) {
            $query_where_parts[] = sprintf(" (posts.post_date_gmt) >=  '%s' ",
                $date_min);
        }
//        var_dump($date_max); exit;
        if ($date_max) {
            $query_where_parts[] = sprintf(" (posts.post_date_gmt) <=  '%s' ",
                $date_max);
        }

        if ($fio) {
            $query_where_parts[] = sprintf(
                " first_name.meta_value LIKE '%%%s%%' 
                OR last_name.meta_value LIKE '%%%s%%' 
                OR users.display_name LIKE '%%%s%%' 
                OR customer_email.meta_value LIKE '%%%s%%' ",
                $fio,
                $fio,
                $fio,
                $fio
            );
        }

        if ($min_price) {
            $query_where_parts[] = sprintf(
                " meta_order_total.meta_value >= %s ",
                $min_price
            );
        }
        if ($max_price) {
            $query_where_parts[] = sprintf(
                " meta_order_total.meta_value <= %s ",
                (float) $max_price
            );
        }
        if ($order_status_id) {
                $query_where_parts[] = sprintf(" posts.post_status IN ('%s') ",
                     $order_status_id
                );
        }

        if ($order_id) {
            $query_where_parts[] = sprintf(" posts.ID = %s ",
                $order_id);
        }

        if (!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
            $query_totals .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        if (!filterNull($sort_by)) {
            $sort_by = "id";
        }

        $query .= " ORDER BY ";
        switch ($sort_by) {
            case 'id':
                $dir = $this->getSortDirection('DESC');
                $query .= "posts.ID " . $dir;
                break;
        }

        $query .= sprintf(" LIMIT %d, %d", (($page - 1) * $limit), $limit);

        $totals = $wpdb->get_row($query_totals, ARRAY_A);

        $order = [];
        $max_price_total = 0;
        $results = $wpdb->get_results($query, ARRAY_A);
//        var_dump($query); exit;
        $orders_status = $this->get_orders_statuses();
        if ($results && is_array($results)):
            foreach ($results as $key => $result_order) {
                $order[$key]['order_number'] = (string)$result_order['id_order'];
                $order[$key]['currency_code'] = (string)$result_order['currency_code'];
                $order[$key]['date_added'] = (string)$result_order['date_add'];
                $order[$key]['fio'] = (string)$result_order['customer'];
                $order[$key]['total'] = (string)number_format((float)$result_order['total_paid'], 2, '.', '');

                $order[$key]['status'] = (string)$this->_get_order_status_name($result_order['id_order'], $result_order['status_code']);

                if ($order_id) {
                    $order[$key]['email'] = (string)$result_order['customer_email'];
                    $order[$key]['telephone'] = (string)$result_order['billing_phone'];
                    $order[$key]['statuses'] = $orders_status;
                } else {
                    $order[$key]['order_id'] = (string)$result_order['id_order'];

                    if ((float)$result_order['total_paid'] > (float) $max_price_total) $max_price_total = (float)$result_order['total_paid'];
                }
            }
        endif;
        if ($order_id) return $order[0];
        return array(
            'orders' => $order,
            'statuses' => $orders_status,
            'currency_code' => get_woocommerce_currency(),
            'total_quantity' => (int) $totals['total_orders'],
            'total_sum' => (string)number_format(filterNull($totals['total_sales'], 0), 2, '.', ''),
            'max_price' => (string)number_format((float)$max_price_total, 2, '.', ''),
        );
    }

    protected function get_total_order($user_id = 0)
    {
        global $wpdb;
        $query = "SELECT DISTINCT posts.ID FROM `{$wpdb->posts}` AS posts
                    LEFT JOIN `{$wpdb->postmeta}` AS meta ON posts.ID = meta.post_id
                    WHERE meta.meta_key = '_customer_user'
                    AND posts.post_type = 'shop_order' AND meta.meta_value = %s";
        if (!empty($this->status_list_hide)) {
            $query .= " AND posts.post_status NOT IN ( '" . implode($this->status_list_hide, "', '") . "' )";
        }

        $query = sprintf($query, $user_id);

        $results = $wpdb->get_results($query, ARRAY_A);
        $ids = '';
        for ($i = 0; $i < count($results) - 1; $i++) {
            $ids .= $results[$i]["ID"] . ', ';
        }
        $ids .= $results[count($results) - 1]["ID"];


        $query1 = "SELECT SUM(meta.meta_value)  AS total
                    FROM `{$wpdb->postmeta}` AS meta 
                    WHERE meta.meta_key = '_order_total' AND meta.post_id IN (%s)";

        $query1 = sprintf($query1, $ids);

        $results1 = $wpdb->get_row($query1, ARRAY_A);
        return (string)$results1['total'];

    }

    public function get_customers()
    {
        $fio = filterNull($_REQUEST['fio'], '');
        $page = filterNull($_REQUEST['page'], 1);
        $limit = filterNull($_REQUEST['limit'], 20);
        $cust_with_orders = filterNull($_REQUEST['with_orders'], 0);
        $sort_by = filterNull($_REQUEST['sort'], 'id');

        global $wpdb;
        $query_where_parts = array();

        $fields = "SELECT DISTINCT 
                        customer_user.meta_value as customer_user, 
                        shipping_first_name.meta_value as shipping_first_name,
                        shipping_last_name.meta_value as shipping_last_name, 
                        billing_first_name.meta_value as billing_first_name, 
                        billing_last_name.meta_value as billing_last_name, 
                        order_currency.meta_value as order_currency, 
                        tot.total_orders,
                        user_registered.user_registered,
                        totalsum.order_total
                    ";

        $sql = " FROM {$wpdb->postmeta} AS customer_user 
                    LEFT JOIN {$wpdb->postmeta} AS shipping_first_name ON shipping_first_name.post_id = customer_user.post_id
                        AND shipping_first_name.meta_key = '_shipping_first_name' 
                    LEFT JOIN {$wpdb->postmeta} AS shipping_last_name ON shipping_last_name.post_id = customer_user.post_id 
                        AND shipping_last_name.meta_key = '_shipping_last_name' 
                    LEFT JOIN {$wpdb->postmeta} AS billing_first_name ON billing_first_name.post_id = customer_user.post_id 
                        AND billing_first_name.meta_key = '_billing_first_name' 
                    LEFT JOIN {$wpdb->postmeta} AS billing_last_name ON billing_last_name.post_id = customer_user.post_id
                        AND billing_last_name.meta_key = '_billing_last_name' 
                    LEFT JOIN {$wpdb->postmeta} AS order_currency ON order_currency.post_id = customer_user.post_id
                        AND order_currency.meta_key = '_order_currency'
                    LEFT JOIN {$wpdb->users} AS user_registered ON user_registered.ID = customer_user.meta_value
                    LEFT OUTER JOIN (
                          SELECT COUNT(DISTINCT(posts.ID)) AS total_orders, meta.meta_value AS id_customer 
                          FROM {$wpdb->posts} AS posts
                          LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
                          WHERE meta.meta_key = '_customer_user'
                          AND posts.post_type = 'shop_order' GROUP BY meta.meta_value ) AS tot ON tot.id_customer = customer_user.meta_value
                    LEFT OUTER JOIN (
                        SELECT customer_user.meta_value as customer_user, 
                            SUM(order_total.meta_value) as order_total
                        FROM  {$wpdb->postmeta} AS customer_user 
                        LEFT JOIN {$wpdb->postmeta} AS order_total ON order_total.post_id =  customer_user.post_id
                        AND order_total.meta_key = '_order_total'
                        WHERE customer_user.meta_value > 0
                        AND customer_user.meta_key = '_customer_user'
                        GROUP BY customer_user 
                    ) AS totalsum ON totalsum.customer_user = customer_user.meta_value
                ";

        $query_where_parts[] = ' customer_user.meta_value > 0 ';

        $query_where_parts[] = ' customer_user.meta_key = "_customer_user" ';

        $query = $fields . $sql;

        if ($fio) {
            $query_where_parts[] = sprintf(
                " (CONCAT(billing_first_name.meta_value, ' ', billing_last_name.meta_value) LIKE '%%%s%%'
                    OR CONCAT(shipping_first_name.meta_value, ' ', shipping_last_name.meta_value) LIKE '%%%s%%')"
                , $fio, $fio);
        }

        if ($cust_with_orders) {
            $query_where_parts[] = " tot.total_orders > 0";
        }

        if (!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
        }
if(in_array($sort_by, ['sum', 'date_added', 'quantity'])):
        $query .= " ORDER BY ";

        //sum/quantity/date_added

        switch ($sort_by) {
            case 'id':
                $dir = $this->getSortDirection('ASC');
                $query .= " customer_user.meta_value " . $dir;
                break;
            case 'sum':
                $dir = $this->getSortDirection('DESC');
                $query .= " CAST(totalsum.order_total AS unsigned) " . $dir;
                break;
            case 'date_added':
                $dir = $this->getSortDirection('DESC');
                $sql .= " user_registered.user_registered " . $dir;
                break;
            case 'quantity':
                $dir = $this->getSortDirection('DESC');
                $query .= " CAST(tot.total_orders AS unsigned) " . $dir;
                break;
        }
        endif;

        $query .= sprintf(" LIMIT %d, %d", (($page - 1) * $limit), $limit);

        $customers = array();
        $results = $wpdb->get_results($query, ARRAY_A);
        if ($results):
            foreach ($results as $user) {
                $customer['client_id'] = (string)$user['customer_user'];
                $customer['quantity'] = (string)filterNull($user['total_orders'], '0');
                $customer['total'] = (string) number_format((string)filterNull($user['order_total']),2, '.', '');
                $customer['fio'] = filterNull($user['shipping_first_name'], filterNull($user['billing_first_name'], ''))
                    . ' ' . filterNull($user['shipping_last_name'], filterNull($user['billing_last_name'], ''));
                $customer['currency_code'] = $user['order_currency'];
                $customers[] = $customer;
            }
        endif;
//        $row_page = $wpdb->get_row($query_page, ARRAY_A);
        return $customers;
    }


    /**
     * @return array
     */
    protected function getProductsByID($id)
    {
        if (!$id) return false;

        global $wpdb;
        $query_where_parts = array();

        $query_products = "SELECT DISTINCT 
              posts.ID as pr_id
            FROM {$wpdb->posts} AS posts
            ";

        $query_where_parts[] = sprintf(" posts.post_type = 'product'  and posts.ID = %s", $id);

        if (!empty($this->status_list_hide)) {
            $query_where_parts[] = " posts.post_status NOT IN ( '" . implode($this->status_list_hide, "', '") . "' )";
        }

        if (!empty($query_where_parts)) {
            $query_products .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        $query_products .= " ORDER BY posts.ID " . $this->getSortDirection('DESC');

        $products = $wpdb->get_row($query_products, ARRAY_A);
        $result = [];
        if ($products):
            /**
             * "description" : "Revolutionary multi-touch interface.↵	iPod touch features the same multi-touch screen technology as iPhone.",
             */
            $result['product_id'] = (string)$products["pr_id"];

            $allproductinfo = new WC_Product($products["pr_id"]);

            $result['name'] = $allproductinfo->get_name();
            $result['model'] = filterNull($allproductinfo->get_attribute('Модель'),
                filterNull($allproductinfo->get_attribute('model'),
                    ''));
            $result['price'] = (string)number_format(floatval($allproductinfo->get_price()), 2, '.', '');
            $result['currency_code'] = get_woocommerce_currency();
            $result['quantity'] = (string)($allproductinfo->get_stock_quantity() ?
                $allproductinfo->get_stock_quantity() : '0');

            $descript = $allproductinfo->get_description();

            $szSearchPattern = '~<img [^>]* />~';
            preg_match_all( $szSearchPattern, $descript, $aPics );
            if ($aPics) {
                $descript = preg_replace("~<img [^>]* />~", "", $descript);
            }
            $result['description'] = $descript;

        endif;

        return $result;
    }

    /**
     * @return array
     */
    protected function getProductImages($id)
    {
        if (!$id) return false;

        $productWP = new WC_product($id);
        $mainimage = $productWP->get_image_id();
        $res[] = array_shift(wp_get_attachment_image_src($mainimage, 'shop_catalog'));
        $attachment_ids = $productWP->get_gallery_image_ids();

        foreach ($attachment_ids as $attachment_id) {
            $res[] = array_shift(wp_get_attachment_image_src($attachment_id, 'shop_catalog'));
        }

        return filterNull($res, []);
    }

    /**
     * @return array
     */
    protected function getProductsList()
    {
        $page = filterNull($_REQUEST['page'], 1);
        $limit = filterNull($_REQUEST['limit'], 10);
        $name = filterNull($_REQUEST['name'], '');

        global $wpdb;
        $query_where_parts = array();

        $query_products = "SELECT DISTINCT 
              posts.ID as pr_id,
              posts.post_title as pr_name
            FROM {$wpdb->posts} AS posts
            ";

        $query_where_parts[] = " posts.post_type = 'product' ";

        if (!empty($this->status_list_hide)) {
            $query_where_parts[] = " posts.post_status NOT IN ( '" . implode($this->status_list_hide, "', '") . "' )";
        }
        if ($name)
            $query_where_parts[] = sprintf(" posts.post_title LIKE '%%%s%%' ", $name);

        if (!empty($query_where_parts)) {
            $query_products .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        $query_products .= " ORDER BY posts.ID " . $this->getSortDirection('DESC');


        $query_products .= sprintf(" LIMIT %d, %d", (($page - 1) * $limit), $limit);

        $products = $wpdb->get_results($query_products, ARRAY_A);
        $result = [];
        if ($products):
            foreach ($products as $key => $orderproduct) {

                $result[$key]['product_id'] = (string)$orderproduct["pr_id"];
                $result[$key]['name'] = $orderproduct['pr_name'];
                # Объект продукта WP
                $allproductinfo = new WC_Product($orderproduct["pr_id"]);

                $result[$key]['model'] = filterNull($allproductinfo->get_attribute('Модель'),
                    filterNull($allproductinfo->get_attribute('model'),
                        ''));
                $result[$key]['price'] = (string)number_format(floatval($allproductinfo->get_price()), 2, '.', '');
                $result[$key]['currency_code'] = get_woocommerce_currency();
                $result[$key]['quantity'] = (string)($allproductinfo->get_stock_quantity() ?
                    $allproductinfo->get_stock_quantity() : '0');

                # url картинки продукта
                $attachment_id = get_post_thumbnail_id($orderproduct["pr_id"]);
                $id_image = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                $result[$key]['image'] = filterNull($id_image[0], '');
            }
        endif;

        return $result;
    }


//    update_status


    /**
     * @param $order_id
     * @param array $data
     * @return array
     */
    protected function getOrderProducts($order_id)
    {
        global $wpdb;
        $query_where_parts = array();

        $query_products = "SELECT 
              order_item_name as pr_name,
              product_id.meta_value as pr_id,
              meta_items_qty.meta_value as pr_quantity,
              order_shipping_tax.meta_value as shipping_tax,
              order_total.meta_value as order_total
            FROM {$wpdb->posts} AS posts
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items 
              ON order_items.order_id = posts.ID AND order_items.order_item_type = 'line_item'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS meta_items_qty 
              ON meta_items_qty.order_item_id = order_items.order_item_id AND meta_items_qty.meta_key = '_qty'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_id 
              ON product_id.order_item_id = order_items.order_item_id AND product_id.meta_key = '_product_id'
            LEFT JOIN {$wpdb->postmeta} AS order_shipping_tax ON order_shipping_tax.post_id = posts.ID 
              AND order_shipping_tax.meta_key = '_order_shipping_tax'
            LEFT JOIN {$wpdb->postmeta} AS order_total ON order_total.post_id = posts.ID
              AND order_total.meta_key = '_order_total'
            ";

        if (!function_exists('wc_get_order_status_name')) {
            $query = " LEFT JOIN {$wpdb->term_relationships} AS order_status_terms ON order_status_terms.object_id = posts.ID
                            AND order_status_terms.term_taxonomy_id IN (SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'shop_order_status')
                        LEFT JOIN {$wpdb->terms} AS status_terms ON status_terms.term_id = order_status_terms.term_taxonomy_id";
            $query_products .= $query;
        }

        $query_where_parts[] = " posts.post_type = 'shop_order' ";

        if (!empty($this->status_list_hide)) {
            $query_where_parts[] = " posts.post_status NOT IN ( '" . implode($this->status_list_hide, "', '") . "' )";
        }

        if ($order_id) {
            $query_where_parts[] = sprintf(" posts.ID = %s ",
                $order_id);
        }

        if (!empty($query_where_parts)) {
            $query_products .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        $query_products .= " ORDER BY posts.ID " . $this->getSortDirection('DESC');

        $products = $wpdb->get_results($query_products, ARRAY_A);
        $result = [];
        $res = [];
        $total_price = 0;
        $total_discount = 0;
        $shipping_price = 0;
        if ($products):
            foreach ($products as $key => $orderproduct) {
                $res[$key]['product_id'] = (string)$orderproduct["pr_id"];
                $res[$key]['name'] = (string)$orderproduct['pr_name'];
                # количество продуктов
                $res[$key]['quantity'] = (string)$orderproduct['pr_quantity'];
                # Стоимость доставки
                $shipping_price = (string)$orderproduct['shipping_tax'];
                # Общая стоимость заказа
                $total_price = (string)$orderproduct['order_total'];

                # Объект продукта WP
                $allproductinfo = new WC_Product($orderproduct["pr_id"]);
                $res[$key]['price'] = (string)number_format((float)$allproductinfo->get_price(), 2, '.', '');
                $res[$key]['discount_price'] = (string)number_format((float)$allproductinfo->get_sale_price(), 2, '.', '');
                $res[$key]['discount'] = (string)number_format((float)$allproductinfo->get_regular_price() - $allproductinfo->get_sale_price(), 2, '.', '');
//            $result[$key]['description'] = $allproductinfo->get_description();
                $total_discount += (($allproductinfo->get_regular_price() - $allproductinfo->get_sale_price()) * $res[$key]['quantity']);

                # url картинки продукта
                $attachment_id = get_post_thumbnail_id($orderproduct["pr_id"]);
                $id_image = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                $res[$key]['image'] = (string)filterNull($id_image[0], '');

                # тут нужно имя модели
                $res[$key]['model'] = (string)filterNull($allproductinfo->get_attribute('Модель'),
                    filterNull($allproductinfo->get_attribute('model'),
                        ''));
            }
        endif;

        $result['products'] = $res;
        $alltotalprice = $total_price + $shipping_price;
        $result['total_order_price'] = [
            "total_discount" => (string)number_format((float)($total_discount), 2, '.', ''),
            "total_price" => (string)number_format((float)($total_price), 2, '.', ''),
            "currency_code" => (string)get_woocommerce_currency(),
            "shipping_price" => (string)number_format((float)($shipping_price), 2, '.', ''),
            "total" => (string)number_format((float)($alltotalprice), 2, '.', ''),
        ];

        return $result;
    }


    /**
     *
     * "name": "Отменено",
     * "order_status_id": "7",
     * "language_id": "1"
     *
     * @return array
     */
    public function get_orders_statuses()
    {
        $orders_statuses = array();

        $statuses = get_order_statuses();
        if ($statuses):
            foreach ($statuses as $code => $name) {
                $orders_statuses[] = array('order_status_id' => $code, 'name' => $name, 'language_id' => 1);
            }
        endif;

        return $orders_statuses;
    }

    protected function isValidStatus($stat_id)
    {
        if (array_key_exists($stat_id, get_order_statuses())) {
            return true;
        }
        return false;
    }

    private function getSortDirection($default_direction = 'DESC')
    {
//        if (isset($this->order_by) && !empty($this->order_by)) {
//            $direction = $this->order_by;
//        } else {
//            $direction = $default_direction;
//        }
# Тут доделаю!!!!! чтобі менять сортировку при каждлм клике в разніе стороны
        return $default_direction; //' ' . $direction;
    }


    function _get_order_status_name($order_id, $post_status)
    {
        if (function_exists('wc_get_order_status_name')) {
            return wc_get_order_status_name($post_status);
        }

        if ($order_id > 0) {
            $terms = wp_get_object_terms($order_id, 'shop_order_status', array('fields' => 'slugs'));
            $status = isset($terms[0]) ? $terms[0] : apply_filters('woocommerce_default_order_status', 'pending');
        } else {
            $status = $post_status;
        }


        $statuses = get_order_statuses();

        return $statuses[$status];
    }

    public function valid()
    {
        $error = 0;
        if (!filterNull($_REQUEST['token']) || !$this->issetToken($_REQUEST['token'])) {
            $error = 'You need to be logged!';
        }
        return $error;
    }

    protected static function check_auth($username, $pass)
    {
        global $wpdb;

        $wp_hasher = new PasswordHash(8, TRUE);
        $sql = "SELECT ID, user_login, user_pass FROM {$wpdb->prefix}users WHERE user_login = %s AND user_status = %s";
        $query = $wpdb->prepare($sql, $username, '1');
        $user = $wpdb->get_row($query, ARRAY_A);

        if ($wp_hasher->CheckPassword($pass, $user['user_pass'])) {
            return $user;
        }

        return false;
    }

    protected function setUserDeviceToken($user_id, $token, $os_type = '')
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}user_device (user_id, device_token, os_type )
                    VALUES (%s, %s, %s)",
            $user_id, $token, $os_type
        );
        $wpdb->query($sql);
        return;
    }

    /**
     * @param array $data
     * @return array|null|object|void
     */
    protected function getTotalCustomers($data = [])
    {
        global $wpdb;
        $query_where_parts = array();

        $query = "SELECT COUNT(DISTINCT(c.ID)) AS count_customers
                  FROM {$wpdb->users} AS c
                      LEFT JOIN {$wpdb->usermeta} AS usermeta ON usermeta.user_id = c.ID";


        $queryTime = "SELECT c.user_registered
                  FROM {$wpdb->users} AS c
                      LEFT JOIN {$wpdb->usermeta} AS usermeta ON usermeta.user_id = c.ID";

        $query_where_parts[] = " (usermeta.meta_key = '{$wpdb->prefix}capabilities' AND usermeta.meta_value LIKE '%customer%') ";

        if ($data !== 'totalsum') {
            $filter_date = $this->formalSqlFilterArray($data['filter'], 'c.user_registered');
            if ($filter_date) $query_where_parts[] = $filter_date;
        }

        if (!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
            $queryTime .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        $totals = $wpdb->get_row($query, ARRAY_A);
        $totalsTime = $wpdb->get_results($queryTime, ARRAY_A);
        if ($totalsTime):
            foreach ($totalsTime as $totalT) {
                $totals['user_registered'][] = $totalT['user_registered'];
            }
        endif;


        return $totals;
    }

    protected function is_valid_filter($filter)
    {
        switch ($filter) {
            case 'day':
            case 'week':
            case 'month':
            case 'year':
                return true;
                break;
            default:
                return false;
                break;
        }
    }

    protected function formalSqlFilterArray($filter = 'day', $param)
    {
        switch ($filter) {
            case 'week':
                $result = " DATE($param) >=  NOW() - INTERVAL 7 DAY";
                break;
            case 'month':
                $result = " DATE($param) >= NOW() - INTERVAL 1 MONTH";
                break;
            case 'year':
                $result = " YEAR($param) = YEAR(NOW()) ";
                break;
            case 'this_year':
                $result = " DATE_FORMAT($param,'%Y') = DATE_FORMAT(NOW(),'%Y')";
                break;
            default:
                $result = " DATE($param) = DATE(NOW()) ";
                break;
        }

        return $result;
    }

    public function getTotalOrders($data = [])
    {
        global $wpdb;
        $query_where_parts = [];

        $query_orders = "SELECT
                COUNT(posts.ID) AS count_orders,
                SUM(meta_order_total.meta_value) AS total_sales
            FROM {$wpdb->posts} AS posts
            LEFT JOIN {$wpdb->postmeta} AS meta_order_total 
               ON meta_order_total.post_id = posts.ID 
               AND meta_order_total.meta_key = '_order_total'";

        $guery_ordes_times = "SELECT
                posts.post_date
            FROM {$wpdb->posts} AS posts
            LEFT JOIN {$wpdb->postmeta} AS meta_order_total 
               ON meta_order_total.post_id = posts.ID 
               AND meta_order_total.meta_key = '_order_total'";

        if (!function_exists('wc_get_order_status_name')) {
            $query = " LEFT JOIN {$wpdb->term_relationships} AS order_status_terms 
                            ON order_status_terms.object_id = posts.ID
                            AND order_status_terms.term_taxonomy_id IN (SELECT term_taxonomy_id 
                            FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'shop_order_status')
                        LEFT JOIN {$wpdb->terms} AS status_terms 
                            ON status_terms.term_id = order_status_terms.term_taxonomy_id";
            $query_orders .= $query;
            $guery_ordes_times .= $query;
        }

        $query_where_parts[] = " posts.post_type = 'shop_order' ";
        if ($data !== 'totalsum') {
            $filter_date = $this->formalSqlFilterArray($data['filter'], 'posts.post_date');
            if ($filter_date) $query_where_parts[] = $filter_date;
        }

        if (!empty($query_where_parts)) {
            $query_orders .= " WHERE " . implode(" AND ", $query_where_parts);
            $guery_ordes_times .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        $orders_stat_time = $wpdb->get_results($guery_ordes_times, ARRAY_A);
        $orders_stat = $wpdb->get_results($query_orders, ARRAY_A);
        $orders_stat = array_shift($orders_stat);
        $totals['count_orders'] = $orders_stat['count_orders'];
        $totals['total_sales'] = number_format((float)$orders_stat['total_sales'], 2, '.', '');
        if ($orders_stat_time):
            foreach ($orders_stat_time as $totalT) {
                $totals['order_date'][] = $totalT['post_date'];
            }
        endif;
        return $totals;
    }


    public function ChangeOrderDelivery($address, $city, $order_id)
    {
        $type = 'update';

        global $wpdb;
        $fields = "SELECT
                    shipping_address_index.meta_value as shipping_address,                                       
                    shipping_company.meta_value as shipping_company, 
                    shipping_first_name.meta_value as shipping_first_name,
                    shipping_last_name.meta_value as shipping_last_name,
                    shipping_postcode.meta_value as shipping_postcode,
                    shipping_city.meta_value as shipping_city,
                    shipping_country.meta_value as shipping_country
                    ";

        $sql = " FROM {$wpdb->posts} AS posts
            LEFT JOIN {$wpdb->postmeta} AS shipping_address_index 
                ON shipping_address_index.post_id = posts.ID AND shipping_address_index.meta_key = '_shipping_address_index'
            LEFT JOIN {$wpdb->postmeta} AS shipping_company 
                ON shipping_company.post_id = posts.ID AND shipping_company.meta_key = '_shipping_company'
            LEFT JOIN {$wpdb->postmeta} AS shipping_first_name 
                ON shipping_first_name.post_id = posts.ID AND shipping_first_name.meta_key = '_shipping_first_name'
            LEFT JOIN {$wpdb->postmeta} AS shipping_last_name 
                ON shipping_last_name.post_id = posts.ID AND shipping_last_name.meta_key = '_shipping_last_name'
            LEFT JOIN {$wpdb->postmeta} AS shipping_postcode 
                ON shipping_postcode.post_id = posts.ID AND shipping_postcode.meta_key = '_shipping_postcode'        
            LEFT JOIN {$wpdb->postmeta} AS shipping_country 
                ON shipping_country.post_id = posts.ID AND shipping_country.meta_key = '_shipping_country'       
            LEFT JOIN {$wpdb->postmeta} AS shipping_city 
                ON shipping_city.post_id = posts.ID AND shipping_city.meta_key = '_shipping_city'
                ";

        $query = $fields . $sql;
        $query_where_parts[] = " posts.post_type = 'shop_order' ";
        $query_where_parts[] = sprintf(" posts.ID = %s ",
            $order_id);
        if (!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
        }
        $result = $wpdb->get_row($query, ARRAY_A);
        if (!$result) $type = 'insert';

        $addressExpl = explode(" ", $address);
        $flat = array_pop($addressExpl);
        $addressExpl = implode(' ', $addressExpl);

        $addr = [
            $result['shipping_first_name'],
            $result['shipping_last_name'],
            $result['shipping_company'],
            $address, $city,
            $result['shipping_postcode'],
            $result['shipping_country'],
        ];

        $addr = implode(" ", $addr);
        switch ($type) {
            case 'update':

                $updateStr1 = "UPDATE {$wpdb->postmeta}
                SET 
                    meta_value = '%s'
                WHERE  meta_key = '%s' AND  post_id = %s ";
                break;
            case 'insert':
                $updateStr1 = "INSERT INTO {$wpdb->postmeta}
                   meta_value, meta_key
                VALUES
                    ('%s', '%s')
                WHERE post_id = %s ";
                break;
            default:
                return false;
                break;
        }
        $updateStrAdr = sprintf($updateStr1,
            $addr, '_shipping_address_index', $order_id);

        $wpdb->query($updateStrAdr);

        if ($city) {
            $updateStrCity = sprintf($updateStr1,
                $city, '_shipping_city', $order_id);
            $wpdb->query($updateStrCity);
        }

        if ($addressExpl):
            $updateStrStreet = sprintf($updateStr1,
                $addressExpl, '_shipping_address_1', $order_id);
            $wpdb->query($updateStrStreet);
        endif;

        if ($flat):
            $updateStrFl = sprintf($updateStr1,
                $flat, '_shipping_address_2', $order_id);
            $wpdb->query($updateStrFl);
        endif;

        return true;
    }

    protected function _get_customer_orders($id)
    {
        global $wpdb;

        $customer = new WP_User($id);

        if ($customer->ID == 0) {
            return false;
        }

        $sort_by = filterNull($_REQUEST['sort'], 'id');


        $sql = "SELECT
                    posts.ID AS id_order,
                    meta_total.meta_value AS total_paid,
                    meta_curr.meta_value AS currency_code,
                    posts.post_status AS order_status_id,
                    posts.post_date as date_add,
                    (SELECT SUM(meta_value) FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = order_items.order_item_id AND meta_key = '_qty') AS pr_qty
                FROM {$wpdb->posts} AS posts
                    LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
                    LEFT JOIN {$wpdb->postmeta} AS meta_total ON meta_total.post_id = posts.ID AND meta_total.meta_key = '_order_total'
                    LEFT JOIN {$wpdb->postmeta} AS meta_curr ON meta_curr.post_id = posts.ID AND meta_curr.meta_key = '_order_currency'
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items on order_items.order_id = posts.ID AND order_item_type = 'line_item'
                WHERE meta.meta_key = '_customer_user'
                    AND meta.meta_value = '%s'
                    AND posts.post_type = 'shop_order'";

        if (!empty($this->status_list_hide)) {
            $sql .= " AND posts.post_status NOT IN ( '" . implode($this->status_list_hide, "', '") . "' )";
        }

        $sql .= " GROUP BY order_items.order_id";

        if ($sort_by):
            $sql .= " ORDER BY ";
            switch ($sort_by) {
                case 'id':
                    $dir = $this->getSortDirection('DESC');
                    $sql .= " posts.ID " . $dir;
                    break;
                case 'date_added':
                    $dir = $this->getSortDirection('DESC');
                    $sql .= " posts.post_date " . $dir;
                    break;

                case 'total':
                    $dir = $this->getSortDirection('DESC');
                    $sql .= " CAST(meta_total.meta_value AS unsigned) " . $dir;
                    break;

                case 'cancelled':
                case 'wc-cancelled':
                case (get_order_statuses()[$sort_by] == 'Отменен'):
                    $sql .= " case when posts.post_status = 'wc-cancelled' then 1 else 2 end";
                    break;

                case 'completed':
                case $this->getStatusKey($sort_by):
                case (get_order_statuses()[$sort_by] == 'Выполнен'):
                    $sql .= " case when posts.post_status = 'wc-completed' then 1 else 2 end";
                    break;

                default:
                    return [
                        'result' => false,
                        'error' => 'Unknown sort type',
                    ];
                    break;
            }
        endif;
        $query = $wpdb->prepare($sql, $id);

        $orders = array();
        $results = $wpdb->get_results($query, ARRAY_A);
        foreach ($results as $order) {
            $order['total_paid'] = $order['total_paid'];
            $order['ord_status'] = $this->_get_order_status_name($order['id_order'], $order['order_status_id']);
            $order['ord_status_code'] = $order['order_status_id'];
            $orders[] = $order;
        }
        return $orders;
    }

    protected function _get_customer_orders_total($id)
    {
        global $wpdb;
        $customer = new WP_User($id);

        if ($customer->ID == 0) {
            return false;
        }

        $sql = "SELECT COUNT(DISTINCT(posts.ID)) AS c_orders_count, SUM(meta_total.meta_value) AS sum_ords
                FROM $wpdb->posts AS posts
                    LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
                    LEFT JOIN {$wpdb->postmeta} AS meta_total ON meta_total.post_id = posts.ID AND meta_total.meta_key = '_order_total'
                    LEFT JOIN {$wpdb->postmeta} AS meta_curr ON meta_curr.post_id = posts.ID AND meta_curr.meta_key = '_order_currency'
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items on order_items.order_id = posts.ID AND order_item_type = 'line_item'
                WHERE meta.meta_key = '_customer_user'
                    AND meta.meta_value = '%s'
                    AND posts.post_type = 'shop_order'";

        if (!empty($this->status_list_hide)) {
            $sql .= " AND posts.post_status NOT IN ( '" . implode($this->status_list_hide, "', '") . "' )";
        }

        $sql = $wpdb->prepare($sql, $id);

        $orders_total = array("c_orders_count" => 0, "sum_ords" => 0);
        if ($row_total = $wpdb->get_row($sql, ARRAY_A)) {
            $orders_total = $row_total;
        }

        $orders_total['sum_ords'] = $orders_total['sum_ords'];
        $orders_total['c_orders_count'] = $orders_total['c_orders_count'];

        return $orders_total;
    }

    protected function getUserDevices($user_id, $dev_token)
    {
        global $wpdb;
        $res = $wpdb->query(
            $wpdb->prepare("SELECT * 
                                      FROM {$wpdb->prefix}user_device 
                                      WHERE user_id=%s 
                                      AND device_token=%s",
                $user_id, $dev_token
            )
        );
        return $res;
    }

    protected function setUserToken($id, $token)
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            'INSERT INTO ' . $wpdb->prefix . 'user_token (user_id, token)
                        VALUES (%s, %s)', $id, $token);
        $wpdb->query($sql);

        return;
    }

    protected function getUserToken($id)
    {
        global $wpdb;

        $res = $wpdb->get_row($wpdb->prepare('SELECT token FROM  ' . $wpdb->prefix
            . 'user_token WHERE user_id = %s', $id));

        return $res->token;
    }

    protected function issetToken($token)
    {
        global $wpdb;

        $res = $wpdb->get_row($wpdb->prepare("
                    SELECT token 
                    FROM  {$wpdb->prefix}user_token 
                    WHERE token = %s",
            $token
        ));
        return $res->token;
    }


    protected static function woocommerce_pinta_activation()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("activate-plugin_{$plugin}");

        self::remove_woocommerce_pinta_tables();
    }


    protected static function remove_woocommerce_pinta_tables()
    {
        global $wpdb;
# удаляем таблицы, если были созданы раннее
        $sql = "DROP TABLE IF EXISTS {$wpdb->prefix}user_token, {$wpdb->prefix}user_token";
        $wpdb->query($sql);
    }

    protected static function woocommerce_pinta_deactivate()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("deactivate-plugin_{$plugin}");

        remove_action('woocommerce_order_status_changed', 'pinta_push_change_status');

    }

    protected static function woocommerce_pinta_uninstall()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        check_admin_referer('bulk-plugins');

// Проверка, был ли файл зарегстрирован
        if (__FILE__ != WP_UNINSTALL_PLUGIN) {
            return;
        }

# Раскомментируйте следующую строку, чтобы увидеть функцию в действии
//exit( var_dump( $_GET ) );
    }

}