<?php

function wsk_generate_pdf( $url_keywords, $keyword_similarity ) {
	// Instanciar FPDF
	$pdf = new FPDF();
	$pdf->AddPage();
	$pdf->SetFont('Arial', 'B', 16);

	// Título
	$pdf->Cell(0, 10, 'Reporte de Análisis de Palabras Clave', 0, 1, 'C');
	$pdf->Ln(10);

	// URLs analizadas
	$pdf->SetFont('Arial', '', 12);
	$pdf->Cell(0, 10, 'URLs Analizadas:', 0, 1);
	foreach ($url_keywords as $url => $keywords) {
		$pdf->SetFont('Arial', 'B', 10);
		$pdf->Cell(0, 10, "URL: $url", 0, 1);

		$pdf->SetFont('Arial', '', 10);
		$pdf->MultiCell(0, 10, 'Palabras Clave: ' . implode(', ', $keywords));
		$pdf->Ln(5);
	}

	// Similitud entre palabras clave
	$pdf->SetFont('Arial', '', 12);
	$pdf->Cell(0, 10, 'Similitud entre Palabras Clave:', 0, 1);
	foreach ($keyword_similarity as $keyword => $similarity) {
		$pdf->SetFont('Arial', 'B', 10);
		$pdf->Cell(0, 10, "Palabra Clave: $keyword", 0, 1);

		$pdf->SetFont('Arial', '', 10);
		$pdf->MultiCell(0, 10, 'Similitud: ' . json_encode($similarity));
		$pdf->Ln(5);
	}

	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="analisis_keywords.pdf"');
	$pdf->Output('I', 'analisis_keywords.pdf');
	exit;


	// Enviar PDF al navegador
	//header('Content-Type: application/pdf');
	//header('Content-Disposition: attachment; filename="analisis_keywords.pdf"');
	//$pdf->Output();
	//exit;
}
