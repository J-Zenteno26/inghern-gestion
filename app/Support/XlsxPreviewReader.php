<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use XMLReader;
use ZipArchive;

final class XlsxPreviewReader
{
    public const MAX_ROWS = 200;

    public const MAX_COLUMNS = 50;

    /**
     * @return array{
     *     sheets: array<int, string>,
     *     selected_sheet: int,
     *     selected_name: string,
     *     rows: array<int, array{number: int, cells: array<int, string>}>,
     *     visible_columns: int,
     *     total_rows: int,
     *     total_columns: int,
     *     limited: bool
     * }
     */
    public function read(string $path, int $selectedSheet = 0): array
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(XMLReader::class)) {
            throw new RuntimeException('El servidor no dispone de las extensiones necesarias para leer XLSX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('El archivo XLSX no pudo abrirse.');
        }

        try {
            [$sheets, $sheetPaths] = $this->workbook($zip);
            if ($sheets === []) {
                throw new RuntimeException('El archivo XLSX no contiene hojas legibles.');
            }

            $selectedSheet = max(0, min($selectedSheet, count($sheets) - 1));
            $sharedStrings = $this->sharedStrings($zip, $path);
            $sheet = $this->sheet($path, $sheetPaths[$selectedSheet], $sharedStrings);

            return [
                'sheets' => $sheets,
                'selected_sheet' => $selectedSheet,
                'selected_name' => $sheets[$selectedSheet],
                ...$sheet,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function workbook(ZipArchive $zip): array
    {
        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $relationshipPaths = [];
        $relationshipXPath = new DOMXPath($relationships);

        foreach ($relationshipXPath->query('//*[local-name()="Relationship"]') ?: [] as $relationship) {
            if (! $relationship instanceof DOMElement) {
                continue;
            }

            $target = ltrim($relationship->getAttribute('Target'), '/');
            $relationshipPaths[$relationship->getAttribute('Id')] = $this->normalizePath(
                str_starts_with($target, 'xl/') ? $target : 'xl/'.$target,
            );
        }

        $sheets = [];
        $paths = [];
        $workbookXPath = new DOMXPath($workbook);
        foreach ($workbookXPath->query('//*[local-name()="sheet"]') ?: [] as $sheet) {
            if (! $sheet instanceof DOMElement) {
                continue;
            }

            $relationshipId = $sheet->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id',
            );
            $path = $relationshipPaths[$relationshipId] ?? null;
            if ($path === null || $zip->locateName($path) === false) {
                continue;
            }

            $sheets[] = $sheet->getAttribute('name') ?: 'Hoja '.(count($sheets) + 1);
            $paths[] = $path;
        }

        return [$sheets, $paths];
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip, string $xlsxPath): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $reader = $this->reader($xlsxPath, 'xl/sharedStrings.xml');
        $strings = [];

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }

                $document = new DOMDocument;
                if (! $document->loadXML($reader->readOuterXml(), LIBXML_NONET | LIBXML_COMPACT)) {
                    $strings[] = '';

                    continue;
                }

                $xpath = new DOMXPath($document);
                $text = '';
                foreach ($xpath->query('//*[local-name()="t"]') ?: [] as $node) {
                    $text .= $node->textContent;
                }
                $strings[] = $text;
            }
        } finally {
            $reader->close();
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array{
     *     rows: array<int, array{number: int, cells: array<int, string>}>,
     *     visible_columns: int,
     *     total_rows: int,
     *     total_columns: int,
     *     limited: bool
     * }
     */
    private function sheet(string $xlsxPath, string $sheetPath, array $sharedStrings): array
    {
        $reader = $this->reader($xlsxPath, $sheetPath);
        $rows = [];
        $totalRows = 0;
        $totalColumns = 0;
        $limited = false;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                if ($reader->localName === 'dimension') {
                    [$dimensionRows, $dimensionColumns] = $this->dimensions($reader->getAttribute('ref'));
                    $totalRows = max($totalRows, $dimensionRows);
                    $totalColumns = max($totalColumns, $dimensionColumns);

                    continue;
                }

                if ($reader->localName !== 'row') {
                    continue;
                }

                $rowNumber = (int) ($reader->getAttribute('r') ?: count($rows) + 1);
                $totalRows = max($totalRows, $rowNumber);

                if (count($rows) >= self::MAX_ROWS) {
                    $limited = true;

                    break;
                }

                $row = $this->row($reader->readOuterXml(), $sharedStrings);
                $totalColumns = max($totalColumns, $row['columns']);
                $rows[] = [
                    'number' => $rowNumber,
                    'cells' => $row['cells'],
                ];
            }
        } finally {
            $reader->close();
        }

        $limited = $limited
            || $totalRows > self::MAX_ROWS
            || $totalColumns > self::MAX_COLUMNS;

        return [
            'rows' => $rows,
            'visible_columns' => min(max($totalColumns, 1), self::MAX_COLUMNS),
            'total_rows' => $totalRows,
            'total_columns' => $totalColumns,
            'limited' => $limited,
        ];
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array{cells: array<int, string>, columns: int}
     */
    private function row(string $xml, array $sharedStrings): array
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return ['cells' => [], 'columns' => 0];
        }

        $xpath = new DOMXPath($document);
        $cells = [];
        $maximumColumn = 0;

        foreach ($xpath->query('//*[local-name()="c"]') ?: [] as $cell) {
            if (! $cell instanceof DOMElement) {
                continue;
            }

            $column = $this->columnIndex($cell->getAttribute('r'));
            $maximumColumn = max($maximumColumn, $column);
            if ($column < 1 || $column > self::MAX_COLUMNS) {
                continue;
            }

            $type = $cell->getAttribute('t');
            $valueNode = $xpath->query('./*[local-name()="v"]', $cell)?->item(0);
            $value = $valueNode?->textContent ?? '';

            if ($type === 's') {
                $value = $sharedStrings[(int) $value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = '';
                foreach ($xpath->query('.//*[local-name()="t"]', $cell) ?: [] as $textNode) {
                    $value .= $textNode->textContent;
                }
            } elseif ($type === 'b') {
                $value = $value === '1' ? 'VERDADERO' : 'FALSO';
            }

            $cells[$column] = $value;
        }

        return ['cells' => $cells, 'columns' => $maximumColumn];
    }

    private function xml(ZipArchive $zip, string $path): DOMDocument
    {
        $contents = $zip->getFromName($path);
        if ($contents === false) {
            throw new RuntimeException('Falta una parte requerida del archivo XLSX.');
        }

        $document = new DOMDocument;
        if (! $document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Una parte del archivo XLSX contiene XML inválido.');
        }

        return $document;
    }

    private function reader(string $xlsxPath, string $entry): XMLReader
    {
        $reader = new XMLReader;
        $uri = 'zip://'.str_replace('\\', '/', $xlsxPath).'#'.$entry;
        if (! $reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('No fue posible leer una hoja del archivo XLSX.');
        }

        return $reader;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dimensions(?string $reference): array
    {
        if (! $reference || ! preg_match('/(?:[A-Z]+\d+:)?([A-Z]+)(\d+)$/i', $reference, $matches)) {
            return [0, 0];
        }

        return [(int) $matches[2], $this->columnIndex($matches[1])];
    }

    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }

        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index;
    }

    private function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
