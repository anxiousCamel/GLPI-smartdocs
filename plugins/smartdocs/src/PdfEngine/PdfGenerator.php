<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Gerador de PDF: aplica overlays (texto, imagem, checkbox, assinatura)
 * sobre um PDF base usando FPDI + TCPDF.
 *
 * Decisão de biblioteca: FPDI importa páginas do PDF base; TCPDF
 * renderiza o conteúdo dinâmico sobre cada página importada.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\PdfEngine;

use setasign\Fpdi\Tcpdf\Fpdi;

final class PdfGenerator
{
    /**
     * Gera o PDF final com todos os campos aplicados.
     *
     * @param string $pdfBasePath  Caminho físico do PDF base
     * @param array  $fieldOverlays Campos a desenhar (com computedPosition, value, type)
     * @param string $outputDir    Diretório de saída
     * @param string $outputName   Nome do arquivo de saída
     *
     * @return string Caminho do PDF gerado
     */
    public function generate(
        string $pdfBasePath,
        array $fieldOverlays,
        string $outputDir,
        string $outputName
    ): string {
        if (!file_exists($pdfBasePath)) {
            throw new \RuntimeException('PDF base não encontrado: ' . $pdfBasePath);
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        // Agrupa campos por página computada
        $fieldsByPage = [];
        foreach ($fieldOverlays as $field) {
            $pageIndex = (int) ($field['computedPageIndex'] ?? ($field['page_index'] ?? 0));
            if (!isset($fieldsByPage[$pageIndex])) {
                $fieldsByPage[$pageIndex] = [];
            }
            $fieldsByPage[$pageIndex][] = $field;
        }

        $totalPagesInBase = $pdf->setSourceFile($pdfBasePath);

        // Determina quantas páginas precisamos (máximo entre base e overlays)
        $maxPageIndex = !empty($fieldsByPage) ? max(array_keys($fieldsByPage)) : 0;
        $totalOutputPages = max($totalPagesInBase, $maxPageIndex + 1);

        for ($pageIndex = 0; $pageIndex < $totalOutputPages; $pageIndex++) {
            $pdf->AddPage();

            // Importa a página do PDF base (reutiliza do início se necessário)
            $sourcePageIndex = ($pageIndex % $totalPagesInBase) + 1;
            $templateId = $pdf->importPage($sourcePageIndex);
            $pdf->useTemplate($templateId, 0, 0, null, null, true);

            // Aplica overlays desta página
            $fields = $fieldsByPage[$pageIndex] ?? [];
            foreach ($fields as $field) {
                $this->renderField($pdf, $field);
            }
        }

        $outputPath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $outputName;
        $pdf->Output($outputPath, 'F');

        return $outputPath;
    }

    /**
     * Renderiza um campo sobre o PDF.
     */
    private function renderField(Fpdi $pdf, array $field): void
    {
        $type = $field['type'] ?? 'text';
        $position = $field['computedPosition'] ?? json_decode($field['position'] ?? '{}', true);
        if (!is_array($position)) {
            $position = [];
        }

        $x = ($position['x'] ?? 0) * $pdf->getPageWidth();
        $y = ($position['y'] ?? 0) * $pdf->getPageHeight();
        $w = ($position['width'] ?? 0) * $pdf->getPageWidth();
        $h = ($position['height'] ?? 0) * $pdf->getPageHeight();

        $value = $field['value'] ?? '';

        match ($type) {
            'text'      => $this->renderText($pdf, $x, $y, $w, $h, $value, $field),
            'image'     => $this->renderImage($pdf, $x, $y, $w, $h, $value, $field),
            'signature' => $this->renderImage($pdf, $x, $y, $w, $h, $value, $field),
            'checkbox'  => $this->renderCheckbox($pdf, $x, $y, $w, $h, $value),
            default     => null,
        };
    }

    private function renderText(Fpdi $pdf, float $x, float $y, float $w, float $h, string $value, array $field): void
    {
        $config = json_decode($field['config'] ?? '{}', true);
        $fontSize = (float) ($config['font_size'] ?? 10);
        $fontFamily = $config['font_family'] ?? 'helvetica';
        $align = $config['align'] ?? 'L';

        $pdf->SetFont($fontFamily, '', $fontSize);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $value, 0, 0, $align);
    }

    private function renderImage(Fpdi $pdf, float $x, float $y, float $w, float $h, string $value, array $field): void
    {
        if (empty($value) || !file_exists($value)) {
            return;
        }

        $pdf->Image($value, $x, $y, $w, $h, '', '', '', false, 300, '', false, false, 0);
    }

    private function renderCheckbox(Fpdi $pdf, float $x, float $y, float $w, float $h, string $value): void
    {
        $checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        // Desenha a borda
        $pdf->Rect($x, $y, min($w, $h), min($w, $h), 'D');

        if ($checked) {
            // Preenche com um X
            $size = min($w, $h);
            $pdf->Line($x + 2, $y + 2, $x + $size - 2, $y + $size - 2);
            $pdf->Line($x + $size - 2, $y + 2, $x + 2, $y + $size - 2);
        }
    }
}
