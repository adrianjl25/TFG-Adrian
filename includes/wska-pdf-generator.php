<?php

function convert_to_iso($text){
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
}


function wsk_generate_pdf( $url_keywords, $keyword_similarity ) {
	// Instanciar FPDF
	$pdf = new FPDF();
	$pdf->AddPage();
	$pdf->SetFont('Arial', 'B', 16);

	// Título
	$pdf->Cell(0, 10, convert_to_iso('Reporte de Análisis de Palabras Clave'), 0, 1, 'C');
	$pdf->Ln(10);

	// URLs analizadas
	$pdf->SetFont('Arial', 'B', 14);
	$pdf->Cell(0, 10, 'URLs Analizadas:', 0, 1, 'L');
	foreach ($url_keywords as $url => $keywords) {
		$pdf->SetFont('Arial', '', 12);
		$pdf->Cell(0, 10, "URL: $url", 0, 1, 'L');

		$pdf->SetFont('Arial', '', 10);
		$pdf->MultiCell(0, 10, convert_to_iso('Palabras Clave: ' . implode(', ', $keywords)));
		$pdf->Ln(5);
	}

	// Similitud entre palabras clave
	$pdf->SetFont('Arial', 'B', 12);
	$pdf->Cell(0, 10, convert_to_iso('Similitud entre Palabras Clave:'), 0, 1, 'L');
	foreach ($keyword_similarity as $keyword => $similarity) {
		$pdf->SetFont('Arial', '', 10);
		$pdf->Cell(0, 10, convert_to_iso("Palabra Clave: $keyword"), 0, 1, 'L');

		$pdf->SetFont('Arial', '', 10);
		$pdf->MultiCell(0, 10, convert_to_iso('Similitud: ' . json_encode($similarity)));
		$pdf->Ln(5);
	}

	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="analisis_keywords.pdf"');
	$pdf->Output('I', 'analisis_keywords.pdf');
	exit;

}
