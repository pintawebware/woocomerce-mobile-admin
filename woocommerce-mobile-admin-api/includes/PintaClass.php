<?php

include_once(WOOCOMMERCE_PINTA_DIR . 'includes/FunctionsClass.php');

register_deactivation_hook(__FILE__, array('ma_connector', 'woocommerce_pinta_deactivation'));

class PintaClass extends FunctionsClass
{
    const PLUGIN_VERSION = '2.0';
    const HASH_ALGORITHM = 'sha256';

    public function __construct()
    {
		
        parent:: __construct();

        $this->check_db();
        $type = filterNull($_GET['route']);

        switch ($type) {
            case 'login':
                $this->login();
                break;
            case 'deletedevicetoken':
                $this->deletedevicetoken();
                break;
            case 'updatedevicetoken':
                $this->updatedevicetoken();
                break;
            case 'statistic':
                $this->statistic();
                break;
            case 'orders';
                $this->orders();
                break;
            case 'getorderinfo':
                $this->getorderinfo();
                break;
            case 'orderproducts':
                $this->orderproducts();
                break;
            case 'paymentanddelivery':
                $this->paymentanddelivery();
                break;
            case 'products':
                $this->products();
                break;
            case 'productinfo':
                $this->productinfo();
                break;
            case 'changestatus':
                $this->changestatus();
                break;
            case 'delivery':
                $this->delivery();
                break;
            case 'clientinfo':
                $this->clientinfo();
                break;
            case 'clients':
                $this->clients();
                break;
            case 'clientorders':
                $this->clientorders();
                break;
            case 'orderhistory':
                $this->orderhistory();
                break;
            case 'сhangequantity':
                $this->сhangeQuantity();
                break;
            case 'updateproduct':
                $this->updateProduct();
                break;
            case 'mainimage':
                $this->setMainImage();
                break;
            case 'getsubstatus':
                $this->getSubstatus();
                break;
            case 'getcategories':
                $this->getCategories();
                break;
            case 'deleteimage':
                $this->deleteImage();
                break;
            default:
                break;
        }
    }


    /**
     * @api {post} index.php?route=login  Login
     * @apiName Login
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {String} username User unique username.
     * @apiParam {Number} password User's  password.
     * @apiParam {String} device_token User's device's token for firebase notifications.
     * @apiParam {String} os_type User's type of Operational system.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} token  Token.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "response":
     *       {
     *          "token": "e9cf23a55429aa79c3c1651fe698ed7b",
     *       },
     *       "status": true
     *       "version": 2.0,
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Incorrect username or password",
     *       "version": 2.0,
     *       "status" : false
     *     }
     *
     */
    private function login()
    {
        
        if (!filterNull($_REQUEST['username']) || !filterNull($_REQUEST['password'])) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $user = $this->check_Auth($_REQUEST['username'], $_REQUEST['password']);

        if (!filterNull($user['ID'])) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Incorrect username or password', 'status' => false]);
            die;
        }
        $os = $_REQUEST['os_type'] ? $_REQUEST['os_type'] : '';
        if (filterNull($_REQUEST['device_token'])) {
            $devices = $this->getUserDevices($user['ID'], $_REQUEST['device_token'], $os);
            if (!$devices) {
                $this->setUserDeviceToken($user['ID'], $_REQUEST['device_token'], $os);
            }
        }

        $token = $this->getUserToken($user['ID']);
        if (!isset($token)) {
            $token = md5(mt_rand());
            $this->setUserToken($user['ID'], $token);
        }

