<?php
/*
Plugin Name: Callback Widget – Bazz CallBack
Plugin URI: http://viktor-web.ru
Text Domain: bazz-callback-widget
Domain Path: /languages
Description: This plugin makes a simple widget for callback on your website.
Author: Viktor Ievlev
Version: 4.0
Author URI: https://viktor-web.ru
License: GPLv2
*/
/*
    Copyright 2016  Viktor Ievlev  (email: bazz@bk.ru)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//current version constant
define( 'BAZZ_WIDGET_VERSION', '4.0' );

define('BAZZ_LEAD_CENTER_API_URL', 'https://bazz-callback-bot.twc1.net/?action=forward');
define('BAZZ_LEAD_CENTER_TG_BOT', 'bazzCallBackBot');

//activation hook
register_activation_hook( __FILE__, 'bazz_install' );
function bazz_install() {
	if ( ! get_option( 'bazz_options' ) ) {
		$bazz_options_arr = array(
			'email'           => 'example@mail.com',
			'work_time_start' => '9',
			'work_time_end'   => '21',
			'time'            => '48',
			'day_text'        => __( 'The loading is extremely high, we will call you back in the near future. Thanks!', 'bazz-callback-widget' ),
			'night_text'      => __( 'Unfortunately, we don\'t work for now. We will call you back tomorrow! Thanks!', 'bazz-callback-widget' ),
			'bottom'          => '68'
		);
		update_option( 'bazz_options', $bazz_options_arr );
	}
}

//deactivation hook
register_deactivation_hook( __FILE__, 'bazz_deactivate' );
function bazz_deactivate() {

}

//uninstall hook
register_uninstall_hook( __FILE__, 'bazz_uninstall' );
function bazz_uninstall() {
	delete_option( 'bazz_options' );
}

//Add new options
function bazz_new_option( $option_name, $option_value ) {
	$bazz_options_array = get_option( 'bazz_options' );
	if ( ! array_key_exists( $option_name, $bazz_options_array ) ) {
		$bazz_options_array[ $option_name ] = $option_value;
		update_option( 'bazz_options', $bazz_options_array );
	}

}

add_action( 'init', 'bazz_widget_add_new_options' );
function bazz_widget_add_new_options() {
	/* Add here new options */

    //Added in 3.26
    bazz_new_option('api_key', '');

	//Added in 2.2
	bazz_new_option( 'in_russia', '1' );

	//Added in 3.0
	bazz_new_option( 'color_scheme', '#00AFF2' );
	bazz_new_option( 'left_right', 'right' );

}

//localize
function localize_load() {
	load_plugin_textdomain( 'bazz-callback-widget', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'localize_load' );

//load styles and scripts
add_action( 'init', 'bazz_widget_styles' );
function bazz_widget_styles() {
	if ( ! is_admin() ) {
		wp_enqueue_style( 'bazz_widget_style', plugins_url( 'css/bazz-widget.css', __FILE__ ), array(), BAZZ_WIDGET_VERSION, 'all' );
	} else {
		wp_enqueue_style( 'bazz_widget_admin_style', plugins_url( 'css/bazz-widget-admin.css', __FILE__ ), array(), BAZZ_WIDGET_VERSION, 'all' );
	}
}

add_action( 'wp_footer', 'bazz_widget_scripts' );
function bazz_widget_scripts() {
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'bazz_maskedinput', plugins_url( 'js/jquery.maskedinput.min.js', __FILE__ ), 'jquery', null, true );
	wp_enqueue_script( 'bazz_draggable', plugins_url( 'js/jquery.draggable.min.js', __FILE__ ), 'jquery', null, true );
	wp_enqueue_script( 'bazz_widget_script', plugins_url( 'js/bazz-widget.js', __FILE__ ), 'jquery', null, true );
	wp_localize_script( 'bazz_widget_script', 'bazz_ajax',
		array(
			'url' => admin_url( 'admin-ajax.php' )
		)
	);
	$bazz_options = get_option( 'bazz_options' );
	$locale = get_locale();
	if( 'ru_RU' === $locale ) {
		$current_lang = 'RU';
	} else {
		$current_lang = 'EN';
	}
	wp_localize_script( 'bazz_widget_script', 'bazz_options',
		array(
			'currentLang'       => $current_lang,
			'bazz_in_russia'    => $bazz_options['in_russia'],
			'bazz_color_scheme' => $bazz_options['color_scheme']
		)
	);
}

