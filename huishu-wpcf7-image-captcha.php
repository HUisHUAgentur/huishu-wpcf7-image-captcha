<?php
/*
Plugin Name: HUisHU WPCF7 Image Captcha
Description: Image Captcha for WPCF7
Version:     2.2.5
Author:      HUisHU. Digitale Kreativagentur GmbH
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: hu-wpcf7-image-captcha
Domain Path: /languages
*/

/**
 * Silence is golden; exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once(plugin_dir_path( __FILE__ ).'class-tgm-plugin-activation.php');

function hu_wic_load_textdomain() {
	load_plugin_textdomain( 'hu-wpcf7-image-captcha', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}
add_action( 'init', 'hu_wic_load_textdomain' );

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
$myUpdateChecker->setBranch('ver2.0');

/**
 * Add Label Field to Essential Iconfont Helper
 */
function hu_cf7ic_add_label_field_to_iconfont_helper($fields, $icon, $name){$langs = array();
    $langs = array();
    // Check if WP Multilang is active
    if(function_exists('wpm_get_languages')){
        $languages = wpm_get_languages();
        if( is_array( $languages ) ){
            foreach( $languages as $code => $language ){
                $langs[$code] = $language;
            }
        } 
    } else {
        // Check if WPML is active and if languages are set, if so, add a field for each language
        $languages = apply_filters('wpml_active_languages', NULL);
        if (!empty($languages)) {
            foreach ($languages as $lang) {
                $langs[$lang['language_code']] = $lang['native_name'];
            }
        }
    }
    if( ! empty( $langs ) ){
        foreach( $langs as $code => $name ){
            $fields['imagecaptcha_' . $code] = 'Captcha-Beschriftung für Icon ' . $icon . ' (<i class="icon-' . $icon . '"></i>)' . ' (' . $name . ')';
        }
    } else {
        $fields['imagecaptcha'] = 'Captcha-Beschriftung für Icon ' . $icon . ' (<i class="icon-' . $icon . '"></i>)';
    }   
    return $fields;
}
add_filter( 'hu_ep_ih_icon_fields_to_save', 'hu_cf7ic_add_label_field_to_iconfont_helper', 10, 3 );

function get_cf7ic_without_cf7( $name = 'kc_captcha', $id = 'kc_captcha', $class = 'wpcf7-form-control-wrap imagecaptcha' ){
	$tag = new WPCF7_FormTag(
        array(
            'name' => $name, 
            'type' => 'cf7ic*', 
            'options' => array(
                'id' => 'cf7ic_captcha'
            )
        )
    );
	return call_cf7ic($tag);
}

function validate_cf7ic_without_cf7( $name = 'kc_captcha' ){
    $kc_val1 = isset( $_POST[$name] ) ? sanitize_text_field( $_POST[$name] ) : '';   // Get selected icon value
    $kc_val2 = isset( $_POST[cf7ic_get_honeypot_field_name()] ) ? sanitize_text_field( $_POST[cf7ic_get_honeypot_field_name()] ) : ''; // Get honeypot value
    if( empty( $kc_val1 ) ) {
        return false;    
    }
    if( ! ( $kc_val1 == cf7ic_get_human_field_value() ) ) {
        return false;
    }
    if( ! empty( $kc_val2 ) ) {
        return false;
    }
	return true;
}

/**
 * Add custom shortcode to Contact Form 7
 */
add_action( 'wpcf7_init', 'add_shortcode_cf7ic' );
function add_shortcode_cf7ic() {
    wpcf7_add_form_tag( 
        'cf7ic', 
        'call_cf7ic', 
        array( 
            'name-attr' => true,
			'not-for-mail' => true,
        ) 
    );
	wpcf7_add_form_tag( 
        'cf7ic*', 
        'call_cf7ic', 
        array( 
            'name-attr' => true, 
			'not-for-mail' => true,
        ) 
    );
}

/**
 * cf7ic shortcode
 */
