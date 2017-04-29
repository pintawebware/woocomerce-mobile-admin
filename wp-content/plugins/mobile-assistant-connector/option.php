<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class ConnectorSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
        
    /**
     * Start up
     */
    public function __construct()
    {
//        add_action('admin_menu', array(
//            $this,
//            'add_plugin_page'
//        ));
        add_action('admin_menu', array(
            $this,
            'add_admin_page'
        ));
/*        add_action('admin_init', array(
            $this,
            'page_init'
        ));
        add_action('admin_footer', array(
            $this,
            'qr_code'
        ));*/
    }
    
    public function qr_code()
    {
        $qr_config = array(
            'url' => get_site_url(),
            'login' => $this->options['login'],
            'password' => $this->options['pass']
        );
        
        $qr_config = base64_encode(json_encode($qr_config));
        //http://davidshimjs.github.io/qrcodejs/ 
        echo '
            <script type="text/javascript">
                var QRCode = new QRCode("mobassist_qr_code", {
                    text: "' . $qr_config . '",
                    width: 250,
                    height: 250,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            </script>';
    }

    public function add_admin_page()
    {
        add_menu_page( 'Mobile Assistant Connector',
            'MA Connector',
            'manage_options',
            'connector',
            array(
                $this,
                'render_page'
            ),
            plugins_url('/images/woo.png', __FILE__)
        );
    }
    
    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_menu_page( 'Mobile Assistant Connector',
            'MA Connector',
            'manage_options',
            'connector',
            array(
                $this,
                'create_admin_page'
            ),
            plugins_url('/images/woo.png', __FILE__)
        );
    }
    /**
     * Options page callback
     */
    
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option('mobassistantconnector');

?>

        <div class="sa_wrap">
			<div class="sa_content">
				<h2>Mobile Assistant Connector</h2>
				<form method="post" action="options.php">
				<?php settings_fields('mobassistantconnector_group'); ?>
				<div class="section_wrap">
				<?php do_settings_sections('connector-access'); ?>
				</div>
                <div class="section_wrap">
                    <?php do_settings_sections('connector-push_devices'); ?>
                </div>
				<div class="section_wrap qr">
				<?php do_settings_sections('connector-qr'); ?>
				</div>
				<div class="button_toolbar tablenav bottom">
				<?php submit_button('Save Connector Configuration', 'primary', 'submit-form', false); ?>
				</div>
				</form>
			</div>
			<div class="sa_sidebar">
				<div class="section">
				<h3>Get the App from Google Play</h3>
					<a class="ma_play" href="http://goo.gl/3cq67C" target="_blank" ><img src="https://api.qrserver.com/v1/create-qr-code/?data=http%3A%2F%2Fgoo.gl%2F3cq67C&amp;size=200x200&amp;format=png&amp;margin=0&amp;color=0-0-0&amp;bgcolor=255-255-255" alt="Get the App from Google Play">
					Click or use your device camera <br />to read the qr-code
					</a>
				</div>
			</div>	
        </div>
		
        <?php
    }

    public function ajax() {
//        check_ajax_referer('aam_ajax');

        //clean buffer to make sure that nothing messing around with system
        while (@ob_end_clean()){}

        //process ajax request
        echo AAM_Backend_View::getInstance()->processAjax();
        exit();
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'mobassistantconnector_group', // Option group
            'mobassistantconnector', // Option name
            array(
                $this,
                'sanitize'
            ) // Sanitize
        );

//        add_settings_section(
//            'setting_section_id',
//            'Connector Page',
//            array(
//                $this,
//                'print_section_all'
//            ),
//            'connector-all'
//        );

        // This page will be under "Settings"
/*        add_menu_page( 'Mobile Assistant Connector',
            'MA Connector',
            'manage_options',
            'connector',
            array(
                $this,
                'render_page'
            ),
            plugins_url('/images/woo.png', __FILE__)
        );*/