function bazz_widget_admin_scripts() {
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'bazz_slider', plugins_url( 'js/jquery.ui-slider.js', __FILE__ ), 'jquery' );
	wp_enqueue_script( 'bazz_slider_rtl', plugins_url( 'js/jquery.ui.slider-rtl.min.js', __FILE__ ), 'jquery' );
}

//AJAX
add_action( 'wp_ajax_bazz_widget_action', 'bazz_widget_send' );
add_action( 'wp_ajax_nopriv_bazz_widget_action', 'bazz_widget_send' );
function bazz_widget_send() {

    // Protection
    if( empty( $_POST ) || ! wp_verify_nonce( $_POST['nonce'], 'bazz_widget_nonce') || $_POST['check'] !== '' ) {
        wp_send_json_error( '<div style="color: #FFFFFF; font-size: 18px; line-height: 1.2; padding-top: 13px;">Forbidden!</div>' );
    }

    $phone            = isset( $_POST['phone'] )    ? $_POST['phone']    : '';
    $name             = ! empty( $_POST['name'] )   ? $_POST['name']     : __( "The client wasn't introduced", 'bazz-callback-widget' );
    $callback_page    = isset( $_POST['refferer'] ) ? $_POST['refferer'] : '';
	$blog_url         = get_home_url();
	$admin_email      = get_option( 'admin_email' );
	$bazz_options_arr = get_option( 'bazz_options' );
	$email            = $bazz_options_arr['email'];
	$current_time     = current_time( 'G' );
	$work_time_start  = $bazz_options_arr['work_time_start'];
	$work_time_end    = $bazz_options_arr['work_time_end'];
	$text             = $bazz_options_arr['day_text'];
	if ( $work_time_start > $current_time || $current_time >= $work_time_end ) {
		$text = $bazz_options_arr['night_text'];
	}
	$to      = $email;
	$headers = "Content-type: text/plain; charset=utf-8\r\n";
	$subject = "$blog_url " . __( '[FROM BAZZ WIDGET]', 'bazz-callback-widget' );
	$message = __( 'Phone', 'bazz-callback-widget' ) . " - $phone\n" .
	           __( 'Name', 'bazz-callback-widget' ) . " - $name\n" .
               __( 'From page', 'bazz-callback-widget' ) . " - " . $blog_url . $callback_page;
    do_action('bazz_pre_send_email', $phone, $name, $blog_url . $callback_page);
	$send    = wp_mail( $to, $subject, $message, $headers );
	if ( $send ) {
		wp_send_json_success( '<div style="color: #FFFFFF; font-size: 18px; line-height: 1.2; padding-top: 13px;">' . $text . '</div>' );
	} else {
		wp_send_json_error( '<div style="color: #FFFFFF; font-size: 18px; line-height: 1.2; padding-top: 13px;">Email sending error.</div>' );
	}
}
//Change the name in the header FROM
add_filter( 'wp_mail_from_name', 'bazz_wp_mail_from_name' );
function bazz_wp_mail_from_name( $email_from ){
	return get_option( 'blogname' );
}

