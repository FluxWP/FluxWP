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

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Emsfb_Elementor_Widget extends Widget_Base {

    public function get_name() {
        return 'emsfb_form';
    }

    public function get_title() {
        return __('\\nFlux Forms\\n', 'flx-forms');
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return ['easy-form-builder', 'general'];
    }

    public function get_keywords() {
        return ['form', 'contact', 'easy form builder', 'efb', 'forms', 'survey', 'questionnaire', 'registration'];
    }

    public function get_custom_help_url() {
        return 'https://whitestudio.team/docs/easy-form-builder/';
    }

    protected function register_controls() {

        $this->start_controls_section(
            'content_section',
            [
                'label' => __('\\nForm Settings\\n', 'flx-forms'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $form_options = $this->get_forms();

        $this->add_control(
            'form_id',
            [
                'label' => __('\\nSelect Form\\n', 'flx-forms'),
                'type' => Controls_Manager::SELECT,
                'options' => $form_options,
                'default' => '',
                'label_block' => true,
                'description' => __('\\nChoose a form to display. The tracking form allows users to find submissions by confirmation code.\\n', 'flx-forms'),
            ]
        );

        $this->add_control(
            'show_form_title',
            [
                'label' => __('\\nShow Form Title\\n', 'flx-forms'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'flx-forms'),
                'label_off' => __('No', 'flx-forms'),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => [
                    'form_id!' => '',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_container_section',
            [
                'label' => __('\\nContainer\\n', 'flx-forms'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('\\nPadding\\n', 'flx-forms'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .efb-elementor-form-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'container_background',
            [
                'label' => __('\\nBackground Color\\n', 'flx-forms'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .efb-elementor-form-wrapper' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'label' => __('Border', 'flx-forms'),
                'selector' => '{{WRAPPER}} .efb-elementor-form-wrapper',
            ]
        );

        $this->add_control(
            'container_border_radius',
            [
                'label' => __('\\nBorder Radius\\n', 'flx-forms'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .efb-elementor-form-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_box_shadow',
                'label' => __('\\nBox Shadow\\n', 'flx-forms'),
                'selector' => '{{WRAPPER}} .efb-elementor-form-wrapper',
            ]
        );

        $this->end_controls_section();
    }

    private function get_forms() {
        if (!class_exists('Emsfb_Widgets_Helper')) {
            return ['' => __('\\n— Select a Form —\\n', 'flx-forms')];
        }

        return Emsfb_Widgets_Helper::get_forms_for_select(true, true);
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $form_id = $settings['form_id'];

        if (empty($form_id)) {

            $is_edit_mode = false;
            if (class_exists('\Elementor\Plugin')) {
                try {
                    $elementor = \Elementor\Plugin::$instance;
                    if ($elementor && isset($elementor->editor) && $elementor->editor && method_exists($elementor->editor, 'is_edit_mode')) {
                        $is_edit_mode = $elementor->editor->is_edit_mode();
                    }
                } catch (\Exception $e) {
                    $is_edit_mode = false;
                } catch (\Error $e) {
                    $is_edit_mode = false;
                }
            }

            if ($is_edit_mode) {

                echo $this->render_editor_placeholder();
            }
            return;
        }

        echo '<div class="efb-elementor-form-wrapper">';

        if ($settings['show_form_title'] === 'yes') {
            $forms = Emsfb_Widgets_Helper::get_all_forms(true);
            foreach ($forms as $form) {
                if (strval($form['id']) === strval($form_id)) {
                    echo '<h3 class="efb-elementor-form-title">' . esc_html($form['name']) . '</h3>';
                    break;
                }
            }
        }

        if (class_exists('Emsfb_Widgets_Helper')) {
            echo Emsfb_Widgets_Helper::render_form($form_id);
        } else {

            $shortcode = $form_id === 'tracking'
                ? '[Easy_Form_Builder_confirmation_code_finder]'
                : '[EMS_Form_Builder id="' . intval($form_id) . '"]';
            echo do_shortcode($shortcode);
        }

        echo '</div>';
    }

    private function render_editor_placeholder() {
        return sprintf(
            '<div class="efb-elementor-placeholder" style="
                padding: 40px 30px;
                background: linear-gradient(135deg, #202a8d 0%%, #ff4b93 100%%);
                border-radius: 12px;
                text-align: center;
                color: #fff;
                font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif;
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
            esc_url(Emsfb_Widgets_Helper::get_logo_url()),
            esc_html__('\\nSelect a form from the widget settings panel.\\n', 'flx-forms'),
            esc_html__('\\n👈 Use the sidebar to choose a form\', \'tr\']]', 'flx-forms')
        );
    }

    protected function content_template() {
        ?>
        <#
        var formId = settings.form_id;

        if (!formId) {
            #>
            <div class="efb-elementor-placeholder" style="
                padding: 40px 30px;
                background: linear-gradient(135deg, #202a8d 0%, #ff4b93 100%);
                border-radius: 12px;
                text-align: center;
                color: #fff;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            ">
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 10px;">Flux Forms</div>
                <div style="font-size: 14px; opacity: 0.9;"><?php echo esc_html__('\\nSelect a form from the widget settings panel.\\n', 'flx-forms'); ?></div>
                <div style="margin-top: 15px; padding: 10px 20px; background: rgba(255,255,255,0.2); border-radius: 6px; display: inline-block; font-size: 13px;">
                    <?php echo esc_html__('\\n👈 Use the sidebar to choose a form\', \'tr\']]', 'flx-forms'); ?>
                </div>
            </div>
            <#
        } else {
            #>
            <div class="efb-elementor-form-wrapper">
                <div class="efb-elementor-preview" style="
                    padding: 30px;
                    background: linear-gradient(135deg, #202a8d 0%, #ff4b93 100%);
                    border-radius: 12px;
                    text-align: center;
                    color: #fff;
                ">
                    <div style="font-size: 18px; font-weight: 700; margin-bottom: 12px;">Flux Forms</div>
                    <div style="font-size: 14px; background: rgba(255,255,255,0.15); padding: 10px 15px; border-radius: 6px; display: inline-block;">
                        <span style="opacity: 0.8;"><?php echo esc_html__('\\nSelected Form ID:\\n', 'flx-forms'); ?></span>
                        <strong>{{ formId }}</strong>
                    </div>
                    <div style="margin-top: 15px; font-size: 13px; opacity: 0.8;">
                        <?php echo esc_html__('\\nThe form will appear here on the frontend.\\n', 'flx-forms'); ?>
                    </div>
                </div>
            </div>
            <#
        }
        #>
        <?php
    }
}