/*        add_settings_section(
            'setting_section_id',
            'Connector Users',
            array(
                $this,
                'print_section_users'
            ),
            'connector-users'
        );

        add_settings_section(
            'setting_section_id', // ID
            'Connector Access', // Title
            array(
                $this,
                'print_section_access'
            ), // Callback
            'connector-access' // Page
        );

        add_settings_section(
            'setting_section_id', // ID
            'Push Notification Devices', // Title
            array(
                $this,
                'print_section_push_devices'
            ), // Callback
            'connector-push_devices' // Page
        );

        add_settings_section(
            'setting_section_id', // ID
            'Store Mobile Assistant', // Title
            array(
                $this,
                'print_section_qr'
            ), // Callback
            'connector-qr' // Page
        );

        //--- login ---
        add_settings_field(
            'login', // ID
            'Login', // Title 
            array(
                $this,
                'login_callback'
            ), // Callback
            'connector-access', // Page
            'setting_section_id' // Section           
        );

        //--- pass
        add_settings_field(
            'pass',
            'Password',
            array(
                $this,
                'pass_callback'
            ),
            'connector-access',
            'setting_section_id'
        );*/
    }
	
    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input)
    {
        $new_input = array();
        $options_prev = get_option('mobassistantconnector');
        $new_input['pass'] = $options_prev['pass'];

        if (isset($input['login']) && $input['login'] != $options_prev['login']) {
            $new_input['login'] = sanitize_text_field($input['login']);
			$this->delete_session_keys();
		}
        
        if (isset($input['pass']) && $input['pass'] != $options_prev['pass']) {
			$new_input['pass'] = md5(sanitize_text_field($input['pass']));
			$this->delete_session_keys();
		}
        
        return $new_input;
    }
    
    /** 
     * Print the Section text
     */
    public function print_section_access()
    {
        print 'Enter connector credentials below:';
    }

    public function print_section_users()
    {
        print 'Users:';
    }

    public function render_page()
    {
        ob_start();
        require_once(dirname(__FILE__) . '/backend/view/main.php');
//        require_once(dirname(__FILE__) . '/backend/view/index.phtml');

        $content = ob_get_contents();
        ob_end_clean();

        echo $content;
    }

    public function print_section_all()
    {
        print 'Users:';
    }

    public function print_section_config()
    {
        print 'Enter Connector settings below:';
    }

    public function print_section_push_devices() {
        $login_data = get_option( 'mobassistantconnector' );

        print( '<input type="hidden" name="mobassistantconnector_base_url" id="mobassistantconnector_base_url" value="' . get_site_url() . '">
            <input type="hidden" name="mobassistantconnector_key" id="mobassistantconnector_key" value="'
            . hash( 'sha256', $login_data['login'] . $login_data['pass'] . AUTH_KEY ) . '">
            <div id="mobassistantconnector_super_parent_devices_container"></div>' );
    }
    
    public function print_section_qr()
    {
        print ('<p class="description" >Store URL and access details (login and password) for Mobile Assistant Connector are encoded in this QR code.<br /> Scan it with special option available on connection settings page of <b>WooCommerce Mobile Assistant</b> to autofill acess settings and connect to your WooCommerce store.:</p>');
        print('<div style="position: relative;">
                    <div id="mobassist_qr_code" style="width: 250px;"></div>
                    <div id="mobassist_qr_code_changed" style="padding: 100px 0px 0px 10px;; display: none; z-index: 1000; text-align: left; position: absolute; top: 0; color: red; width: 200px; opacity: 1">
                        Login details have been changed. Save changes for code to be regenerated
                    </div>
                </div>');
    }
    /** 
     * Get the settings option array and print one of its values
     */
    public function login_callback()
    {
        printf('<input type="text" id="mobassist_login" name="mobassistantconnector[login]" value="%s" /> %s', isset($this->options['login']) ? esc_attr($this->options['login']) : '1', $this->options['login'] == '1' ? '<span style="color:red"><br />Please change immediately!</span>' : '');
    }
    
    public function pass_callback()
    {
        printf('<input type="password" id="mobassist_pass" name="mobassistantconnector[pass]" value="%s" />%s', isset($this->options['pass']) ? esc_attr($this->options['pass']) : '1', $this->options['pass'] == md5('1') ? '<span style="color:red"><br />Please change immediately!</span>' : '');
    }

	private function delete_session_keys() {
		global $wpdb;

		$result = $wpdb->query( "DELETE FROM {$wpdb->prefix}mobileassistant_session_keys" );
		$m = 0;
	}

}

if (is_admin()) {
    $GLOBALS['ConnectorSettingsPage'] = new ConnectorSettingsPage();
}
?>