//HTML layout
add_action( 'wp_footer', 'bazz_layout' );
function bazz_layout() { ?>
	<?php $bazz_options_arr = get_option( 'bazz_options' ); ?>
	<?php if ( 'left' == $bazz_options_arr['left_right'] ) {
		$other_side = 'right';
	} else {
		$other_side = 'left';
	} ?>
    <style>
        .bazz-widget {
            bottom: <?php echo($bazz_options_arr['bottom']); ?>px;
			<?php echo($bazz_options_arr['left_right']); ?>: 75px;
			<?php echo $other_side; ?>: auto !important;
        }

        .bazz-widget, .bazz-widget-close, .bazz-widget-form-submit, .bazz-widget-button, .bazz-widget-name-close, .bazz-widget-inner-circle {
            background-color: <?php echo($bazz_options_arr['color_scheme']); ?>;
        }

        .bazz-widget-inner-border, .bazz-widget-form-submit, .bazz-widget-name-close {
            border-color: <?php echo($bazz_options_arr['color_scheme']); ?>;
        }
        .bazz-widget-form-top .countdown {
            color: <?php echo($bazz_options_arr['color_scheme']); ?>;
        }
		<?php  if ( is_rtl() ) : ?>
		.bazz-widget-form-top,
		.bazz-widget-form-bottom {
			direction: RTL;
		}
		.bazz-widget-close {
			left: auto;
			right: -10px;
		}
		.bazz-widget-form label,
		.bazz-widget-form input {
			text-align: right;
		}
        <?php endif; ?>

        @media only screen and (max-width: 575px) {
            .bazz-widget {
                bottom: -10px;
            <?php echo( $bazz_options_arr['left_right'] ); ?>: -10px;
            <?php echo $other_side; ?>: auto !important;
            }

            .bazz-widget.opened {
                bottom: 0;
            <?php echo( $bazz_options_arr['left_right'] ); ?>: 0;
            <?php echo $other_side; ?>: auto !important;
            }
        }
    </style>
    <div class="bazz-widget">
        <div class="bazz-widget-button">
            <i></i>
            <i><span><?php _e( 'CALL ME', 'bazz-callback-widget' ); ?></span></i>
        </div>
        <div class="bazz-widget-close">+</div>
        <div class="bazz-widget-form">
            <div class="bazz-widget-form-top">
                <label>

                    <?php $you_hyperlink =  '<a href="javascript:void(0);" class="bazz-widget-your-name">' . esc_html__( 'you', 'bazz-callback-widget' ) . '</a>'; ?>

					<?php if ( $bazz_options_arr['time'] == 0 ) { ?>

						<?php echo( sprintf( esc_html__( 'We will call %s back in the near future!', 'bazz-callback-widget' ), $you_hyperlink ) ); ?>

					<?php } else { ?>

						<?php
							if ( (int) $bazz_options_arr['time'] < 10 ) {
								$time_option = '0' . $bazz_options_arr['time'];
							} else {
								$time_option = $bazz_options_arr['time'];
							}
						?>

						<?php $time_option = '00:<span class="bazz_time">' . $time_option . '</span>'; ?>
						<?php echo( sprintf( esc_html__( 'We will call %s back in %s seconds!', 'bazz-callback-widget' ), $you_hyperlink, $time_option ) ); ?>

					<?php } ?>

                </label>
                <input type="text" value="" name="bazz-widget-check" id="bazz-widget-check" hidden/>
                <?php wp_nonce_field( 'bazz_widget_nonce','bazz-widget-nonce', true ); ?>
                <input id="bazz-widget-phone" name="bazz-widget-phone" value="" type="tel"
                       placeholder="<?php _e( 'Phone here', 'bazz-callback-widget' ); ?>"/>
                <a href="javascript:void(0);"
                   class="bazz-widget-form-submit"><?php _e( 'Call me!', 'bazz-callback-widget' ); ?></a>
                <div class="bazz-widget-form-info"></div>
            </div>
            <div class="bazz-widget-form-bottom">
                <label><?php _e( "Introduce yourself, and we'll call you by name", 'bazz-callback-widget' ); ?></label>
                <input id="bazz-widget-name" class="grey-placeholder" name="bazz-widget-name" value="" type="text"
                       placeholder="<?php _e( 'name here', 'bazz-callback-widget' ); ?>"/>
                <a href="javascript:void(0);" class="bazz-widget-name-close"></a>
            </div>
        </div>
        <div class="bazz-widget-inner-circle"></div>
        <div class="bazz-widget-inner-border"></div>
    </div>
<?php }

