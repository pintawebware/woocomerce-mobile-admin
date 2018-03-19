<?php

include_once(WOOCOMMERCE_PINTA_DIR . 'includes/functions.php');
// include_once('wp-includes/class-phpass.php');

/**
 * Class PintaFunctionsClass
 */
class FunctionsClass
{
    protected $status_list_hide = array("auto-draft", "draft", "trash");

    public function __construct()
    {
        $this->check_is_woo_activated();

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

        #  Gets extra data associated with the order if needed.
        if ($order->get_extra_data_keys()):
            foreach ($order->get_extra_data_keys() as $key) {
                $function = 'set_' . $key;
                if (is_callable(array($order, $function))) {
                    $order->{$function}(get_post_meta($order->get_id(), '_' . $key, true));
                }
            }
        endif;
    }

    protected function getOrderHistory($id)
    {
        global $wpdb;
        $sql = "SELECT comment_date, comment_content FROM {$wpdb->comments} WHERE comment_post_ID = %s 
            AND comment_type='order_note' ORDER BY comment_date DESC ";
        $sql = sprintf($sql, $id);
        $res = $wpdb->get_results($sql, ARRAY_A);

        $response = [];
        if ($res):
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
        endif;

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
            "shipping_method" => !empty($result["shipping_method"]) ? $result["shipping_method"] : '',
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
        $order_status_id = filterNull($_REQUEST['order_status_id'], '');
        $date_min = filterNull($_REQUEST['date_min'], '');
        $date_max = filterNull($_REQUEST['date_max'], '');
        $sort_by = filterNull($_REQUEST['sort_by'], '');

        if (is_array($filter) && $filter) {
            $fio = filterNull($filter['fio'], '');
            $min_price = filterNull($filter['min_price'], '0');
            $max_price = filterNull($filter['max_price'], '');
            $order_status_id = filterNull($filter['order_status_id'], '');
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

        if ($date_min) {
            $query_where_parts[] = sprintf(" (posts.post_date_gmt) >=  '%s' ",
                $date_min);
        }

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
                (float)$max_price
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
#         var_dump($query); exit;
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

                    if ((float)$result_order['total_paid'] > (float)$max_price_total) $max_price_total = (float)$result_order['total_paid'];
                }
            }
        endif;
        if ($order_id) return $order[0];
        return array(
            'orders' => $order,
            'statuses' => $orders_status,
            'currency_code' => get_woocommerce_currency(),
            'total_quantity' => (int)$totals['total_orders'],
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

        if (in_array($sort_by, ['sum', 'date_added', 'quantity'])):
            $query .= " ORDER BY ";

            # sum/quantity/date_added

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
                    $query .= " user_registered.user_registered " . $dir;
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
                $customer['total'] = (string)number_format((string)filterNull($user['order_total']), 2, '.', '');
                $customer['fio'] = filterNull($user['shipping_first_name'], filterNull($user['billing_first_name'], ''))
                    . ' ' . filterNull($user['shipping_last_name'], filterNull($user['billing_last_name'], ''));
                $customer['currency_code'] = $user['order_currency'];
                $customers[] = $customer;
            }
        endif;

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
             * "description" : "Revolutionary multi-touch interface.↵   iPod touch features the same multi-touch screen technology as iPhone.",
             */
            $result['product_id'] = (string)$products["pr_id"];


            $allproductinfo = new WC_Product($products["pr_id"]);

            $result['name'] = $allproductinfo->get_name();
            $result['price'] = (string)number_format(floatval($allproductinfo->get_regular_price()), 2, '.', '');

            $result['currency_code'] = get_woocommerce_currency();
            $result['quantity'] = (string)($allproductinfo->get_stock_quantity() ?
                $allproductinfo->get_stock_quantity() : '0');

            $result['sku'] = $allproductinfo->get_sku();
            $result['statuses'] = $this->getProductStatuses();

            $result['stock_statuses'] = $this->getWooSubstatuses();
            $result['stock_status_name'] = $this->stockStatusNameById($allproductinfo->get_stock_status());
            $result['status_name'] = array_key_exists($allproductinfo->get_status(), get_post_statuses())
                ? get_post_statuses()[$allproductinfo->get_status()] : 'Draft';
            $result['categories'] = $this->get_categories($products["pr_id"]);

            $attributes = $allproductinfo->get_attributes();
            foreach ($attributes as $attribute) {
                $attribute_record = array();
                $attribute_record['option_name'] = $attribute->get_name(); 
                $attribute_record['option_id'] = $attribute->get_id(); 
                $attribute_record['option_value'] = $attribute->get_options();
                $result['options']['individual'][] = $attribute_record; 
            }

            $result['options']['general'] = $this->get_options($products["pr_id"]);

            $descript = $allproductinfo->get_description();
            $szSearchPattern = '~<img [^>]* />~';
            preg_match_all($szSearchPattern, $descript, $aPics);
            if ($aPics) {
                $descript = preg_replace("~<img [^>]* />~", "", $descript);
            }
            $result['description'] = $descript;
            $result['images'] = $this->getProductImages($products["pr_id"]);
        endif;

