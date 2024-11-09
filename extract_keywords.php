<?php
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