//menu
$plugin_file = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin_file", 'plugin_settings_link' );
function plugin_settings_link( $links ) {
	$settings_links = array(
		'<a href="options-general.php?page=bazz_menu">' . __( 'Settings', 'bazz-callback-widget' ) . '</a>'
	);

	foreach( $settings_links as $settings_link ) {
		array_unshift( $links, $settings_link );
	}

	return $links;
}

add_action( 'admin_menu', 'bazz_widget_menu' );
function bazz_widget_menu() {
    $page = add_options_page( 'Bazz CallBack Widget settins page', 'Bazz CallBack Widget settings', 'manage_options', 'bazz_menu', 'bazz_menu_page' );
    add_action( 'admin_init', 'bazz_register_settings' );
    add_action( 'load-' . $page, 'bazz_widget_admin_scripts' );
}

function bazz_register_settings() {
	register_setting( 'bazz_settings_group', 'bazz_options', 'bazz_sanitize_options' );
}

function bazz_sanitize_options( $input ) {
	$input['email']           = sanitize_email( $input['email'] );
	$input['work_time_start'] = sanitize_text_field( $input['work_time_start'] );
	$input['work_time_end']   = sanitize_text_field( $input['work_time_end'] );
	$input['time']            = sanitize_text_field( $input['time'] );
	$input['day_text']        = sanitize_text_field( $input['day_text'] );
	$input['night_text']      = sanitize_text_field( $input['night_text'] );
	$input['bottom']          = sanitize_text_field( $input['bottom'] );
	$input['in_russia']       = sanitize_text_field( $input['in_russia'] );
	$input['color_scheme']    = sanitize_text_field( $input['color_scheme'] );

	return $input;
}

