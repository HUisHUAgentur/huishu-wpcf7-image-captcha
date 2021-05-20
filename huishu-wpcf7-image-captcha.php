<?php
/*
Plugin Name: HUisHU WPCF7 Image Captcha
Description: Image Captcha for WPCF7
Version:     1.5.1
Author:      HUisHU. Digitale Kreativagentur OHG.
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Silence is golden; exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once(plugin_dir_path( __FILE__ ).'class-tgm-plugin-activation.php');

function hu_wpcf7_image_captcha_add_required_plugins_to_tgmpa(){
    $plugins = array(
        array(
            'name'      => 'Contact Form 7',
            'slug'      => 'contact-form-7',
        ),
        array(
            'name'      => 'HUisHU Essentials Plugins – Iconfont Helper',
            'slug'      => 'huishu-essentials-iconfont-helper',
            'source'    => 'https://github.com/HUisHUAgentur/huishu-essentials-iconfont-helper/archive/master.zip',
        ),
    );

    $config = array(
        'id'           => 'hu_wpcf7_image_captcha',   // Unique ID for hashing notices for multiple instances of TGMPA.
        'default_path' => '',                      // Default absolute path to bundled plugins.
        'menu'         => 'tgmpa-install-plugins', // Menu slug.
        'parent_slug'  => 'plugins.php',            // Parent menu slug.
        'capability'   => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
        'has_notices'  => true,                    // Show admin notices or not.
        'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
        'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
        'is_automatic' => false,                   // Automatically activate plugins after installation or not.
        'message'      => '',                      // Message to output right before the plugins table.
    );

    tgmpa( $plugins, $config );
}
add_action('tgmpa_register','hu_wpcf7_image_captcha_add_required_plugins_to_tgmpa');


/**
 * Use Plugin Update Checker to check for Updates on Github
 */
require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/HUisHUAgentur/huishu-wpcf7-image-captcha/',
	__FILE__,
	'huishu-essentials-wpcf7-image-captcha'
);


function get_cf7ic_without_cf7($name = 'kc_captcha',$id = 'kc_captcha',$class = 'wpcf7-form-control-wrap imagecaptcha'){
	$tag = new WPCF7_FormTag(array('name' => 'imagecaptcha*','options' => array('id' => 'cf7ic_captcha')));
	return call_cf7ic($tag);
}

/**
 * Add custom shortcode to Contact Form 7
 */
add_action( 'wpcf7_init', 'add_shortcode_cf7ic' );
function add_shortcode_cf7ic() {
    wpcf7_add_form_tag( 'cf7ic', 'call_cf7ic', array( 'name-attr' => true ) );
				wpcf7_add_form_tag( 'cf7ic*', 'call_cf7ic', array( 'name-attr' => true ) );
}

/**
 * cf7ic shortcode
 */
function call_cf7ic( $tag ) {  
    //$tag = new WPCF7_FormTag( $tag );
    wp_enqueue_style( 'huishu-wpcf7-image-captcha-style' ); // enqueue css
	if($csspath = hu_ep_ih_get_css_file_url()){
		wp_enqueue_style('hu-ep-ih-iconfont-style'); 
    }
	$glyphs = hu_ep_ih_get_all_icons();
	if ( empty( $tag->name ) ) {
        return '';
    }

    $validation_error = wpcf7_get_validation_error( $tag->name );

    $class = wpcf7_form_controls_class( $tag->type );

    if ( $validation_error ) {
        $class .= ' wpcf7-not-valid';
    }
    $atts = array();
    $atts['class'] = $tag->get_class_option( $class );
    $atts['id'] = $tag->get_id_option();

    if ( $tag->is_required() ) {
    	$atts['aria-required'] = 'true';
    }

    $atts['aria-invalid'] = $validation_error ? 'true' : 'false';

    $atts['name'] = $tag->name;

    $atts = wpcf7_format_atts( $atts );
	$noempty = array();
	foreach($glyphs as $glyphid => $glyphname){
		if(!empty($glyphname)){
			$noempty[$glyphid] = $glyphname;
		}
	}
	$glyphs = $noempty;
	$captchas = array_flip($glyphs);
    $choice = array_rand( $captchas, 4);
    foreach($choice as $key) {
        $choices[$key] = $captchas[$key];
    }
    // Pick a number between 0-2 and use it to determine which array item will be used as the answer
    $human = rand(0,3);
	$humankey = array_keys($choices)[$human];
    $output = '<span class="captcha-image" '.$style.'>';
	$i = -1;
	$output.='<span class="choices">';
	foreach($choices as $title => $image) {
		$i++;
		if($i == $human) { 
			$value = "kc_human"; 
		} else { 
			$value = "dancer"; 
		}
		$output .= '<label><input type="radio" name="kc_captcha" value="'. $value .'" /><i class="icon-'. $image .'"></i></label>';
    }
    if(function_exists('wpm')){
        $question = __('[:de]Bist du ein Mensch? Dann klicke bitte auf '.__($choice[$human]).'.[:en]Are you human? Then click the '.__($choice[$human]).'.[:]');
    } else {
        $question = __('Bist du ein Mensch? Dann klicke bitte auf '.__($choice[$human]).'.');
    }
    $output .= '
    </span></span>
    <span style="display:none">
        <input type="text" name="kc_honeypot">
    </span>';
	$output.= '<span class="cf7ic_instructions">';
	$output.=$question;
	$output.='</span>';
					
	$myCustomField = sprintf(
        '<span class="wpcf7-form-control-wrap %1$s"><span %2$s>%3$s</span>%4$s</span>',
        sanitize_html_class( $tag->name ),
        $atts,
        $output,
        $validation_error
    );					
    //return '<div class="wpcf7-form-control-wrap kc_captcha"><div class="wpcf7-form-control wpcf7-radio kc_captcha">'.$output.'</div></div>';
	return $myCustomField;
}