function call_cf7ic( $tag ) {  
    //$tag = new WPCF7_FormTag( $tag );
    wp_enqueue_style( 'huishu-wpcf7-image-captcha-style' ); // enqueue css
	if( $csspath = hu_ep_ih_get_css_file_url() ){
		wp_enqueue_style( 'hu-ep-ih-iconfont-style' ); 
    }
    $glyphs = '';
    $lang = NULL;
    if(function_exists('wpm_get_language')){
        $lang = wpm_get_language();
    } else {
        $lang = apply_filters('wpml_current_language', NULL);
    }
    // If languages are set, use the language code to get the correct icon set
    if (!empty($lang)) {
        $glyphs = hu_ep_ih_get_all_icons('imagecaptcha_' . $lang);
    } else {
        $glyphs = hu_ep_ih_get_all_icons('imagecaptcha');
    }
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

    if ( $validation_error ) {
        $atts['aria-invalid'] = 'true';
		$atts['aria-describedby'] = wpcf7_get_validation_error_reference(
			$tag->name
		);
	} else {
		$atts['aria-invalid'] = 'false';
	}

    $tabindex = $tag->get_option( 'tabindex', 'signed_int', true );

	if ( false !== $tabindex ) {
		$tabindex = (int) $tabindex;
	}

    $atts['name'] = $tag->name;

	$noempty = array();
	foreach( $glyphs as $glyphid => $glyphname ){
		if( ! empty( $glyphname ) ){
			$noempty[$glyphid] = $glyphname;
		}
	}
	$glyphs = $noempty;
	$captchas = array_flip( $glyphs );
    $choice = array_rand( $captchas, 4);
    foreach( $choice as $key ) {
        $choices[$key] = $captchas[$key];
    }
    // Pick a number between 0-2 and use it to determine which array item will be used as the answer
    $human = rand( 0, 3 );
	$humankey = array_keys( $choices )[$human];
    $output = '<span class="captcha-image">';
	$i = -1;
	$output.= '<span class="choices">';
	foreach( $choices as $title => $image ){
		$i++;
		if( $i == $human ){ 
			$value = cf7ic_get_human_field_value(); 
		} else { 
			$value = cf7ic_get_false_field_value($i); 
		}
        $item_atts = array(
            'type' => 'radio',
            'name' => $tag->name,
            'value' => $value,
            'checked' => false,
            'tabindex' => $tabindex,
            'aria-label' => 'Icon ' . $choice[$i]
        );
        $item_atts = wpcf7_format_atts( $item_atts );
        $output .= '<label>';
        $output.= sprintf( 
            '<input %1$s />',
            $item_atts
        );
        $output.= '<i aria-hidden="true" class="icon-'. $image .'"></i>';
        $output.= '</label>';
        if ( false !== $tabindex and 0 < $tabindex ) {
			$tabindex += 1;
		}
    }
    if(function_exists('wpm')){
        $question = __('[:de]Bist du ein Mensch? Dann klicke bitte auf '.__($choice[$human]).'.[:en]Are you human? Then please click '.__($choice[$human]).'.[:]');
    } else {
        $question = __('Bist du ein Mensch? Dann klicke bitte auf '.__($choice[$human]).'.');
    }
    $output .= '
    </span></span>
    <span style="display:none">
        <input type="text" aria-label="Bitte nicht ausfüllen" name="'.cf7ic_get_honeypot_field_name().'">
    </span>';
	$output.= '<span class="cf7ic_instructions">';
	$output.= $question;
	$output.='</span>';
					
	$myCustomField = sprintf(
        '<span class="wpcf7-form-control-wrap" data-name="%1$s"><span %2$s>%3$s</span>%4$s</span>',
        sanitize_html_class( $tag->name ),
        wpcf7_format_atts( $atts ),
        $output,
        $validation_error
    );					
    return $myCustomField;
}

function cf7ic_get_honeypot_field_name(){
    return base64_encode('kc_honeypot');
}

function cf7ic_get_human_field_value(){
    return base64_encode('kc_human');
}

function cf7ic_get_false_field_value( $num ){
    return base64_encode('kc_dancer' . $num);
}

function cf7ic_captcha_ajax_refill( $items ){
    if ( ! is_array( $items ) ) {
		return $items;
	}

	$tags = wpcf7_scan_form_tags( array( 'type' => 'cf7ic' ) );

	if ( empty( $tags ) ) {
		return $items;
	}

	$refill = array();

	foreach ( $tags as $tag ) {
		$name = $tag->name;
		$options = $tag->options;

		if ( empty( $name ) ) {
			continue;
		}

		$op = wpcf7_captchac_options( $options );

		if ( $filename = wpcf7_generate_captcha( $op ) ) {
			$captcha_url = wpcf7_captcha_url( $filename );
			$refill[$name] = $captcha_url;
		}
	}

	if ( ! empty( $refill ) ) {
		$items['captcha'] = $refill;
	}

	return $items;
}
//add_filter( 'wpcf7_refill_response', 'cf7ic_ajax_refill', 10, 1 );
//add_filter( 'wpcf7_feedback_response', 'cf7ic_captcha_ajax_refill', 10, 1 );