        echo(json_encode([
            'response' => [
                'token' => $token,
            ],
            'status' => true,
            'version' => self::PLUGIN_VERSION,
        ]));
        die;
    }

    /**
     * @api {post} index.php?route=deletedevicetoken  deleteUserDeviceToken
     * @apiName deleteUserDeviceToken
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {String} old_token User's device's token for firebase notifications.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "version": 2.0,
     *       "status": true,
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Missing some params",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */
    private function deletedevicetoken()
    {
        if (filterNull($_REQUEST['old_token'])) {
            $old_token = $_REQUEST['old_token'];

            global $wpdb;
            $sql = "SELECT * FROM {$wpdb->prefix}user_device  WHERE  device_token = %s";
            $query = $wpdb->prepare($sql, $old_token);
            $isset_for_deleting = $wpdb->query($query);

            if ($isset_for_deleting) {
                $sql = "DELETE FROM {$wpdb->prefix}user_device  WHERE  device_token = %s";
                $wpdb->query($wpdb->prepare(
                    $sql, $old_token
                ));
                echo json_encode([
                    'version' => self::PLUGIN_VERSION,
                    'status' => true,
                ]);
                die;
            } else {
                echo json_encode([
                    'version' => self::PLUGIN_VERSION,
                    'error' => 'Can not find your token',
                    'status' => false
                ]);
                die;
            }
        } else {
            echo json_encode([
                'version' => self::PLUGIN_VERSION,
                'error' => 'Missing some params',
                'status' => false
            ]);
            die;
        }
    }

    /**
     * @api {post} index.php?route=updatedevicetoken  updateUserDeviceToken
     * @apiName updateUserDeviceToken
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {String} new_token User's device's new token for firebase notifications.
     * @apiParam {String} old_token User's device's old token for firebase notifications.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "status": true,
     *       "version": 2.0
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Missing some params",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */
    private function updatedevicetoken()
    {

        if (filterNull($_REQUEST['old_token']) && filterNull($_REQUEST['new_token'])) {
            $updated = $this->updateUserDeviceToken($_REQUEST['old_token'], $_REQUEST['new_token']);
            if ($updated) {
                echo json_encode(['version' => self::PLUGIN_VERSION, 'status' => true]);
                die;
            } else {
                echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can not find your token', 'status' => false]);
                die;
            }
        } else {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }
    }


    /**
     * @api {get} index.php?route=statistic  getDashboardStatistic
     * @apiName getDashboardStatistic
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {String} filter Period for filter(day/week/month/year).
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Array} xAxis Period of the selected filter.
     * @apiSuccess {Array} Clients Clients for the selected period.
     * @apiSuccess {Array} Orders Orders for the selected period.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} total_sales  Sum of sales of the shop.
     * @apiSuccess {Number} sale_year_total  Sum of sales of the current year.
     * @apiSuccess {Number} orders_total  Total orders of the shop.
     * @apiSuccess {Number} clients_total  Total clients of the shop.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *           "response": {
     *               "xAxis": [
     *                  1,
     *                  2,
     *                  3,
     *                  4,
     *                  5,
     *                  6,
     *                  7
     *              ],
     *              "clients": [
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0
     *              ],
     *              "orders": [
     *                  1,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0
     *              ],
     *              "total_sales": "1920.00",
     *              "sale_year_total": "305.00",
     *              "currency_code": "UAH",
     *              "orders_total": "4",
     *              "clients_total": "3"
     *           },
     *           "status": true,
     *           "version": 2.0
     *  }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Unknown filter set",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */
    private function statistic()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (filterNull($_REQUEST['filter'])) {
            if (!$this->is_valid_filter($_REQUEST['filter'])) {
                echo json_encode(['error' => 'Unknown filter set', 'status' => false]);
                die;
            }

            $clients = $this->getTotalCustomers(['filter' => $_REQUEST['filter']]);
            $orders = $this->getTotalOrders(['filter' => $_REQUEST['filter']]);
            $clients_for_time = [];
            $orders_for_time = [];

            switch ($_REQUEST['filter']) {
                case 'day':
                    $hours = range(0, 23);
                    for ($i = 0; $i <= 23; $i++) {
                        $b = 0;
                        $o = 0;

                        if ($clients['user_registered']):
                            foreach ($clients['user_registered'] as $value) {
                                $hour = strtotime($value);
                                $hour = date("h", $hour);

                                if ($hour == $i) {
                                    $b = $b + 1;
                                }
                            }
                        endif;
                        $clients_for_time[] = $b;

                        if ($orders['order_date']):
                            foreach ($orders['order_date'] as $value) {

                                $day = strtotime($value);
                                $day = date("h", $day);

                                if ($day == $i) {
                                    $o = $o + 1;
                                }
                            }
                        endif;;
                        $orders_for_time[] = $o;
                    }
                    break;
                case 'week':
                    $hours = range(1, 7);

                    for ($i = 1; $i <= 7; $i++) {
                        $b = 0;
                        $o = 0;
                        if ($clients['user_registered']):
                            foreach ($clients['user_registered'] as $value) {
                                $date = strtotime($value);

                                $f = date("N", $date);

                                if ($f == $i) {
                                    $b = $b + 1;
                                }
                            }
                        endif;

                        $clients_for_time[] = $b;
                        if ($orders['order_date']):
                            foreach ($orders['order_date'] as $val) {

                                $day = strtotime($val);
                                $day = date("N", $day);

                                if ($day == $i) {
                                    $o = $o + 1;
                                }
                            }
                        endif;
                        $orders_for_time[] = $o;
                    }
                    break;
                case 'month':
                    $hours = range(1, 30);
                    for ($i = 1; $i <= 30; $i++) {
                        $b = 0;
                        $o = 0;
                        if ($clients['user_registered']):
                            foreach ($clients['user_registered'] as $value) {

                                $day = strtotime($value);
                                $day = date("d", $day);

                                if ($day == $i) {
                                    $b = $b + 1;
                                }
                            }
                        endif;
                        $clients_for_time[] = $b;

                        if ($orders['order_date']):
                            foreach ($orders['order_date'] as $value) {

                                $day = strtotime($value);
                                $day = date("d", $day);

                                if ($day == $i) {
                                    $o = $o + 1;
                                }
                            }
                        endif;
                        $orders_for_time[] = $o;
                    }
                    break;
                case 'year':
                    $hours = range(1, 12);

                    for ($i = 1; $i <= 12; $i++) {
                        $b = 0;
                        $o = 0;
                        if ($clients['user_registered']):
                            foreach ($clients['user_registered'] as $value) {

                                $date = strtotime($value);

                                $f = date("m", $date);

                                if ($f == $i) {
                                    $b = $b + 1;
                                }
                            }
                        endif;
                        $clients_for_time[] = $b;

                        if ($orders['order_date']):
                            foreach ($orders['order_date'] as $val) {

                                $day = strtotime($val);
                                $day = date("m", $day);

                                if ($day == $i) {
                                    $o = $o + 1;
                                }
                            }
                        endif;
                        $orders_for_time[] = $o;
                    }

                    break;
                default:
                    break;
            }

            $data['xAxis'] = $hours;
            $data['clients'] = $clients_for_time;
            $data['orders'] = $orders_for_time;

            $sale_total = $this->getTotalOrders('totalsum');

            $data['currency_code'] = get_woocommerce_currency();
            $data['total_sales'] = (string)number_format($sale_total['total_sales'], 2, '.', '');
            $sale_year_total = $this->getTotalOrders(['filter' => 'this_year']);
            $data['sale_year_total'] = (string)number_format($sale_year_total['total_sales'], 2, '.', '');

            $data['orders_total'] = (string)$sale_total['count_orders'];
            $data['clients_total'] = (string)$this->getTotalCustomers('totalsum')['count_customers'];

            echo json_encode(['version' => self::PLUGIN_VERSION, 'response' => $data, 'status' => true]);
            die;

        } else {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }
    }

    /**
     * @api {get} index.php?route=orders  getOrders
     * @apiName GetOrders
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} page number of the page.
     * @apiParam {Number} limit limit of the orders for the page.
     * @apiParam {String} fio full name of the client.
     * @apiParam {Number} order_status_id unique id of the order.
     * @apiParam {Number} min_price min price of order.
     * @apiParam {Number} max_price max price of order.
     * @apiParam {Date} date_min min date adding of the order.
     * @apiParam {Date} date_max max date adding of the order.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Array} orders  Array of the orders.
     * @apiSuccess {Array} statuses  Array of the order statuses.
     * @apiSuccess {Number} order_id  ID of the order.
     * @apiSuccess {Number} order_number  Number of the order.
     * @apiSuccess {String} fio     Client's FIO.
     * @apiSuccess {String} status  Status of the order.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {String} order[currency_code] currency of the order.
     * @apiSuccess {Number} total  Total sum of the order.
     * @apiSuccess {Date} date_added  Date added of the order.
     * @apiSuccess {Date} total_quantity  Total quantity of the orders.
     *
     *
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     * "response": {
     * "orders": [
     * {
     * "order_number": "34",
     * "currency_code": "UAH",
     * "date_added": "2017-05-03 10:24:12",
     * "fio": "Вася Пупкин",
     * "total": "99.00",
     * "status": "Обработка",
     * "order_id": "34"
     * },
     * {
     * "order_number": "33",
     * "currency_code": "UAH",
     * "date_added": "2017-05-03 10:18:29",
     * "fio": "Вася Пупкин",
     * "total": "315.00",
     * "status": "Обработка",
     * "order_id": "33"
     * },
     * {
     * "order_number": "32",
     * "currency_code": "UAH",
     * "date_added": "2017-05-03 10:11:47",
     * "fio": "Вася Пупкин",
     * "total": "315.00",
     * "status": "Обработка",
     * "order_id": "32"
     * },
     * {
     * "order_number": "31",
     * "currency_code": "UAH",
     * "date_added": "2017-05-03 10:00:13",
     * "fio": "Вася Пупкин",
     * "total": "198.00",
     * "status": "Обработка",
     * "order_id": "31"
     * },
     * {
     * "order_number": "30",
     * "currency_code": "UAH",
     * "date_added": "2017-04-28 11:37:34",
     * "fio": "User3Name User3Surname",
     * "total": "1044.00",
     * "status": "Обработка",
     * "order_id": "30"
     * }
     * ],
     * "statuses": [
     * {
     * "order_status_id": "wc-pending",
     * "name": "В ожидании оплаты",
     * "language_id": 1
     * },
     * {
     * "order_status_id": "wc-processing",
     * "name": "Обработка",
     * "language_id": 1
     * },
     * {
     * "order_status_id": "wc-on-hold",
     * "name": "На удержании",
     * "language_id": 1
     * },
     * {
     * "order_status_id": "wc-completed",
     * "name": "Выполнен",
     * "language_id": 1
     * },
     * {
     * "order_status_id": "wc-cancelled",
     * "name": "Отменен",
     * "language_id": 1
     * },
     * {
     * "order_status_id": "wc-refunded",
     * "name": "Возвращён",
     * "language_id": 1
     * },
     * {
     * "order_status_id": "wc-failed",
     * "name": "Не удался",
     * "language_id": 1
     * }
     * ],
     * "currency_code": "UAH",
     * "total_quantity": 15,
     * "total_sum": "29088.00",
     * "max_price": "1044.00"
     * },
     * "version": "2.0",
     * "status": true
     * }
     * @apiErrorExample Error-Response:
     *
     * {
     *      "version": 2.0,
     *      "Status" : false
     *
     * }
     *
     *
     */
    private function orders()
    {
        $error = $this->valid();
        if ($error) {
            echo(json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]));
            die;
        }

        $orders = $this->getOrders();

        echo json_encode(['response' => $orders, 'version' => self::PLUGIN_VERSION, 'status' => true]);
        die;
    }

    /**
     * @api {get} index.php?route=getorderinfo  getOrderInfo
     * @apiName getOrderInfo
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} order_number  Number of the order.
     * @apiSuccess {String} fio     Client's FIO.
     * @apiSuccess {String} status  Status of the order.
     * @apiSuccess {String} email  Client's email.
     * @apiSuccess {Number} phone  Client's phone.
     * @apiSuccess {Number} total  Total sum of the order.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Date} date_added  Date added of the order.
     * @apiSuccess {Array} statuses  Statuses list for order.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *      "response" :
     *          {
     *              "order_number" : "6",
     *              "currency_code": "RUB",
     *              "fio" : "Anton Kiselev",
     *              "email" : "client@mail.ru",
     *              "telephone" : "056 000-11-22",
     *              "date_added" : "2016-12-24 12:30:46",
     *              "total" : "1405.00",
     *              "status" : "Сделка завершена",
     *              "statuses" :
     *                  {
     *                         {
     *                             "name": "Отменено",
     *                             "order_status_id": "7",
     *                             "language_id": "1"
     *                         },
     *                         {
     *                             "name": "Сделка завершена",
     *                             "order_status_id": "5",
     *                             "language_id": "1"
     *                          },
     *                          {
     *                              "name": "Ожидание",
     *                              "order_status_id": "1",
     *                              "language_id": "1"
     *                           }
     *                    }
     *          },
     *      "status" : true,
     *      "version": 2.0
     * }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Can not found order with id = 5",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     */
    private function getorderinfo()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!filterNull($_REQUEST['order_id'])) {
            echo json_encode([
                'version' => self::PLUGIN_VERSION,
                'error' => 'You have not specified ID',
                'status' => false
            ]);
            die;
        }

        $id = $_REQUEST['order_id'];

        $order = $this->getOrders($id);

        if ($order) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'response' => $order, 'status' => true]);
            die;
        } else {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can not found order with id = ' . $id, 'status' => false]);
            die;
        }
    }


    /**
     * @api {get} index.php?route=orderproducts  getOrderProducts
     * @apiName getOrderProducts
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {ID} order_id unique order id.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Url} image  Picture of the product.
     * @apiSuccess {Number} quantity  Quantity of the product.
     * @apiSuccess {String} name     Name of the product.
     * @apiSuccess {Number} Price  Price of the product.
     * @apiSuccess {Number} total_order_price  Total sum of the order.
     * @apiSuccess {Number} total_price  Sum of product's prices.
     * @apiSuccess {String} currency_code  currency of the order.
     * @apiSuccess {Number} shipping_price  Cost of the shipping.
     * @apiSuccess {Number} total  Total order sum.
     * @apiSuccess {Number} product_id  unique product id.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *      "response":
     *          {
     *              "products": [
     *              {
     *                  "image" : "http://opencart/image/catalog/demo/htc_touch_hd_1.jpg",
     *                  "name" : "HTC Touch HD",
     *                  "quantity" : 3,
     *                  "price" : 100.00,
     *                  "product_id" : 90,
     *                  "discount" : 1.00,
     *                  "discount_price" : 99.00,
     *              },
     *              {
     *                  "image" : "http://opencart/image/catalog/demo/iphone_1.jpg",
     *                  "name" : "iPhone",
     *                  "quantity" : 1,
     *                  "price" : 500.00,
     *                  "product_id" : 97,
     *                  "discount" : 10.00,
     *                  "discount_price" : 490.00,
     *               }
     *            ],
     *            "total_order_price":
     *              {
     *                   "total_discount": 0,
     *                   "total_price": 2250,
     *                   "currency_code": "RUB",
     *                   "shipping_price": 35,
     *                   "total": 2285
     *               }
     *
     *         },
     *      "status": true,
     *      "version": 2.0
     * }
     *
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *          "error": "Can not found any products in order with id = 10",
     *          "version": 2.0,
     *          "Status" : false
     *     }
     *
     */

    private function orderproducts()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!filterNull($_REQUEST['order_id'])) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'You have not specified ID', 'status' => false]);
            die;
        }

        $id = $_REQUEST['order_id'];

        $products = $this->getOrderProducts($id);

        if ($products) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'response' => $products, 'status' => true]);
            die;
        } else {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can not found any products in order with id = ' . $id, 'status' => false]);
            die;
        }
    }

    /**
     * @api {get} index.php?route=paymentanddelivery  getOrderPaymentAndDelivery
     * @apiName getOrderPaymentAndDelivery
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} payment_method     Payment method.
     * @apiSuccess {String} shipping_method  Shipping method.
     * @apiSuccess {String} shipping_address  Shipping address.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *
     *      {
     *          "response":
     *              {
     *                  "payment_method" : "Оплата при доставке",
     *                  "shipping_method" : "Доставка с фиксированной стоимостью доставки",
     *                  "shipping_address" : "проспект Карла Маркса 1, Днепропетровск, Днепропетровская область, Украина."
     *              },
     *          "status": true,
     *          "version": 2.0
     *      }
     * @apiErrorExample Error-Response:
     *
     *    {
     *      "error": "Can not found order with id = 90",
     *      "version": 2.0,
     *      "Status" : false
     *   }
     *
     */

    public function paymentanddelivery()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!filterNull($_REQUEST['order_id'])) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'You have not specified ID', 'status' => false]);
            die;
        }

        $id = $_REQUEST['order_id'];

        $order = $this->getOrdersDeliveryInfo($id);

        if (!$order) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can not found order with id = ' . $id, 'status' => false]);
            die;
        }
        echo json_encode(['version' => self::PLUGIN_VERSION, 'response' => $order, 'status' => true]);
        die;
    }


    /**
     * @api {get} index.php?route=products  getProductsList
     * @apiName getProductsList
     * @apiGroup All
     * @apiVersion 2.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} page number of the page.
     * @apiParam {Number} limit limit of the orders for the page.
     * @apiParam {String} name name of the product for search.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} product_id  ID of the product.
     * @apiSuccess {String} name  Name of the product.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} price  Price of the product.
     * @apiSuccess {Number} quantity  Actual quantity of the product.
     * @apiSuccess {Url} image  Url to the product image.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response":
     *   {
     *      "products":
     *      {
     *           {
     *             "product_id" : "1",
     *             "name" : "HTC Touch HD",
     *             "price" : "100.00",
     *             "currency_code": "UAH",
     *             "quantity" : "83",
     *             "image" : "http://site-url/image/catalog/demo/htc_touch_hd_1.jpg"
     *           },
     *           {
     *             "product_id" : "2",
     *             "model" : "White",
     *             "name" : "iPhone",
     *             "price" : "300.00",
     *             "currency_code": "UAH",
     *             "quantity" : "30",
     *             "image" : "http://site-url/image/catalog/demo/iphone_1.jpg"
     *           }
     *      }
     *   },
     *   "Status" : true,
     *   "version": 2.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Not one product not found",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */

    public function products()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        $products = $this->getProductsList();

        echo json_encode(['version' => self::PLUGIN_VERSION, 'response' => ['products' => $products], 'status' => true]);
        die;
    }

    /**
     * @api {get} index.php?route=productinfo  getProductInfo
     * @apiName getProductInfo
     * @apiGroup All
     * @apiVersion 2.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} product_id unique product ID.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} product_id  ID of the product.
     * @apiSuccess {String} name  Name of the product.
     * @apiSuccess {Number} price  Price of the product.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} quantity  Actual quantity of the product.
     * @apiSuccess {String} description     Detail description of the product.
     * @apiSuccess {Array}  images Array of the images of the product.
     * @apiSuccess {String} sku  sku of product.
     * @apiSuccess {Array} statuses  Array of statuses of the product.
     * @apiSuccess {Array} stock_statuses  Array of stock statuses of the product.
     * @apiSuccess {String} status  status of product. (is published)
     * @apiSuccess {String} stock_status_name Name of stock status of the product.
     * @apiSuccess {String} status_name Name of status of the product.
     * @apiSuccess {Array} categories Array of categories of the product.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     * "version":"2.0",
     * "response":
     *  {
     *      "product_id":"28",
     *      "name":"\u0422\u043e\u0432\u0430\u04403",
     *      "price":"315.00",
     *      "currency_code":"UAH",
     *      "quantity":"978",
     *      "sku":"ar123",
     *      "statuses":
     *       [
     *              {
     *                  "status_id":"pending",
     *                  "name":"Pending Review"
     *              },
     *              {
     *                  "status_id":"private",
     *                  "name":"Private"
     *              },
     *              {
     *                  "status_id":"publish",
     *                  "name":"Published"
     *              }
     *       ],
     *        "stock_statuses":
     *         [
     *              {
     *                  "stock_status_id":"instock",
     *                  "name":"In Stock"
     *              },
     *              {
     *                  "stock_status_id":"outofstock",
     *                  "name":"Out of Stock"
     *              }
     *         ],
     *          "stock_status_name":"In Stock",
     *          "status_name":"Published",
     *          "categories":[
     *           {
     *              "category_id": "16",
     *              "name": "Тестовая категория"
     *           },
     *           {
     *              "category_id": "21",
     *              "name": "Категория3 - Категория4"
     *           },
     *           {
     *              "category_id": "24",
     *              "name": "Категория3 - Категория7"
     *           }
     *           ],
     *          "description":"\u0411\u043b\u0430 \u0431\u043b\u0430 \u0431\u043b\u0430",
     *          "images":
     *          [
     *              {
     *                  "image":"http:\/\/wordpress.local\/wp-content\/uploads\/2017\/04\/Chrysanthemum-300x300.jpg",
     *                  "image_id":"11"
     *              },
     *              {
     *                  "image":"http:\/\/wordpress.local\/wp-content\/uploads\/2017\/04\/Jellyfish-300x300.jpg",
     *                  "image_id":13
     *              },
     *              {
     *                  "image":"http:\/\/wordpress.local\/wp-content\/uploads\/2017\/04\/Koala-1-300x300.jpg",
     *                  "image_id":29
     *              },
     *              {
     *                  "image":"http:\/\/wordpress.local\/wp-content\/uploads\/2017\/04\/Lighthouse-1-300x300.jpg",
     *                  "image_id":27
     *              }
     *          ]
     *      },
     *      "status":true
     * }
     *
     *
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found product with id = 10",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     */

    public function productinfo()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!filterNull($_REQUEST['product_id'])) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'You have not specified ID', 'status' => false]);
            die;
        }

        $id = $_REQUEST['product_id'];

        $product = $this->getProductsByID($id);
        if (!$product) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can not found product with id = ' . $_REQUEST['product_id'], 'status' => false]);
            die;
        }
