<?php
/* Plugin Name: Web Site Sitemap Analysis
Description: Un plugin simple sobre como utilizar shortcodes en WordPress para listar sitemaps de una URL.
Version: 1.0
Author: Lux GPT
*/

// Función para obtener las URLs del sitemap
function get_sitemap_urls($url) {
    // Asegurarse de que la URL termina correctamente
    if (substr($url, -11) !== 'sitemap.xml') {
        $url = rtrim($url, '/') . '/sitemap.xml';
    }

    // Intentar obtener el contenido del sitemap
    $sitemap_content = @file_get_contents($url);
    if ($sitemap_content === false) {
        return ['Error: No se pudo obtener el contenido del sitemap.'];
    }

    $sitemap_xml = simplexml_load_string($sitemap_content);
    if ($sitemap_xml === false) {
        return ['Error: XML no válido.'];
    }

    // Extraer las URLs del sitemap
    $urls = [];
    foreach ($sitemap_xml->url as $url_entry) {
        $urls[] = (string) $url_entry->loc;
    }
    if (empty($urls)) {
        $urls[] = 'Debug: No URLs found in sitemap.';
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
                if (strpos($url, 'Error:') === 0 || strpos($url, 'Debug:') === 0) {
                    echo '<li>' . esc_html($url) . '</li>';
                } else {
                    echo '<li>' . esc_url($url) . '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p>No se encontraron URLs en el sitemap.</p>';
        }
    }
    return ob_get_clean();
}

// Registrar el shortcode [formulario_url]
add_shortcode('formulario_url', 'render_form_shortcode');
?>