/**
 * Custom validator
 */
function cf7ic_check_if_spam( $result, $tag ) {
    $name = $tag->name;
    $kc_val1 = isset( $_POST[$name] ) ? trim( $_POST[$name] ) : '';   // Get selected icon value
    $honeypot_name = cf7ic_get_honeypot_field_name();
    $kc_val2 = isset( $_POST[$honeypot_name] ) ? trim( $_POST[$honeypot_name] ) : ''; // Get honeypot value

    if( ! empty( $kc_val1 ) && ( $kc_val1 != cf7ic_get_human_field_value() ) ) {
        if( function_exists( 'wpm' ) ){
            $result->invalidate( $tag, __('[:de]Bitte wähle das korrekte Symbol aus.[:en]Please choose the correct symbol.') );
        } else {
            $result->invalidate( $tag, 'Bitte wähle das korrekte Symbol aus.' );
        }
    }

    if( empty( $kc_val1 ) ){
        if( function_exists( 'wpm' ) ){
            $result->invalidate( $tag, __( '[:de]Bitte wähle ein Symbol aus.[:en]Please choose a symbol.' ) );
        } else {
            $result->invalidate( $tag, 'Bitte wähle ein Symbol aus.' );
        }
    }

    if( ! empty( $kc_val2 ) ) {
        if( function_exists( 'wpm' ) ){
            $result->invalidate( $tag, __(wpcf7_get_message( 'spam' )) );
        } else {
            $result->invalidate( $tag, wpcf7_get_message( 'spam' ) );
        }
    }
	return $result;
}
add_filter( 'wpcf7_validate_cf7ic*', 'cf7ic_check_if_spam', 10, 2 );
add_filter( 'wpcf7_validate_cf7ic', 'cf7ic_check_if_spam', 10, 2 );


// Add Contact Form Tag Generator Button
add_action( 'wpcf7_admin_init', 'cf7ic_add_tag_generator', 55 );

function cf7ic_add_tag_generator() {
	$tag_generator = WPCF7_TagGenerator::get_instance();
	$tag_generator->add( 
        'cf7ic', 
    __( 'Image Captcha', 'contact-form-7-image-captcha' ),
		'cf7ic_tag_generator',
        array( 'version' => '2' ) 
    );
}

function cf7ic_tag_generator( $contact_form, $options ) {
    $field_types = array(
		'cf7ic' => array(
			'display_name' => __( 'HU Image Captcha', 'contact-form-7' ),
			'heading' => __( 'HU Image Captcha', 'contact-form-7' ),
			'description' => __( 'Generates a form-tag for an Image Captcha field.', 'contact-form-7' ),
		),
	);

	$tgg = new WPCF7_TagGeneratorGenerator( $options['content'] );
	?>
    <header class="description-box">
		<h3><?php
			echo esc_html( $field_types['cf7ic']['heading'] );
		?></h3>

		<p><?php
			$description = wp_kses(
				$field_types['cf7ic']['description'],
				array(
					'a' => array( 'href' => true ),
					'strong' => array(),
				),
				array( 'http', 'https' )
			);

			echo $description;
		?></p>
	</header>

	<div class="control-box">
		<?php
			$tgg->print( 'field_type', array(
				'with_required' => true,
				'select_options' => array(
					'cf7ic' => $field_types['cf7ic']['display_name'],
				),
			) );

			$tgg->print( 'field_name' );			
		?>
	</div>

	<footer class="insert-box">
		<?php
			$tgg->print( 'insert_box_content' );

			$tgg->print( 'mail_tag_tip' );
		?>
	</footer>
    <?php
}

function huishu_wpcf7_image_captcha_register_scripts(){
	wp_register_style( 'huishu-wpcf7-image-captcha-style', plugins_url( 'huishu-wpcf7-image-captcha.css', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . 'huishu-wpcf7-image-captcha.css' ) );
}
add_action( 'init', 'huishu_wpcf7_image_captcha_register_scripts' );