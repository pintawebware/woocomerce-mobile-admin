<?php if (defined('MOBASSIS_KEY')) { ?>
    <div class="panel-heading">
        <div style="display: inline-block; margin-right: 18px; margin-bottom: 15px; float: right">
                        <span style="margin-right: 15px">
                            <?php printf(__("Module version: <b>%s</b>", 'mobile-assistant-connector'), MobileAssistantConnector::PLUGIN_VERSION); ?>
                        </span>
            Useful links:
            <a href="https://wordpress.org/plugins/mobile-assistant-connector/" class="link" target="_blank"><?php _e( 'Check new version', 'mobile-assistant-connector' ); ?></a> |
            <a href="https://support.emagicone.com/submit_ticket" class="link" target="_blank"><?php _e( 'Submit a ticket', 'mobile-assistant-connector' ); ?></a> |
            <a href="http://mobile-store-assistant-help.emagicone.com/woocommerce-mobile-assistant-installation-instructions" class="link" target="_blank"><?php _e( 'Documentation', 'mobile-assistant-connector' ); ?></a>
        </div>
    </div>
    <div class="panel-body">
        <!-- Modal -->
        <div class="modal fade" id="emoModalNewUser" role="dialog">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">

                </div>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><?php _e( 'New user', 'mobile-assistant-connector' ); ?></h4>
                </div>
                <!-- Modal Body -->
                <div class="modal-body">
                    <form id="add_new_user" data-async class="form-horizontal" role="form" method="POST" action="<?php echo plugins_url('/../../functions/ajax.php', __FILE__) ?>">
                        <fieldset>
                            <input type="hidden" name="key" id="mobassistantconnector_key" value="<?php echo (hash( 'sha256', MOBASSIS_KEY  . AUTH_KEY )); ?>">
                            <input type="hidden" name="action" id="action" value="ema_callback">
                            <div class="alert alert-success" id="new-user-success">
                                <?php _e('<strong>Success!</strong> User has been created', 'mobile-assistant-connector'); ?>
                            </div>
                            <div class="alert alert-warning" id="new-user-warning">
                            </div>
                            <div class="form-group">
                                <label  class="col-sm-3 control-label"
                                        for="inputLogin3"><?php _e( 'Login', 'mobile-assistant-connector' ); ?></label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="new_user_login"
                                           id="inputLogin3" placeholder="Login" autocomplete="off"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label"
                                       for="inputPassword3" ><?php _e( 'Password', 'mobile-assistant-connector' ); ?></label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control" name="new_user_password"
                                           id="inputPassword3" placeholder="Password" autocomplete="off"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="hidden" class="form-control" name="call_function"
                                       id="call_function" autocomplete="off" value="mac_add_user"/>
                                <div class="col-sm-offset-2 col-sm-10">
                                    <button type="submit" class="btn btn-default" style="background-color: #005684;"><span style="color: white;"><?php _e( 'Add', 'mobile-assistant-connector' ); ?></span></button>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e( 'Add', 'mobile-assistant-connector' ); ?></button>
                </div>
            </div>
        </div>

        <!-- Modal HTML -->
        <div id="emoModalDeleteUser" class="modal fade">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title"><?php _e( 'Confirmation', 'mobile-assistant-connector' ); ?></h4>
                    </div>
                    <div class="modal-body">
                        <p><?php _e( 'Do you want to delete current user?', 'mobile-assistant-connector' ); ?></p>
                        <p class="text-warning"><small><?php _e( 'All linked devices will be deleted too.', 'mobile-assistant-connector' ); ?></small></p>
                    </div>
                    <div class="modal-footer">
                        <form id="delete_user" data-async class="form-horizontal" role="form" method="POST" action="<?php echo plugins_url('/../../functions/ajax.php', __FILE__) ?>">
                            <input type="hidden" name="key" id="mobassistantconnector_key" value="<?php echo (hash( 'sha256', MOBASSIS_KEY  . AUTH_KEY )); ?>">
                            <input type="hidden" name="action" id="action" value="ema_callback">
                            <input type="hidden" class="form-control" name="call_function"
                                   id="call_function" autocomplete="off" value="mac_delete_user"/>
                            <input type="hidden" class="form-control" name="mac_del_user_id"
                                   id="mac_del_user_id" autocomplete="off" value=""/>
                            <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e( 'Cancel', 'mobile-assistant-connector' ); ?></button>
                            <button type="submit" class="btn btn-danger"><?php _e( 'Delete', 'mobile-assistant-connector' ); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <form action="<?php echo plugins_url('/../../functions/ajax.php', __FILE__) ?>" method="post" enctype="multipart/form-data" id="form_mobassist" class="form-horizontal">
            <input type="hidden" name="save_continue" id="save_continue" value="0">
            <input type="hidden" name="action" id="action" value="ema_callback">
            <input type="hidden" name="bulk_actions" id="bulk_actions" value="0">
            <input type="hidden" name="mobassistantconnector_base_url" id="mobassistantconnector_base_url" value="<?php print(get_site_url()); ?>">
            <input type="hidden" class="form-control" name="call_function" id="call_function" autocomplete="off" value="mac_save_user"/>
            <input type="hidden" class="form-control" name="mac_save_user_id" id="mac_save_user_id" autocomplete="off" value=""/>
            <input type="hidden" name="mobassistantconnector_key" id="mobassistantconnector_key" value="<?php echo (hash( 'sha256', MOBASSIS_KEY  . AUTH_KEY )); ?>">
            <input type="hidden" name="key" id="sec_key" value="<?php echo (hash( 'sha256', MOBASSIS_KEY  . AUTH_KEY )); ?>">

            <div class="alert alert-success" id="save-user-success">
                <?php _e( '<strong>Success!</strong> User has been saved.', 'mobile-assistant-connector' ); ?>
            </div>
            <div class="alert alert-warning" id="save-user-warning">
            </div>
            <div class="alert alert-warning" id="default-user-credentials">
                <?php _e( '<strong>Warning!</strong>&nbspMobile Assistant Connector: Change default login credentials to make your connection secure!', 'mobile-assistant-connector' ); ?>
            </div>
            <div class="alert alert-warning" id="ema-system-warnings">
                <?php _e( '<strong>Warning!</strong>&nbspMobile Assistant Connector:', 'mobile-assistant-connector' ); ?>
            </div>
            <div class="col-sm-2" style="font-weight: bold;">
                <span class="control-label"><?php _e( 'Users:', 'mobile-assistant-connector' ); ?></span><hr />
                <ul class="nav nav-pills nav-stacked" id="users">
                    <li id="user-add" style="cursor:pointer;"  data-toggle="modal" data-target="#emoModalNewUser"><a>
                            <img src="<?php echo plugins_url('/../../images/add.png', __FILE__) ?>" title="Add user"><span style="color:green;"> <?php _e( 'Add user ', 'mobile-assistant-connector' ); ?></span></a>
                    </li>
                </ul>

                <hr style="margin-top: 30px;">

                <div style="margin-top: 25px; text-align: center;">
                    <span><?php _e( 'Get the App from Google Play', 'mobile-assistant-connector' ); ?></span>
                    <a class="ma_play" href="https://goo.gl/DGh0DN" target="_blank">
                        <div id="mobassist_app_url_qr" style="margin-top: 7px; margin-bottom: 4px; display: inline-block;">
                            <img src="data:image/png;base64,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" style="display: block;">
                        </div><br>
                        <span><?php _e( 'Click or use your device camera to read the qr-code', 'mobile-assistant-connector' ); ?></span>
                    </a>
                </div>

            </div>

            <div class="col-sm-10">
                <div class="tab-content">

                    <?php $user_row = 1; $active_user_row = 1; ?>
                        <div class="tab-pane" id="tab-user<?php /*echo $user['user_id'];*/ ?>">
                            <input type="hidden" name="user[user_id]" value="<?php /*echo $user['user_id'];*/ ?>" />

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-status"><?php _e( 'Status:', 'mobile-assistant-connector' ); ?></label>
                                <div class="col-sm-10">
                                    <select name="user[status]" id="input-status" class="form-control">
                                            <option value="1"><?php _e( 'Enabled', 'mobile-assistant-connector' ); ?></option>
                                            <option value="0""><?php _e( 'Disabled', 'mobile-assistant-connector' ); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group required">
                                <label class="col-sm-2 control-label" for="mobassist_login<?php /*echo $user_row*/; ?>"><span data-toggle="tooltip" title="Login"><?php _e( 'Login', 'mobile-assistant-connector' ); ?></span></label>
                                <div class="col-sm-10">
    <!--                                <input type="hidden" id="mobassist_login_old--><?php ///*echo $user_row;*/ ?><!--" value="--><?php ///*echo $user['username'];*/ ?><!--"/>-->
                                    <input type="text" id="mobassist_login<?php /*echo $user_row;*/ ?>" class="form-control mobassist_login" data-user_row="" name="user[username]" value="<?php /*echo $user['username'];*/ ?>" autocomplete="off" placeholder="<?php /*echo $entry_login;*/ ?>" <?php _e( 'required', 'mobile-assistant-connector' ); ?> />
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="mobassist_pass"><span data-toggle="tooltip" title="Password"><?php _e( 'Password', 'mobile-assistant-connector' ); ?></span></label>
                                <div class="col-sm-10">
    <!--                                <input type="hidden" id="mobassist_pass_old--><?php ///*echo $user_row; */?><!--" value="--><?php ///*echo $user['password'];*/ ?><!--"/>-->
                                    <input type="password" id="mobassist_pass" class="form-control mobassist_pass" data-user_row="" name="user[password]" value="<?php /*echo $user['password'];*/ ?>" autocomplete="off" placeholder="<?php /*echo $entry_pass;*/ ?>" />
                                </div>
                            </div>

                            <div class="form-group" style="border-top: 1px solid #eee;">
                                <label class="col-sm-2 control-label" for="mobassist_qr"><span data-toggle="tooltip" title="QR Code (configuration)"><?php _e( 'QR Code (configuration)', 'mobile-assistant-connector' ); ?></span></label>
                                <div class="col-sm-10">
                                    <div style="position: relative; width: 250px">
                                        <div id="mobassist_qr_code" class="qr-code"><?php _e( 'QR Code (configuration)', 'mobile-assistant-connector' ); ?></div>
                                        <div id="mobassist_qr_code_changed" style="display: none; z-index: 1000; text-align: center; position: absolute; top: 0; left: 0; height: 100%;">
                                            <div style="position: relative; width: 100%; height: 100%;">
                                                <div style="background: #fff; opacity: 0.9; position: absolute; height: 100%; width: 100%">&nbsp;</div>
                                                <div style="font-size: 16px; color: #DF0101; width: 100%; text-align: center; padding-top: 45px; position: absolute; font-weight: bold;"><?php _e( 'Login details have been changed.<br>Save changes for code to be regenerated', 'mobile-assistant-connector' ); ?></div>
                                            </div>
                                        </div>
                                        <span id="mobassistantconnector_qr_code_url"><a id="qr_code_url" href="" target="_blank"><?php _e( 'URL to share current QR-code', 'mobile-assistant-connector' ); ?></a></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="border-top: 1px solid #eee;">
                                <label class="col-sm-2 control-label"><?php _e( 'Permissions', 'mobile-assistant-connector' ); ?></label>
                                <div class="col-sm-10 perms_group" id="user_permissions">
                                    <div class="perms_group"><?php _e( 'Push notification', 'mobile-assistant-connector' ); ?><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_push_new_order" name="user_allowed_actions[push_notification_settings_new_order]" class="perms" value="0"/> <?php _e( 'New order created', 'mobile-assistant-connector' ); ?></label><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_push_order_status_changed" name="user_allowed_actions[push_notification_settings_order_statuses]" class="perms" value="0"/> <?php _e( 'Order status changed', 'mobile-assistant-connector' ); ?></label><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_push_new_customer" name="user_allowed_actions[push_notification_settings_new_customer]" class="perms" value="0"/> <?php _e( 'New customer created', 'mobile-assistant-connector' ); ?></label><br/>
                                    </div>
                                    <br/>
                                    <div class="perms_group"><?php _e( 'Store statistics', 'mobile-assistant-connector' ); ?><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_store_stats" name="user_allowed_actions[store_stats]" class="perms" value="0" > <?php _e( 'View store statistics', 'mobile-assistant-connector' ); ?></label><br/>
                                    </div>
                                    <br/>
                                    <div class="perms_group"><?php _e( 'Orders', 'mobile-assistant-connector' ); ?><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_order_list" name="user_allowed_actions[orders_list]" data-user_row="" class="perms perm_order_list" value="0" > <?php _e( 'View order list', 'mobile-assistant-connector' ); ?></label><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_order_details" name="user_allowed_actions[order_details]" class="perms perm_order_list_child" value="0" > <?php _e( 'View order details', 'mobile-assistant-connector' ); ?></label><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_order_status_updating" name="user_allowed_actions[update_order_status]" class="perms perm_order_list_child" value="0" > <?php _e( 'Change order status', 'mobile-assistant-connector' ); ?></label><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_order_details_pdf" name="user_allowed_actions[order_details_pdf]" class="perms perm_order_list_child" value="0" > <?php _e( 'Download order invoice PDF', 'mobile-assistant-connector' ); ?></label><br/>
                                    </div>
                                    <br/>
                                    <div class="perms_group"><?php _e( 'Customers', 'mobile-assistant-connector' ); ?><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_customers_list" name="user_allowed_actions[customers_list]" data-user_row="" class="perms perm_customer_list" value="0" > <?php _e( 'View customer list', 'mobile-assistant-connector' ); ?></label><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_customer_details" name="user_allowed_actions[customer_details]" class="perms perm_customer_list_child" value="0" > <?php _e( 'View customer details', 'mobile-assistant-connector' ); ?></label><br/>
                                    </div>
                                    <br/>
                                    <div class="perms_group"><?php _e( 'Products', 'mobile-assistant-connector' ); ?><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_products_list" name="user_allowed_actions[products_list]" data-user_row="" class="perms perm_product_list" value="0" > <?php _e( 'View product list', 'mobile-assistant-connector' ); ?></label><br/>
                                        <label class="perms_label"><input type="checkbox" id="user_allowed_actions_product_details" name="user_allowed_actions[product_details]" class="perms perm_product_list_child" value="0" > <?php _e( 'View product details', 'mobile-assistant-connector' ); ?></label><br/>
                                    </div>
                                </div>
                            </div>


                            <div class="form-group"  style="border-top: 1px solid #eee;">
                                <label class="col-sm-2 control-label" for="table_push_devices"><span data-toggle="tooltip" title="<?php /*echo $push_messages_settings_help;*/ ?>"><?php _e( 'Push notifications settings', 'mobile-assistant-connector' ); ?></label>
                                <div class="col-sm-10" id="table_push_devices" style="margin-top: 5px">
                                    <?php /*$push_devices = $user['devices'];*/ ?>

                                    <table id="mobassistantconnector_devices_table" class="table table-bordered table-hover" style="font-size: 12px;">
                                        <thead>
                                        <tr>
                                            <th><?php _e( 'Device name', 'mobile-assistant-connector' ); ?></th>
                                            <th><?php _e( 'Device account email', 'mobile-assistant-connector' ); ?></th>
                                            <th class="text-center"><?php _e( 'Last activity', 'mobile-assistant-connector' ); ?></th>
                                            <th class="text-right"><?php _e( 'App connection ID', 'mobile-assistant-connector' ); ?></th>
    <!--                                        <td>Store</td>-->
                                            <th class="text-center"><?php _e( 'New order', 'mobile-assistant-connector' ); ?></th>
                                            <th class="text-center"><?php _e( 'New customer', 'mobile-assistant-connector' ); ?></th>
                                            <th><?php _e( 'Order statuses', 'mobile-assistant-connector' ); ?></th>
    <!--                                        <td>Currency</td>-->
                                            <th class="text-center"><?php _e( 'Status', 'mobile-assistant-connector' ); ?></th>
                                            <th class="text-center"></th>
                                        </tr>
                                        </thead>

                                        <tbody class="table_body">
                                            <tr><td class="text-center" colspan="12"><?php /*echo $no_data;*/ ?></td></tr>
                                        </tbody>
                                    </table>
                                    <div class="mobassistantconnector_loader_diveces col-sm-6" id="mobassistantconnector_loader_diveces"><img src="<?php echo plugins_url('/../../images/loader.gif', __FILE__) ?>"></div>
                                </div>
                                <div class="col-sm-10">
                                    <div class="button_toolbar tablenav bottom">
                                        <button type="button" data-href="/delete.php?id=23" data-toggle="modal" data-target="#emoModalDeleteUser" class="btn btn-danger btn-sm pull-left launch-modal" id="delete-user" style="">
                                            <span class=""><img src="<?php echo plugins_url('/../../images/trash.png', __FILE__) ?>" title="Delete user"></span>&nbsp;&nbsp;<?php _e( 'Delete User', 'mobile-assistant-connector' ); ?></button>
                                        <!--                                    --><?php //submit_button('Delete User', 'delete', 'submit-form', false); ?>
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="button_toolbar tablenav bottom pull-right">
                                        <?php submit_button(__('Save User Details', 'mobile-assistant-connector'), 'primary', 'submit-form', false); ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                </div>

        </form>
    </div>
    <?php
}