        return $result;
    }

    protected function getProductStatuses()
    {
        $result = [];
        foreach (get_post_statuses() as $key => $value) {
            if ($key == 'draft') continue;
            $result[] = [
                'status_id' => $key,
                'name' => $value,
            ];
        }
        return $result;
    }

    public function stockStatusNameById($stock_id)
    {

        $stock_arr = [
            'instock' => 'In Stock',
            'outofstock' => 'Out of Stock',
        ];
        return $stock_arr[$stock_id];
    }

    /**
     * @param $prod_id
     */
    protected function get_categories($prod_id)
    {

        global $wpdb;

        $sql = "SELECT tr.object_id, tt.term_taxonomy_id, tt.parent, t.name FROM {$wpdb->prefix}term_taxonomy tt 
                INNER JOIN {$wpdb->prefix}term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                INNER JOIN {$wpdb->prefix}terms t 
                ON t.term_id = tt.term_taxonomy_id WHERE tr.object_id = %s AND tt.taxonomy = %s";
        $query = $wpdb->prepare($sql, $prod_id, 'product_cat');

        $products = $wpdb->get_results($query, ARRAY_A);

        $result = [];

        foreach ($products as $key => $value) {
            $result[] = [
                'category_id' => $value['term_taxonomy_id'],
                'name' => (!$value['parent']) ? $value['name'] : $this->get_category_full_name($value['term_taxonomy_id'])
            ];
        }
        return $result;
    }

    /**
     * @param $product_id
     */
    protected function get_options($product_id)
    {
        global $wpdb;

        $product_attributes_query = "SELECT 
                                                wat.attribute_id                option_id,
                                                wat.attribute_label             option_name,
                                                t.term_id                       option_value_id,
                                                t.name                          option_value_name 
                                           FROM {$wpdb->prefix}term_taxonomy                    tt 
                                     INNER JOIN {$wpdb->prefix}term_relationships               tr 
                                             ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                                     INNER JOIN {$wpdb->prefix}terms                            t 
                                             ON t.term_id = tt.term_id 
                                     INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomies wat 
                                          WHERE tr.object_id = $product_id 
                                            AND tt.taxonomy LIKE 'pa_%'
                                            AND tt.taxonomy = CONCAT('pa_', wat.attribute_name)
        ";
        $product_attributes_result = $wpdb->get_results($product_attributes_query, ARRAY_A);

        return $product_attributes_result;
    }


    /**
     * @param $prod_id
     */
    protected function get_main_category_in_string($prod_id)
    {

        global $wpdb;

        $sql = "SELECT t.name FROM {$wpdb->prefix}term_taxonomy tt 
                INNER JOIN {$wpdb->prefix}term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                INNER JOIN {$wpdb->prefix}terms t 
                ON t.term_id = tt.term_taxonomy_id WHERE tr.object_id = %s AND tt.taxonomy = %s";
        $query = $wpdb->prepare($sql, $prod_id, 'product_cat');

        $products = $wpdb->get_row($query, ARRAY_A);

#         $result = '';
#         if ($products):
#             $counter = count($products);
#         foreach ($products as $key => $value) {
#             $result .= $value['name'];
#             if ($key < $counter-1) $result .= ', ';
#         }
#         endif;
        return $products ? $products['name'] : '';# $result;
    }

    /**
     * Return full category name with all parents
     * @param $prod_id
     */
    protected function get_category_full_name($categ_id)
    {

        global $wpdb;
        $sql = "SELECT 
                    tt.term_taxonomy_id, tt.parent, t.name FROM {$wpdb->prefix}term_taxonomy tt 
                INNER JOIN {$wpdb->prefix}terms t 
                     ON t.term_id = tt.term_taxonomy_id WHERE tt.term_taxonomy_id = %s AND tt.taxonomy = %s";
        $query = $wpdb->prepare($sql, $categ_id, 'product_cat');
        $categ = $wpdb->get_row($query, ARRAY_A);
        $name = '';
        if (!$categ['parent']) {
            $name .= $categ['name'];
        } else {
            $name .= $this->get_category_full_name($categ['parent']) . ' - ' . $categ['name'];
        }

        return $name;
    }

    /**
     * @return array
     */
    protected function getProductImages($id)
    {
        if (!$id) return false;

        $productWP = new WC_product($id);

        $mainimage = $productWP->get_image_id();

        if (!$mainimage) {
            $res[] = [
                'image' => '',
                'image_id' => '-1', # $mainimage
            ];
        } else {
            $res[] = [
                'image' => (is_array(wp_get_attachment_image_src($mainimage, 'shop_catalog'))) ?
                    array_shift(wp_get_attachment_image_src($mainimage, 'shop_catalog')) : '',
                'image_id' => '-1', # $mainimage
            ];
        }
        $attachment_ids = $productWP->get_gallery_image_ids();
        usort($attachment_ids, function ($a, $b) {
            return ($a - $b);
        });
        if ($attachment_ids):
//            $result = [];
            foreach ($attachment_ids as $attachment_id) {
                if ($attachment_id != $mainimage && is_array(wp_get_attachment_image_src($attachment_id, 'shop_catalog'))):
                    $res[] = [
                        'image' => array_shift(wp_get_attachment_image_src($attachment_id, 'shop_catalog')),
                        'image_id' => (string)$attachment_id,
                    ];
                endif;
            }

//            array_pop($result, $res['0']);
        endif;
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

        $query_products .= " ORDER BY posts.ID " . $this->getSortDirection('ASC');


        $query_products .= sprintf(" LIMIT %d, %d", (($page - 1) * $limit), $limit);

        $products = $wpdb->get_results($query_products, ARRAY_A);
        $result = [];
        if ($products):
            foreach ($products as $key => $orderproduct) {

                $result[$key]['product_id'] = (string)$orderproduct["pr_id"];
                $result[$key]['name'] = $orderproduct['pr_name'];
                # Объект продукта WP
                $allproductinfo = new WC_Product($orderproduct["pr_id"]);
                $result[$key]['price'] = (string)number_format(floatval($allproductinfo->get_regular_price()), 2, '.', '');
                $result[$key]['currency_code'] = get_woocommerce_currency();
                $result[$key]['model'] = get_post_meta($orderproduct["pr_id"], '_sku', true);
                $result[$key]['quantity'] = (string)($allproductinfo->get_stock_quantity() ?
                    $allproductinfo->get_stock_quantity() : '0');

                # url картинки продукта
                $attachment_id = get_post_thumbnail_id($orderproduct["pr_id"]);
                $id_image = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                $result[$key]['image'] = filterNull($id_image[0], '');

                $result[$key]['category'] = $this->get_main_category_in_string($orderproduct["pr_id"]);
            }
        endif;

        return $result;
    }


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
                $res[$key]['model'] = get_post_meta($orderproduct["pr_id"], '_sku', true);
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
#             $result[$key]['description'] = $allproductinfo->get_description();
                $total_discount += (($allproductinfo->get_regular_price() - $allproductinfo->get_sale_price()) * $res[$key]['quantity']);

                # url картинки продукта
                $attachment_id = get_post_thumbnail_id($orderproduct["pr_id"]);
                $id_image = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                $res[$key]['image'] = (string)filterNull($id_image[0], '');
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
#         if (isset($this->order_by) && !empty($this->order_by)) {
#             $direction = $this->order_by;
#         } else {
#             $direction = $default_direction;
#         }

        return $default_direction; # ' ' . $direction;
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
//        $wp_hasher = new PasswordHash(8, TRUE);

        $sql = "SELECT ID, user_login, user_pass FROM $wpdb->users WHERE user_login = %s";

        $query = $wpdb->prepare($sql, $username);

        $user = $wpdb->get_row($query, ARRAY_A);

