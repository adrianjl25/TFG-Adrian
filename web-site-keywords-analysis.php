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
require_once plugin_dir_path(WSKA_CP_DIR) . 'web-site-keywords-analysis/lib/fpdf.php';

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
// TODO: He eliminado el fichero functions.php porque no es el lugar correcto para tenerlo y puede generar problemas
// TODO: Te recomiendo que también elimines la carga de los scripts del fichero functions.php de la plantilla que estás utilizando
// TODO: Es una buena práctica desacoplar la plantilla de los plugins
// TODO: Si necesitas cargar un script para tu plugin la forma correcta es utilizando una función como la que viene a continuación.
// TODO: Si necesitas cargar más scripts o una hoja de estilo para tu plugin también lo puedes hacer utilizando la función que viene a continuación
// TODO: Primero crea una carpeta assets/css/ dentro de tu plugin y añade el fichero de estilos wska-cp-css.css
// TODO: Y lo mismos para javascript.
// TODO: Te lo dejo indicado por si lo quieres utilizar, y si no, comenta las líneas para que vaya más rápido.
function agregar_scripts_d3venn() {

	// enqueue plugin script
    // TODO: Valora descargar la versión mini de los escripts y cargarlos desde local a tu plugin.
    // TODO: El rendimiento será mejor, y evitas el riesgo de que no está disponible la funcionalidad cuando la necesitas.
    wp_enqueue_script('d3', 'https://d3js.org/d3.v5.min.js', array(), null, true);
	wp_enqueue_script('d3-venn', 'https://cdn.jsdelivr.net/npm/venn.js@0.2.20/build/venn.min.js', array('d3'), null, true);

	// enqueue plugin script
	wp_enqueue_script( 'wska-cp-js', plugins_url( 'assets/js/wska-cp-js.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );

	// enqueue plugin styles
	wp_enqueue_style( 'wska-cp-css', plugins_url( 'assets/css/wska-cp-css.css', __FILE__ ), array(), '1.0.0', false );

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

// Función para generar y descargar el PDF
function generate_pdf($url_keywords, $keyword_similarity) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    $pdf->Cell(0, 10, 'Resultados de Analisis de Similitud de Palabras Clave', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    foreach ($url_keywords as $url => $keywords) {
        $pdf->Cell(0, 10, 'URL: ' . $url, 0, 1);
        $pdf->Cell(0, 10, 'Palabras Clave: ' . implode(', ', $keywords), 0, 1);
    }

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Similitud de Palabras Clave', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($keyword_similarity as $keyword => $count) {
        $pdf->Cell(0, 10, $keyword . ': ' . $count, 0, 1);
    }

    $pdf->Output('D', 'analisis_similitud_palabras_clave.pdf');
}

// Función para renderizar el formulario y procesar la entrada
function render_url_form_shortcode() {
    ob_start(); ?>

    <!-- Formulario para introducir la url del sitio que queremos analizar -->
    <!-- TODO: Posible Mejora, hacer una llamada AJAX con un token, más seguro.
         TODO: Solo si te da tiempo, si no es bueno incluir este tipo de cosas en la memoria.
    -->
    <form id="url-form" method="post">
        <label for="url-input">Introduce una URL:</label><br>
        <input type="url" id="url-input" name="url-input" required><br><br>
        <input type="submit" name="submit-url" value="Enviar">
    </form>
    <div id="venn"></div> <!-- Añadir un contenedor para el diagrama de Venn -->

    <?php

    if (isset($_POST['submit-url'])) {

        // Limpiamos la url para evitar problemas
        $url = sanitize_text_field($_POST['url-input']);

        echo "<p>Hola, has introducido la URL: " . esc_url($url) . "</p>";

        // Obtenemos el sitemap
        $urls = get_sitemap_urls($url);

        // Si hay urls seguimos con el análisis
        if (!empty($urls)) {
            $url_keywords = [];

            echo '<ul>';

            foreach ($urls as $url) {

                // TODO: Oculto temporalmente la salida por pantalla de la url
                // echo '<li>' . esc_url($url) . '</li>';

                // TODO: Instala el plugin que encontrarás en este post: https://luxdesignworks.com/depurar-wordpress/
                // TODO: Tienes que hacer clic en el enlace Write Log Plugin y se descarga
                // TODO: Instala y activa el plugin en tu instalación de WordPress subiendo el archivo
                // TODO: También debes cambiar a modo depuración tu WordPress y añadir algunas líneas para que te funcione
                // TODO: Las instrucciones están también el post de arriba, es muy sencillo lo puedes hacer a través de phpstorm
                // TODO: Cuando lo tengas puedes escribir en el log de depuración en lugar de sacarlo por pantalla
                // TODO: El fichero de log lo puedes encontrar en la carpeta /wp-content/debug.log
                // TODO: Y lo puedes visualizar también desde phpstorm abriendo el fichero

                // TODO: No olvides deshacer estos cambios cuando termines, y comentar o eliminar las líneas de log
	            write_log( 'URL: ' . $url );

                // Obtener los meta datos de una página a partir de la url
                // TODO: Comprobar si hay error al conectar a la url.
                // TODO: Añadir un temporizador después de cada consulta para evitar que el sitio web
                // TODO: bloquee el acceso.
                // TODO: También te puedes plantear analizar solo un número fijo de páginas entre 5 y 10.
                $metadata = get_page_metadata($url);

                // Mostrar título y descripción SEO (Meta Datos de la Página)
                // TODO: Oculto temporalmente la salida por pantalla
                // echo '<ul>';
                // echo '<li><strong>Título:</strong> ' . esc_html($metadata['title']) . '</li>';
                // echo '<li><strong>Descripción:</strong> ' . esc_html($metadata['description']) . '</li>';

                // TODO: Escribir en el log, deshacer o eliminar título y descripción SEO
                write_log('Título SEO: ' . $metadata['title']);
                write_log('Descripción SEO: ' . $metadata['description']);

                // Obtener las palabras clave de los metadatos de la página
                $keywords = extract_keywords($metadata);

                // Mostrar las palabras clave
	            // TODO: Oculto temporalmente la salida por pantalla
                // echo '<li><strong>Palabras clave:</strong> ' . implode(', ', array_keys($keywords)) . '</li>';
                // echo '</ul>';

	            // TODO: Oculto temporalmente la salida por pantalla de las palabras clave
                write_log('Palabras Clave: ' . implode(', ', array_keys($keywords)));


                // Almacenamos las palabras clave asociadas a la url para analizarlas después
                $url_keywords[$url] = array_keys($keywords);
            }
            echo '</ul>';

            // Analizamos la similitud de las palabras clave
            $keyword_similarity = analyze_keyword_similarity($url_keywords);

            echo '<h3>Similitud de palabras clave:</h3>';
            echo '<ul>';

            foreach ($keyword_similarity as $keyword => $count) {
                echo '<li><strong>' . esc_html($keyword) . ':</strong> ' . $count . '</li>';
            }
            echo '</ul>';

            // Añadimos el botón para descargar el PDF
            // TODO: Si da tiempo sustituir por una llamada AJAX.
            // TODO: Lo comento temporalmente para utilizar un ejemplo como el que viene en los tutoriales.
            // TODO: Puedes encontrar varios ejemplos en lib/tutorial/index.html, incluso puedes visualizarlos desde phpstorm
            echo '<form method="post" target="_blank">';
            echo '<input type="hidden" name="url_keywords" value="' . esc_attr(json_encode($url_keywords)) . '">';
            echo '<input type="hidden" name="keyword_similarity" value="' . esc_attr(json_encode($keyword_similarity)) . '">';
            echo '<input type="submit" name="download_pdf" value="Descargar PDF">';
            echo '</form>';


            // TODO: Prueba con enlace, eliminar
            // $pdf_template_path = 'wska-cp-template-pdf.php';
	        // echo '<a href=' . $pdf_template_path . ' target=_blank class=demo>Descargar PDF</a>';

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

            // TODO: Esto se podría cambiar a una llamada AJAX si da tiempo
            // TODO: También se puede apuntar como mejora en la memoria
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var vennData = ' . json_encode(array_values($venn_data)) . ';
                var chart = venn.VennDiagram();
                d3.select("#venn").datum(vennData).call(chart);
            });
            </script>';

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
add_shortcode('formulario_url', 'render_url_form_shortcode');




?>
