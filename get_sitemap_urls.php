<?php
function get_sitemap_urls($url) {
    $sitemap_content = file_get_contents($url . '/sitemap.xml');
    if ($sitemap_content === false) {
        return [];
    }

    $sitemap_xml = simplexml_load_string($sitemap_content);
    $urls = [];

    // Supongamos que el sitemap tiene el formato estándar
    foreach ($sitemap_xml->url as $url_entry) {
        $urls[] = (string) $url_entry->loc;
    }

    return $urls;
}
