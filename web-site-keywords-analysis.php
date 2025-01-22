<?php
/*
Plugin Name: WebSite Sitemap Analysis
Description: Plugin para encontrar las palabras clave dominantes en una página web a partir de la url.
Version: 1.0
Author: Adrián Jiménez Lozano
Author URI: https://www.linkedin.com/in/adrian-jimenez-a8171b2a7/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: web-site-keywords-analysis
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly / Evitar accesos directos al código del plugin

// Definir WSKA CP DIR
const WSKA_CP_DIR = __DIR__;
const WSKA_PLUGIN_FILE = __FILE__;

// Acciones al iniciar el plugin

// Enlazamos la carga de la librería a la acción de carga de plugins
// TODO: He visto que tenías las diferentes funciones de los diferentes pasos en ficheros diferentes.
// TODO: Pero no los utilizabas, por eso los he eliminado, porque así tu solución es más fácil de leer y gestionar.
// TODO: De todas formas es una buena práctica dividir en ficheros especializados, si quieres tenerlo dividido
// TODO: Te recomiendo tener una carpeta templates para almacenar los diferentes ficheros con diferentes funcionalidades
// TODO: Después puedes cargar cada uno de los ficheros como se ve en la función a continuación para tener la funcionalidad disponible

// Cargar la libreria para PDF
require_once plugin_dir_path(WSKA_CP_DIR) . 'web-site-keywords-analysis/assets/lib/fpdf.php';

// Cargar la funcionalidad para generar el pdf
require_once plugin_dir_path( WSKA_CP_DIR ) . 'web-site-keywords-analysis/includes/wska-pdf-generator.php';


// Recibe la acción de pulsar el botón y llama a la función correspondiente para generar el pdf.
if ( isset( $_POST['download_pdf'] ) ) {
	// Capturar y procesar datos del formulario
	$url_keywords = json_decode( stripslashes( $_POST['url_keywords'] ), true );
	$keyword_similarity = json_decode( stripslashes( $_POST['keyword_similarity'] ), true );

	// Llamar a la función de generación de PDF
	wsk_generate_pdf( $url_keywords, $keyword_similarity );
}


// Cargamos las librerías de javascript para dibujar los diagramas de burbujas
function agregar_scripts_d3venn() {
    wp_enqueue_script('d3', 'https://d3js.org/d3.v5.min.js', array(), null, true);
	wp_enqueue_script('d3-venn', 'https://cdn.jsdelivr.net/npm/venn.js@0.2.20/build/venn.min.js', array('d3'), null, true);

	// enqueue plugin script
	wp_enqueue_script( 'wska-cp-js', plugins_url( 'assets/js/wska-cp-js.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );

	// enqueue plugin styles
	wp_enqueue_style( 'wska-cp-css', plugins_url( 'assets/css/wska-cp-css.css', __FILE__ ), array(), '1.0.0', false );

    // enqueue plugin styles
    wp_enqueue_style( 'web-site-keywords-analysis.css', plugins_url( 'assets/css/web-site-keywords-analysis.css', __FILE__ ), array(), '1.0.0', false );

}
add_action('wp_enqueue_scripts', 'agregar_scripts_d3venn');

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
        return false; //False para que no salgan en el analisis los errores
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

// Función para extraer palabras clave de las URLs.
function extract_keywords($metadata, $min_length = 3) {
    $content = implode(' ', $metadata); // Unir título, h1 y descripción
    //Para que detecte letras con tildes y caracteres especiales
    $content = mb_strtolower($content, 'UTF-8'); //Convertir a minúsculas
    $words = preg_split('/\PL+/u', $content, -1, PREG_SPLIT_NO_EMPTY); // Separar por palabras, considerando caracteres Unicode
    $words = array_filter($words, function($word) use ($min_length) {   //Hacemos que las palabras clave tengan que tener un mínimo de 2 letras
        return mb_strlen($word, 'UTF-8') >= $min_length;
    });
    $frequencies = array_count_values($words); // Contar frecuencias de cada palabra

    // Excluir palabras comunes
    $stopwords = ['de', 'la', 'y', 'el', 'en', 'los']; // Puedes expandir esta lista
    $keywords = array_diff_key($frequencies, array_flip($stopwords));

    // Devolver las palabras más frecuentes
    arsort($keywords);
    return array_slice($keywords, 0, 5, true); // Devolver las 5 palabras más frecuentes
}

// Función para analizar la similitud de palabras clave.
function analyze_keyword_similarity($url_keywords)
{
    $keyword_count = [];

    foreach ($url_keywords as $keywords) {
        foreach ($keywords as $keyword) {
            if (!isset($keyword_count[$keyword])) {
                $keyword_count[$keyword] = 0;
            }
            $keyword_count[$keyword]++;
        }
    }
    //Para ordenar la lista de palabras clave de mayor a menor
    arsort($keyword_count);
    return array_slice($keyword_count, 0, 10, true);
}


// Función para renderizar el formulario y procesar la entrada
function render_url_form_shortcode() {
    ob_start(); ?>

    <!-- Formulario para introducir la url del sitio que queremos analizar -->
    <!-- TODO: Posible Mejora, hacer una llamada AJAX con un token, más seguro.
         TODO: Solo si te da tiempo, si no es bueno incluir este tipo de cosas en la memoria.
    -->
    <form id="url-form" method="post">
        <label for="url-input">Introduzca la URL cuyo «sitemap» quiere analizar:</label><br>
        <input type="url" id="url-input" name="url-input" required><br><br>
        <input type="submit" name="submit-url" id="submit-url" value="Enviar">
    </form>
    <div id="venn"></div> <!-- Añadir un contenedor para el diagrama de Venn -->

    <?php

    if (isset($_POST['submit-url'])) {
        $url = sanitize_text_field($_POST['url-input']);
        echo "<p>URL analizada: " . esc_url($url) . "</p>";
        $urls = get_sitemap_urls($url);
        if (empty($urls)) {
            echo '<p>Error: No se encontraron URLs válidas en el sitemap.</p>';
            return ob_get_clean();
        }
        $url_keywords = [];
        foreach ($urls as $url) {
            $metadata = get_page_metadata($url);
            if ($metadata !== false) { // Solo procesar páginas válidas
                $keywords = extract_keywords($metadata);
                $url_keywords[$url] = array_keys($keywords);
            }
        }
        if (empty($url_keywords)) {
            echo '<p>No se encontraron páginas procesables en el sitemap.</p>';
            return ob_get_clean();
        }
        $keyword_similarity = analyze_keyword_similarity($url_keywords);
        echo '<h3>Similitud de palabras clave:</h3><ul>';
        foreach ($keyword_similarity as $keyword => $count) {
            echo '<li><strong>' . esc_html($keyword) . ':</strong> ' . $count . '</li>';
        }
        echo '</ul>';

        // Convertir los datos para el diagrama de Venn
        $venn_data = [];
        $keyword_sets = [];
        foreach ($url_keywords as $url => $keywords) {
            foreach ($keywords as $keyword) {
                if (isset($keyword_similarity[$keyword])) { //Solo considera las 10 palabras que más se repiten
                    if (!isset($venn_data[$keyword])) {
                        $venn_data[$keyword] = ['sets' => [$keyword], 'size' => 1];
                    } else {
                        $venn_data[$keyword]['size'] += 1;
                    }
                    $keyword_sets[$url][] = $keyword;
                }
            }
        }

        // Añadir intersecciones reales según los datos
        foreach ($keyword_sets as $keywords) {
            foreach (array_unique($keywords) as $keyword) {
                foreach (array_unique($keywords) as $inner_keyword) {
                    if ($keyword !== $inner_keyword) {
                        $intersection_size = 0;
                        foreach ($url_keywords as $key => $values) {
                            if (in_array($keyword, $values) && in_array($inner_keyword, $values)) {
                                $intersection_size++;
                            }
                        }
                        if ($intersection_size > 0) {
                            $venn_data[] = [
                                'sets' => [$keyword, $inner_keyword],
                                'size' => $intersection_size
                            ];
                        }
                    }
                }
            }
        }
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var vennData = ' . json_encode(array_values($venn_data)) . ';
            var chart = venn.VennDiagram();
            d3.select("#venn").datum(vennData).call(chart);
        });
        </script>';
    }
    return ob_get_clean();
}
// Registrar el shortcode [formulario_url]
add_shortcode('formulario_url', 'render_url_form_shortcode');




?>
