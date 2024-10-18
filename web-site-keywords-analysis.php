<?php
/*
Plugin Name: Web Site Keywords Analysis
Description: Un plugin simple sobre como utilizar shortcodes en WordPress, o el principio de un TFG... ;)
Version: 1.0
Author: Lux GPT
*/

// Función para obtener las URLs del sitemap
function get_sitemap_urls($url) {
    $sitemap_content = @file_get_contents($url . '/sitemap.xml'); // Utiliza @ para suprimir errores de file_get_contents
    if ($sitemap_content === false) {
        return ['Error: No se pudo obtener el contenido del sitemap.'];
    }
    libxml_use_internal_errors(true); // Habilita el manejo de errores interno de libxml
    $sitemap_xml = simplexml_load_string($sitemap_content);
    if ($sitemap_xml === false) {
        // Captura y muestra los errores
        $errors = libxml_get_errors();
        libxml_clear_errors();
        $error_message = 'Error: XML no válido. Detalles: ';
        foreach ($errors as $error) {
            $error_message .= $error->message . ' ';
        }
        return [$error_message];
    }
    $urls = [];
    foreach ($sitemap_xml->url as $url_entry) {
        $urls[] = (string) $url_entry->loc;
    }
    return $urls;
}

// Función para renderizar el formulario y procesar la entrada
function render_form_shortcode() {
    ob_start(); ?>
    <form id="url-form" method="post">
        <label for="url-input">Introduce una URL:</label><br>
        <input type="url" id="url-input" name="url-input" required><br><br>
        <input type="submit" name="submit-url" value="Enviar">
    </form>
    <?php
    if (isset($_POST['submit-url'])) {
        $url = sanitize_text_field($_POST['url-input']);
        echo "<p>Hola, has introducido la URL: " . esc_url($url) . "</p>";
        $urls = get_sitemap_urls($url);
        if (!empty($urls)) {
            echo '<ul>';
            foreach ($urls as $url) {
                echo '<li>' . esc_url($url) . '</li>';
            }
            echo '</ul>';
        } else {
            echo 'No se encontraron URLs en el sitemap.';
        }
    }
    return ob_get_clean();
}

// Registrar el shortcode [formulario_url]
add_shortcode('formulario_url', 'render_form_shortcode');
?>
