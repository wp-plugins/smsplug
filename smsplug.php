<?php
/*
Plugin Name: SMSPlug
Plugin URI: http://plugins.wirtschaftsinformatiker.cc/wp-smsplug
Description: Allows the user to send sms via the smsblaster service.
Author: Marco Bischoff
Version: 0.3
Author URI: http://wirtschaftsinformatiker.cc/
License: GPL 2.0, @see http://www.gnu.org/licenses/gpl-2.0.html
*/

/*SMSBlaster class initialisieren*/
require_once("SMS.inc");

if(function_exists('load_plugin_textdomain'))
	load_plugin_textdomain('smsplug', false, dirname(plugin_basename(__FILE__)) . '/languages');

define("SMSPLUG_USERNAME", get_option('smsplug_userid'));
define("SMSPLUG_PASSWORD", decrypt(get_option('smsplug_password')));
define("SMSPLUG_TITLE", get_option('smsplug_title'));
define("SMSPLUG_TABLE", "smsplug");
define("SMSPLUG_URL",  'http://' . $_SERVER['SERVER_NAME'] . str_replace ('\\','/',substr (dirname (__FILE__),strlen ($_SERVER['DOCUMENT_ROOT']))) . '/');
define("SMSPLUG_ORIGINATOR", get_option("smsplug_originator"));
define("SMSPLUG_AMOUNT", get_option("smsplug_smsamount"));
define("SMSPLUG_ONLYREGISTEREDUSERS", get_option("smsplug_registeredusers"));

function SMSPlug_install() {
	global $table_prefix, $user_level, $wpdb;
	
	$table_name = $table_prefix . SMSPLUG_TABLE;
	get_currentuserinfo();
	if ($user_level < 8) { return false; };
	$tables = array();
	$tables = $wpdb->get_results('SHOW TABLES FROM '.DB_NAME.';', ARRAY_N);
	update_option("smsblaster_title", "SMSPlug");

	$first_install = false;

	$sql = "CREATE TABLE ".$table_name." (
			  smsplug_id bigint(20) unsigned NOT NULL auto_increment,
			  smsplug_author varchar(128) NOT NULL default '',
			  smsplug_msg varchar(160) NOT NULL default '',
			  smsplug_number varchar(160) NOT NULL default '',
			  smsplug_dateadded datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY  (smsplug_id)
			) ";


	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	dbDelta($sql);
} 
function encrypt($text) {
	return base64_encode($text);
} 

function decrypt($encrypted_text) {
 	return base64_decode($encrypted_text);
}