//        $product_img = $this->getProductImages($id);
//        $product['images'] = $product_img;

        echo json_encode(['version' => self::PLUGIN_VERSION, 'response' => $product, 'status' => true]);
        die;
    }

    /**
     * @api {get} index.php?route=changestatus  ChangeStatus
     * @apiName ChangeStatus
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {String} comment New comment for order status.
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Number} status_id unique status ID.
     * @apiParam {Token} token your unique token.
     * @apiParam {Boolean} inform status of the informing client.
     * @apiParam {String} lang Code code of the letter template.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} name Name of the new status.
     * @apiSuccess {String} date_added Date of adding status.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *          "response":
     *              {
     *                  "name" : "Сделка завершена",
     *                  "date_added" : "2016-12-27 12:01:51"
     *              },
     *          "status": true,
     *          "version": 2.0
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Missing some params",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */

    public function changestatus()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['status_id'] || !$_REQUEST['order_id']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $statusID = $_REQUEST['status_id'];
        $orderID = $_REQUEST['order_id'];
        $comment = filterNull($_REQUEST['comment'], '');
        $inform = filterNull($_REQUEST['inform'], false);
        $lang = isset($_REQUEST['lang']) ? $_REQUEST['lang'] : 'ru';

        if (!$this->isValidStatus($statusID)) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Unknown status params', 'status' => false]);
            die;
        }
        $response = changeOrderStatus($orderID, $statusID, $comment, $inform, $lang);
        if (!$response) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Error', 'status' => false]);
            die;
        }

        $post_object = get_post($orderID);
        $data = [
            "name" => get_order_statuses()[$post_object->post_status],
            "date_added" => $post_object->post_modified,
        ];

        echo json_encode(['version' => self::PLUGIN_VERSION, 'response' => $data, 'status' => true]);
        die;
    }

    /**
     * @api {get} index.php?route=delivery  ChangeOrderDelivery
     * @apiName ChangeOrderDelivery
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {String} address New shipping address.
     * @apiParam {String} city New shipping city.
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} response Status of change address.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *         "status": true,
     *         "version": 2.0
     *    }
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Can not change address",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */

    public function delivery()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['order_id'] || !$_REQUEST['address']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $address = $_REQUEST['address'];
        $order_id = $_REQUEST['order_id'];
        $city = filterNull($_REQUEST['city'], false);

        $data = $this->ChangeOrderDelivery($address, $city, $order_id);
        if ($data) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'status' => true]);
            die;

        } else {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can not change address', 'status' => false]);
            die;

        }
    }

    /**
     * @api {get} index.php?route=clientinfo  getClientInfo
     * @apiName getClientInfo
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} client_id unique client ID.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} client_id  ID of the client.
     * @apiSuccess {String} fio     Client's FIO.
     * @apiSuccess {Number} total  Total sum of client's orders.
     * @apiSuccess {Number} quantity  Total quantity of client's orders.
     * @apiSuccess {String} email  Client's email.
     * @apiSuccess {String} telephone  Client's telephone.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} cancelled  Total quantity of cancelled orders.
     * @apiSuccess {Number} completed  Total quantity of completed orders.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response"
     *   {
     *         "client_id" : "88",
     *         "fio" : "Anton Kiselev",
     *         "total" : "1006.00",
     *         "quantity" : "5",
     *         "cancelled" : "1",
     *         "completed" : "2",
     *         "currency_code": "UAH",
     *         "email" : "client@mail.ru",
     *         "telephone" : "13456789"
     *   },
     *   "Status" : true,
     *   "version": 2.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Not one client found",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */
    private function clientinfo()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['client_id']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $client_id = $_REQUEST['client_id'];

        $cl_obj = new WP_User($client_id);

        if (!$cl_obj->id) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can not found client with id = ' . $client_id, 'status' => false]);
            die;
        }

        $customer_orders = $this->_get_customer_orders($cl_obj->ID);
        $count_canceled = 0;
        $count_completed = 0;
        if ($customer_orders):
            foreach ($customer_orders as $ord) {
                $currency_code = $ord['currency_code'];
                switch ($ord['ord_status_code']) {
                    case 'wc-completed':
                        $count_completed++;
                        break;
                    case 'wc-cancelled':
                        $count_canceled++;
                        break;
                }
            }
        endif;
        $customer_order_totals = $this->_get_customer_orders_total($cl_obj->ID);
        $response = [
            "client_id" => (string)$client_id,
            "fio" => filterNull($cl_obj->display_name,
                filterNull($cl_obj->first_name, '') . ' '
                . filterNull($cl_obj->last_name, '')),
            "total" => (string)number_format((float)filterNull($customer_order_totals['sum_ords']), 2, '.', ''),
            "quantity" => (string)filterNull($customer_order_totals['c_orders_count']),
            "cancelled" => (string)$count_canceled,
            "completed" => (string)$count_completed,
            "currency_code" => filterNull($currency_code, ''),
            "email" => filterNull($cl_obj->user_email, ''),
            "telephone" => filterNull($cl_obj->billing_phone, ''),
        ];

        echo(json_encode([
            'version' => self::PLUGIN_VERSION,
            'response' => $response,
            'status' => true]));
        die;
    }

    /**
     * @api {get} index.php?route=clients  getClients
     * @apiName GetClients
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} page number of the page.
     * @apiParam {Number} limit limit of the orders for the page.
     * @apiParam {String} fio full name of the client.
     * @apiParam {String} sort param for sorting clients(sum/quantity/date_added).
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} client_id  ID of the client.
     * @apiSuccess {String} fio     Client's FIO.
     * @apiSuccess {Number} total  Total sum of client's orders.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} quantity  Total quantity of client's orders.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response"
     *   {
     *     "clients"
     *      {
     *          {
     *              "client_id" : "88",
     *              "fio" : "Anton Kiselev",
     *              "total" : "1006.00",
     *              "currency_code": "UAH",
     *              "quantity" : "5"
     *          },
     *          {
     *              "client_id" : "10",
     *              "fio" : "Vlad Kochergin",
     *              "currency_code": "UAH",
     *              "total" : "555.00",
     *              "quantity" : "1"
     *          }
     *      }
     *    },
     *    "Status" : true,
     *    "version": 2.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Not one client found",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     */
    public function clients()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        $customers = $this->get_customers();
        if (!$customers) {
            echo json_encode([
                'version' => self::PLUGIN_VERSION,
                'error' => "Not one client found",
                'status' => false
            ]);
            die;
        }

        echo(json_encode([
            'version' => self::PLUGIN_VERSION,
            'response' => ['clients' => $customers],
            'status' => true]));
        die;
    }

    /**
     * @api {get} index.php?route=clientorders  getClientOrders
     * @apiName getClientOrders
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} client_id unique client ID.
     * @apiParam {String} sort param for sorting orders(total/date_added/completed/cancelled).
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} order_id  ID of the order.
     * @apiSuccess {Number} order_number  Number of the order.
     * @apiSuccess {String} status  Status of the order.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} total  Total sum of the order.
     * @apiSuccess {Date} date_added  Date added of the order.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response"
     *   {
     *       "orders":
     *          {
     *             "order_id" : "1",
     *             "order_number" : "1",
     *             "status" : "Сделка завершена",
     *             "currency_code": "UAH",
     *             "total" : "106.00",
     *             "date_added" : "2016-12-09 16:17:02"
     *          },
     *          {
     *             "order_id" : "2",
     *             "currency_code": "UAH",
     *             "order_number" : "2",
     *             "status" : "В обработке",
     *             "total" : "506.00",
     *             "date_added" : "2016-10-19 16:00:00"
     *          }
     *    },
     *    "Status" : true,
     *    "version": 2.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "You have not specified ID",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */

    public function clientorders()
    {

        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['client_id']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $client_id = intval($_REQUEST['client_id']);

        $customer_orders = $this->_get_customer_orders($client_id);

        $response = [];
        if (!$customer_orders || isset($customer_orders['error'])) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => isset($customer_orders['error']) ? $customer_orders['error'] : 'Customer hasn\'t any orders', 'status' => false]);
            die;
        }

        if ($customer_orders):
            foreach ($customer_orders as $cord) {
                $data['order_id'] = (string)$cord["id_order"];
                $data['order_number'] = (string)$cord["id_order"];
                $data['status'] = $cord["ord_status"];
                $data['currency_code'] = $cord["currency_code"];
                $data['total'] = (string)$cord["total_paid"];
                $data['date_added'] = $cord["date_add"];
                $response[] = $data;
            }
        endif;

        echo(json_encode([
            'version' => self::PLUGIN_VERSION,
            'response' => ['orders' => $response],
            'status' => true]));
        die;
    }


    /**
     * @api {get} index.php?route=orderhistory  getOrderHistory
     * @apiName getOrderHistory
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} name     Status of the order.
     * @apiSuccess {Number} order_status_id  ID of the status of the order.
     * @apiSuccess {Date} date_added  Date of adding status of the order.
     * @apiSuccess {String} comment  Some comment added from manager.
     * @apiSuccess {Array} statuses  Statuses list for order.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *       {
     *           "response":
     *               {
     *                   "orders":
     *                      {
     *                          {
     *                              "name": "Отменено",
     *                              "order_status_id": "7",
     *                              "date_added": "2016-12-13 08:27:48.",
     *                              "comment": "Some text"
     *                          },
     *                          {
     *                              "name": "Сделка завершена",
     *                              "order_status_id": "5",
     *                              "date_added": "2016-12-25 09:30:10.",
     *                              "comment": "Some text"
     *                          },
     *                          {
     *                              "name": "Ожидание",
     *                              "order_status_id": "1",
     *                              "date_added": "2016-12-01 11:25:18.",
     *                              "comment": "Some text"
     *                           }
     *                       },
     *                    "statuses":
     *                        {
     *                             {
     *                                  "name": "Отменено",
     *                                  "order_status_id": "7",
     *                                  "language_id": "1"
     *                             },
     *                             {
     *                                  "name": "Сделка завершена",
     *                                  "order_status_id": "5",
     *                                  "language_id": "1"
     *                              },
     *                              {
     *                                  "name": "Ожидание",
     *                                  "order_status_id": "1",
     *                                  "language_id": "1"
     *                              }
     *                         }
     *               },
     *           "status": true,
     *           "version": 2.0
     *       }
     * @apiErrorExample Error-Response:
     *
     *     {
     *          "error": "Can not found any statuses for order with id = 5",
     *          "version": 2.0,
     *          "Status" : false
     *     }
     */

    public function orderhistory()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['order_id']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $id = $_REQUEST['order_id'];

        $orders = $this->getOrderHistory($id);

        if (!$orders) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Empty history', 'status' => false]);
            die;
        }

        $response = [
            "orders" => $orders,
            "statuses" => $this->get_orders_statuses(),
        ];

        echo json_encode([
            'version' => self::PLUGIN_VERSION,
            'response' => $response,
            'status' => true]);
        die;
    }

    /**
     * @api {get} index.php?route=сhangequantity  сhangeQuantity
     * @apiName сhangeQuantity
     * @apiGroup All
     * @apiVersion 1.0.0
     *
     * @apiParam {Number} new_quantity new quantity of products.
     * @apiParam {Number} product_id unique product ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "version": 2.0,
     *       "status": true,
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Missing some params",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */

    public function сhangeQuantity()
    {

        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['product_id'] || !$_REQUEST['new_quantity']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $pr_id = $_REQUEST['product_id'];
        $new_qu = $_REQUEST['new_quantity'];
        if (!((float)$new_qu) || !((int)$pr_id)) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'No valid data. Correct your data and try again.', 'status' => false]);
            die;
        }

        $result = $this->setNewProductStock($pr_id, $new_qu);

        if (!$result) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can\'t find product or data didn\'t change or any another error. Try again later.', 'status' => false]);
            die;
        }


        echo json_encode([
            'version' => self::PLUGIN_VERSION,
            'status' => true]);
        die;

    }


    /**
     * @api {get} index.php?route=updateproduct  updateProduct
     * @apiName updateProduct
     * @apiGroup All
     * @apiVersion 2.0.0
     *
     *
     * @apiParam {Token}  token your unique token.
     * @apiParam {Number} product_id unique product ID. (post_id wp_posts)
     * @apiParam {Number} quantity quantity of products (_stock wp_postmeta)
     * @apiParam {Array}  photos массив фоток (три массива приходят от мобильщиков, массив новых, массив для удаления и main image) (_product_image_gallery,)
     * @apiParam {String} name product name.(post_title in wp_posts)
     * @apiParam {String} description full product description. (post_content in wp_posts)
     *                    short product description (post_excerpt in wp_posts) making from full description
     * @apiParam {String} model model of Product.
     * @apiParam {String} sku sku of Product (_sku wp_postmeta). (артикул)
     * @apiParam {String} substatus stock status of Product (_stock_status = instock wp_postmeta). Subtract Stock  (в наличии)
     * @apiParam {String} status  Product status (is publish?)
     * @apiParam {String} price  Product price
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     * "version": "2.0",
     * "response": {
     * "product_id": "157",
     * "name": "MyProduct!!!",
     * "price": "11.00",
     * "currency_code": "UAH",
     * "quantity": "5",
     * "sku": "art12345",
     * "statuses": [
     * {
     * "status_id": "pending",
     * "name": "Pending Review"
     * },
     * {
     * "status_id": "private",
     * "name": "Private"
     * },
     * {
     * "status_id": "publish",
     * "name": "Published"
     * }
     * ],
     * "stock_statuses": [
     * {
     * "status_id": "instock",
     * "name": "In Stock"
     * },
     * {
     * "status_id": "outofstock",
     * "name": "Out of Stock"
     * }
     * ],
     * "stock_status_name": "In Stock",
     * "status_name": "Published",
     * "categories": [],
     * "description": "Big Description",
     * "images": [
     * {
     * "image": "http://woocommerce.pixy.pro/wp-content/uploads/2017/07/Foreks-sovetnik-kalmar-skachat-besplatno-i-bez-registratsii-4-1.jpg",
     * "image_id": -1
     * }
     * ]
     * },
     * "status": true
     * }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Missing some params",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     *
     */

    public function updateProduct()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['name']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $pr_id = $_REQUEST['product_id'];