/**
 * Custom validator
 */
 add_filter('wpcf7_validate_cf7ic*','cf7ic_check_if_spam', 10, 2);
 add_filter('wpcf7_validate_cf7ic','cf7ic_check_if_spam', 10, 2);
function cf7ic_check_if_spam( $result, $tag ) {

    $kc_val1 = isset( $_POST['kc_captcha'] ) ? trim( $_POST['kc_captcha'] ) : '';   // Get selected icon value
    $kc_val2 = isset( $_POST['kc_honeypot'] ) ? trim( $_POST['kc_honeypot'] ) : ''; // Get honeypot value

    if(!empty($kc_val1) && $kc_val1 != 'kc_human' ) {
        //$tag->name = "kc_captcha";
                            //	var_dump('hallp');
        if(function_exists('wpm')){
            $result->invalidate( $tag, '[:de]Bitte wähle das korrekte Symbol aus.[:en]Please choose the correct symbol.' );
        } else {
            $result->invalidate( $tag, 'Bitte wähle das korrekte Symbol aus.' );
        }
    }
    if(empty($kc_val1) ) {
        //$tag->name = "kc_captcha";
        if(function_exists('wpm')){
            $result->invalidate( $tag, '[:de]Bitte wähle ein Symbol aus.[:en]Please choose a symbol.' );
        } else {
            $result->invalidate( $tag, 'Bitte wähle ein Symbol aus.' );
        }
        
    }
    if(!empty($kc_val2) ) {
        //$tag->name = "kc_captcha";
        
        if(function_exists('wpm')){
            $result->invalidate( $tag, __(wpcf7_get_message( 'spam' )) );
        } else {
            $result->invalidate( $tag, wpcf7_get_message( 'spam' ) );
        }
    }
    //var_dump($result);
				return $result;
}


// Add Contact Form Tag Generator Button
add_action( 'wpcf7_admin_init', 'cf7ic_add_tag_generator', 55 );

function cf7ic_add_tag_generator() {
	$tag_generator = WPCF7_TagGenerator::get_instance();
	$tag_generator->add( 'cf7ic', __( 'Image Captcha', 'contact-form-7-image-captcha' ),
		'cf7ic_tag_generator' );
}

function cf7ic_tag_generator( $contact_form, $args = '' ) {
	$args = wp_parse_args( $args, array() ); ?>
		<div class="control-box">
		<table class="form-table">
			<tbody>
				<tr>
				<th scope="row"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></th>
				<td>
					<fieldset>
					<legend class="screen-reader-text"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></legend>
					<label><input type="checkbox" name="required" /> <?php echo esc_html( __( 'Required field', 'contact-form-7' ) ); ?></label>
					</fieldset>
				</td>
				</tr>
				<tr>
				<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?></label></th>
				<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="insert-box">
		<input type="text" name="cf7ic" class="tag code" readonly="readonly" onfocus="this.select()" />
		<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
		</div>
	</div>
<?php
}

add_action('init','huishu_wpcf7_image_captcha_register_scripts');

function huishu_wpcf7_image_captcha_register_scripts(){
	wp_register_style('huishu-wpcf7-image-captcha-style',plugins_url('huishu-wpcf7-image-captcha.css', __FILE__),array(),filemtime(plugin_dir_path(__FILE__).'huishu-wpcf7-image-captcha.css'));
}