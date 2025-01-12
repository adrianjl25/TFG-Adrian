<?php
/* Plugin Name: Web Site Sitemap Analysis
Description: Un plugin simple sobre como utilizar shortcodes en WordPress para listar sitemaps de una URL.
Version: 1.0
Author: Lux GPT
*/


require('lib/fpdf.php');

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
function render_form_shortcode() {
    ob_start(); ?>
    <form id="url-form" method="post">
        <label for="url-input">Introduce una URL:</label><br>
        <input type="url" id="url-input" name="url-input" required><br><br>
        <input type="submit" name="submit-url" value="Enviar">
    </form>
    <div id="venn"></div> <!-- Añadir un contenedor para el diagrama de Venn -->
    <?php
    if (isset($_POST['submit-url'])) {
        $url = sanitize_text_field($_POST['url-input']);
        echo "<p>Hola, has introducido la URL: " . esc_url($url) . "</p>";
        $urls = get_sitemap_urls($url);
        if (!empty($urls)) {
            $url_keywords = [];
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
                $url_keywords[$url] = array_keys($keywords);
            }
            echo '</ul>';


            //Analizamos la similitud de las palabras clave
            $keyword_similarity = analyze_keyword_similarity($url_keywords);
            echo '<h3>Similitud de palabras clave:</h3>';
            echo '<ul>';
            foreach ($keyword_similarity as $keyword => $count) {
                echo '<li><strong>' . esc_html($keyword) . ':</strong> ' . $count . '</li>';
            }
            echo '</ul>';

            // Añadimos el botón para descargar el PDF
            echo '<form method="post">';
            echo '<input type="hidden" name="url_keywords" value="' . esc_attr(json_encode($url_keywords)) . '">';
            echo '<input type="hidden" name="keyword_similarity" value="' . esc_attr(json_encode($keyword_similarity)) . '">';
            echo '<input type="submit" name="download_pdf" value="Descargar PDF">';
            echo '</form>';


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