function form_SMSPlug() {
	global $current_user;
	
	$title = get_option('smsplug_title');
	
	echo $before_widget . $before_title . '<h2 class="widget SMSPlug">'. $title .'</h2>'. $after_title;
	echo "<p>";
		_e('Es stehen dir noch die folgende Anzahl SMS zur Verf&uuml;gung: ','smsblaster');
		echo(get_option("smsplug_smsamount") - smsplug_checkcreditsperuser());
	echo "</p>";
			get_currentuserinfo();
			
			print '
				<script language="javascript" type="text/javascript">
				function smsblaster_send() {
					var form = $("smsplug_form");
					var input = form["smsplug_msg"];

					new Ajax.Request("'.SMSPLUG_URL.'/smsplug_send.php", {
					  method: "post",
					  parameters: $("smsplug_form").serialize() + "&smsplug_msg=" + $(input).getValue() ,
					  onSuccess: function(transport) {
					    $("smsplug_submitted").innerHTML = transport.responseText;
					    $("smsplug_form").reset();
					    $("smsplug_chars").innerHTML = "160";
					  }
					});
 				}
 				
 				function smsplug_counter() {
					var div, txt, counter;
 					div = $("smsplug_chars");
  					txt = $("smsplug_msg");
  					counter = 160 - parseInt(txt.value.length,10);
  					div.innerHTML = counter;
  					if(counter < 1) {
	  					txt.value = txt.value.substring(0, 160);
  					}
  					txt.focus();
				}
 				
				</script>
			';
			echo '<div id="smsplug_submitted"></div>';
			echo 'Zeichen: <span id="smsplug_chars">160</span>';
			echo '<div>';
			print '<form id="smsplug_form" onsubmit="smsplug_send(); return false;">
				<input type="hidden" name="smsplug_userid" value="'. $current_user->ID .'" />
				<label for="smsplug_natel">'.__('Natelnummer', 'smsblaster').'</label><br />
				<input type="text" id="smsplug_natel" name="smsplug_natel" value=""><br />
				<label for="smsplug_msg">'.__('Mitteilung', 'smsblaster').'</label><br />
				<textarea id="smsplug_msg" cols="20" rows="10" onkeyup="smsplug_counter();" wrap="physical" ></textarea><br />
				<input type="submit" class="button-primary" value="'.__('Senden', 'smsblaster').'"  />
				</form>';
			
			echo '</div>';
			
			echo $after_widget;
}
//function SMSBlaster_widget_init() {
function widget_SMSPlug($args) {			
	//Speichern der Nachricht in einer Tabellen mit IP/Id des Users und Datum/Uhrzeit
	global $current_user;

	extract($args);
		if(get_option("smsplug_registeredusers") == true) {
			if(is_user_logged_in()) {
				
				if(smsplug_checkcreditsperuser() < get_option("smsplug_smsamount")) {
					form_SMSPlug();
				} else {
					echo "<p>";
					_e('Es stehen dir f&uuml;r diesen Monat keine SMS mehr zur Verf&uuml;gung', 'smsblaster');
					echo "</p>";
				}
				
			}
		} else {
				form_SMSPlug();
		}
}
//}

function smsplug_checkcredits() {
	$iReturn = 0;
	$oSms = new SMS(SMSBPLUG_USERNAME, SMSPLUG_PASSWORD);
	if(!$oSms->showCredits()) {
	  die('Error: ' . $oSms->getErrorDescription() . "\n");
	} else {
		$iReturn = $oSms->getCredits();
	}	
	return $iReturn;
}

function smsplug_checkcreditsperuser() {
	global $current_user, $wpdb, $table_prefix;
	$tablename = $table_prefix . SMSPLUG_TABLE;
	$aResult = $wpdb->get_results('select * from '.$tablename.' where smsplug_author = '.$current_user->ID.' and DATE_FORMAT(smsplug_dateadded, "%Y%m") = DATE_FORMAT(now(), "%Y%m");', ARRAY_N);
	$iSMS = count($aResult);
	//$iSMS = get_option("smsblaster_smsamount") - $iSMS;
	return $iSMS;
}