function bazz_menu_page() { ?>
    <h2><?php _e( 'Hi there! This is settings of the', 'bazz-callback-widget' ); ?> Bazz CallBack Widget</h2>
    <p><?php _e( 'Change the parameters to the your discretion.', 'bazz-callback-widget' ); ?><br></p>
    <form method="post" action="options.php" id="bazz-settings-form">
		<?php settings_fields( 'bazz_settings_group' ); ?>
		<?php $bazz_options = get_option( 'bazz_options' ); ?>
        <div class="option-color-scheme">
            <label for=""><?php _e( 'Color scheme:', 'bazz-callback-widget' ); ?><input type="color"
                                                                                        name="bazz_options[color_scheme]"
                                                                                        value="<?php echo esc_attr( $bazz_options['color_scheme'] ); ?>"/></label>
        </div>
        <div class="option-email">
            <label for=""><?php _e( 'To send the message to:', 'bazz-callback-widget' ); ?><input type="email"
                                                                                                  name="bazz_options[email]"
                                                                                                  value="<?php echo esc_attr( $bazz_options['email'] ); ?>"/></label>
        </div>
        <div class="option-telegram">
            <label for="bazz_options[send_telegram]">
                <?php
                _e( 'To send the message to Telegram messenger', 'bazz-callback-widget' ); ?>
                <input type="checkbox" name="bazz_options[send_telegram]" id="bazz_options[send_telegram]" value="1" <?php if ( isset( $bazz_options['send_telegram'] ) && $bazz_options['send_telegram'] == 1 ) {
                    echo( 'checked' );
                } ?>
            </label>
            <i>This function is in beta version</i>
            <div class="telegram_details">
                <label for=""><?php _e( 'API key:', 'bazz-callback-widget' ); ?>
                    <input type="text" name="bazz_options[api_key]" value="<?php echo esc_attr( $bazz_options['api_key'] ?? '' ); ?>"/>
                </label>
            <?php if (empty($bazz_options['api_key'])) { ?>
                <a href="<?php echo bazz_get_connect_url(); ?>" target="_blank"><?php _e('Connect Telegram', 'bazz-callback-widget'); ?></a>
            <?php }?>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                var $checkbox = $('input[name="bazz_options[send_telegram]"]');
                var $details = $('.telegram_details');

                // Функция которая показывает/скрывает в зависимости от состояния
                function toggleDetails() {
                    $details.toggle($checkbox.is(':checked'));
                }

                toggleDetails(); // применяем текущее состояние
                $checkbox.on('change', toggleDetails);
            });
        </script>
        <div class="option-work-time">
            <input type="text" id="work-time-start" name="bazz_options[work_time_start]"
                   value="<?php echo esc_attr( $bazz_options['work_time_start'] ); ?>"/>
            <input type="text" id="work-time-end" name="bazz_options[work_time_end]"
                   value="<?php echo esc_attr( $bazz_options['work_time_end'] ); ?>"/>
            <label><?php _e( 'Specify working time:', 'bazz-callback-widget' ); ?></label>
            <div></div>

            <script>
                jQuery(document).ready(function () {
					var isRTL = false;
					if ( jQuery('html').attr('dir') == 'rtl' ) {
						isRTL = true;
					}
                    var min_value = parseInt(jQuery("#work-time-start").val());
                    var max_value = parseInt(jQuery("#work-time-end").val());
                    jQuery(".option-work-time > div").slider({
                        isRTL: isRTL,
						min: 0,
                        max: 24,
                        values: [min_value, max_value],
                        range: true,
                        slide: function (event, ui) {
                            jQuery(".option-work-time label > strong:eq(0)").text(ui.values[0]);
                            jQuery(".option-work-time label > strong:eq(1)").text(ui.values[1]);
                            jQuery("#work-time-start").val(ui.values[0]);
                            jQuery("#work-time-end").val(ui.values[1]);
                        }
                    });
                });
            </script>

            <label>
				<?php $from_time = '<strong>' . esc_attr( $bazz_options['work_time_start'] ) . '</strong>'; ?>
				<?php $to_time   = '<strong>' . esc_attr( $bazz_options['work_time_end'] ) . '</strong>'; ?>
				<?php echo( sprintf( esc_html__( 'The working day with %s h to %s h', 'bazz-callback-widget' ), $from_time, $to_time ) ); ?>
			</label>
			<br>
            <em>
				<?php _e( '*The time zone is using WordPress settings.', 'bazz-callback-widget' ); ?>
				<?php echo ( sprintf( esc_html__( 'For now is %s', 'bazz-callback-widget' ), current_time( 'H:i', 0 ) ) ); ?>
			</em>
        </div>
        <div class="option-timer">
            <label for=""><?php _e( 'The countdown will be started on', 'bazz-callback-widget' ); ?> <input type="text"
                                                                                                            name="bazz_options[time]"
                                                                                                            value="<?php echo esc_attr( $bazz_options['time'] ); ?>"/> <?php _e( 'seconds.', 'bazz-callback-widget' ); ?>
            </label>
            <em><?php _e( 'Set 0 and countdown functionality will be disabled', 'bazz-callback-widget' ); ?></em>
        </div>
        <div class="option-text3">
            <label for=""><?php _e( 'The text after sending in working hours (afternoon):', 'bazz-callback-widget' ); ?></label>
            <textarea name="bazz_options[day_text]"><?php echo( $bazz_options['day_text'] ); ?></textarea>
        </div>
        <div class="option-text4">
            <label for=""><?php _e( 'The text after sending in NOT working hours (night):', 'bazz-callback-widget' ); ?></label>
            <textarea name="bazz_options[night_text]"><?php echo( $bazz_options['night_text'] ); ?></textarea>
        </div>
		<?php if ( get_locale() == 'ru_RU' ) :?>
        <div class="option-in-russia">
            <label for=""><?php _e( 'Your clients are from Russia?', 'bazz-callback-widget' ); ?></label>
            <select name="bazz_options[in_russia]" id="">
                <option value="1" <?php if ( $bazz_options['in_russia'] == 1 ) {
					echo( 'selected' );
				} ?>><?php _e( 'Yes', 'bazz-callback-widget' ); ?></option>
                <option value="0" <?php if ( $bazz_options['in_russia'] == 0 ) {
					echo( 'selected' );
				} ?>><?php _e( 'No', 'bazz-callback-widget' ); ?></option>
            </select>
        </div>
		<?php else : ?>
            <input type="hidden" name="bazz_options[in_russia]" value="0" />
		<?php endif; ?>
        <div class="option-bottom">
            <label for=""><?php _e( 'Distance from the window bottom', 'bazz-callback-widget' ); ?>
                <input type="text" name="bazz_options[bottom]"
                       value="<?php echo esc_attr( $bazz_options['bottom'] ); ?>"/> px.</label>
        </div>
        <div class="option-left-right">
            <label for=""><?php _e( 'Show on the left/right side', 'bazz-callback-widget' ); ?>
                <select name="bazz_options[left_right]" id="">
                    <option value="left" <?php if ( $bazz_options['left_right'] == 'left' ) {
						echo( 'selected' );
					} ?>><?php _e( 'Left', 'bazz-callback-widget' ); ?></option>
                    <option value="right" <?php if ( $bazz_options['left_right'] == 'right' ) {
						echo( 'selected' );
					} ?>><?php _e( 'Right', 'bazz-callback-widget' ); ?></option>
                </select>
        </div>
        <div>
            <input type="submit" value="<?php _e( 'Save', 'bazz-callback-widget' ); ?>"/>
        </div>
    </form>
