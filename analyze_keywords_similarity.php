<?php

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

    return $keyword_count;
}
