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
    // Para intentar obtener el contenido del sitemap
    $sitemap_content = @file_get_contents($url); // Utiliza @ para suprimir errores de file_get_contents
    if ($sitemap_content === false) {
        return ['Error: No se pudo obtener el contenido del sitemap.'];
    }
    $sitemap_xml = simplexml_load_string($sitemap_content);
    if ($sitemap_xml === false) {
        return ['Error: XML no válido.'];
    }

    //Extraemos las URLs del sitemap
    $urls = [];
    foreach ($sitemap_xml->url as $url_entry) {
        $urls[] = (string) $url_entry->loc;
    }
    if (empty($urls)) {
        $urls[] = 'Debug: No URLs found in sitemap.';
    }
    return $urls;
}

// Función para obtener los metadatos de una página
function get_page_metadata($url) {
    $html = @file_get_contents($url);
    if ($html === false) {
        return [
            'title' => 'Error: No se pudo obtener el contenido de la página.',
          //  'h1' => '',
            'description' => ''
        ];
    }

    // Obtener título
    preg_match('/<title>(.*?)<\/title>/', $html, $title_match);
    $title = $title_match[1] ?? 'Sin título';

    // Obtener H1
    ///preg_match('/<h1>(.*?)<\/h1>/', $html, $h1_match);
   // $h1 = $h1_match[1] ?? 'Sin H1';

    // Obtener meta description
    preg_match('/<meta name="description" content="(.*?)"/', $html, $description_match);
    $description = $description_match[1] ?? 'Sin descripción';

    return [
        'title' => $title,
      // 'h1' => $h1,
        'description' => $description
    ];
}

// Funcion para extraer palabras clave de las URLs
function extract_keywords($metadata) {
    $content = implode(' ', $metadata); // Unir título, h1 y descripción
    $words = str_word_count(strtolower($content), 1); // Convertir todo a minúsculas y separar por palabras
    $frequencies = array_count_values($words); // Contar frecuencias de cada palabra

    // Excluir palabras comunes
    $stopwords = ['de', 'la', 'y', 'el', 'en', 'los']; // Puedes expandir esta lista
    $keywords = array_diff_key($frequencies, array_flip($stopwords));

    // Devolver las palabras más frecuentes
    arsort($keywords);
    return array_slice($keywords, 0, 5, true); // Por ejemplo, devolver las 5 palabras más frecuentes
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
                $metadata = get_page_metadata($url);
                echo '<ul>';
                echo '<li><strong>Título:</strong> ' . esc_html($metadata['title']) . '</li>';
               // echo '<li><strong>H1:</strong> ' . esc_html($metadata['h1']) . '</li>';
                echo '<li><strong>Descripción:</strong> ' . esc_html($metadata['description']) . '</li>';
                $keywords = extract_keywords($metadata);
                echo '<li><strong>Palabras clave:</strong> ' . implode(', ', array_keys($keywords)) . '</li>';
                echo '</ul>';
            }
            echo '</ul>';
        } else {
            if (isset($urls[0]) && strpos($urls[0], 'Error:') !== false) {
                echo '<p>' . esc_html($urls[0]) . '</p>';
            } else {
                echo '<p>No se encontraron URLs en el sitemap.</p>';
            }
        }
    }
    return ob_get_clean();
}

// Registrar el shortcode [formulario_url]
add_shortcode('formulario_url', 'render_form_shortcode');
?>
