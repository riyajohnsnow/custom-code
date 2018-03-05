<?php
if (!defined('ABSPATH')) {
    exit;
}

//generate the html for a Select2 element.

class HF_Select2 {

    protected $default_attributes = array(
        'type' => 'hidden',
        'placeholder' => '',
        'class' => '',
    );
    protected $attributes = array();

    public function __construct(array $attributes) {
        $this->attributes = array_merge($this->default_attributes, $attributes);
    }

    public static function render(array $attributes) {
        $select2 = new self($attributes);
        $select2->print_html();
    }

    protected function get_property_name($property) {
        $data_properties = HF_Subscriptions::is_woocommerce_prior_to('3.0') ? array('placeholder', 'selected', 'allow_clear') : array('placeholder', 'allow_clear');
        return in_array($property, $data_properties) ? 'data-' . $property : $property;
    }

    protected function attributes_to_html(array $attributes) {

        $html = array();
        foreach ($attributes as $property => $value) {
            if (!is_scalar($value)) {
                $value = hf_json_encode($value);
            }
            $html[] = $this->get_property_name($property) . '="' . esc_attr($value, HF_Subscriptions::TEXT_DOMAIN) . '"';
        }
        return implode(' ', $html);
    }

    public function print_html() {
        
        $allowed_attributes = array_map(array($this, 'get_property_name'), array_keys($this->attributes));
        $allowed_attributes = array_fill_keys($allowed_attributes, array());
        echo wp_kses($this->get_html(), array('input' => $allowed_attributes, 'select' => $allowed_attributes, 'option' => $allowed_attributes));
    }

    public function get_html() {
        
        $html = "\n<!--select2 -->\n";
        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            $html .= '<input ';
            $html .= $this->attributes_to_html($this->attributes);
            $html .= '/>';
        } else {
            $attributes = $this->attributes;
            $selected_value = isset($attributes['selected']) ? $attributes['selected'] : '';
            $attributes['selected'] = 'selected';

            $option_attributes = array_intersect_key($attributes, array_flip(array('value', 'selected')));
            $select_attributes = array_diff_key($attributes, $option_attributes);

            $html .= '<select ' . $this->attributes_to_html($select_attributes) . '>';
            $html .= '<option ' . $this->attributes_to_html($option_attributes) . '>' . $selected_value . '</option>';
            $html .= '</select>';
        }

        $html .= "\n<!--/select2 -->\n";
        return $html;
    }

}