//        $result = false;
        if ((int)$pr_id == 0) {
            $result = $this->addNewProduct();
        } else {

            if (!((int)$pr_id)) {
                echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'No valid data. Correct your data and try again.', 'status' => false]);
                die;
            }

            $result = $this->updateProductInfo($pr_id);
        }

        if (!$result) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Can\'t find product or data didn\'t change or any another error. Try again later.', 'status' => false]);
            die;
        }
        echo json_encode([
            'response' => $result,
            'version' => self::PLUGIN_VERSION,
            'status' => true,
        ]);
        die;
    }


    /**
     * @api {post} index.php?route=mainimage  mainImage
     * @apiName mainImage
     * @apiGroup All
     * @apiVersion 2.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} product_id unique product ID.
     * @apiParam {Number} image_id unique image ID.
     *
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status Status of the product update.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Status" : true,
     *   "version": 2.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found category with id = 10",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */

    public function setMainImage()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['product_id'] || !$_REQUEST['image_id']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $img_id = $_REQUEST['image_id'];
        $pr_id = $_REQUEST['product_id'];

        if (!((int)$pr_id) || !((int)$img_id)) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'No valid data. Correct your data and try again.', 'status' => false]);
            die;
        }

        $result = $this->setMImage($pr_id, $img_id, true);

        if (!$result) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Error. Try again later.', 'status' => false]);
            die;
        }
        echo json_encode([
            'version' => self::PLUGIN_VERSION,
            'status' => true,
        ]);
        die;
    }


    /**
     * @api {post} index.php?route=getsubstatus  getSubstatus
     * @apiName getSubstatus
     * @apiGroup All
     * @apiVersion 2.0.0
     *
     * @apiParam {Token} token your unique token.
     *
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status Status of the answer.
     * @apiSuccess {Object} response Pesponse with stock statuses
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "response":
     *       {
     *         "stock_statuses":
     *             [
     *                  {
     *                    "stock_status_id":"instock",
     *                    "name":"In Stock"
     *                  },
     *                 {
     *                    "stock_status_id":"outofstock",
     *                    "name":"Out of Stock"
     *                 }
     *             ]
     *        },
     *   "version":"2.0",
     *    "status":true
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found category with id = 10",
     *      "version": 2.0,
     *      "Status" : false
     * }
     */
    public function getSubstatus()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }


        $result = $this->getWooSubstatuses();
        $result_stat = $this->getProductStatuses();

        if (!$result) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Error. Try again later.', 'status' => false]);
            die;
        }
        echo json_encode([
            'response' => [
                'stock_statuses' => $result,
                'statuses' => $result_stat,
            ],
            'version' => self::PLUGIN_VERSION,
            'status' => true,
        ]);
        die;
    }


    /**
     * @api {post} index.php?route=getcategories  getCategories
     * @apiName getCategories
     * @apiGroup All
     * @apiVersion 2.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} category_id unique category ID.
     *
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Array} categories  array of categories.
     * @apiSuccess {Boolean} status Status of the product update.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {"response":
     *      {
     *          "categories":
     *          [
     *              {
     *                  "category_id":"17",
     *                  "name":"\u0422\u0435\u0441\u0442\u043e\u0432\u0430\u044f \u043a\u0430\u0442\u0435\u0433\u043e\u0440\u0438\u044f2",
     *                  "parent":false
     *              }
     *          ]
     *      },
     *      "version":"2.0",
     *      "status":true
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found category with id = 10",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */
    public function getCategories()
    {
        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['category_id']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $cat_id = $_REQUEST['category_id'];

        if (!((int)$cat_id)) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'No valid data. Correct your data and try again.', 'status' => false]);
            die;
        }

        $result = $this->getCategoriesByParentId($cat_id);

        if (!$result) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Error! Haven\'t categories with such parent!', 'status' => false]);
            die;
        }
        echo json_encode([
            'response' => [
                'categories' => $result
            ],
            'version' => self::PLUGIN_VERSION,
            'status' => true,
        ]);
        die;
    }


    /**
     * @api {post} index.php?route=deleteimage  deleteImage
     * @apiName deleteImage
     * @apiGroup All
     * @apiVersion 2.0.0
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} product_id unique product ID.
     * @apiParam {Number} image_id unique image ID.
     *
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status Status of the product update.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Status" : true,
     *   "version": 2.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found category with id = 10",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */

    public function deleteImage()
    {

        $error = $this->valid();
        if ($error) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => $error, 'status' => false]);
            die;
        }

        if (!$_REQUEST['product_id'] || !$_REQUEST['image_id']) {
            echo json_encode(['version' => self::PLUGIN_VERSION, 'error' => 'Missing some params', 'status' => false]);
            die;
        }

        $result = ($_REQUEST['image_id'] == -1)
            ? $this->removeProductMainImage($_REQUEST['product_id'])
            : $this->removeProductImageById($_REQUEST['product_id'], $_REQUEST['image_id']);

        if (!$result || ($result && isset($result['error']) && $result['error'])) {
            echo json_encode([
                'version' => self::PLUGIN_VERSION,
                'error' => $result['message'] ? $result['message'] : 'Error. Try again later.',
                'status' => false
            ]);
            die;
        }
        echo json_encode([
            'version' => self::PLUGIN_VERSION,
            'status' => true,
        ]);
        die;
    }
}