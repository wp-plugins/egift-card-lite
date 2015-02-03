<?php
/*
Plugin Name: eGift Card Lite
Plugin URI: http://www.wpgiftcertificatereloaded.com/egift-card
Description: This plugin allows you to sell a printable eGift Cards as well as manage sold eGift Cards. Payments are handled and accepted through PayPal. The certificates are QR code encoded. Use shortcode: [egiftcardlite].
Version: 1.00
Author: GC GROUP
Author URI: http://www.wpgiftcertificatereloaded.com/egift-card
*/
include_once(dirname(__FILE__).'/const.php');
wp_enqueue_script("jquery");
register_activation_hook(__FILE__, array("egiftcardlite_class", "install"));

class egiftcardlite_class
{
	var $options;
	var $error;
	var $info;
	
	var $exists;
	var $enable_paypal;
	var $paypal_id;
	var $paypal_sandbox;
	var $use_https;
	var $title;
	var $description;
	var $price;
    var $currency;
	var $validity_period;
	var $owner_email;
	var $from_name;
	var $from_email;
	var	$success_email_subject;
	var $success_email_body;
	var $failed_email_subject;
	var $failed_email_body;
	var $company_title;
	var $company_description;
	var $terms;
	
	var $default_options;
	
	function __construct() {
        $this->options = array(
            "exists",
            "enable_paypal",
            "paypal_id",
            "paypal_sandbox",
            "title",
            "description",
            "price",
            "currency",
            "validity_period",
			"enableecard",
			"ecardname",
			"smallimage",
			"bigimage",
			"certimage",
            "use_https",
            "owner_email",
            "from_name",
            "from_email",
            "success_email_subject",
            "success_email_body",
            "failed_email_subject",
            "failed_email_body",
            "company_title",
            "company_description",
            "terms"
        );
        $this->default_options = array(
            "exists" => 1,
            "enable_paypal" => "on",
            "paypal_id" => "sales@" . str_replace("www.", "", $_SERVER["SERVER_NAME"]),
            "paypal_sandbox" => "off",
            "title" => "Gift Certificate",
            "description" => "",
            "price" => "10.00",
            "currency" => "USD",
            "validity_period" => 365,
			"enableecard" => "on",
			"ecardname" => "Birthday",
			"smallimage" => "smallimage_sample.png",
			"bigimage" => "bigimage_sample.png",
			"certimage" => "certimage_sample.jpg",			
            "use_https" => "off",
            "owner_email" => "admin@" . str_replace("www.", "", $_SERVER["SERVER_NAME"]),
            "from_name" => get_bloginfo("name"),
            "from_email" => "noreply@" . str_replace("www.", "", $_SERVER["SERVER_NAME"]),
            "success_email_subject" => "Gift certificate successfully purchased",
            "success_email_body" => "Dear {first_name},\r\n\r\nThank you for purchasing gift certificate(s) \"{certificate_title}\". Please find printable version here:\r\n{certificate_url}\r\n\r\nThanks,\r\nAdministration of " . get_bloginfo("name"),
            "failed_email_subject" => "Payment not completed",
            "failed_email_body" => "Dear {first_name},\r\n\r\nWe would like to inform you that we received payment from you.\r\nPayment status: {payment_status}\r\nOnce the payment is completed and cleared, we send gift certificate to you.\r\n\r\nThanks,\r\nAdministration of " . get_bloginfo("name"),
            "company_title" => get_bloginfo("name"),
            "company_description" => get_bloginfo("name"),
            "terms" => "Insert your own Terms & Conditions here. For example:\r\n1. You can declare that gift certificates are refundable, but some restrictions may apply.\r\n2. It is allowed to change certificate owner name and explain how to do this.\r\netc..."
        );

		if (!empty($_COOKIE["egiftcardlite_error"]))
		{
			$this->error = stripslashes($_COOKIE["egiftcardlite_error"]);
			setcookie("egiftcardlite_error", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}
		if (!empty($_COOKIE["egiftcardlite_info"]))
		{
			$this->info = stripslashes($_COOKIE["egiftcardlite_info"]);
			setcookie("egiftcardlite_info", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}

		$this->get_settings();

		if (is_admin()) {
			if ($this->check_settings() !== true) add_action('admin_notices', array(&$this, 'admin_warning'));
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('init', array(&$this, 'admin_request_handler'));
			add_action('admin_head', array(&$this, 'admin_header'), 15);
		} else {
			add_action('init', array(&$this, 'front_init'));
			add_action("wp_head", array(&$this, "front_header"));
			add_shortcode('egiftcardlite', array(&$this, "shortcode_handler"));
		}
	}

	function install () {
		global $wpdb;

		$table_name = $wpdb->prefix . "egcl_certificates";
		//if($wpdb->get_var("show tables like '".$table_name."'") != $table_name)
		//{
			
		$upload_dir = wp_upload_dir();
		if (!file_exists($upload_dir["basedir"].'/egiftcardlite')) {
			wp_mkdir_p($upload_dir["basedir"].'/egiftcardlite');
		}	


		
$sourcelocation = plugin_dir_path( __FILE__ ).'defaultimages/';
$targetlocation = $upload_dir["basedir"].'/egiftcardlite/';

copy($sourcelocation.'smallimage_sample.png', $targetlocation.'smallimage_sample.png');		
copy($sourcelocation.'bigimage_sample.png', $targetlocation.'bigimage_sample.png');	
copy($sourcelocation.'certimage_sample.jpg', $targetlocation.'certimage_sample.jpg');	
		
			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL auto_increment,
				tx_str varchar(31) collate utf8_unicode_ci NOT NULL,
				code varchar(31) collate utf8_unicode_ci NOT NULL,
				recipient varchar(255) collate utf8_unicode_ci NOT NULL,
				email varchar(255) collate utf8_unicode_ci NOT NULL,
				price float NOT NULL,
				currency varchar(15) collate utf8_unicode_ci NOT NULL,
				status int(11) NOT NULL,
				registered int(11) NOT NULL,
				blocked int(11) NOT NULL,
				deleted int(11) NULL,
				ecard tinyint(1) NOT NULL DEFAULT '0',
				UNIQUE KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		//}
		$table_name = $wpdb->prefix . "egcl_transactions";
		if($wpdb->get_var("show tables like '".$table_name."'") != $table_name)
		{
			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL auto_increment,
				tx_str varchar(31) collate utf8_unicode_ci NOT NULL,
				payer_name varchar(255) collate utf8_unicode_ci NOT NULL,
				payer_email varchar(255) collate utf8_unicode_ci NOT NULL,
				gross float NOT NULL,
				currency varchar(15) collate utf8_unicode_ci NOT NULL,
				payment_status varchar(31) collate utf8_unicode_ci NOT NULL,
				transaction_type varchar(31) collate utf8_unicode_ci NOT NULL,
				details text collate utf8_unicode_ci NOT NULL,
				created int(11) NOT NULL,
				UNIQUE KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
	}

	function get_settings() {
		$exists = get_option('egiftcardlite_exists');
		if ($exists != 1)
		{
			foreach ($this->options as $option) {
				$this->$option = $this->default_options[$option];
			}
		}
		else
		{
			foreach ($this->options as $option) {
				$this->$option = get_option('egiftcardlite_'.$option);
			}
		}
		//if (empty($this->enable_paypal)) $this->enable_paypal = $this->default_options["enable_paypal"];
		$this->enable_paypal = "on";
		if (empty($this->paypal_sandbox)) $this->paypal_sandbox = $this->default_options["paypal_sandbox"];
	}

	function update_settings() {
		//print_r($_REQUEST);
		if (current_user_can('manage_options')) {
			foreach ($this->options as $option) {
				update_option('egiftcardlite_'.$option, $this->$option);
			}
		}
	}

	function populate_settings() {
		//print_r($_REQUEST);
		//exit;
		$upload_dir = wp_upload_dir();
		$cardbasepath=$upload_dir["basedir"].'/egiftcardlite/';
	//print_r($_FILES);
	//print $_FILES["egiftcardlite_smallimage_file"]["name"];
//exit;	
if ($_FILES["egiftcardlite_smallimage_file"]["error"] > 0) {
$_POST['egiftcardlite_smallimage']=$_POST['egiftcardlite_smallimage_hidden'];

}else
{

//$dir = plugin_dir_path( __FILE__ );
$randstring=rand(11111, 91111);
move_uploaded_file($_FILES["egiftcardlite_smallimage_file"]["tmp_name"],$cardbasepath.$randstring.$_FILES["egiftcardlite_smallimage_file"]["name"]);
$_POST['egiftcardlite_smallimage']=$randstring.$_FILES["egiftcardlite_smallimage_file"]["name"];
//print $cardbasepath.$randstring.$_FILES["egiftcardlite_smallimage_file"]["name"];
//exit;//
}	

if ($_FILES["egiftcardlite_bigimage_file"]["error"] > 0) {

$_POST['egiftcardlite_bigimage']=$_POST['egiftcardlite_bigimage_hidden'];
}else
{

//$dir = plugin_dir_path( __FILE__ );
$randstring=rand(11111, 91111);
move_uploaded_file($_FILES["egiftcardlite_bigimage_file"]["tmp_name"],$cardbasepath.$randstring.$_FILES["egiftcardlite_bigimage_file"]["name"]);
$_POST['egiftcardlite_bigimage']=$randstring.$_FILES["egiftcardlite_bigimage_file"]["name"];
}	


if ($_FILES["egiftcardlite_certimage_file"]["error"] > 0) {

$_POST['egiftcardlite_certimage']=$_POST['egiftcardlite_certimage_hidden'];
}else
{

//$dir = plugin_dir_path( __FILE__ );
$randstring=rand(11111, 91111);
move_uploaded_file($_FILES["egiftcardlite_certimage_file"]["tmp_name"],$cardbasepath.$randstring.$_FILES["egiftcardlite_certimage_file"]["name"]);
$_POST['egiftcardlite_certimage']=$randstring.$_FILES["egiftcardlite_certimage_file"]["name"];
}

		
		foreach ($this->options as $option) {
			if (isset($_POST['egiftcardlite_'.$option])) {
				$this->$option = stripslashes($_POST['egiftcardlite_'.$option]);
			}
		}
	}

	function check_settings() {
		$errors = array();
		if ($this->enable_paypal == "on")
		{
			if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->paypal_id) || strlen($this->paypal_id) == 0) $errors[] = "PayPal ID must be valid e-mail address";
		}
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->owner_email) || strlen($this->owner_email) == 0) $errors[] = "Admin e-mail must be valid e-mail address";
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->from_email) || strlen($this->from_email) == 0) $errors[] = "Sender e-mail must be valid e-mail address";
		if (strlen($this->title) < 3) $errors[] = "Certificate title is too short";
		if (!is_numeric($this->price) || floatval($this->price) <= 0) $errors[] = "Certificate price is invalid";
		if (!is_numeric($this->validity_period) || floatval($this->validity_period) <= 0) $errors[] = "Validity period is invalid";
		if (strlen($this->from_name) < 3) $errors[] = "Sender name is too short";
		if (strlen($this->success_email_subject) < 3) $errors[] = "Successful payment e-mail subject must contain at least 3 characters";
		else if (strlen($this->success_email_subject) > 64) $errors[] = "Successful payment e-mail subject must contain maximum 64 characters";
		if (strlen($this->success_email_body) < 3) $errors[] = "Successful payment e-mail body must contain at least 3 characters";
		if (strlen($this->failed_email_subject) < 3) $errors[] = "Failed payment e-mail subject must contain at least 3 characters";
		else if (strlen($this->failed_email_subject) > 64) $errors[] = "Failed payment e-mail subject must contain maximum 64 characters";
		if (strlen($this->failed_email_body) < 3) $errors[] = "Failed payment e-mail body must contain at least 3 characters";

		if (empty($errors)) return true;
		return $errors;
	}

	function admin_menu() {
		if (get_bloginfo('version') >= 3.0) {
			define("egcl_PERMISSION", "add_users");
		}
		else{
			define("egcl_PERMISSION", "edit_themes");
		}	
		add_menu_page(
			"eGift Card Lite"
			, "eGift Card Lite"
			, egcl_PERMISSION
			, "egc-lite"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"egc-lite"
			, "Settings"
			, "Settings"
			, egcl_PERMISSION
			, "egc-lite"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"egc-lite"
			, "Certificates"
			, "Certificates"
			, egcl_PERMISSION
			, "egc-lite-certificates"
			, array(&$this, 'admin_certificates')
		);
		add_submenu_page(
			"egc-lite"
			, "Add Certificate"
			, "Add Certificate"
			, egcl_PERMISSION
			, "egc-lite-add"
			, array(&$this, 'admin_add_certificate')
		);
		add_submenu_page(
			"egc-lite"
			, "Transactions"
			, "Transactions"
			, egcl_PERMISSION
			, "egc-lite-transactions"
			, array(&$this, 'admin_transactions')
		);
	}

	function admin_settings() {
		global $wpdb;
		$message = "";
		$errors = array();
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		else
		{
			$errors = $this->check_settings();
			if (is_array($errors)) echo "<div class='error'><p>The following error(s) exists:<br />- ".implode("<br />- ", $errors)."</p></div>";
		}
		if ($_GET["updated"] == "true")
		{
			$message = '<div class="updated"><p>Plugin settings successfully <strong>updated</strong>.</p></div>';
		}
		print ('
		<div class="wrap admin_egiftcardlite_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>eGift Card Lite - Settings</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			<div class="postbox-container" style="width: 260px; float: right;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>WPGC eGift Cards</span></h3>
							<div class="inside">
								<ul>
									<li style="display: list-item;"><a href="http://www.wpgiftcertificatereloaded.com/egift-card" target="_blank">Overview of features</a></li>
									<li style="display: list-item;"><a href="http://www.wpgiftcertificatereloaded.com/egift-card" target="_blank">Checkout our modules</a></li>
									<li style="display: list-item;"><a href="http://www.wpgiftcertificatereloaded.com/egift-card" target="_blank">Screenshots</a></li>
									</ul>
								<center>
									<a href="http://www.wpgiftcertificatereloaded.com/egift-card" target="_blank"><img src="'.plugins_url('/images/gift-certificate.jpg', __FILE__).'" alt="WPGC eGift Cards"></a>
								</center>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="postbox-container" style="margin-right: 280px; float: none;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>General Settings</span></h3>
							<div class="inside">
								<table class="egiftcardlite_useroptions">
									<tr>
										<th>PayPal ID:</th>
										<td><input type="text" id="egiftcardlite_paypal_id" name="egiftcardlite_paypal_id" value="'.htmlspecialchars($this->paypal_id, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter valid PayPal e-mail, all payments are sent to this account.</em></td>
									</tr>
									<tr>
										<th>Sandbox mode:</th>
										<td><input type="checkbox" id="egiftcardlite_paypal_sandbox" name="egiftcardlite_paypal_sandbox" '.($this->paypal_sandbox == "on" ? 'checked="checked"' : '').'> Enable PayPal sandbox mode<br /><em>Please tick checkbox if you would like to test PayPal service.</em></td>
									</tr>
									<tr>
										<th>Certificate title:</th>
										<td><input type="text" name="egiftcardlite_title" id="egiftcardlite_title" value="'.htmlspecialchars($this->title, ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter campaign title. The title is displayed on sign up page and printed on certificate.</em></td>
									</tr>
									<tr>
										<th>Description:</th>
										<td><textarea id="egiftcardlite_description" name="egiftcardlite_description" style="width: 98%; height: 120px;">'.htmlspecialchars($this->description, ENT_QUOTES).'</textarea><br /><em>Describe campaign. The description is displayed on sign up page.</em></td>
									</tr>
									<tr>
										<th>Price:</th>
										<td>
										<input type="text" name="egiftcardlite_price" id="egiftcardlite_price" value="'.htmlspecialchars($this->price, ENT_QUOTES).'" style="width: 60px; text-align: right;">
										<select name="egiftcardlite_currency" id="egiftcardlite_currency">
                                            <option value="AUD"' . (($this->currency == "AUD") ?  "selected":"") .' >AUD</option>
                                            <option value="CAD"' . (($this->currency == "CAD") ?  "selected":"") .' >CAD</option>
                                            <option value="CHF"' . (($this->currency == "CHF") ?  "selected":"") .' >CHF</option>
                                            <option value="DKK"' . (($this->currency == "DKK") ?  "selected":"") .' >DKK</option>					    
                                            <option value="EUR"' . (($this->currency == "EUR") ?  "selected":"") .' >EUR</option>
                                            <option value="GBP"' . (($this->currency == "GBP") ?  "selected":"") .' >GBP</option>
                                            <option value="MXN"' . (($this->currency == "MXN") ?  "selected":"") .' >MXN</option>
                                            <option value="NOK"' . (($this->currency == "NOK") ?  "selected":"") .' >NOK</option>
                                            <option value="NZD"' . (($this->currency == "NZD") ?  "selected":"") .' >NZD</option>
                                            <option value="SEK"' . (($this->currency == "SEK") ?  "selected":"") .' >SEK</option>
                                            <option value="USD" '.(($this->currency == "USD" || $this->currency == "")? "selected":"").' >USD</option>
										</select>
										<br /><em>Enter price per one gift certificate.</em></td>
									</tr>
									<tr>
										<th>Validity period (days):</th>
										<td><input type="text" name="egiftcardlite_validity_period" id="egiftcardlite_validity_period" value="'.htmlspecialchars($this->validity_period, ENT_QUOTES).'" style="width: 60px; text-align: right;"><br /><em>Enter validity period for certificate (days).</em></td>
									</tr>
									<tr>
										<th>Ecard:</th>
										<td><input type="checkbox" id="egiftcardlite_enableecard" name="egiftcardlite_enableecard" '.($this->enableecard == "on" ? 'checked="checked"' : '').'> Enable Ecard<br /><em>Enable this option if you would like to allow users to select ecard.</em></td>
									</tr>	
									
									<tr>
										<th>Ecard title:</th>
										<td><input type="text" name="egiftcardlite_ecardname" id="egiftcardlite_ecardname" value="'.htmlspecialchars($this->ecardname, ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter Ecard name.</em></td>
									</tr>									

									<tr>
										<th>Ecard thumb image:</th>
										<td><input type="file" id="egiftcardlite_smallimage_file" name="egiftcardlite_smallimage_file"/><input type="hidden" id="egiftcardlite_smallimage_hidden" name="egiftcardlite_smallimage_hidden" value="'.$this->smallimage.'">'.$this->smallimage.' </td>
									</tr>	
									<tr>
										<th>Ecard popup image:</th>
										<td><input type="file" id="egiftcardlite_bigimage_file" name="egiftcardlite_bigimage_file"/><input type="hidden" id="egiftcardlite_bigimage_hidden" name="egiftcardlite_bigimage_hidden" value="'.$this->bigimage.'">'.$this->bigimage.' </td>
									</tr>	
									<tr>
										<th>Ecard certificate image:</th>
										<td><input type="file" id="egiftcardlite_certimage_file" name="egiftcardlite_certimage_file"/><input type="hidden" id="egiftcardlite_certimage_hidden" name="egiftcardlite_certimage_hidden" value="'.$this->certimage.'">'.$this->certimage.' </td>
									</tr>	

									
									
									<tr>
										<th>Company title:</th>
										<td><input type="text" id="egiftcardlite_company_title" name="egiftcardlite_company_title" value="'.htmlspecialchars($this->company_title, ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter the title of your company. The title is placed on gift certificate.</em></td>
									</tr>
									<tr>
										<th>Company description:</th>
										<td><textarea id="egiftcardlite_company_description" name="egiftcardlite_company_description" style="width: 98%; height: 120px;">'.htmlspecialchars($this->company_description, ENT_QUOTES).'</textarea><br /><em>Describe your company. This text is placed below company title on gift certificate.</em></td>
									</tr>
									<tr>
										<th>Terms & Conditions:</th>
										<td><textarea id="egiftcardlite_terms" name="egiftcardlite_terms" style="width: 98%; height: 120px;">'.htmlspecialchars($this->terms, ENT_QUOTES).'</textarea><br /><em>Your customers must be agree with Terms & Conditions before purchasing gif certificate. Leave this field blank if you don\'t need Terms & Conditions box to be shown.</em></td>
									</tr>
									<tr>
										<th>Use HTTPS:</th>
										<td><input type="checkbox" id="egiftcardlite_use_https" name="egiftcardlite_use_https" '.($this->use_https == "on" ? 'checked="checked"' : '').'> Display certificate page via HTTPS<br /><em>Do not activate this option if you do not have SSL certificate for your domain.</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="egiftcardlite_update_settings" />
								<input type="hidden" name="egiftcardlite_exists" value="1" />
								<input type="submit" class="button-primary" name="submit" value="Update Settings">
								</div>
								<br class="clear">
							</div>
						</div>

						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>E-mail templates</span></h3>
							<div class="inside">
								<table class="egiftcardlite_useroptions">
									<tr>
										<th>Admin e-mail:</th>
										<td><input type="text" id="egiftcardlite_owner_email" name="egiftcardlite_owner_email" value="'.htmlspecialchars($this->owner_email, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter your e-mail. All alerts about completed/failed payments are sent to this e-mail address.</em></td>
									</tr>
									<tr>
										<th>Sender name:</th>
										<td><input type="text" id="egiftcardlite_from_name" name="egiftcardlite_from_name" value="'.htmlspecialchars($this->from_name, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter sender name. All messages are sent using this name as "FROM:" header value.</em></td>
									</tr>
									<tr>
										<th>Sender e-mail:</th>
										<td><input type="text" id="egiftcardlite_from_email" name="egiftcardlite_from_email" value="'.htmlspecialchars($this->from_email, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter sender e-mail. All messages are sent using this e-mail as "FROM:" header value.</em></td>
									</tr>
									<tr>
										<th>Successful payment e-mail subject:</th>
										<td><input type="text" id="egiftcardlite_success_email_subject" name="egiftcardlite_success_email_subject" value="'.htmlspecialchars($this->success_email_subject, ENT_QUOTES).'" style="width: 98%;"><br /><em>In case of successful and cleared payment, your customers receive e-mail message about successful that. This is subject field of the message.</em></td>
									</tr>
									<tr>
										<th>Successful payment e-mail body:</th>
										<td><textarea id="egiftcardlite_success_email_body" name="egiftcardlite_success_email_body" style="width: 98%; height: 120px;">'.htmlspecialchars($this->success_email_body, ENT_QUOTES).'</textarea><br /><em>This e-mail message is sent to your customers in case of successful and cleared payment. You can use the following keywords: {first_name}, {last_name}, {payer_email}, {certificate_title}, {certificate_url}.</em></td>
									</tr>
									<tr>
										<th>Failed purchasing e-mail subject:</th>
										<td><input type="text" id="egiftcardlite_failed_email_subject" name="egiftcardlite_failed_email_subject" value="'.htmlspecialchars($this->failed_email_subject, ENT_QUOTES).'" style="width: 98%;"><br /><em>In case of pending, non-cleared or fake payment, your customers receive e-mail message about that. This is subject field of the message.</em></td>
									</tr>
									<tr>
										<th>Failed purchasing e-mail body:</th>
										<td><textarea id="egiftcardlite_failed_email_body" name="egiftcardlite_failed_email_body" style="width: 98%; height: 120px;">'.htmlspecialchars($this->failed_email_body, ENT_QUOTES).'</textarea><br /><em>This e-mail message is sent to your customers in case of pending, non-cleared or fake payment. You can use the following keywords: {first_name}, {last_name}, {payer_email}, {payment_status}.</em></td>
									</tr>
								</table>
								<div class="alignright">
									<input type="submit" class="button-primary" name="submit" value="Update Settings">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>
		');
	}

	function admin_certificates() {
		global $wpdb;

		if (isset($_GET["s"])) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."egcl_certificates WHERE status != '".GCL_STATUS_DRAFT."' AND deleted='0'".((strlen($search_query) > 0) ? " AND (code LIKE '%".addslashes($search_query)."%' OR recipient LIKE '%".addslashes($search_query)."%' OR email LIKE '%".addslashes($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/GCL_ROWS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=egc-lite-certificates".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : ""), $page, $totalpages);

		$sql = "SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE status != '".GCL_STATUS_DRAFT."' AND deleted='0'".((strlen($search_query) > 0) ? " AND (code LIKE '%".addslashes($search_query)."%' OR recipient LIKE '%".addslashes($search_query)."%' OR email LIKE '%".addslashes($search_query)."%')" : "")." ORDER BY registered DESC LIMIT ".(($page-1)*GCL_ROWS_PER_PAGE).", ".GCL_ROWS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		print ('
			<div class="wrap admin_egiftcardlite_wrap">
				<div id="icon-upload" class="icon32"><br /></div><h2>eGift Card Lite - Certificates</h2><br />
				'.$message.'
				<form action="'.get_bloginfo("wpurl").'/wp-admin/admin.php" method="get" style="margin-bottom: 10px;">
				<input type="hidden" name="page" value="egc-lite-certificates" />
				Search: <input type="text" name="s" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
				<input type="submit" class="button-secondary action" value="Search" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="Reset search results" onclick="window.location.href=\''.get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-certificates\';" />' : '').'
				</form>
				<div class="egiftcardlite_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-add">Create New Certificate</a></div>
				<div class="egiftcardlite_pageswitcher">'.$switcher.'</div>
				<table class="egiftcardlite_strings">
				<tr>
					<th>Certificate</th>
					<th>Recipient</th>
					<th style="width: 120px;">Actions</th>
				</tr>
		');
		if (sizeof($rows) > 0)
		{
			foreach ($rows as $row)
			{
				$bg_color = "";
				if ($row["status"] == GCL_STATUS_ACTIVE_REDEEMED) $bg_color = "#F0FFF0";
				else if (time() > $row["registered"] + 24*3600*$this->validity_period) $bg_color = "#E0E0E0";
				else if ($row["status"] >= GCL_STATUS_PENDING) $bg_color = "#FFF0F0";
				else if ($row["status"] == GCL_STATUS_ACTIVE_BYADMIN) $bg_color = "#F0F0FF";
				
				if ($row["status"] == GCL_STATUS_ACTIVE_BYUSER || $row["status"] == GCL_STATUS_ACTIVE_BYADMIN) {
					if (time() <= $row["registered"] + 24*3600*$this->validity_period) $expired = "Expires in ".$this->period_to_string($row["registered"] + 24*3600*$this->validity_period - time());
					else $expired = "Expired!";
				} else if ($row["status"] == GCL_STATUS_ACTIVE_REDEEMED) {
					$expired = "Redeemed ".date("Y-m-d", $row["blocked"])."";
				} else $expired = "Blocked ".date("Y-m-d", $row["blocked"])."";
				
				print ('
				<tr'.(!empty($bg_color) ? ' style="background-color: '.$bg_color.';"': '').'>
					<td><strong>'.$row["code"].'</strong>'.(!empty($expired) ? '<br /><em style="font-size: 12px; line-height: 14px;">'.$expired.'</em>' : '').'</td>
					<td>'.htmlspecialchars((empty($row['recipient']) ? 'Unknown recipient' : $row['recipient']), ENT_QUOTES).'<br /><em style="font-size: 12px; line-height: 14px;">'.htmlspecialchars($row['email'], ENT_QUOTES).'</em></td>
					<td style="text-align: center;">
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-add&id='.$row['id'].'" title="Edit certificate"><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="Edit certificate" border="0"></a>
						<a target="_blank" href="'.get_bloginfo("wpurl").'/?cid='.$row["code"].'" title="Display certificate"><img src="'.plugins_url('/images/certificate.png', __FILE__).'" alt="Display certificate" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-transactions&tid='.$row['tx_str'].'" title="Payment transactions"><img src="'.plugins_url('/images/transactions.png', __FILE__).'" alt="Payment transactions" border="0"></a>
						'.(((time() <= $row["registered"] + 24*3600*$this->validity_period) && ($row["status"] == GCL_STATUS_ACTIVE_BYUSER || $row["status"] == GCL_STATUS_ACTIVE_BYADMIN)) ? '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=egiftcardlite_block&id='.$row['id'].'" title="Block certificate" onclick="return egiftcardlite_submitOperation();"><img src="'.plugins_url('/images/block.png', __FILE__).'" alt="Block certificate" border="0"></a>' : '').'
						'.(((time() <= $row["registered"] + 24*3600*$this->validity_period) && ($row["status"] == GCL_STATUS_ACTIVE_BYUSER || $row["status"] == GCL_STATUS_ACTIVE_BYADMIN)) ? '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=egiftcardlite_redeem&id='.$row['id'].'" title="Mark certificate as redeemed" onclick="return egiftcardlite_submitOperation();"><img src="'.plugins_url('/images/redeem.png', __FILE__).'" alt="Mark certificate as redeemed" border="0"></a>' : '').'
						'.(($row["status"] >= GCL_STATUS_PENDING) ? '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=egiftcardlite_unblock&id='.$row['id'].'" title="Unblock certificate" onclick="return egiftcardlite_submitOperation();"><img src="'.plugins_url('/images/unblock.png', __FILE__).'" alt="Unblock certificate" border="0"></a>' : '').'
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=egiftcardlite_delete&id='.$row['id'].'" title="Delete certificate" onclick="return egiftcardlite_submitOperation();"><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="Delete certificate" border="0"></a>
					</td>
				</tr>
				');
			}
		}
		else
		{
			print ('
				<tr><td colspan="4" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? 'No results found for "<strong>'.htmlspecialchars($search_query, ENT_QUOTES).'</strong>"' : 'List is empty. Click <a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-add">here</a> to create new certificate.').'</td></tr>
			');
		}
		print ('
				</table>
				<div class="egiftcardlite_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-add">Create New Certificate</a></div>
				<div class="egiftcardlite_pageswitcher">'.$switcher.'</div>
				<div class="egiftcardlite_legend">
				<strong>Legend:</strong>
					<p><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="Edit certificate details" border="0"> Edit certificate details</p>
					<p><img src="'.plugins_url('/images/certificate.png', __FILE__).'" alt="Display certificate" border="0"> Display certificate</p>
					<p><img src="'.plugins_url('/images/transactions.png', __FILE__).'" alt="Payment transactions" border="0"> Show payment transactions</p>
					<p><img src="'.plugins_url('/images/redeem.png', __FILE__).'" alt="Mark certificate as redeemed" border="0"> Mark certificate as redeemed</p>
					<p><img src="'.plugins_url('/images/block.png', __FILE__).'" alt="Block certificate" border="0"> Block certificate</p>
					<p><img src="'.plugins_url('/images/unblock.png', __FILE__).'" alt="Unblock certificate" border="0"> Unblock certificate</p>
					<p><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="Delete certificate" border="0"> Delete certificate</p>
					<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px;"></div> Active certificate, purchased by customer<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #F0F0FF;"></div> Active certificate, created by administrator<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #F0FFF0;"></div> Redeemed certificate<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #FFF0F0;"></div> Blocked/Pending certificate<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #E0E0E0;"></div> Expired certificate
				</div>
			</div>
		');
	}

	function admin_add_certificate() {
		global $wpdb;

		unset($id);
		$status = "";
		if (isset($_GET["id"]) && !empty($_GET["id"])) {
			$id = intval($_GET["id"]);
			$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
			if (intval($certificate_details["id"]) == 0) unset($id);
			else {
				$status = "Active, created by user";
				if ($certificate_details["status"] == GCL_STATUS_ACTIVE_REDEEMED) $status = "Redeemed";
				else if (time() > $certificate_details["registered"] + 24*3600*$this->validity_period) $status = "Expired";
				else if ($certificate_details["status"] >= GCL_STATUS_PENDING) $status = "Blocked/Pending";
				else if ($certificate_details["status"] == GCL_STATUS_ACTIVE_BYADMIN) $status = "Active, created by admin";
			}
		}
		$errors = true;
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		else if ($errors !== true) {
			$message = "<div class='error'><p>The following error(s) exists:<br />- ".implode("<br />- ", $errors)."</p></div>";
		} else if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		print ('
		<div class="wrap admin_egiftcardlite_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>eGift Card Lite - '.(!empty($id) ? 'Edit certificate' : 'Create new certificate').'</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>'.(!empty($id) ? 'Edit certificate' : 'Create new certificate').'</span></h3>
							<div class="inside">
								<table class="egiftcardlite_useroptions">
									'.(!empty($id) ? '
									<tr>
										<th>Certificate number:</th>
										<td><strong>'.htmlspecialchars($certificate_details['code'], ENT_QUOTES).'</strong></td>
									</tr>
									<tr>
										<th>Certificate status:</th>
										<td style="padding-bottom: 24px;"><strong>'.$status.'</strong></td>
									</tr>' : '').'
									<tr>
										<th>Recipient:</th>
										<td><input type="text" name="egiftcardlite_recipient" id="egiftcardlite_recipient" value="'.htmlspecialchars($certificate_details['recipient'], ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter recipient\'s name.</em></td>
									</tr>
									<tr>
										<th>E-mail:</th>
										<td><input type="text" name="egiftcardlite_email" id="egiftcardlite_email" value="'.htmlspecialchars($certificate_details['email'], ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter recipient\'s e-mail.</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="egiftcardlite_update_certificate" />
								'.(!empty($id) ? '<input type="hidden" name="egiftcardlite_id" value="'.$id.'" />' : '').'
								<input type="submit" class="button-primary" name="submit" value="Submit details">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>');
	}

	function admin_transactions() {
		global $wpdb;
		if (isset($_GET["s"])) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		if (isset($_GET["tid"])) $transaction_id = trim(stripslashes($_GET["tid"]));
		else $transaction_id = "";
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."egcl_transactions WHERE id > 0".(strlen($transaction_id) > 0 ? " AND tx_str = '".$transaction_id."'" : "").((strlen($search_query) > 0) ? " AND (payer_name LIKE '%".addslashes($search_query)."%' OR payer_email LIKE '%".addslashes($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/GCL_ROWS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=egc-lite-transactions".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : "").(strlen($transaction_id) > 0 ? "&tid=".$transaction_id : ""), $page, $totalpages);

		$sql = "SELECT * FROM ".$wpdb->prefix."egcl_transactions WHERE id > 0".(strlen($transaction_id) > 0 ? " AND tx_str = '".$transaction_id."'" : "").((strlen($search_query) > 0) ? " AND (payer_name LIKE '%".addslashes($search_query)."%' OR payer_email LIKE '%".addslashes($search_query)."%')" : "")." ORDER BY created DESC LIMIT ".(($page-1)*GCL_ROWS_PER_PAGE).", ".GCL_ROWS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);

		print ('
			<div class="wrap admin_egiftcardlite_wrap">
				<div id="icon-edit-pages" class="icon32"><br /></div><h2>eGift Card Lite - Transactions</h2><br />
				<form action="'.get_bloginfo("wpurl").'/wp-admin/admin.php" method="get" style="margin-bottom: 10px;">
				<input type="hidden" name="page" value="egc-lite-transactions" />
				'.(strlen($transaction_id) > 0 ? '<input type="hidden" name="tid" value="'.$transaction_id.'" />' : '').'
				Search: <input type="text" name="s" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
				<input type="submit" class="button-secondary action" value="Search" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="Reset search results" onclick="window.location.href=\''.get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-transactions'.(strlen($transaction_id) > 0 ? '&tid='.$transaction_id : '').'\';" />' : '').'
				</form>
				<div class="egiftcardlite_pageswitcher">'.$switcher.'</div>
				<table class="egiftcardlite_strings">
				<tr>
					<th>Certificates</th>
					<th>Payer</th>
					<th style="width: 100px;">Amount</th>
					<th style="width: 120px;">Status</th>
					<th style="width: 130px;">Created*</th>
				</tr>
		');
		if (sizeof($rows) > 0) {
			foreach ($rows as $row) {
				$certificates = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE tx_str = '".$row["tx_str"]."'", ARRAY_A);
				$list = array();
				foreach ($certificates as $certificate) {
					if ($certificate["deleted"] == 0) $list[] = '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-certificates&s='.$certificate["code"].'">'.$certificate["code"].'</a>';
					else $list[] = '*'.$certificate["code"];
				}
				print ('
				<tr>
					<td>'.implode(", ", $list).'</td>
					<td>'.htmlspecialchars($row['payer_name'], ENT_QUOTES).'<br /><em style="font-size: 12px; line-height: 14px;">'.htmlspecialchars($row['payer_email'], ENT_QUOTES).'</em></td>
					<td style="text-align: right;">'.number_format($row['gross'], 2, ".", "").' '.$row['currency'].'</td>
					<td>'.$row["payment_status"].'<br /><em style="font-size: 12px; line-height: 14px;">'.$row["transaction_type"].'</em></td>
					<td>'.date("Y-m-d H:i:s", $row["created"]).'</td>
				</tr>
				');
			}
		} else {
			print ('
				<tr><td colspan="5" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? 'No results found for "<strong>'.htmlspecialchars($search_query, ENT_QUOTES).'</strong>"' : 'List is empty.').'</td></tr>
			');
		}
		print ('
				</table>
				<div class="egiftcardlite_pageswitcher">'.$switcher.'</div>
			</div>');
	}
	
	function admin_request_handler() {
		global $wpdb;
		if (!empty($_POST['ak_action'])) {
			switch($_POST['ak_action']) {
				case 'egiftcardlite_update_settings':
					$this->populate_settings();
					if (isset($_POST["egiftcardlite_paypal_sandbox"])) $this->paypal_sandbox = "on";
					else $this->paypal_sandbox = "off";
					if (isset($_POST["egiftcardlite_use_https"])) $this->use_https = "on";
					else $this->use_https = "off";
					$errors = $this->check_settings();
					if ($errors === true) {
						$this->update_settings();
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite&updated=true');
						die();
					} else {
						$this->update_settings();
						$message = "";
						if (is_array($errors)) $message = "The following error(s) occured:<br />- ".implode("<br />- ", $errors);
						setcookie("egiftcardlite_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite');
						die();
					}
					break;
				case "egiftcardlite_update_certificate":
					unset($id);
					if (isset($_POST["egiftcardlite_id"]) && !empty($_POST["egiftcardlite_id"])) {
						$id = intval($_POST["egiftcardlite_id"]);
						$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
						if (intval($certificate_details["id"]) == 0) unset($id);
					}
					$recipient = trim(stripslashes($_POST["egiftcardlite_recipient"]));
					$email = trim(stripslashes($_POST["egiftcardlite_email"]));

					unset($errors);
					if (strlen($recipient) < 2) $errors[] = "recipient's name is too short";
					else if (strlen($recipient) > 128) $errors[] = "recipient's name is too long";
					if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $email) || strlen($email) == 0) $errors[] = "e-mail must be valid e-mail address";

					if (empty($errors)) {
						if (!empty($id)) {
							$sql = "UPDATE ".$wpdb->prefix."egcl_certificates SET 
								recipient = '".@$wpdb->prepare($recipient)."',
								email = '".@$wpdb->prepare($email)."'
								WHERE id = '".$id."'";
							if ($wpdb->query($sql) !== false) {
								setcookie("egiftcardlite_info", "Certificate successfully updated", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
								die();
							} else {
								setcookie("egiftcardlite_error", "Service is not available", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-add&id='.$id);
								die();
							}
						} else {
							$code = $this->generate_certificate();
							$sql = "INSERT INTO ".$wpdb->prefix."egcl_certificates (
								tx_str, code, recipient, email, price, currency, status, registered, blocked, deleted) VALUES (
								'".$code."',
								'".$code."',
								'".@$wpdb->prepare($recipient)."',
								'".@$wpdb->prepare($email)."',
								'0',
								'',
								'".GCL_STATUS_ACTIVE_BYADMIN."',
								'".time()."', '0', '0'
								)";
							if ($wpdb->query($sql) !== false) {
								$message = "Certificate successfully added";
								setcookie("egiftcardlite_info", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
								die();
							} else {
								setcookie("egiftcardlite_error", "Service is not available", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-add');
								die();
							}
						}
					} else {
						$message = "The following error(s) occured:<br />- ".implode("<br />- ", $errors);
						setcookie("egiftcardlite_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-add'.(!empty($id) ? "&id=".$id : ""));
						die();
					}
					break;
			}
		}
		if (!empty($_GET['ak_action'])) {
			switch($_GET['ak_action']) {
				case 'egiftcardlite_delete':
					$id = intval($_GET["id"]);
					$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "egcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($certificate_details["id"]) == 0) {
						setcookie("egiftcardlite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					}
					$sql = "UPDATE ".$wpdb->prefix."egcl_certificates SET deleted = '1' WHERE id = '".$id."'";
					if ($wpdb->query($sql) !== false) {
						setcookie("egiftcardlite_info", "Certificate successfully removed", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					} else {
						setcookie("egiftcardlite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					}
					break;

				case 'egiftcardlite_block':
					$id = intval($_GET["id"]);
					$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($certificate_details["id"]) == 0) {
						setcookie("egiftcardlite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					}
					if (time() <= $certificate_details["registered"] + 24*3600*$this->validity_period && ($certificate_details["status"] == GCL_STATUS_ACTIVE_BYUSER || $certificate_details["status"] == GCL_STATUS_ACTIVE_BYADMIN)) {
						$sql = "UPDATE ".$wpdb->prefix."egcl_certificates SET status = '".GCL_STATUS_PENDING_BLOCKED."', blocked = '".time()."' WHERE id = '".$id."'";
						$wpdb->query($sql);
						setcookie("egiftcardlite_info", "Certificate successfully blocked", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					} else {
						setcookie("egiftcardlite_error", "You can not block this certificate", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					}
					break;
					
				case 'egiftcardlite_unblock':
					$id = intval($_GET["id"]);
					$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($certificate_details["id"]) == 0) {
						setcookie("egiftcardlite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					}
					if ($certificate_details["status"] == GCL_STATUS_PENDING_PAYMENT || $certificate_details["status"] == GCL_STATUS_PENDING_BLOCKED) {
						if (intval($certificate_details["blocked"]) >= $certificate_details["registered"]) {
							$registered = time() - $certificate_details["blocked"] + $certificate_details["registered"];
						} else $registered = $certificate_details["registered"];
						$sql = "UPDATE ".$wpdb->prefix."egcl_certificates SET status = '".($certificate_details["price"] > 0 ? GCL_STATUS_ACTIVE_BYUSER : GCL_STATUS_ACTIVE_BYADMIN)."', registered = '".$registered."' WHERE id = '".$id."'";
						$wpdb->query($sql);
						setcookie("egiftcardlite_info", "Certificate successfully unblocked", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					} else {
						setcookie("egiftcardlite_error", "You can not unblock this certificate", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					}
					break;
				case 'egiftcardlite_redeem':
					$id = intval($_GET["id"]);
					$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($certificate_details["id"]) == 0) {
						setcookie("egiftcardlite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					}
					if (time() <= $certificate_details["registered"] + 24*3600*$this->validity_period && ($certificate_details["status"] == GCL_STATUS_ACTIVE_BYUSER || $certificate_details["status"] == GCL_STATUS_ACTIVE_BYADMIN)) {
						$sql = "UPDATE ".$wpdb->prefix."egcl_certificates SET status = '".GCL_STATUS_ACTIVE_REDEEMED."', blocked = '".time()."' WHERE id = '".$id."'";
						$wpdb->query($sql);
						setcookie("egiftcardlite_info", "Certificate successfully marked as redeemed", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					} else {
						setcookie("egiftcardlite_error", "You can not mark this certificate as redeemed", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=egc-lite-certificates');
						die();
					}
					break;
				default:
					break;
			}
		}
	}

	function admin_warning() {
		echo '
		<div class="updated"><p><strong>eGift Card Lite plugin almost ready.</strong> You must do some <a href="admin.php?page=egc-lite">settings</a> for it to work.</p></div>
		';
	}

	function admin_header() {
		global $wpdb;
		echo '
		<link rel="stylesheet" type="text/css" href="'.plugins_url('/css/style.css', __FILE__).'?ver=1.28" media="screen" />
		<script type="text/javascript">
			function egiftcardlite_submitOperation() {
				var answer = confirm("Do you really want to continue?")
				if (answer) return true;
				else return false;
			}
		</script>';
	}

	function front_init() {
		global $wpdb;
		
		if($_REQUEST['cid'] or $_REQUEST['tid'])
		{
			
		$this->showcertificate();
        exit; 		
			
		}
		if($_REQUEST['egiftpaypalipn']=="ipn")
		{		
		$this->egiftpaypalipn();
		exit;
		}
		
		if ($_POST["egiftcardlite_signup_action"] == "yes") {
			header ('Content-type: text/html; charset=utf-8');
			unset($errors);
			$recipients = array();
			for ($i=0; $i<10; $i++) {
				if (isset($_POST["egiftcardlite_signup_recipient".$i])) {
					$tmp = trim(stripslashes($_POST["egiftcardlite_signup_recipient".$i]));
					if (strlen($tmp) > 0) $recipients[] = $tmp;
				}
			}
			if (sizeof($recipients) == 0) $errors[] = "please enter at least one recipient's name";
			for ($i=0; $i<sizeof($recipients); $i++) {
				if (strlen($recipients[$i]) > 63) {
					$errors[] = "one of recipient's name is too long";
					break;
				}
			}
			if (!empty($errors)) {
				echo "ERRORS: ".ucfirst(implode(", ", $errors)).".";
				die();
			}
			$boughtecard=0;	
			if($_POST['ecard'] and $_POST['ecardid'])
			{
			$boughtecard=1;	
				
			}


			$price = number_format(sizeof($recipients)*$this->price, 2, ".", "");
			$items = array();
			$tx_str = $this->generate_certificate();
			for ($i=0; $i<sizeof($recipients); $i++) {
				$code = $this->generate_certificate();
				$sql = "INSERT INTO ".$wpdb->prefix."egcl_certificates (
					tx_str, code, recipient, email, price, currency, status, registered, blocked, deleted,ecard) VALUES (
					'".$tx_str."',
					'".$code."',
					'".addslashes($wpdb->prepare($recipients[$i]))."',
					'',
					'".$this->price."',
					'".$this->currency."',
					'".GCL_STATUS_DRAFT."',
					'".time()."', '".time()."', '0','".$boughtecard."'
					)";
				if ($wpdb->query($sql) !== false) {
					$items[] = htmlspecialchars($recipients[$i], ENT_QUOTES).' <span>(<a target="_blank" href="'.($this->use_https == "on" ? str_replace("http://", "https://", get_bloginfo("wpurl")) : get_bloginfo("wpurl")).'/?cid='.$code.'">certificate preview</a>)</span>';
				}
			}
			if (sizeof($items) == 0) {
				echo "ERRORS: Sevice temporarily not available.";
				die();
			}
			
$ecardselected="";			
if($boughtecard==1)
{	
			
$upload_dir = wp_upload_dir();
$baseurl=$upload_dir['baseurl']."/egiftcardlite/";
//$ecardlist  .=  '<div class="option_div"><input selected  type="radio"  name="ecardid" id="ecardid" value="'.$row['ecards_id'].'">';
$ecardselected  =  '<tr><td>Gift card:</td><td class="egiftcardlite_confirmation_data"><img src="'.$baseurl.$this->smallimage.'" height="auto" width="120"  border="0" /></td></tr>';
}				
			
			
			echo '
<div class="egiftcardlite_confirmation_info">
	<table class="egiftcardlite_confirmation_table">
		<tr><td style="width: 170px">Gift Certificate:</td><td class="egiftcardlite_confirmation_data">'.htmlspecialchars($this->title, ENT_QUOTES).' ('.number_format($this->price, 2, ".", "").' '.$this->currency.')'.(strlen($this->description) > 0 ? '<br /><em>'.htmlspecialchars($this->description, ENT_QUOTES).'</em><br />' : '').'</td></tr>
		<tr><td>Expires on:</td><td class="egiftcardlite_confirmation_data">'.date("F j, Y", time()+24*3600*$this->validity_period).'</td></tr>
		<tr><td>Certificate:</td><td class="egiftcardlite_confirmation_data">'.implode("<br />", $items).'</td></tr>'.$ecardselected.'
		<tr><td>Total price:</td><td class="egiftcardlite_confirmation_price">'.$price.' '.$this->currency.'</td></tr>
	</table>
	<div class="egiftcardlite_signup_buttons">
		<input type="button" class="egiftcardlite_signup_button" id="egiftcardlite_signup_pay" name="egiftcardlite_signup_pay" value="Purchase" onclick="jQuery(\'#egiftcardlite_buynow\').click();">
		<input type="button" class="egiftcardlite_signup_button" id="egiftcardlite_signup_edit" name="egiftcardlite_signup_edit" value="Edit info" onclick="egiftcardlite_edit();">
	</div>
	<form action="'.(($this->paypal_sandbox == "on") ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr').'" method="post" style="display: none;">
		<input type="hidden" name="cmd" value="_xclick">
		<input type="hidden" name="business" value="'.$this->paypal_id.'">
		<input type="hidden" name="no_shipping" value="1">
		<input type="hidden" name="lc" value="US">
		<input type="hidden" name="rm" value="2">
		<input type="hidden" name="item_name" value="Gift Certificate ('.sizeof($recipients).(sizeof($recipients) > 1 ? ' persons' : ' person').')">
		<input type="hidden" name="item_number" value="1">
		<input type="hidden" name="amount" value="'.$price.'">
		<input type="hidden" name="currency_code" value="'.$this->currency.'">
		<input type="hidden" name="custom" value="'.$tx_str.'">
		<input type="hidden" name="bn" value="PP-BuyNowBF:btn_buynow_LG.gif:NonHostedGuest">
		<input type="hidden" name="return" value="http://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"].'">
		<input type="hidden" name="cancel_return" value="http://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"].'">
		<input type="hidden" name="notify_url" value="'.get_bloginfo("wpurl").'/?egiftpaypalipn=ipn">
		<input type="submit" id="egiftcardlite_buynow" value="Submit">
	</form>
	<em>Printable gift certificate'.(sizeof($recipients) > 1 ? 's' : '').' will be sent to your PayPal e-mail.</em>
</div>';


			die();
		} else if (isset($_GET["gcl-certificate"])) {
			$cid = $_GET["gcl-certificate"];
			$cid = preg_replace('/[^a-zA-Z0-9]/', '', $cid);
			$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE code = '".$cid."' AND deleted = '0'", ARRAY_A);
			if (intval($certificate_details["id"]) != 0) {
				if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge|maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|pixi|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/iu', $_SERVER['HTTP_USER_AGENT']))
					header("Location: ".($this->use_https == "on" ? str_replace("http://", "https://", get_bloginfo("wpurl")) : get_bloginfo("wpurl")).'/?cid='.$certificate_details["code"]);
				else 
					header("Location: ".get_bloginfo("wpurl").'/wp-admin/admin.php?page=egc-lite-certificates&s='.$certificate_details["code"]);
				exit;
			}
		}
	}
	
	function front_header() {
		echo '
		<link rel="stylesheet" type="text/css" href="'.plugins_url('/css/front.css?ver=1.28', __FILE__).'" media="screen" />';
		//echo '		<script type="text/javascript" src="'.plugins_url('/js/wpgc-ecards.js', __FILE__).'" />';

	}

	function shortcode_handler($_atts) {
		global $wpdb;
		$form = "";
		
	
		if ($this->check_settings() === true)
		{
				//print "Inside";
			//$id = intval($_atts["id"]);
			
			$terms = htmlspecialchars($this->terms, ENT_QUOTES);
			$terms = str_replace("\n", "<br />", $terms);
			$terms = str_replace("\r", "", $terms);

			$form = '
<script type="text/javascript">
function egiftcardlite_addrecipient() {
	var fields = jQuery(".egiftcardlite_additional");
	for(i=0; i<fields.length; i++) {
		if (!jQuery(fields[i]).is(":visible")) {
			jQuery(fields[i]).toggle(200);
			if (i == fields.length-1) jQuery(".egiftcardlite_addrecipient").toggle(200);
			return;
		}
	}
}
function egiftcardlite_edit() {
	jQuery("#egiftcardlite_confirmation_container").fadeOut(500, function() {
		jQuery("#egiftcardlite_signup_form").fadeIn(500, function() {});
	});
}
function egiftcardlite_presubmit() {
	jQuery("#egiftcardlite_signup_submit").css("display", "none");
	jQuery("#egiftcardlite_signup_spinner").css("display", "block");
}
jQuery(document).ready(function() {
	jQuery("#egiftcardlite_signup_iframe").load(function() {
		var data = jQuery("#egiftcardlite_signup_iframe").contents().find("html").html();
		if (data.indexOf("ERRORS:") >= 0) {
			jQuery("#egiftcardlite_signup_errorbox").html(data);
			jQuery("#egiftcardlite_signup_errorbox").css("display", "block");
			jQuery("#egiftcardlite_signup_spinner").css("display", "none");
			jQuery("#egiftcardlite_signup_submit").css("display", "inline-block");
		} else if (data.indexOf("egiftcardlite_confirmation_info") >= 0) {
			jQuery("#egiftcardlite_signup_form").fadeOut(500, function() {
				jQuery("#egiftcardlite_signup_errorbox").css("display", "none");
				jQuery("#egiftcardlite_signup_spinner").css("display", "none");
				jQuery("#egiftcardlite_signup_submit").css("display", "inline-block");
				jQuery("#egiftcardlite_confirmation_container").html(data);
				jQuery("#egiftcardlite_confirmation_container").fadeIn(500, function() {});
			});
		} else {
			jQuery("#egiftcardlite_signup_errorbox").css("display", "none");
			jQuery("#egiftcardlite_signup_spinner").css("display", "none");
			jQuery("#egiftcardlite_signup_submit").css("display", "inline-block");
		}
	});
});
</script>
<div class="egiftcardlite_signup_box">
	<div id="egiftcardlite_confirmation_container"></div>
	<form action="" target="egiftcardlite_signup_iframe" enctype="multipart/form-data" method="post" id="egiftcardlite_signup_form" onsubmit="egiftcardlite_presubmit(); return true;">
	<label class="egiftcardlite_bigfont">'.htmlspecialchars($this->title, ENT_QUOTES).' ('.number_format($this->price, 2, ".", "").' '.$this->currency.')</label>
	'.(strlen($this->description) > 0 ? '<br /><em>'.htmlspecialchars($this->description, ENT_QUOTES).'</em><br />' : '').'
	<br /><br />
	<label for="egiftcardlite_signup_string">Recipient\'s name <span>(mandatory)</span></label><br />
	<input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient0" name="egiftcardlite_signup_recipient0" value=""><br />
	<em>Enter recipient\'s name. This name is printed on gift certificate.</em>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient1" name="egiftcardlite_signup_recipient1" value=""></div>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient2" name="egiftcardlite_signup_recipient2" value=""></div>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient3" name="egiftcardlite_signup_recipient3" value=""></div>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient4" name="egiftcardlite_signup_recipient4" value=""></div>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient5" name="egiftcardlite_signup_recipient5" value=""></div>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient6" name="egiftcardlite_signup_recipient6" value=""></div>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient7" name="egiftcardlite_signup_recipient7" value=""></div>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient8" name="egiftcardlite_signup_recipient8" value=""></div>
	<div class="egiftcardlite_additional egiftcardlite_hidden"><input type="text" class="egiftcardlite_signup_long" id="egiftcardlite_signup_recipient9" name="egiftcardlite_signup_recipient9" value=""></div>
	<br /><br />
	<a class="egiftcardlite_addrecipient" href="#" onclick="egiftcardlite_addrecipient(); return false;">Add recipient</a>
	<br /><br />';
	
	if ($this->enableecard=="on")
	{
	$ecardlist="";	
		$upload_dir = wp_upload_dir();
		$baseurl=$upload_dir['baseurl']."/egiftcardlite/";
		
$ecardlist  .=  '<div class="option_div" style="vertical-align: middle;"><input checked  type="radio"  name="ecardid" id="ecardid" value="yes">';
$ecardlist  .=  '&nbsp;<a class="thumb2" href="#thumb2"><img src="'.$baseurl.$this->smallimage.'" height="auto" width="120"  border="0" /><span><img src="'.$baseurl.$this->bigimage.'"  /><br />'.$this->ecardname.'</span></a></div>';
		
		
	$form .= '<div class="egiftcardlite_bigfont"><input checked  value="yes" type="checkbox" id="ecard" name="ecard" onclick="jQuery(\'#egiftcardlite_ecardslist\').toggle(300); ">&nbsp;Create a Gift Certificate as an e-Gift Card style<br />
	<em>Tick checkbox to choose Gift Card style. Hover over thumbnail to preview and Click on button to select it..</em> <br>
	<div id="egiftcardlite_ecardslist" style="">

	'.$ecardlist.'</div></div><br>';
	}
	
	
			if (!empty($this->terms)) $form .= '
	<div id="egiftcardlite_signup_terms_box" style="display: none;">
	<label for="egiftcardlite_signup_link">Terms & Conditions</label><br />
	<div class="egiftcardlite_signup_terms">'.$terms.'</div>
	<br /></div><p class="egiftcardlite_text">By clicking "Continue" button I agree with <a href="#" onclick="jQuery(\'#egiftcardlite_signup_terms_box\').toggle(300); return false;">Terms & Conditions</a>.</p>';
			$form .= '	
	<div class="egiftcardlite_signup_buttons">
		<input type="hidden" name="egiftcardlite_signup_action" value="yes">
		<input type="submit" class="egiftcardlite_signup_button" id="egiftcardlite_signup_submit" name="egiftcardlite_signup_submit" value="Continue">
		<div id="egiftcardlite_signup_spinner"></div>
	</div>
	<div id="egiftcardlite_signup_errorbox"></div>
	</form>
</div>
<iframe id="egiftcardlite_signup_iframe" name="egiftcardlite_signup_iframe" style="border: 0px; height: 0px; width: 0px; margin: 0px; padding: 0px;"></iframe>';
		}
		return $form;
	}	
	
	function page_switcher ($_urlbase, $_currentpage, $_totalpages)
	{
		$pageswitcher = "";
		if ($_totalpages > 1)
		{
			$pageswitcher = "<div class='tablenav bottom'><div class='tablenav-pages'>Pages: <span class='pagiation-links'>";
			if (strpos($_urlbase,"?") !== false) $_urlbase .= "&amp;";
			else $_urlbase .= "?";
			if ($_currentpage == 1) $pageswitcher .= "<a class='page disabled'>1</a> ";
			else $pageswitcher .= " <a class='page' href='".$_urlbase."p=1'>1</a> ";

			$start = max($_currentpage-3, 2);
			$end = min(max($_currentpage+3,$start+6), $_totalpages-1);
			$start = max(min($start,$end-6), 2);
			if ($start > 2) $pageswitcher .= " <b>...</b> ";
			for ($i=$start; $i<=$end; $i++)
			{
				if ($_currentpage == $i) $pageswitcher .= " <a class='page disabled'>".$i."</a> ";
				else $pageswitcher .= " <a class='page' href='".$_urlbase."p=".$i."'>".$i."</a> ";
			}
			if ($end < $_totalpages-1) $pageswitcher .= " <b>...</b> ";

			if ($_currentpage == $_totalpages) $pageswitcher .= " <a class='page disabled'>".$_totalpages."</a> ";
			else $pageswitcher .= " <a class='page' href='".$_urlbase."p=".$_totalpages."'>".$_totalpages."</a> ";
			$pageswitcher .= "</span></div></div>";
		}
		return $pageswitcher;
	}
	
	function cut_string($_string, $_limit=40) {
		if (strlen($_string) > $_limit) return substr($_string, 0, $_limit-3)."...";
		return $_string;
	}
	
	function period_to_string($period) {
		$period_str = "";
		$days = floor($period/(24*3600));
		$period -= $days*24*3600;
		$hours = floor($period/3600);
		$period -= $hours*3600;
		$minutes = floor($period/60);
		if ($days > 1) $period_str = $days." days, ";
		else if ($days == 1) $period_str = $days." day, ";
		if ($hours > 1) $period_str .= $hours." hours, ";
		else if ($hours == 1) $period_str .= $hours." hour, ";
		else if (!empty($period_str)) $period_str .= "0 hours, ";
		if ($minutes > 1) $period_str .= $minutes." minutes";
		else if ($minutes == 1) $period_str .= $minutes." minute";
		else $period_str .= "0 minutes";
		return $period_str;
	}
	
	function add_url_parameters($_base, $_params) {
		if (strpos($_base, "?")) $glue = "&";
		else $glue = "?";
		$result = $_base;
		if (is_array($_params)) {
			foreach ($_params as $key => $value) {
				$result .= $glue.rawurlencode($key)."=".rawurlencode($value);
				$glue = "&";
			}
		}
		return $result;
	}
	
	function generate_certificate() {
		$symbols = '123456789ABCDEFGHGKLMNPQRSTUWVXYZ';
		$code = "";
		for ($i=0; $i<12; $i++) {
			$code .= $symbols[rand(0, strlen($symbols)-1)];
		}
		return $code;
	}
	
	function showcertificate()
	{
	global $wpdb;	
	
			$upload_dir = wp_upload_dir();
		$baseurl=$upload_dir['baseurl']."/egiftcardlite/";
if ($this->check_settings() === true) {
	$cid = $_GET["cid"];
	$cid = preg_replace('/[^a-zA-Z0-9]/', '', $cid);
	$tid = $_GET["tid"];
	$tid = preg_replace('/[^a-zA-Z0-9]/', '', $tid);

	
	if (!empty($tid)) $certificates = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE tx_str = '".$tid."' AND deleted = '0'", ARRAY_A);
	else $certificates = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE code = '".$cid."' AND deleted = '0'", ARRAY_A);
	print ('
<html>
<head>
<title>eGift Cards</title>
<style>
body {font-family: arial, verdana; font-size: 13px; color: #000;}
</style>
</head>
<body>');

	if (sizeof($certificates) > 0) {
		$i = 0;
		foreach ($certificates as $row) {
			$description = htmlspecialchars($this->company_description, ENT_QUOTES);
			$description = str_replace("\n", "<br />", $description);
			$description = str_replace("\r", "", $description);
			if ($row["status"] == GCL_STATUS_DRAFT) $status = '<span style="color: red; font-weight: bold;">NOT PAID</a>';
			else if ($row["status"] == GCL_STATUS_ACTIVE_REDEEMED) $status = '<span style="color: red; font-weight: bold;">REDEEMED</a>';
			else if (time() > $row["registered"] + 24*3600*$this->validity_period) $status = '<span style="color: red; font-weight: bold;">EXPIRED</a>';
			else if ($row["status"] >= GCL_STATUS_PENDING) $status = '<span style="color: red; font-weight: bold;">BLOCKED</a>';
			else $status = "";
			
			
			
			if($row["ecard"]!=0)
				{
				

							
							
							
							
				
				print '<style>body {
  font-size: 12px;
  margin: 20; 
  
  padding: 0;
  font-family: sans-serif;
}
mystyle {
  font-size: 12px;
  margin: 0; 
  padding: 0;
  font-family: sans-serif;
}
p, ul, blockquote, pre, td, th, label {
  font-size: 12px;
  margin: 0; 
  padding: 0;
  font-family: sans-serif;
}</style><div style="display:table;padding:20px;border-radius:5px;border:2px solid #999;"><table width=600px cellpadding=10 cellspacing=10><tr><td bgcolor="grey"><img src="'.$baseurl.$this->certimage.'" height="300px" width="595px"></td></tr>';
				if($_details['message'])
				{
				//print '<tr><td  align=center><table bgcolor=grey width=70% align=center><tr><td><font size=10>'.$_details['message']['value'].'</font></td></tr></table></td></tr>';
				}
				
print '<tr><td  align=center><table  width=100% align=center><tr><td><img src="'.$baseurl.$this->smallimage.'" height="auto" width="220" ></td><td align=left class="mystyle"><h2>'.htmlspecialchars($this->title, ENT_QUOTES).'</h2></td></tr></table></td></tr>';					
				
				

							
		$details_html .= '<table style="width: 100%;"><tr>
			<td style="font-weight: bold; padding-bottom: 6px; width: 40%;">Campaign:</td>
			<td style="padding-bottom: 6px;">'.htmlspecialchars($this->title, ENT_QUOTES).'</td>
		</tr>
		<tr>
			<td style="font-weight: bold; padding-bottom: 6px; width: 40%;">Number:</td>
			<td style="padding-bottom: 6px;">'.htmlspecialchars($row['code'], ENT_QUOTES).'</td>
		</tr>
		<tr>
			<td style="font-weight: bold; padding-bottom: 6px; width: 40%;">Valid until:</td>
			<td style="padding-bottom: 6px;">'.date("F j, Y", $row['registered']+24*3600*$this->validity_period).'</td>
		</tr>
		<tr>
			<td style="font-weight: bold; padding-bottom: 6px; width: 40%;">Price:</td>
			<td style="padding-bottom: 6px;">'.number_format($this->price, 2, ".", "").' '.$this->currency.'&nbsp;'.$status.'</td>
		</tr>
		<tr>
			<td style="font-weight: bold; padding-bottom: 6px; width: 40%;">Owner:</td>
			<td style="padding-bottom: 6px;">'.htmlspecialchars($row['recipient'], ENT_QUOTES).'</td>
		</tr>
		';							
							
							$details_html .= '
	</table>';
							$qrcode = '
								<!--<img src="http://chart.apis.google.com/chart?chs=150x150&cht=qr&chld=|1&chl='.rawurlencode(get_bloginfo("wpurl").'/?wpgc='.$_certificate_details["code"]).'" alt="QR Code" />-->
								<img src="'.plugins_url('/phpqrcode/qrcode.php?url='.rawurlencode(get_bloginfo("wpurl").'/?gcl-certificate='.$row["code"]), __FILE__).'" alt="QR Code" width="150" height="150" />';
								//print $qrcode;
								
								//print 								"<br>".$wpgc->attr['url']; 
							$qrcode = apply_filters('wpgc_certificate_html_qrcode', $qrcode, $_certificate_details);
							$qrcode = apply_filters('wpgc_custom_certificate_code_qrcode_display', $qrcode,$wpgc->options);
print '<tr><td  align=center><table  width=100% align=center><tr><td>'.$details_html.'</td><td align=right><div style="display:table;padding:2px;border-radius:5px;border:2px solid #999;">'.$qrcode.'</div></td></tr></table></td></tr>';	
	
                            $terms =  unserialize($_certificate_details['campaign_options']);
			      
                           // $html.=stripslashes($terms['termsbox']['terms']).'</td></tr></table>';
	
print '<!--<tr><td  align=center><div style="display:table;padding:20px;border-radius:5px;border:2px solid #999;"><table  width=100% align=center><tr><td>'.stripslashes($terms['termsbox']['terms']).'</td></tr></table></div></td></tr>-->';					
				
				
				print "</table></div>";
				//print $options['ecard'];
				exit;
				}			
			
			
			
			
			
			
			print ('
		<table style="border: solid 2px #000;width: 600px; margin-bottom: 20px; border-collapse: collapse">
			<tr>
				<td style="padding: 10px; vertical-align: middle; text-align: center; border: 1px solid #000; width: 150px;">
					<!-- <img src="http://chart.apis.google.com/chart?chs=150x150&cht=qr&chld=|1&chl='.rawurlencode(get_bloginfo("wpurl").'/?gcl-certificate='.$row["code"]).'" alt="QR Code" /> -->
					<img src="'.plugins_url('/phpqrcode/qrcode.php?url='.rawurlencode(get_bloginfo("wpurl").'/?gcl-certificate='.$row["code"]), __FILE__).'" alt="QR Code" width="150" height="150" />
					'.$status.'
				</td>
				<td style="padding: 10px; vertical-align: top; border: 1px solid #000;">
					<table style="width: 100%;">
						<tr>
							<td colspan="2" style="font-size: 16px; font-weight: bold; text-align: center; padding-bottom: 10px;">'.htmlspecialchars($this->title, ENT_QUOTES).'</td>
						</tr>
						<tr>
							<td style="font-weight: bold; padding-bottom: 10px; width: 50%;">Number:</td>
							<td style="padding-bottom: 10px;">'.htmlspecialchars($row['code'], ENT_QUOTES).'</td>
						</tr>
						<tr>
							<td style="font-weight: bold; padding-bottom: 10px;">Valid until:</td>
							<td style="padding-bottom: 10px;">'.date("F j, Y", $row['registered']+24*3600*$this->validity_period).'</td>
						</tr>
						<tr>
							<td style="font-weight: bold; padding-bottom: 10px;">Price:</td>
							<td style="padding-bottom: 10px;">'.number_format($this->price, 2, ".", "").' '.$this->currency.'</td>
						</tr>
						<tr>
							<td style="font-weight: bold; padding-bottom: 10px;">Owner:</td>
							<td style="padding-bottom: 10px;">'.htmlspecialchars($row['recipient'], ENT_QUOTES).'</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="text-align: center; border: 1px solid #000; padding: 10px;"><span style="font-size:24px;">'.$this->company_title.'</span><br/>'.$description.'</td>
			</tr>
		</table>');
			$i++;
			if ($i % 2 == 0) print ('<div style="page-break-after: always;"></div>');
		}
	} else {
		print('No certificates found!');
	}
	print ('
</body>
</html>');		
		
		
		
		
		
		
}	
		
		
		
	}
	
function egiftpaypalipn()
{
global $wpdb;	
	
$request = "cmd=_notify-validate";
foreach ($_POST as $key => $value) {
	$value = urlencode(stripslashes($value));
	$request .= "&".$key."=".$value;
}

		$paypalurl = ($this->paypal_sandbox == "on" ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr');
		$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $paypalurl);
					//BOF IPN - HTTP 1.1 LINE ADDED
					curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
					//EOF IPN - HTTP 1.1 LINE ADDED
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HEADER, false);
					curl_setopt($ch, CURLOPT_TIMEOUT, 20);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					//BOF IPN - HTTP 1.1 LINE ADDED
					curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
					//EOF IPN - HTTP 1.1 LINE ADDED
		$result = curl_exec($ch);
		curl_close($ch);                
		if (substr(trim($result), 0, 8) != "VERIFIED") die();

		$item_number = stripslashes($_POST['item_number']);
		$item_name = stripslashes($_POST['item_name']);
		$payment_status = stripslashes($_POST['payment_status']);
		$transaction_type = stripslashes($_POST['txn_type']);
		$seller_paypal = stripslashes($_POST['business']);
		$payer_paypal = stripslashes($_POST['payer_email']);
		$gross_total = stripslashes($_POST['mc_gross']);
		$mc_currency = stripslashes($_POST['mc_currency']);
		$first_name = stripslashes($_POST['first_name']);
		$last_name = stripslashes($_POST['last_name']);
		$tx_str = stripslashes($_POST['custom']);
		$tx_str = preg_replace('/[^a-zA-Z0-9]/', '', $tx_str);
		
		$certificates = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."egcl_certificates WHERE tx_str = '".$tx_str."'", ARRAY_A);
		if ($transaction_type == "web_accept" && $payment_status == "Completed")
		{
			if (sizeof($certificates) == 0) $payment_status = "Unrecognized";
			else
			{
				if (empty($seller_paypal)) {
					$tx_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."egcl_transactions WHERE details LIKE '%txn_id=".$txn_id."%' AND payment_status != 'Unrecognized'", ARRAY_A);
					if (intval($tx_details["id"]) != 0) $seller_paypal = $this->paypal_id;
				}
				if (strtolower($seller_paypal) != strtolower($this->paypal_id)) $payment_status = "Unrecognized";
				else {
					$total = 0;
					foreach ($certificates as $certificate) {
						$total += floatval($this->price);
						$currency = $this->currency;
						$campaign_title = $this->title;
					}
					if (floatval($gross_total) < $total || $mc_currency != $currency) $payment_status = "Unrecognized";
				}
			}
		}
		$sql = "INSERT INTO ".$wpdb->prefix."egcl_transactions (
			tx_str, payer_name, payer_email, gross, currency, payment_status, transaction_type, details, created) VALUES (
			'".$tx_str."',
			'".$wpdb->prepare($first_name).' '.$wpdb->prepare($last_name)."',
			'".$wpdb->prepare($payer_paypal)."',
			'".floatval($gross_total)."',
			'".$mc_currency."',
			'".$payment_status."',
			'".$transaction_type."',
			'".mysql_real_escape_string($request)."',
			'".time()."'
		)";
		$wpdb->query($sql);
		if ($transaction_type == "web_accept")
		{
			if ($payment_status == "Completed") {
				$sql = "UPDATE ".$wpdb->prefix."egcl_certificates SET 
					status = '".GCL_STATUS_ACTIVE_BYUSER."',
					registered = '".time()."',
					email = '".mysql_real_escape_string($payer_paypal)."',
					blocked = '0'
					WHERE tx_str = '".$tx_str."'";
					
				if ($wpdb->query($sql) !== false) {
					$tags = array("{first_name}", "{last_name}", "{payer_email}", "{certificate_title}", "{certificate_url}", "{price}", "{currency}", "{transaction_date}");
					$vals = array($first_name, $last_name, $payer_paypal, $campaign_title, ($this->use_https == "on" ? str_replace("http://", "https://", get_bloginfo("wpurl")) : get_bloginfo("wpurl")).'/?tid='.$tx_str, $gross_total, $mc_currency, date("Y-m-d H:i:s")." (server time)");
					$body = str_replace($tags, $vals, $this->success_email_body);
					$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
					$mail_headers .= "From: ".$this->from_name." <".$this->from_email.">\r\n";
					$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
					wp_mail($payer_paypal, $this->success_email_subject, $body, $mail_headers);
					
					$body = str_replace($tags, $vals, "Dear Administrator,\r\n\r\nWe would like to inform you that {first_name} {last_name} ({payer_email}) paid {price} {currency} for gift certificate \"{certificate_title}\". Printable version: {certificate_url}. Payment date: {transaction_date}.\r\n\r\nThanks,\r\nAdministrator");
					$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
					$mail_headers .= "From: ".$this->from_name." <".$this->from_email.">\r\n";
					$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
					wp_mail($this->owner_email, "Completed payment received", $body, $mail_headers);
				} else {
					$tags = array("{first_name}", "{last_name}", "{payer_email}", "{certificate_title}", "{certificate_url}", "{price}", "{currency}", "{payment_status}", "{transaction_date}");
					$vals = array($first_name, $last_name, $payer_paypal, $campaign_title, ($this->use_https == "on" ? str_replace("http://", "https://", get_bloginfo("wpurl")) : get_bloginfo("wpurl")).'/?tid='.$tx_str, $gross_total, $mc_currency, "Server fail", date("Y-m-d H:i:s")." (server time)");
					$body = str_replace($tags, $vals, $this->failed_email_body);
					$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
					$mail_headers .= "From: ".$this->from_name." <".$this->from_email.">\r\n";
					$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
					wp_mail($payer_paypal, $this->failed_email_subject, $body, $mail_headers);

					$body = str_replace($tags, $vals, "Dear Administrator,\r\n\r\nWe would like to inform you that {first_name} {last_name} ({payer_email}) paid {price} {currency} for gift certificate \"{certificate_title}\". Printable version: {certificate_url}. Payment date: {transaction_date}. The payment was completed. But some server fails exists. Please activate certificate manually.\r\n\r\nThanks,\r\nAdministrator");
					$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
					$mail_headers .= "From: ".$this->from_name." <".$this->from_email.">\r\n";
					$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
					wp_mail($this->owner_email, "Completed payment received, server fails", $body, $mail_headers);
				}
			} else if ($payment_status == "Failed" || $payment_status == "Pending" || $payment_status == "Processed" || $payment_status == "Unrecognized") {
				$sql = "UPDATE ".$wpdb->prefix."egcl_certificates SET 
					status = '".GCL_STATUS_PENDING_PAYMENT."',
					registered = '".time()."',
					email = '".mysql_real_escape_string($payer_paypal)."',
					blocked = '".time()."'
					WHERE tx_str = '".$tx_str."'";
				$wpdb->query($sql);
				$tags = array("{first_name}", "{last_name}", "{payer_email}", "{certificate_title}", "{certificate_url}", "{price}", "{currency}", "{payment_status}", "{transaction_date}");
				$vals = array($first_name, $last_name, $payer_paypal, $campaign_title, ($this->use_https == "on" ? str_replace("http://", "https://", get_bloginfo("wpurl")) : get_bloginfo("wpurl")).'/?tid='.$tx_str, $gross_total, $mc_currency, $payment_status, date("Y-m-d H:i:s")." (server time)");

				$body = str_replace($tags, $vals, $this->failed_email_body);
				$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
				$mail_headers .= "From: ".$this->from_name." <".$this->from_email.">\r\n";
				$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
				wp_mail($payer_paypal, $this->failed_email_subject, $body, $mail_headers);

				$body = str_replace($tags, $vals, "Dear Administrator,\r\n\r\nWe would like to inform you that {first_name} {last_name} ({payer_email}) paid {price} {currency} for gift certificate \"{certificate_title}\". Printable version: {certificate_url}. Payment date: {transaction_date}.\r\nPayment status: {payment_status}.\r\n\r\nThanks,\r\nAdministrator");
				$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
				$mail_headers .= "From: ".$this->from_name." <".$this->from_email.">\r\n";
				$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
				wp_mail($this->owner_email, "Non-completed payment received", $body, $mail_headers);
			}
		}	
	
}	
	
}
$egiftcardlite = new egiftcardlite_class();
?>