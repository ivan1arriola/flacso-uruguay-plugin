<?php
if (!defined('ABSPATH')) { exit; }

trait FLACSO_Formulario_Preinscripcion_Assets {
    public function enqueue_assets_formulario($info) {
        $assets_url = plugin_dir_url(__FILE__) . 'assets/';
        
        // Enqueue external libraries
        wp_enqueue_style('intl-tel-input-css', 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.12.4/build/css/intlTelInput.css', array(), '25.12.4');
        wp_enqueue_style('country-select-js-css', 'https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/css/countrySelect.min.css', array(), '2.0.1');
        
        // Enqueue custom styles
        wp_enqueue_style('flacso-formulario-styles', $assets_url . 'styles.css', array('intl-tel-input-css', 'country-select-js-css'), '1.0.1');
        
        // Enqueue external JS libraries
        wp_enqueue_script('intl-tel-input-js', 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.12.4/build/js/intlTelInput.min.js', array(), '25.12.4', true);
        wp_enqueue_script('country-select-js', 'https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/js/countrySelect.min.js', array(), '2.0.1', true);
        wp_enqueue_script('libphonenumber-js', 'https://cdn.jsdelivr.net/npm/libphonenumber-js@1.11.14/bundle/libphonenumber-min.js', array(), '1.11.14', true);
        
        // Enqueue custom script with dependencies
        wp_enqueue_script('flacso-formulario-script', $assets_url . 'scripts.js', array('jquery', 'intl-tel-input-js', 'country-select-js', 'libphonenumber-js'), '1.0.1', true);
        
        // Localize script with PHP data
        wp_localize_script('flacso-formulario-script', 'flacsoFormConfig', array(
            'convenios' => $info['convenios_validos'],
            'maxFileSize' => 5,
            'maxTotalSize' => 25,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'tituloPosgrado' => $info['titulo_posgrado']
        ));
    }

    public function render_librerias_externas() { 
        // Este método ahora está vacío, los assets se encolanan con enqueue_assets_formulario
    }

    public function render_styles_finales() {
        // Este método ahora está vacío, los estilos se encolanan con enqueue_assets_formulario
    }

    public function render_scripts_finales($info) {
        // Este método ahora está vacío, los scripts se encolanan con enqueue_assets_formulario
    }

}



