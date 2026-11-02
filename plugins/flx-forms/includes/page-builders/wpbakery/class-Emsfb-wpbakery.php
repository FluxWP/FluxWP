<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Emsfb_Widgets_Helper') && defined('EMSFB_PLUGIN_DIRECTORY')) {
    $helper_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-widgets-helper.php';
    if (file_exists($helper_file)) {
        require_once $helper_file;
    }
}

class Emsfb_WPBakery_Integration {

    private static $instance = null;

    const BRAND_PRIMARY = '#ff4b93';
    const BRAND_SECONDARY = '#202a8d';

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        if (!defined('WPB_VC_VERSION')) {
            return;
        }

        add_action('vc_before_init', [$this, 'register_element']);
        add_action('vc_load_iframe_jscss', [$this, 'enqueue_editor_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    }

    public function register_element() {
        if (!function_exists('vc_map')) {
            return;
        }

        vc_map([
            'name' => __('Flux Forms', 'flx-forms'),
            'base' => 'efb_wpbakery_form',
            'category' => __('Content', 'flx-forms'),
            'icon' => Emsfb_Widgets_Helper::get_logo_url(),
            'description' => __('Display an Flux Forms form', 'flx-forms'),
            'class' => 'efb-wpbakery-element',
            'weight' => 100,
            'show_settings_on_create' => true,
            'params' => [
                [
                    'type' => 'dropdown',
                    'heading' => __('Select Form', 'flx-forms'),
                    'param_name' => 'form_id',
                    'value' => $this->get_forms_for_dropdown(),
                    'description' => __('Choose a form to display. The tracking form allows users to find submissions by confirmation code.', 'flx-forms'),
                    'save_always' => true,
                    'admin_label' => true,
                    'group' => __('Form Settings', 'flx-forms'),
                ],
                [
                    'type' => 'checkbox',
                    'heading' => __('Show Form Title', 'flx-forms'),
                    'param_name' => 'show_title',
                    'value' => [
                        __('Yes', 'flx-forms') => 'yes'
                    ],
                    'description' => __('Display the form name as a heading above the form.', 'flx-forms'),
                    'group' => __('Form Settings', 'flx-forms'),
                ],
                [
                    'type' => 'textfield',
                    'heading' => __('Extra Class Name', 'flx-forms'),
                    'param_name' => 'extra_class',
                    'description' => __('Add custom CSS classes for styling.', 'flx-forms'),
                    'group' => __('Design', 'flx-forms'),
                ],
                [
                    'type' => 'css_editor',
                    'heading' => __('CSS', 'flx-forms'),
                    'param_name' => 'css',
                    'group' => __('Design', 'flx-forms'),
                ],
            ],
        ]);

        add_shortcode('efb_wpbakery_form', [$this, 'render_shortcode']);
    }

    private function get_forms_for_dropdown() {
        $forms_array = [
            __('— Select a Form —', 'flx-forms') => ''
        ];

        if (class_exists('Emsfb_Widgets_Helper')) {
            $forms = Emsfb_Widgets_Helper::get_all_forms(true);

            foreach ($forms as $form) {
                $forms_array[$form['name']] = strval($form['id']);
            }
        }

        return $forms_array;
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'form_id' => '',
            'show_title' => '',
            'extra_class' => '',
            'css' => '',
        ], $atts, 'efb_wpbakery_form');

        $form_id = $atts['form_id'];

        if (empty($form_id)) {

            if ($this->is_editor_mode()) {
                return $this->render_editor_placeholder();
            }
            return '';
        }

        $wrapper_classes = ['efb-wpbakery-form-wrapper'];

        if (!empty($atts['extra_class'])) {
            $wrapper_classes[] = esc_attr($atts['extra_class']);
        }

        if (!empty($atts['css']) && function_exists('vc_shortcode_custom_css_class')) {
            $wrapper_classes[] = vc_shortcode_custom_css_class($atts['css']);
        }

        $output = '<div class="' . implode(' ', $wrapper_classes) . '">';

        if ($atts['show_title'] === 'yes') {
            if (class_exists('Emsfb_Widgets_Helper')) {
                $forms = Emsfb_Widgets_Helper::get_all_forms(true);
                foreach ($forms as $form) {
                    if (strval($form['id']) === strval($form_id)) {
                        $output .= '<h3 class="efb-wpbakery-form-title">' . esc_html($form['name']) . '</h3>';
                        break;
                    }
                }
            }
        }

        if (class_exists('Emsfb_Widgets_Helper')) {
            $output .= Emsfb_Widgets_Helper::render_form($form_id);
        } else {

            $shortcode = $form_id === 'tracking'
                ? '[Easy_Form_Builder_confirmation_code_finder]'
                : '[EMS_Form_Builder id="' . intval($form_id) . '"]';
            $output .= do_shortcode($shortcode);
        }

        $output .= '</div>';

        return $output;
    }

    private function is_editor_mode() {
        if (function_exists('vc_is_inline') && vc_is_inline()) {
            return true;
        }
        if (isset($_GET['vc_editable']) && $_GET['vc_editable'] === 'true') {
            return true;
        }
        if (isset($_GET['vc_action']) && $_GET['vc_action'] === 'vc_inline') {
            return true;
        }
        return false;
    }

    private function render_editor_placeholder() {
        return sprintf(
            '<div class="efb-wpbakery-placeholder" style="
                padding: 40px 30px;
                background: linear-gradient(135deg, %s 0%%, %s 100%%);
                border-radius: 12px;
                text-align: center;
                color: #fff;
                font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif;
                margin: 10px 0;
            ">
                <div style="margin-bottom: 15px;">
                    <img src="%s" alt="Flux Forms" style="width: 60px; height: 60px; border-radius: 10px;" onerror="this.style.display=\'none\'">
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 10px;">Flux Forms</div>
                <div style="font-size: 14px; opacity: 0.9;">%s</div>
                <div style="margin-top: 15px; padding: 10px 20px; background: rgba(255,255,255,0.2); border-radius: 6px; display: inline-block; font-size: 13px;">
                    %s
                </div>
            </div>',
            self::BRAND_SECONDARY,
            self::BRAND_PRIMARY,
            esc_url(Emsfb_Widgets_Helper::get_logo_url()),
            esc_html__('Select a form from the element settings.', 'flx-forms'),
            esc_html__('✏️ Click to edit and choose a form', 'flx-forms')
        );
    }

    public function enqueue_editor_assets() {
        wp_enqueue_style(
            'efb-wpbakery-editor',
            EMSFB_PLUGIN_URL . 'includes/page-builders/wpbakery/assets/css/wpbakery-editor.css',
            [],
            EMSFB_PLUGIN_VERSION
        );

        wp_enqueue_style('Emsfb-bootstrap-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min-efb.css', [], EMSFB_PLUGIN_VERSION);
        wp_enqueue_style('Emsfb-responsive-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/min-1200-style.css', [], EMSFB_PLUGIN_VERSION);
    }

    public function enqueue_admin_styles() {
        global $pagenow;

        if ($pagenow === 'post.php' || $pagenow === 'post-new.php') {
            wp_enqueue_style(
                'efb-wpbakery-admin',
                EMSFB_PLUGIN_URL . 'includes/page-builders/wpbakery/assets/css/wpbakery-admin.css',
                [],
                EMSFB_PLUGIN_VERSION
            );
        }
    }
}

Emsfb_WPBakery_Integration::get_instance();