<?php }

// Call API site verification during api-key saving
add_action('update_option_bazz_options', 'bazz_update_options', 10, 2);
function bazz_update_options($old_value, $new_value) {
    if (
            isset($old_value['api_key'], $new_value['api_key']) &&
            empty($old_value['api_key']) &&
            ! empty($new_value['api_key'])
    ) {
        $site_id = bazz_generate_site_id();
        $api_key = $new_value['api_key'];
        $api_url = apply_filters('bazz_lead_center_api_url', BAZZ_LEAD_CENTER_API_URL);
        $request_data = [
                'timeout' => 10,
                'headers' => [
                        'Origin' => home_url(),
                        'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                        'api_key' => $api_key,
                        'site_id' => $site_id,
                        'site_url' => get_site_url(),
                        'site_name' => get_bloginfo('name'),
                        'verify' => 1
                ]),
                'sslverify' => (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development') ? false : true
        ];
        $response = wp_remote_post($api_url, $request_data);

        $body = json_decode(wp_remote_retrieve_body($response), true);
    }
}

// В functions.php или в основном файле плагина
add_action('bazz_pre_send_email', 'bazz_send_telegram_lead');

function bazz_send_telegram_lead() {
    $bazz_options = get_option( 'bazz_options' );
    $send_telegram = $bazz_options['send_telegram'] ?? '';
    $api_key = $bazz_options['api_key'] ?? '';
    $site_id = bazz_generate_site_id();
    $api_url = apply_filters('bazz_lead_center_api_url', BAZZ_LEAD_CENTER_API_URL);

    if ( $send_telegram && $api_key ) {
        $request_data = [
            'timeout' => 10,
            'headers' => [
                    'Origin' => home_url(),
                    'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                    'api_key' => $api_key,
                    'site_id' => $site_id,
                    'lead' => [
                            'name' => sanitize_text_field($_POST['name']),
                            'phone' => sanitize_text_field($_POST['phone']),
                    ]
            ]),
            'sslverify' => (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development') ? false : true
        ];
        $response = wp_remote_post($api_url, $request_data);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        // Log response here
    }
}

function bazz_get_connect_url() {
    $botUsername = apply_filters('bazz_lead_center_tg_bot', BAZZ_LEAD_CENTER_TG_BOT);

    $siteId = bazz_generate_site_id();

    return "https://t.me/{$botUsername}?start=site_{$siteId}";
}

function bazz_generate_site_id() {
    // Собираем уникальные и доступные данные для генерации ID
    $siteUrl = get_site_url();
    $siteName = get_bloginfo('name');
    $adminEmail = get_option('admin_email');
    $secretKey = '';

    // Формируем уникальный идентификатор сайта
    $siteData = $siteUrl . '|' . $siteName . '|' . $adminEmail;
    $siteHash = hash_hmac('sha256', $siteData, $secretKey);

    // Берем первые 16 символов для краткости (можно и полный хеш)
    return substr($siteHash, 0, 16);
}
