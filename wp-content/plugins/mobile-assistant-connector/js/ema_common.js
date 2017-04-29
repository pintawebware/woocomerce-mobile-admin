var _old_login = '';
var _old_pass = '';

var $jQema = jQuery.noConflict();


$jQema(document).ready(function() {
    var baseWooUrl       = $jQema('#mobassistantconnector_base_url').val();
    var baseUrl          = emoScript.baseUrl;
    var loader_img       = '<img src="' + baseUrl + 'images/loader.gif">';
    var loader           = '<div class="mobassistantconnector_loader">' + loader_img + '</div>';
    var loader_devices   = '<div id="mobassistantconnector_loader_diveces">' + loader + '</div>';
    var loader_container = '<div id="mobassistantconnector_loader_container">' + loader + '</div>';
    var devices_parent   = $jQema('#mobassistantconnector_super_parent_devices_container');
    var key              = $jQema('#mobassistantconnector_key').val();
    var users            = $jQema('#users li');
    var current_user_id  = false;

    devices_parent.append(loader_devices);

    var yes                = '<img src="' + baseUrl + 'images/yes.png">';
    var no                 = '<img src="' + baseUrl + 'images/no.png">';
    var enabled            = '<img src="' + baseUrl + 'images/enabled.gif" title="Disable">';
    var disabled           = '<img src="' + baseUrl + 'images/disabled.gif" title="Enable">';
    var trash              = '<img src="' + baseUrl + 'images/trash.png" title="Enable">';
    var add                = '<img src="' + baseUrl + 'images/add.png" title="Enable">';

    var mobassistantconnector_devices_container = '<div id="mobassistantconnector_devices_container">' + loader_container + '</div>';
    var mobassistantconnector_devices_table_div = '<div id="mobassistantconnector_devices_table_div"></div>';

    devices_parent.append(mobassistantconnector_devices_container);
    $jQema('#mobassistantconnector_devices_container').append(mobassistantconnector_devices_table_div);

    users.live('click', function() {
        user_id = $jQema(this).attr('id');
        if (user_id !== 'user-add-ema') {
            getUserData(user_id);
        } else {
            $jQema("#new-user-success").hide();
            $jQema("#new-user-warning").hide()
        }
    });

    getUsers();

    setEvents();


    function setEvents() {
        $jQema('div.mobassistantconnector_status').live('click', function() {
            changeStatus($jQema(this));
        });

        $jQema('div.mobassistantconnector_delete').live('click', function() {
            if (confirm('Are you sure you want to delete device?')) {
                deleteDeviceBulk($jQema(this).attr('device_id'));
            }
        });

        $jQema('#mobassistantconnector_bulk_select_all').live('click', function() {
            $jQema('.mobassistantconnector_pushes').each(function() {
                $jQema(this).prop('checked', true);
            });
        });

        $jQema('#mobassistantconnector_bulk_unselect_all').live('click', function() {
            $jQema('.mobassistantconnector_pushes').each(function() {
                $jQema(this).prop('checked', false);
            });
        });

        $jQema('#mobassistantconnector_bulk_status_active').live('click', function() {
            changeStatusBulk(1);
        });

        $jQema('#mobassistantconnector_bulk_status_inactive').live('click', function() {
            changeStatusBulk(0);
        });

        $jQema('#mobassistantconnector_bulk_delete').live('click', function() {
            if (confirm('Are you sure you want to delete selected devices?')) {
                deleteDeviceBulk(false);
            }
        });

        $jQema('#delete_user').live('submit', function(event) {
            var form = $jQema(this);
            var target = form.attr('data-target');
            var alert_success = $jQema("#delete-user-success");
            var alert_warning = $jQema("#delete-user-warning");

            $jQema("#mac_del_user_id").val(current_user_id);

            event.preventDefault();
            form.key = emoScript.secKey;
            var a = 2;

            $jQema.ajax({
                type: form.attr('method'),
                url: ajaxurl,
                cache: false,
                data: form.serialize(),

                success: function(data, status) {
                    if(typeof data == 'object') {
                        if (data.success) {
                            //alert_success.val(data);
                            alert_success.show().delay(5000);
                            //form.modal('hide');
                            //alert_success.delay( 1500).hide();

                            $jQema('#emoModalDeleteUser').delay(5000).modal('hide');
                            current_user_id = false;
                            getUsers();

                            return false;
                            //$jQematarget.html(data);
                        } else {
                            //alert_warning.val(data);
                            alert_warning.html('<strong>Warning!</strong> ' + data.error)
                            alert_warning.show();
                            //$jQematarget.html(data);
                        }
                    }

                },

                error: function(){
                    alert("Unnable to delete user");
                }
            });
        });

        $jQema('#add_new_user').live('submit', function(event) {
            var form = $jQema(this);
            var target = form.attr('data-target');
            var alert_success = $jQema("#new-user-success");
            var alert_warning = $jQema("#new-user-warning");

            alert_success.hide();
            alert_warning.hide();

            event.preventDefault();

            $jQema.ajax({
                type: form.attr('method'),
                url: ajaxurl,
                cache: false,
                data: form.serialize(),

                success: function(data, status) {
                    if(typeof data == 'object') {
                        if (data.success) {
                            //alert_success.val(data);
                            alert_success.show().delay(5000);
                            //form.modal('hide');
                            //alert_success.delay( 1500).hide();

                            $jQema('#emoModalNewUser').delay(5000).modal('hide');
                            current_user_id = data.user_id;
                            getUsers();

                            return false;
                            //$jQematarget.html(data);
                        } else {
                            //alert_warning.val(data);
                            alert_warning.html('<strong>Warning!</strong> ' + data.error)
                            alert_warning.show();
                            //$jQematarget.html(data);
                        }
                    }

                },

                error: function(){
                    alert("Failure to add new user");
                }
            });
        });

        $jQema('#form_mobassist').live('submit', function(event) {
            var form = $jQema(this);
            var target = form.attr('data-target');
            var alert_success = $jQema("#save-user-success");
            var alert_warning = $jQema("#save-user-warning");

            $jQema("[name='user[user_id]']").val(current_user_id);

            alert_success.hide();
            alert_warning.hide();

            event.preventDefault();

            $jQema.ajax({
                type: form.attr('method'),
                url: ajaxurl,
                cache: false,
                data: form.serialize(),

                success: function(data, status) {
                    if(typeof data == 'object') {
                        if (typeof(data.success) !== 'undefined' && data.success) {
                            //alert_success.val(data);
                            alert_success.show();

                            alert_success.delay(6200).hide(1);
                            //form.modal('hide');
                            //alert_success.delay( 1500).hide();

                            current_user_id = data.user_id;
                            getUsers();
                            $jQema('html, body').animate({ scrollTop: 0 }, 'fast');
                            return false;
                            //$jQematarget.html(data);
                        } else {
                            //alert_warning.val(data);
                            alert_warning.html('<strong>Warning!</strong> ' + data.error);
                            alert_warning.show();
                            alert_warning.delay(6200).hide(1);

                            //$jQematarget.html(data);
                        }
                    }

                },

                error: function(){
                    alert("Cant save the user");
                }
            });
        });
    }

    function checkDefaultCredentials() {
        var alert_warning = $jQema("#default-user-credentials");
        var system_warning = $jQema("#ema-system-warnings");
        var d_user_login = $jQema('#mobassist_login');
        var d_user_pass = $jQema('#mobassist_pass');

        if(d_user_login.val() == 1 && d_user_pass.val() == 'c4ca4238a0b923820dcc509a6f75849b') {
            alert_warning.show();
            alert_warning.delay(10200).hide(1);
        }
        if(emoScript.emaSystemWarnings != '') {
            system_warning.text(system_warning.text() +emoScript.emaSystemWarnings);
            system_warning.show();
            system_warning.delay(10200).hide(1);
        }
    }


    function getUsers() {
        $jQema.post(ajaxurl,
            {
                action: 'ema_callback',
                call_function: 'mac_get_users',
                key: key
            },
            function (data) {
                var target      = $jQema('#users');
                var loader_dev  = $jQema('#mobassistantconnector_loader_diveces');
                var loader_bulk = $jQema('#mobassistantconnector_loader_container');

                target.html('');

                if (data != 'error' && data != 'Authentication error') {
                    var is_data_empty = true;
                    var order;
                    var customer;
                    var href_data = '';
                    var status;
                    var active_class = '';

                    $jQema("#form_mobassist :input").attr("disabled", false);
                    $jQema.each(data, function(index, values) {
                        //href_data = '';
                        if (!current_user_id) {
                            current_user_id = values.user_id;
                        }

                        if (current_user_id == values.user_id) {
                            active_class =  ' class="active"';
                        } else {
                            active_class = '';
                        }

                        href_data += '<li id="'+values.user_id+'" '
                            + active_class
                                //+ ((current_user_id == values.user_id) ? ' class="active"' : '')
                            + '><a href="#tab-user' +
                            values.user_id + '" data-toggle="tab"><i class="fa fa-minus-circle"  title="Delete user" '
                            + ' ></i> '
                            + values.username +'</a></li>';

                        //target.html(href_data + target.html());

                    });

                    target.html(href_data + '<li id="user-add-ema" style="cursor:pointer;"  data-toggle="modal" data-target="#emoModalNewUser"><a><i class="fa fa-plus-circle" style="color:green"><img src="' + baseUrl + 'images/add4.png" title="Add user"></i> <span style="color:green;">&nbspAdd user </span></a></li>');

                    if (current_user_id) {
                        getUserData(current_user_id);
                    }

                    //target.html(href_data + target.html());

                } else {
                    $jQema("#form_mobassist :input").attr("disabled", true);
                    loader_bulk.hide();
                    loader_dev.hide();
                    setButtonActive(true);
                    alert(data);
                }
            }, 'json'
        );
    }

    function getUserData(user_id) {
        //user_id = elmnt.attr('id');
        _old_login = '';
        _old_pass  = '';

        if (user_id) {
            current_user_id = user_id;
            $jQema.post(ajaxurl,
                {
                    action: 'ema_callback',
                    call_function: 'mac_get_user_data',
                    user_id: user_id,
                    key: key
                },
                function (data) {
                    var t_user_status = $jQema('#input-status');
                    var t_user_login_old = $jQema('#mobassist_login_old');
                    var t_user_login = $jQema('#mobassist_login');
                    var t_user_pass = $jQema('#mobassist_pass');
                    var t_user_pass_old = $jQema('#mobassist_pass_old');
                    var t_user_qr_code = $jQema('#mobassist_qr_code');
                    var t_user_qr = $jQema('#mobassist_qr');


                    var t_user_qr_url = $jQema('#qr_code_url');

                    var t_user_push_new_order = $jQema('#user_allowed_actions_push_new_order');
                    var t_user_push_order_status_changed = $jQema('#user_allowed_actions_push_order_status_changed');
                    var t_user_push_new_customer = $jQema('#user_allowed_actions_push_new_customer');

                    var t_user_store_statistics = $jQema('#user_allowed_actions_store_stats');

                    var t_user_order_list = $jQema('#user_allowed_actions_order_list');
                    var t_user_order_details = $jQema('#user_allowed_actions_order_details');
                    var t_user_order_status_updating = $jQema('#user_allowed_actions_order_status_updating');
                    var t_user_order_details_pdf = $jQema('#user_allowed_actions_order_details_pdf');

                    var t_user_customer_list = $jQema('#user_allowed_actions_customers_list');
                    var t_user_customer_details = $jQema('#user_allowed_actions_customer_details');

                    var t_user_product_list = $jQema('#user_allowed_actions_products_list');
                    var t_user_product_details = $jQema('#user_allowed_actions_product_details');

                    var loader_dev = $jQema('#mobassistantconnector_loader_diveces');
                    var loader_bulk = $jQema('#mobassistantconnector_loader_container');

                    if (data != 'error' && data != 'Authentication error') {
                        var is_data_empty = true;
                        var order;
                        var customer;
                        var href_data = '';
                        var status;

                        $jQema("#user_permissions input[type=checkbox]").each(function()
                        {
                            $jQema(this).prop('checked', false);
                        });

                        $jQema.each(data, function (index, values) {

                            if (values.status == '1') {
                                t_user_status.val(1);
                            } else {
                                t_user_status.val(0);
                            }

                            //t_user_login.text(values.username);
                            t_user_login.val(values.username);
                            t_user_login_old.val(values.username);
                            t_user_pass.val(values.password);
                            //t_user_pass.text(values.password);
                            t_user_pass_old.val(values.password);
                            t_user_qr_code.val(values.qr_code_data);

                            _old_login = values.username;
                            _old_pass  = values.password;

                            $jQema('#mobassist_qr_code').html('');

                            var qrcode_container = document.getElementById('mobassist_qr_code');
                            var qrcode = new QRCode(qrcode_container, {
                                width : 250,
                                height : 250
                            });

                            qrcode.makeCode(values.qr_code_data);

                            $jQema("#mobassist_qr_code_changed").hide("fast");
                            $jQema("#mobassist_qr_code").css('opacity', '1').show('fast');

                            t_user_qr_url.attr("href", baseWooUrl + '/?connector=mobileassistant&call_function=get_qr_code&hash=' + values.qr_code_hash);

                            if (values.allowed_actions.indexOf("push_notification_settings_new_order") >= 0) {
                                t_user_push_new_order.prop('checked', true);
                            }
                            if (values.allowed_actions.indexOf("push_notification_settings_order_statuses") >= 0) {
                                t_user_push_order_status_changed.prop('checked', true);
                            }
                            if (values.allowed_actions.indexOf("push_notification_settings_new_customer") >= 0) {
                                t_user_push_new_customer.prop('checked', true);
                            }

                            if (values.allowed_actions.indexOf("store_stats") >= 0) {
                                t_user_store_statistics.prop('checked', true);
                            }

                            if (values.allowed_actions.indexOf("orders_list") >= 0) {
                                t_user_order_list.prop('checked', true);
                            }
                            if (values.allowed_actions.indexOf("order_details") >= 0) {
                                t_user_order_details.prop('checked', true);
                            }
                            if (values.allowed_actions.indexOf("update_order_status") >= 0) {
                                t_user_order_status_updating.prop('checked', true);
                            }
                            if (values.allowed_actions.indexOf("order_details_pdf") >= 0) {
                                t_user_order_details_pdf.prop('checked', true);
                            }

                            if (values.allowed_actions.indexOf("customers_list") >= 0) {
                                t_user_customer_list.prop('checked', true);
                            }
                            if (values.allowed_actions.indexOf("customer_details") >= 0) {
                                t_user_customer_details.prop('checked', true);
                            }

                            if (values.allowed_actions.indexOf("products_list") >= 0) {
                                t_user_product_list.prop('checked', true);
                            }
                            if (values.allowed_actions.indexOf("product_details") >= 0) {
                                t_user_product_details.prop('checked', true);
                            }

                            checkDefaultCredentials();
                            /*                            (values.push_new_order) ? t_user_push_new_order.prop('checked', true) : '' ;
                             (values.push_order_statuses) ? t_user_push_order_status_changed.prop('checked', true) : '' ;
                             (values.push_new_customer) ? t_user_push_new_customer.attr('checked', true) : '';*/
                            /*                            t_user_store_statistics
                             t_user_order_list
                             t_user_order_details
                             t_user_product_list
                             t_user_product_details*/
                            loader_dev.show();
                            getDevices(values.user_id);
                        });



                        //target.html(href_data + target.html());

                    } else {
                        loader_bulk.hide();
                        loader_dev.hide();
                        setButtonActive(true);
                        alert(data);
                    }
                }, 'json'
            );
        }
    }

    function getDevices(user_id) {
        $jQema.post(ajaxurl,
            {
                action: 'ema_callback',
                call_function: 'mac_get_devices',
                user_id: user_id,
                key: key
            },
            function (data) {
                var target      = $jQema('#mobassistantconnector_devices_table tbody');
                var loader_dev  = $jQema('#mobassistantconnector_loader_diveces');
                var loader_bulk = $jQema('#mobassistantconnector_loader_container');

                if (data != 'error' && data != 'Authentication error') {
                    var is_data_empty = true;
                    var order;
                    var customer;
                    var status;
                    var table_data = '';
                    var table_data1 = '<table id="mobassistantconnector_devices_table" class="table product">' +
                        '<tr><th>Device Name</th><th>Account Email</th><th>Last Activity</th><th></th><th>App Connection ID</th>' +
                        '<th>New Order</th><th>New Customer</th><th>Order Statuses</th><th>Status</th><th></th></tr>';

                    $jQema.each(data, function(index, values) {
                        is_data_empty = false;
                        var count_pushes = values.pushes.length;

                        for (var i = 0; i < count_pushes; i++) {
                            var delimiter = '';

                            if (i == (count_pushes - 1)) {
                                delimiter = ' class="mobassistantconnector_device_delimiter" ';
                            }

                            table_data += '<tr id="mobassistantconnector_row_device_' + values.pushes[i].id + '"' + delimiter + '>';

                            if (i == 0) {
                                table_data += '<td class="mobassistantconnector_device_delimiter mobassistantconnector_center" rowspan="' + count_pushes + '">' +
                                    values.device_name + '</td><td class="mobassistantconnector_device_delimiter mobassistantconnector_center" rowspan="' + count_pushes + '">' +
                                    values.account_email + '</td><td class="mobassistantconnector_device_delimiter mobassistantconnector_center" ' +
                                    'rowspan="' + count_pushes + '">' + values.last_activity + '</td>';
                            }

                            if (values.pushes[i].new_order == 1) {
                                order = yes;
                            } else {
                                order = no;
                            }

                            if (values.pushes[i].new_customer == 1) {
                                customer = yes;
                            } else {
                                customer = no;
                            }

                            if (values.pushes[i].status == 1) {
                                status = '<div class="mobassistantconnector_status" device_id="' + values.pushes[i].id + '" val="1">' + enabled + '</div>';
                            } else {
                                status = '<div class="mobassistantconnector_status" device_id="' + values.pushes[i].id + '" val="0">' + disabled + '</div>';
                            }

                            table_data += '<td class="mobassistantconnector_center">' +
                                values.pushes[i].app_connection_id + '</td><td class="mobassistantconnector_center">' + order +
                                '</td><td class="mobassistantconnector_center">' + customer +
                                '</td><td>' + values.pushes[i].order_statuses + '</td><td class="mobassistantconnector_center mobassistantconnector_status">' +
                                status + '</td><td class="mobassistantconnector_center"><div class="mobassistantconnector_delete" device_id="' + values.pushes[i].id + '"><img src="' +
                                baseUrl + 'images/trash.png" title="Delete"></div></td></tr>';
                        }
                    });

                    if (is_data_empty) {
                        table_data += '<tr><td colspan="9" style="text-align: center">No data</td></tr>';
                    }

                    //table_data += '</table>';

                    '<div style="margin-top: 10px; margin-bottom: 10px"><button type="button" ' +
                    'id="mobassistantconnector_bulk_btn">Bulk actions <img id="mobassistantconnector_bulk_btn_img" src="' + baseUrl +
                    'images/down.png"></button></div><div id="mobassistantconnector_bulk_list">' +
                    '<table><tr id="mobassistantconnector_bulk_select_all"><td><img src="' + baseUrl +
                    'images/checked.png"></td><td>Select all</td></tr>' +
                    '<tr id="mobassistantconnector_bulk_unselect_all"><td><img src="' + baseUrl +
                    'images/unchecked.png"></td><td>Unselect all</td>' +
                    '</tr><tr class="mobassistantconnector_delimiter"><td colspan="2"><hr></td></tr>' +
                    '<tr id="mobassistantconnector_bulk_status_active"><td><img width="12px" height="12px" src="' + baseUrl +
                    'images/enabled.gif"></td><td>Change status to active</td>' +
                    '</tr><tr id="mobassistantconnector_bulk_status_inactive"><td><img width="12px" height="12px" src="' + baseUrl +
                    'images/disabled.gif"></td><td>Change status to inactive</td></tr>' +
                    '<tr class="mobassistantconnector_delimiter"><td colspan="2"><hr></td></tr>' +
                    '<tr id="mobassistantconnector_bulk_delete"><td><img src="' + baseUrl +
                    'images/bulk_trash.png"></td><td>Delete selected</td></tr></table>';
                    loader_dev.hide();
                    loader_bulk.hide();

                    target.html(table_data);
                } else {
                    loader_bulk.hide();
                    loader_dev.hide();
                    setButtonActive(true);
                    alert("Unable to get devices list" + data);
                }
            }, 'json'
        );
        $jQema('#mobassistantconnector_devices_table').removeClass('mobassistantconnector_inactive');
    }

    function changeStatus(selector) {
        var val      = selector.attr('val');
        var img_prev = selector.html();
        selector.html(loader_img);

        if (val == 0) {
            val = 1;
        } else {
            val = 0;
        }

        $jQema.post(ajaxurl,
            {
                action: 'ema_callback',
                call_function: 'change_status',
                push_ids: selector.attr('device_id'),
                value: val,
                key: key
            },
            function(data) {
                if (data == 'success') {
                    if (val == 1) {
                        selector.html(enabled);
                        selector.attr('val', 1);
                    } else {
                        selector.html(disabled);
                        selector.attr('val', 0)
                    }
                } else {
                    selector.html(img_prev);
                    alert(data);
                }
            }, 'json'
        ).error(function() {
                selector.html(img_prev);
                alert('Unable to change status');
            });
    }

    function changeStatusBulk(val) {
        var push_ids = getPushIds();

        if (push_ids.length == 0) {
            alert('Nothing selected');
            return;
        }

        var loader_bulk = $jQema('#mobassistantconnector_loader_container');

        setButtonActive(false);
        loader_bulk.show();
        $jQema('#mobassistantconnector_devices_table').addClass('mobassistantconnector_inactive');
        $jQema.post(ajaxurl,
            {
                action: 'ema_callback',
                call_function: 'change_status',
                push_ids: push_ids,
                value: val,
                key: key
            },
            function(data) {
                if (data != 'success') {
                    alert(data);
                }

                //getDevices();
            }, 'json'
        ).error(function() {
                loader_bulk.hide();
                setButtonActive(true);
                alert('Some error occurred');
            });
    }

    function deleteDeviceBulk(id) {
        var push_ids;

        if (!id) {
            push_ids = getPushIds();
        } else {
            push_ids = id;
        }

        if (push_ids.length == 0) {
            alert('Nothing selected');
            return;
        }

        var loader_bulk = $jQema('#mobassistantconnector_loader_container');

        setButtonActive(false);
        loader_bulk.show();
        $jQema('#mobassistantconnector_devices_table').addClass('mobassistantconnector_inactive');
        $jQema.post(ajaxurl,
            {
                action: 'ema_callback',
                call_function: 'delete_device',
                push_ids: push_ids,
                key: key
            },
            function(data) {
                if (data != 'success') {
                    alert(data);
                }

                getDevices(current_user_id);
            }, 'json'
        ).error(function() {
                loader_bulk.hide();
                setButtonActive(true);
                alert('Unable to delete ');
            });
    }

    function getPushIds() {
        var push_ids = '';

        $jQema('[name="mobassistantconnector_pushes[]"]:checked').each(function() {
            if (push_ids.length > 0) {
                push_ids += ',' + $jQema(this).val();
            } else {
                push_ids = $jQema(this).val();
            }
        });

        return push_ids;
    }

    function setButtonActive(val) {
        var button = $jQema('#mobassistantconnector_bulk_btn');

        if (val) {
            button.removeAttr('disabled');
        } else {
            button.attr('disabled','disabled');
        }
    }
});