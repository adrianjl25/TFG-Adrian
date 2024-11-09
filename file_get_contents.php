<?php
function get_page_metadata($url) {
    $html = file_get_contents($url);
    if ($html === false) {
        return null;
    }

    // Obtener título
    preg_match('/<title>(.*?)<\/title>/', $html, $title_match);
    $title = $title_match[1] ?? 'Sin título';

    // Obtener H1
    preg_match('/<h1>(.*?)<\/h1>/', $html, $h1_match);
    $h1 = $h1_match[1] ?? 'Sin H1';

    // Obtener meta description
    preg_match('/<meta name="description" content="(.*?)"/', $html, $description_match);
    $description = $description_match[1] ?? 'Sin descripción';

    return [
        'title' => $title,
        'h1' => $h1,
        'description' => $description
    ];
}