//        if ($wp_hasher->CheckPassword($pass, $user['user_pass'])) {
        if (wp_check_password($pass, $user['user_pass'])) {
            if (is_multisite()) {
                if (in_array($user['user_login'], get_super_admins())) {
                    return $user;
                }else{
                    $site_url = get_site_url();
                    $order   = array("http://", "https://");
                    $site_url = str_replace($order, '', $site_url);

                    $wp_site_info = get_sites(array('domain' => $site_url))[0];
                    $wp_capabilities = "wp_".$wp_site_info->blog_id."_capabilities";
                    
                    $sql = "SELECT meta_value FROM $wpdb->usermeta WHERE user_id = %s AND meta_key = '".$wp_capabilities."'";
                    $query = $wpdb->prepare($sql, $user["ID"]);
                    $user_capabilities = $wpdb->get_row($query, ARRAY_A);
                    $user_capabilities = unserialize($user_capabilities['meta_value']);

                    foreach ($user_capabilities as $key => $value) {
                        if ($key == 'administrator') {
                            if ($value == true) {
                                return $user;
                            }
                        }
                    }
                }
            }else{
                return $user;
            } 
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
        $flat = '';
        $addressExpl = explode(" ", $address);

        if (!$city) {
            $city = array_pop($addressExpl);
        }

        if (count($addressExpl) > 1) {
            $flat = array_pop($addressExpl);
        }
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

#         if ($flat):
        $updateStrFl = sprintf($updateStr1,
            $flat, '_shipping_address_2', $order_id);
        $wpdb->query($updateStrFl);
#         endif;

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
        if ($results):
            foreach ($results as $order) {
                $order['total_paid'] = $order['total_paid'];
                $order['ord_status'] = $this->_get_order_status_name($order['id_order'], $order['order_status_id']);
                $order['ord_status_code'] = $order['order_status_id'];
                $orders[] = $order;
            }
        endif;
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

    protected function getUserDevices($user_id, $dev_token, $os)
    {
        global $wpdb;
        $res = $wpdb->query(
            $wpdb->prepare("SELECT * 
                                      FROM {$wpdb->prefix}user_device 
                                      WHERE user_id=%s 
                                      AND device_token=%s
                                      AND os_type=%s",
                $user_id, $dev_token, $os
            )
        );
        return $res;
    }

    public function getAllUserDevices()
    {
        global $wpdb;
        $res = $wpdb->get_results("SELECT DISTINCT device_token, os_type 
                                      FROM {$wpdb->prefix}user_device 
                                      ", ARRAY_A
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

#         remove_action('woocommerce_order_status_changed', 'pinta_push_change_status');

    }

    protected static function woocommerce_pinta_uninstall()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        check_admin_referer('bulk-plugins');

#  Проверка, был ли файл зарегстрирован
        if (__FILE__ != WP_UNINSTALL_PLUGIN) {
            return;
        }

# Раскомментируйте следующую строку, чтобы увидеть функцию в действии
# exit( var_dump( $_GET ) );
    }


    public function sendCurl($fields)
    {
        $API_ACCESS_KEY = 'AAAAlhKCZ7w:APA91bFe6-ynbVuP4ll3XBkdjar_qlW5uSwkT5olDc02HlcsEzCyGCIfqxS9JMPj7QeKPxHXAtgjTY89Pv1vlu7sgtNSWzAFdStA22Ph5uRKIjSLs5z98Y-Z2TCBN3gl2RLPDURtcepk';
        $headers = array
        (
            'Authorization: key=' . $API_ACCESS_KEY,
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_exec($ch);
        curl_close($ch);

        return true;
    }

    /**
     * Set new product stock
     *
     * @param $productId
     * @param $quantity
     * @return false|int
     */
    protected function setNewProductStock($productId, $quantity)
    {

        global $wpdb;

        $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key = %s";
        $wpdb->query($wpdb->prepare(
            $sql, "instock", $productId, "_stock_status"
        ));

        $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key = %s";

        $result = $wpdb->query($wpdb->prepare(
            $sql, $quantity, $productId, "_stock"
        ));

        return $result;
    }


    /**
     *
     */
    protected function addNewProduct()
    {

        global $wpdb;
        $shortDescr = preg_replace("#[^\.]*\.#s", '', $_REQUEST['description'], 1);

        $post = array(
            'post_author' => '2',
            'post_content' => '',
            'post_status' => (array_key_exists($_REQUEST['status'], get_post_statuses())) ? $_REQUEST['status'] : "publish",
            'post_title' => filterNull($_REQUEST['name'], ''),
            'post_parent' => '',
            'post_type' => "product",
            'post_content' => filterNull($_REQUEST['description'], ""),
            'post_excerpt' => filterNull($shortDescr, ""),
        );

        $post_id = wp_insert_post($post, true);
#         if($post_id){
# #             $attach_id = get_post_meta($product->parent_id, "_thumbnail_id", true);
# #             add_post_meta($post_id, '_thumbnail_id', $attach_id);
#         }

#         wp_set_object_terms( $post_id_id, 'Races', 'product_cat' );
#         wp_set_object_terms($post_id_id, 'simple', 'product_type');
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_stock_status', filterNull($_REQUEST['substatus'], 'instock'));
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, 'total_sales', '0');
#         update_post_meta( $post_id, '_downloadable', 'yes');
#         update_post_meta( $post_id, '_virtual', 'yes');
        update_post_meta($post_id, '_regular_price', filterNull($_REQUEST['price'], 0));
        update_post_meta($post_id, '_sale_price', filterNull($_REQUEST['price'], 0));
        update_post_meta($post_id, '_purchase_note', "");
        update_post_meta($post_id, '_featured', "no");
        update_post_meta($post_id, '_weight', "");
        update_post_meta($post_id, '_length', "");
#         update_post_meta( $post_id, '_width', "" );
#         update_post_meta( $post_id, '_height', "" );
        update_post_meta($post_id, '_sku', filterNull($_REQUEST['sku'], ''));
        update_post_meta($post_id, '_product_attributes', array());
        update_post_meta($post_id, '_sale_price_dates_from', "");
        update_post_meta($post_id, '_sale_price_dates_to', "");
        update_post_meta($post_id, '_price', filterNull($_REQUEST['price'], 0));
        update_post_meta($post_id, '_sold_individually', "");
        update_post_meta($post_id, '_manage_stock', "yes");
        update_post_meta($post_id, '_backorders', "no");
        update_post_meta($post_id, '_stock', filterNull($_REQUEST['quantity'], 0));

#         update_post_meta( $post_id, '_wp_attachment_metadata', serialize($new_images) );


#       grant permission to any newly added files on any existing orders for this product
#       do_action( 'woocommerce_process_product_file_download_paths', $post_id, 0, $downdloadArray );
        update_post_meta($post_id, '_download_limit', '');
        update_post_meta($post_id, '_download_expiry', '');
        update_post_meta($post_id, '_download_type', '');
#         update_post_meta( $post_id, '_product_image_gallery', filterNull($_REQUEST['main_img'], 0) );

        # add categories_ids
        # меняем категорию
        if ($_REQUEST['categories'] && (is_array($_REQUEST['categories']))) {
            $cat_ids = $_REQUEST['categories'];
            foreach ($cat_ids as $key => $value) {
                $sql = "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id)
                        VALUES (%s, %s)";

                $wpdb->query($wpdb->prepare(
                    $sql, $post_id, (int)$value
                ));
            }
        }

        /*
         * Update product options.
         */
        if ($_REQUEST['options'] && (is_array($_REQUEST['options']))) {
            $product_options = $_REQUEST['options'];

            /*
             * Empty product attributes in post meta values.
             */
            update_post_meta($product_id, '_product_attributes', array());

            /*
             * Set individual product options.
             */
            if (isset($product_options['individual']) && is_array($product_options['individual'])) {
                $this->updateIndividualProductOptions($pr_id, $product_options['individual']);
            }

            /*
             * Set general product options.
             */
            if (isset($product_options['general']) && is_array($product_options['general'])) {
                $this->updateGeneralProductOptions($pr_id, $product_options['general']);
            }
        }

        # add images
        # загружаем в галерею картинки
        $this->loadImages($post_id, true);

        $img_arr = $this->getProductImages($post_id);

        return [
            'product_id' => $post_id,
            'images' => $img_arr,
        ];
    }

    protected function updateGeneralProductOptions($product_id, $product_options)
    {
        global $wpdb;

        /*
         * Remove previous associations of general options with the given product.
         */
        $delete_product_options_query = 
                 "DELETE tr
                    FROM {$wpdb->term_relationships} tr
              INNER JOIN {$wpdb->term_taxonomy}      tt
                      ON tt.term_taxonomy_id = tr.term_taxonomy_id 
                   WHERE tr.object_id = $product_id 
                     AND tt.taxonomy like 'pa_%'
        ";
        $wpdb->query($delete_product_options_query);

        /*
         * Add new associations of general options with the given product.
         */
        foreach ($product_options as $product_option_id) {
            $insert_product_options_query = $wpdb->prepare(
                "INSERT INTO {$wpdb->term_relationships} 
                             (object_id, term_taxonomy_id) 
                      VALUES (%s, %s)",
                $product_id,
                $product_option_id
            );
            $wpdb->query($insert_product_options_query);

            /*
             * Record the new attributes in post meta values.
             */
            $select_taxonomy_query = $wpdb->prepare(
                "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = '%s'",
                $product_option_id
            );
            $select_taxonomy_result = $wpdb->get_row($select_taxonomy_query, ARRAY_A);
            $taxonomy = $select_taxonomy_result['taxonomy'];
            $product_attributes = get_post_meta($product_id, '_product_attributes');
            $product_attributes[0][sanitize_title($taxonomy)] = array(
                'name'          => wc_clean($taxonomy),
                'value'         => '',
                'position'      => 0, 
                'is_visible'    => 1,
                'is_variation'  => 0, 
                'is_taxonomy'   => 1 
            );
            update_post_meta($product_id, '_product_attributes', $product_attributes[0]);
        }
    }

    protected function updateIndividualProductOptions($product_id, $product_options)
    {
        $product = new WC_Product($product_id);
        $product_attributes = array();
        foreach ($product_options as $product_option) {
            $product_attribute = new WC_Product_Attribute();
            $product_attribute->set_id($product_option['option_id']);
            $product_attribute->set_name($product_option['option_name']);
            $product_attribute->set_options($product_option['option_values']);
            $product_attribute->set_visible(true);
            $product_attributes[] = $product_attribute;
        }
        $product->set_attributes($product_attributes);
        $product->save();
    }

    /**
     *
     */
    function custom_media_sideload_image($image_url = '', $post_id = false, $uploadedfile = [])
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp = download_url($image_url);
        #  Set variables for storage
        #  fix file filename for query strings
        preg_match('/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $image_url, $matches);
        $file_array['name'] = basename($matches[0]) ? basename($matches[0]) : $uploadedfile['name'];
        $file_array['tmp_name'] = $tmp ? $tmp : $uploadedfile['tmp_name'];
        #  If error storing temporarily, unlink
        if (is_wp_error($tmp)) {
            @unlink($file_array['tmp_name']);
            $file_array['tmp_name'] = '';
        }
        $time = current_time('mysql');
        $file = wp_handle_sideload($file_array, array('test_form' => false), $time);
        if (isset($file['error'])) {
            return new WP_Error('upload_error', $file['error']);
        }
        $url = $file['url'];
        $type = $file['type'];
        $file = $file['file'];
        $title = preg_replace('/\.[^.]+$/', '', basename($file));
        $parent = (int)absint($post_id) > 0 ? absint($post_id) : 0;
        $attachment = array(
            'post_mime_type' => $type,
            'guid' => $url,
            'post_parent' => $parent,
            'post_title' => $title ? $title : $uploadedfile['name'],
            'post_name' => $title ? $title : $uploadedfile['name'],
            'post_content' => '',
        );
        $id = wp_insert_attachment($attachment, $file, $parent);
        if (!is_wp_error($id)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $data = wp_generate_attachment_metadata($id, $file);
            wp_update_attachment_metadata($id, $data);
        }
        return $id;
    }

    /*****
     */
    protected function getWooSubstatuses()
    {

        $result = [];
        $result[] = [
            'status_id' => 'instock',
            'name' => 'In Stock'
        ];
        $result[] = [
            'status_id' => 'outofstock',
            'name' => 'Out of Stock'
        ];

        global $wpdb;
        $sql = "SELECT DISTINCT meta_value as stock_status FROM  {$wpdb->postmeta} WHERE meta_key = \"%s\"";
        $sql = sprintf($sql, "_stock_status");
        $st_statuses = $wpdb->get_results($sql, ARRAY_A);

        foreach ($st_statuses as $key => $value) {
            if (!in_array($value['stock_status'], ['instock', 'outofstock'])) {
                $result[] = [
                    'status_id' => $value['stock_status'],
                    'name' => $value['stock_status']
                ];
            }
        }

        return $result;
    }

    /**
     *
     */
    protected function setMImage($pr_id, $img_id, $only_image = false)
    {
        global $wpdb;
        $sql = "SELECT COUNT(*) as count, `meta_value` FROM  {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
        $sql = sprintf($sql, $pr_id, "_thumbnail_id");
        $counter = $wpdb->get_row($sql, ARRAY_A)['count'];
        $old_img = $wpdb->get_row($sql, ARRAY_A)['meta_value'];

        if (!$counter) {
            $sql = "INSERT INTO {$wpdb->postmeta} (meta_value, post_id, meta_key)
                    VALUES (%s, %s, %s)";
        } else {
            $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key = \"%s\"";
        }
        $wpdb->query($wpdb->prepare(
            $sql, $img_id, $pr_id, "_thumbnail_id"
        ));
        // delete from galery
        $this->removeProductImageById($pr_id, $img_id);
        if ($only_image) {
            $this->movePrimaryImage($pr_id, $old_img);
        }

        return true;
    }


    /**
     * Set new product price
     *
     * @param $productId
     * @param $newPrice
     * @return false|int
     */
    protected function updateProductInfo($pr_id)
    {

        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->posts} WHERE ID = %s ";
        $sql = sprintf($sql, $pr_id);
        $res = $wpdb->get_results($sql, ARRAY_A);
        if (!$res) return false;

        # меняем колличество товара
        if ((int)$_REQUEST['quantity']) {

            $sql = "SELECT COUNT(*) as count FROM  {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
            $sql = sprintf($sql, $pr_id, "_stock");
            $counter = $wpdb->get_row($sql, ARRAY_A)['count'];
            if (!$counter) {
                $sql = "INSERT INTO {$wpdb->postmeta} (meta_value, post_id, meta_key)
                    VALUES (%s, %s, %s)";
            } else {
                $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key = \"%s\"";
            }
            $wpdb->query($wpdb->prepare(
                $sql, $_REQUEST['quantity'], $pr_id, "_stock"
            ));
        }

        # меняем название товара
        if ($_REQUEST['name']) {

            $sql = "UPDATE {$wpdb->posts} SET post_title = %s WHERE ID = %s ";
            $wpdb->query($wpdb->prepare(
                $sql, $_REQUEST['name'], $pr_id
            ));

        }
		# меняем цену
        if ($_REQUEST['price']) {
            $sql = "SELECT COUNT(*) as count FROM  {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
            $sql = sprintf($sql, $pr_id, "_regular_price");
            $counter = $wpdb->get_row($sql, ARRAY_A)['count'];

            if (!$counter) {
                $sql = "INSERT INTO {$wpdb->postmeta} (meta_value, post_id, meta_key)
                    VALUES (%s, %s, %s)";
            } else {
                $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key =\"%s\"";
            }
            $wpdb->query($wpdb->prepare(
                $sql, $_REQUEST['price'], $pr_id, "_regular_price"
            ));

            delete_post_meta($pr_id, '_price');

            $sql = "SELECT COUNT(*) as count FROM  {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
            $sql = sprintf($sql, $pr_id, "_price");
            $counter = $wpdb->get_row($sql, ARRAY_A)['count'];
            if (!$counter) {
                $sql = "INSERT INTO {$wpdb->postmeta} (meta_value, post_id, meta_key)
                    VALUES (%s, %s, %s)";
            } else {
                $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key =\"%s\"";
            }
            $wpdb->query($wpdb->prepare(
                $sql, $_REQUEST['price'], $pr_id, "_price"
            ));

        }
        # меняем полное описание товара
        if ($_REQUEST['description']) {

            $sql = "UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %s ";
            $wpdb->query($wpdb->prepare(
                $sql, $_REQUEST['description'], $pr_id
            ));
            $text = $_REQUEST['description'];
            $shortDescr = preg_replace("#[^\.]*\.#s", '', $text, 1);

            # меняем краткое описание товара

            $sql = "UPDATE {$wpdb->posts} SET post_excerpt = %s WHERE ID = %s ";
            $wpdb->query($wpdb->prepare(
                $sql, $shortDescr, $pr_id
            ));
        }
        # меняем артикул
        if ($_REQUEST['sku']) {

            $sql = "SELECT COUNT(*) as count FROM  {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
            $sql = sprintf($sql, $pr_id, "_sku");
            $counter = $wpdb->get_row($sql, ARRAY_A)['count'];
            if (!$counter) {
                $sql = "INSERT INTO {$wpdb->postmeta} (meta_value, post_id, meta_key)
                    VALUES (%s, %s, %s)";
            } else {
                $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key = \"%s\"";
            }
            $wpdb->query($wpdb->prepare(
                $sql, $_REQUEST['sku'], $pr_id, "_sku"
            ));
        }
        # меняем статус остатка
        if ($_REQUEST['substatus'] && in_array($_REQUEST['substatus'], ['outofstock', 'instock'])) {

            $sql = "SELECT COUNT(*) as count FROM  {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
            $sql = sprintf($sql, $pr_id, "_stock_status");
            $counter = $wpdb->get_row($sql, ARRAY_A)['count'];
            if (!$counter) {
                $sql = "INSERT INTO {$wpdb->postmeta} (meta_value, post_id, meta_key)
                    VALUES (%s, %s, %s)";
            } else {
                $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key = \"%s\"";
            }
            $wpdb->query($wpdb->prepare(
                $sql, $_REQUEST['substatus'], $pr_id, "_stock_status"
            ));
        }
        # меняем статус продукта
        if ($_REQUEST['status'] && array_key_exists($_REQUEST['status'], get_post_statuses())) {

            $sql = "UPDATE {$wpdb->posts} SET post_status = %s WHERE ID = %s ";
            $wpdb->query($wpdb->prepare(
                $sql, $_REQUEST['status'], $pr_id
            ));
        }
        # меняем категорию
        if ($_REQUEST['categories'] && (is_array($_REQUEST['categories']))) {

            $query = $wpdb->prepare(
                "DELETE FROM {$wpdb->term_relationships} WHERE object_id = %s ", $pr_id
            );

            $wpdb->query($query);

            $cat_ids = $_REQUEST['categories'];
            foreach ($cat_ids as $key => $value) {
                $sql = "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id)
                        VALUES (%s, %s)";

                $wpdb->query($wpdb->prepare(
                    $sql, $pr_id, (int)$value
                ));
            }
        }

        # загружаем в галерею картинки
        $this->loadImages($pr_id, false);


        $img_arr = $this->getProductImages($pr_id);

        return [
            'product_id' => $pr_id,
            'images' => $img_arr,
        ];
    }


    protected function loadImages($pr_id, $makeFirstMain = false)
    {

        global $wpdb;
        $img_arr = [];
        # загружаем в галерею картинки

        if (isset($_FILES) && !empty($_FILES) && isset($_FILES['image'])) {


            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            $upload_overrides = array('test_form' => false);

            $files = $_FILES['image'];

            $counter = 0;
            for ($i = 0; $i < count($files['name']); $i++) {

                $uploadedfile = array(
                    'name' => isset($files['name'][$i]) ? $files['name'][$i] : '',
                    'type' => isset($files['type'][$i]) ? $files['type'][$i] : '',
                    'tmp_name' => isset($files['tmp_name'][$i]) ? $files['tmp_name'][$i] : '',
                    'error' => isset($files['error'][$i]) ? $files['error'][$i] : '',
                    'size' => isset($files['size'][$i]) ? $files['size'][$i] : '',
                );
                $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

                if ($movefile && !isset($movefile['error'])) {
                    $id = $this->custom_media_sideload_image($movefile['url'], $pr_id, $uploadedfile);
                    $ufiles = get_post_meta($pr_id, '_product_image_gallery', true);
                    if ($ufiles) $ufiles = explode(",", $ufiles);
                    if (empty($ufiles)) $ufiles = array();
                    $ufiles[] = $id;
                    $ufiles = implode(',', $ufiles);
                    update_post_meta($pr_id, '_product_image_gallery', $ufiles);
                    if ($makeFirstMain && !$counter) {
                        $this->setMImage($pr_id, $id); // записываем первую картинку как главную
                    }
                    $counter++;
                }
            }
        }
//        $sql = "SELECT meta_value as imgs FROM  {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
//        $sql = sprintf($sql, $pr_id, "_product_image_gallery");
//        $old_img = $wpdb->get_row($sql, ARRAY_A)['imgs'];
//        if ($old_img):
//            $sql = "SELECT ID, post_name, guid as imd_path FROM  {$wpdb->posts} WHERE id IN (%s)";
//            $sql = sprintf($sql, $old_img);
//            $imgs = $wpdb->get_results($sql, ARRAY_A);
//
//            foreach ($imgs as $key => $value) {
//                $img_arr[] = [
//                    'image' => $value['imd_path'],
//                    'image_id' => $value['ID'],
//                ];
//            }
//        endif;

        return true;//$img_arr;
    }

    /**
     * @param $category_id
     * @return bool|string
     */
    protected function isCategoryHasChild($category_id)
    {

        global $wpdb;
        $sql = "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE parent = %s and taxonomy = \"%s\"";
        $sql = sprintf($sql, $category_id, "product_cat");
        $res = $wpdb->get_row($sql, ARRAY_A);

        return $res['term_taxonomy_id'] ? true : false;

    }

    /**
     * @param $parrent_id
     */
    protected function getCategoriesByParentId($parrent_id)
    {

        if ((int)$parrent_id === -1) $parrent_id = 0;
# 
        global $wpdb;
        $sql = "SELECT tt.term_taxonomy_id, t.name, tt.parent FROM {$wpdb->term_taxonomy} tt 
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_taxonomy_id WHERE tt.parent = %s and tt.taxonomy = \"%s\"";
        $sql = sprintf($sql, $parrent_id, "product_cat");

        $res = $wpdb->get_results($sql, ARRAY_A);
        $arr = [];
        if ($res) {
            foreach ($res as $key => $value) {
                $arr[] = [
                    'category_id' => $value['term_taxonomy_id'],
                    'name' => $value['name'],
                    'parent' => $this->isCategoryHasChild($value['term_taxonomy_id'])
                ];
            }
        }
        return $arr;

    }


    protected function removeProductMainImage($pr_id)
    {
        if (!$pr_id) return false;
        require_once ABSPATH . 'wp-admin/includes/image.php';


        $product = new WC_Product($pr_id);
        $main_img_id = $product->get_image_id();
        if ($main_img_id) {
            $this->removeProductImageById($pr_id, $main_img_id);

            unlink(get_the_post_thumbnail_url($pr_id));
            #  удаляем с базы
            delete_post_thumbnail($pr_id);
            #  подстраховываемся
            #         $query = $wpdb->prepare(
            #             "DELETE FROM {$wpdb->term_postmeta} WHERE post_id = %s AND meta_key = %s", $pr_id, '_thumbnail_id'
            #         );
            #
            #         $wpdb->query($query);

            #  удаляем с сервера

            wp_delete_attachment($pr_id);
            $src = wp_get_attachment_image_src($pr_id);
            unlink($src);

            return $product->get_image_id();
        } else {
            return [
                'error'   => true,
                'message' => 'Error. Can not delete empty image.',
            ];
        }
    }

    protected function removeProductImageById($pr_id, $img_id)
    {
        if (!$pr_id) return false;
#         require_once ABSPATH . 'wp-admin/includes/image.php';
#         delete_metadata()
        global $wpdb;
        $sql = "SELECT meta_value as galery FROM {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
        $sql = sprintf($sql, $pr_id, "_product_image_gallery");

        $res = $wpdb->get_row($sql, ARRAY_A)['galery'];
        $imgs = [];
        if ($res) $imgs = explode(',', $res);
        if (($key = array_search((string)$img_id, $imgs)) !== FALSE) {
            unset($imgs[$key]);
        }

        $result = implode(",", $imgs);

        $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key = \"%s\"";

        $wpdb->query($wpdb->prepare(
            $sql, $result, $pr_id, "_product_image_gallery"
        ));
        return true;
    }

    protected function movePrimaryImage($pr_id, $img_id)
    {
        if (!$pr_id || !$img_id) return false;

        global $wpdb;

        $sql = "SELECT meta_value as galery FROM {$wpdb->postmeta} WHERE post_id = %s and meta_key = \"%s\"";
        $sql = sprintf($sql, $pr_id, "_product_image_gallery");
        $galery = $wpdb->get_row($sql, ARRAY_A)['galery'];

        if ($galery) (array)$images = explode(',', $galery);

        if(stristr($img_id, ',')){
            (array)$new_images = explode(',', $img_id);
            $images = array_merge($images, $new_images);
        }else{
            $images[] = $img_id;
        }

        $result = implode(",", $images);

        $sql = "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %s and meta_key = \"%s\"";

        $wpdb->query($wpdb->prepare(
            $sql, $result, $pr_id, "_product_image_gallery"
        ));
        return true;
    }

}


## Удаляет все вложения записи (прикрепленные медиафайлы) записи вместе с записью (постом)
add_action('before_delete_post', 'delete_attachments_with_post');
function delete_attachments_with_post($postid)
{
    $post = get_post($postid);

    #  проверим тип записи для которых нужно удалять вложение
    if (in_array($post->post_type, ['article', 'question'])) {
        $attachments = get_children(array('post_type' => 'attachment', 'post_parent' => $postid));
        if ($attachments) {
            foreach ($attachments as $attachment) wp_delete_attachment($attachment->ID);
        }
    }
}