function smsplug_control() {	
	
		/*
			NEU Speichern von ID, Passwort und Absender
			sobald username und passwort vorhanden: Rückgabe der Anzahl von SMS Creditts
			Widget nur Sichtbar für angemeldete User
			Anzahl SMS pro User
		*/
		
		if(isset($_POST['submitted'])) {

			update_option('smsplug_userid', $_POST['smsplug_userid']);
			update_option('smsplug_password', encrypt($_POST['smsplug_password']));
			update_option('smsplug_originator', $_POST['smsplug_originator']);
			update_option('smsplug_title', $_POST['smsplug_title']);
	
			if(isset($_POST['smsplug_registeredusers'])){
				update_option('smsplug_registeredusers', true);
				$checked = ' checked="checked"';
			}else{
				update_option('smsplug_registeredusers', false);
				$checked='';
			}
			update_option('smsplug_smsamount', $_POST['smsplug_smsamount']);
			
			echo '<div id="message" class="updated fade"><p>' . __('Options saved.','') . '</p></div>';
		}
		else {
			if(get_option('smsplug_registeredusers')) {
				$checked = ' checked="checked"';
				$div = 'block';
			} else {
				$checked = '';
				$div = 'none';
			}
		}
		
		?>
		<div class="wrap">
			<h2>SMSPlug</h2>
			<h3>SMS Credits: <?php echo smsplug_checkcredits();?></h3>
			<form method="post" action="">
				<?php wp_nonce_field('update-options'); ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e('Titel', 'smsblaster')?></th>
							<td><input type="text" name="smsplug_title" value="<?php echo get_option('smsplug_title'); ?>" /></td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><?php _e('BenutzerID', 'smsblaster')?></th>
							<td><input type="text" name="smsplug_userid" value="<?php echo get_option('smsplug_userid'); ?>" /></td>
						</tr>
			 
						<tr valign="top">
							<th scope="row"><?php _e('Passwort', 'smsblaster')?></th>
							<td><input type="password" name="smsplug_password" value="<?php echo decrypt(get_option('smsplug_password')); ?>" /></td>
						</tr>
			
						<tr valign="top">
							<th scope="row"><?php _e('Versender', 'smsblaster')?></th>
							<td><input type="text"  name="smsplug_originator" value="<?php echo get_option('smsplug_originator'); ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Nur Registrierte Benutzer', 'smsblaster')?></th>
							<td><input type="checkbox" <?php echo $checked; ?> id="smsplug_registeredusers" name="smsplug_registeredusers" onclick="if(document.getElementById('smsplug_registeredusers').checked) { document.getElementById('smsplug_smsamounttr').style.display='block'; } else { document.getElementById('smsplug_smsamounttr').style.display='none'; }" value="<?php echo get_option('smsplug_registeredusers'); ?>" /></td>
						</tr>
						
							<tr valign="top" id="smsplug_smsamounttr" style="display:<?php echo $div; ?>" >
								<th scope="row"><?php _e('Anzahl SMS Pro Monat', 'smsblaster')?></th>
									<td>
										
										<select name="smsplug_smsamount">
											<option value="5" <?php if(get_option('smsplug_smsamount') == 5) echo 'selected'; ?> >5</option>
											<option value="10"  <?php if(get_option('smsplug_smsamount') == 10) echo 'selected'; ?>>10</option>
											<option value="15"  <?php if(get_option('smsplug_smsamount') == 15) echo 'selected'; ?>>15</option>
											<option value="20"  <?php if(get_option('smsplug_smsamount') == 20) echo 'selected'; ?>>20</option>
											<option value="25"  <?php if(get_option('smsplug_smsamount') == 25) echo 'selected'; ?>>25</option>
											<option value="30"  <?php if(get_option('smsplug_smsamount') == 30) echo 'selected'; ?>>30</option>
										</select>
									
								</td>
							 </tr>
						
					</table>
					
					<input type="hidden" name="action" value="update" />
					<!--<input type="hidden" name="page_options" value="smsblaster_userid,smsblaster_password,smsblaster_originator,smsblaster_registeredusers,smsblaster_smsamount" />-->
				
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
					<input type="hidden" name="submitted" value="1" />
				</p>
			</form>
		</div>
<?php
		
		
	}

function SMSPlug_addmenuitems() {
	if (function_exists('add_management_page')) {
		add_management_page('SMSPlug', 'SMSPlug', 0, __FILE__, 'smsplug_control');
	}
	if (function_exists('add_submenu_page')) {
		add_submenu_page('options-general.php','SMSPlug', 'SMSPlug', 0, __FILE__, 'smsplug_control');
	}
}

function SMSPlug_init()
{
	global $table_prefix;
	define("SMSPLUG_TABLEPREFIX", $table_prefix);
	
	wp_enqueue_script('prototype');
	register_sidebar_widget(__('SMSPlug'), 'widget_SMSPlug');
    register_widget_control('SMSPlug', 'widget_SMSPlug', 'widget_SMSPlug', 'smsplug_control', array('width' => 300));
}

if (isset($_GET['activate']) && $_GET['activate'] == 'true') {
	add_action('init', 'SMSPlug_install');
} 

add_action("plugins_loaded", "SMSPlug_init");
add_action('admin_menu', 'SMSPlug_addmenuitems');


?>
