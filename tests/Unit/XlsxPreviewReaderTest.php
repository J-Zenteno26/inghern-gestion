<?php

namespace Tests\Unit;

use App\Support\XlsxPreviewReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class XlsxPreviewReaderTest extends TestCase
{
    public function test_reads_an_xlsx_from_a_stream(): void
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('No fue posible crear el stream de prueba.');
        }
        fwrite($stream, $this->xlsxContents());
        rewind($stream);

        try {
            $preview = (new XlsxPreviewReader)->readStream($stream);
        } finally {
            fclose($stream);
        }

        $this->assertSame(['Resumen'], $preview['sheets']);
        $this->assertSame('Resumen', $preview['selected_name']);
        $this->assertSame('Producción', $preview['rows'][0]['cells'][1]);
        $this->assertSame('42', $preview['rows'][0]['cells'][2]);
    }

    private function xlsxContents(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'inghern-xlsx-test-');
        if ($path === false) {
            throw new RuntimeException('No fue posible crear el XLSX de prueba.');
        }
        unlink($path);

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible abrir el XLSX de prueba.');
            }

            $zip->addFromString('xl/workbook.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                    <sheets><sheet name="Resumen" sheetId="1" r:id="rId1"/></sheets>
                </workbook>
                XML);
            $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
                </Relationships>
                XML);
            $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                    <dimension ref="A1:B1"/>
                    <sheetData>
                        <row r="1">
                            <c r="A1" t="inlineStr"><is><t>Producción</t></is></c>
                            <c r="B1"><v>42</v></c>
                        </row>
                    </sheetData>
                </worksheet>
                XML);
            $zip->close();

            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('No fue posible leer el XLSX de prueba.');
            }

            return $contents;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
