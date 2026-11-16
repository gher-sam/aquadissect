<?php

namespace App\Services\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooter;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooterDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Throwable;
use XMLWriter;

class ReportExports
{
    public function __construct(private readonly ReportCharts $charts) {}

    public function download(array $report, string $format, string $layout)
    {
        $directory = storage_path('app/private/report-exports/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/report.'.$format;
        try {
            $this->write($report, $format, $layout, $path);
        } catch (Throwable $error) {
            File::deleteDirectory($directory);
            throw $error;
        }
        $mime = ['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'csv' => 'text/csv; charset=UTF-8', 'xml' => 'application/xml; charset=UTF-8'][$format];

        return response()->streamDownload(function () use ($path, $directory) {
            try {
                readfile($path);
            } finally {
                File::deleteDirectory($directory);
            }
        }, 'AquaDissect-'.$layout.'-'.$report['filters']['to'].'.'.$format,
            ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Public to allow deterministic parser and rendering checks without a production request. */
    public function write(array $report, string $format, string $layout, string $path): void
    {
        $tables = $this->tables($report, $layout);
        if ($format === 'csv') {
            $this->csv($report, $tables, $path);

            return;
        }
        if ($format === 'xml') {
            $this->xml($report, $tables, $path);

            return;
        }
        $definitions = $this->charts->definitions($report);
        if ($format === 'xlsx') {
            $this->xlsx($report, $tables, $definitions, $path);

            return;
        }
        $images = [];
        try {
            foreach ($definitions as $i => $chart) {
                $file = $path.'.chart-'.$i.'.png';
                $this->charts->png($chart, $file);
                $images[] = $file;
                $definitions[$i]['image'] = $file;
            }
            if ($format === 'pdf') {
                $this->pdf($report, $tables, $definitions, $layout, $path);
            } elseif ($format === 'docx') {
                $this->docx($report, $tables, $definitions, $layout, $path);
            } else {
                throw new \InvalidArgumentException('Unsupported report format.');
            }
        } finally {
            foreach ($images as $image) {
                @unlink($image);
            }
        }
    }

    public function tables(array $r, string $layout): array
    {
        $s = $r['summary'];
        $tables = [['name' => 'Summary', 'headers' => ['Metric', 'Value'], 'rows' => [
            ['Active learners', $s['learners']], ['Active section enrollments', $s['enrollments']],
            ['Assigned learner activity items', $s['assignedItems']], ['Completed items', $s['completedItems']],
            ['Documented exceptions', $s['exceptionItems']], ['Completion percent excluding exceptions', $s['completionRate']],
            ['Mean latest formative percent', $s['meanScore']], ['Median latest formative percent', $s['medianScore']],
            ['Latest formative results counted', $s['scoreSamples']], ['Learners needing review', $s['needsReview']],
            ['Records in period', $s['recordCount']], ['Unattributed historical records', $s['historicalCount']],
            ...($s['practiceXp'] !== null ? [['Your recorded practice XP', $s['practiceXp']]] : []),
        ]], ['name' => 'Assigned activity completion', 'headers' => ['Activity', 'Assigned', 'Completed', 'In progress', 'Prerequisite pending', 'Not recorded', 'Exceptions'],
            'rows' => array_map(fn ($c) => [$c['label'], $c['total'], $c['completed'], $c['inProgress'], $c['blocked'], $c['notRecorded'], $c['exception']], $r['charts']['completion'])],
            ['name' => 'Recorded activity trend', 'headers' => ['Period starting', 'Records', 'Unique learners'],
                'rows' => array_map(fn ($p) => [$p['date'], $p['records'], $p['learners']], $r['charts']['trend']['points'])],
            ['name' => 'Formative score distribution', 'headers' => ['Percent band', 'Latest results'],
                'rows' => array_map(fn ($p) => [$p['label'], $p['count']], $r['charts']['distribution'])],
            ['name' => 'Paired assessments', 'headers' => ['Instrument', 'Version', 'Section ID', 'Matched pairs', 'Unpaired learners', 'Voided attempts', 'Pre mean percent', 'Post mean percent', 'Difference percentage points'],
                'rows' => array_map(fn ($a) => [$a['title'], $a['instrumentVersion'], $a['classId'], $a['matchedPairs'], $a['unpairedLearners'], $a['voidedAttempts'], $a['preMean'], $a['postMean'], $a['difference']], $r['assessments'])],
            ['name' => 'Forecast basis', 'headers' => ['Measure', 'Value'], 'rows' => [
                ['Status', $r['forecast']['message']], ['Method', $r['forecast']['method'] ?? 'Not available'],
                ['Eligible assigned cohort', $r['forecast']['cohort'] ?? null], ['Current completions', $r['forecast']['completed'] ?? null],
                ['Complete history days used', $r['forecast']['windowDays'] ?? null], ['First completions observed', $r['forecast']['observedCompletions'] ?? null],
                ['Average daily completion pace', $r['forecast']['dailyPace'] ?? null], ['Forecast horizon days', $r['filters']['horizon']],
                ['Expected completions at horizon', $r['forecast']['expectedAtHorizon'] ?? null], ['Estimated cohort completion within horizon', $r['forecast']['expectedCompletionDate'] ?? null],
            ]],
            ['name' => 'Forecast recorded history', 'headers' => ['Date', 'Recorded completions'],
                'rows' => array_map(fn ($p) => [$p['date'], $p['completed']], $r['forecast']['history'])],
            ['name' => 'Completion forecast', 'headers' => ['Date', 'Expected completions', 'Slower pace', 'Faster pace'],
                'rows' => array_map(fn ($p) => [$p['date'], $p['expected'], $p['low'], $p['high']], $r['forecast']['projection'])],
        ];
        if ($layout === 'detailed') {
            $tables[] = ['name' => 'Learner support', 'headers' => ['Learner', 'Student ID', 'Section', 'Activity', 'Status', 'Review reasons', 'Latest formative percent'],
                'rows' => array_map(fn ($a) => [$a['learner'], $a['studentId'], $a['section'], $a['activity'], $a['status'], implode('; ', $a['reasons']), $a['latestScore']], $r['support'])];
            foreach ([true, false] as $attributed) {
                $tables[] = ['name' => $attributed ? 'Section learning records' : 'Unattributed historical records',
                    'headers' => ['Type', 'Learner', 'Student ID', 'Section', 'Activity', 'Score', 'Total', 'Practice XP', 'Status', 'Recorded PHT'],
                    'rows' => array_map(fn ($a) => [$a['kind'], $a['learner'], $a['studentId'], $a['section'], $a['activity'], $a['score'], $a['total'], $a['xp'] ?? null, $a['status'], $this->date($a['date'])],
                        array_values(array_filter($r['rows'], fn ($a) => $a['attributed'] === $attributed)))];
            }
        }

        return $tables;
    }

    private function metadata(array $r): array
    {
        return [['Institution', ReportBrand::INSTITUTION], ['Campus', ReportBrand::CAMPUS], ['Contact', ReportBrand::EMAIL],
            ['Website', ReportBrand::WEBSITE], ['Report', 'AquaDissect Learning Analytics Report'],
            ['Period', ReportBrand::period($r)], ['Scope', ReportBrand::scope($r)], ['Generated PHT', $this->date($r['meta']['generatedAt'])],
            ['Generated by role', ucfirst(str_replace('_', ' ', $r['meta']['role']))], ['Status basis', $r['meta']['statusBasis']],
            ['Forecast method', $r['forecast']['method'] ?? 'Not available for this selection'], ['Forecast status', $r['forecast']['message']],
            ['Forecast assumption', $r['forecast']['assumption'] ?? ''], ['Score review threshold', $r['filters']['scoreThreshold'] ?? 'Disabled'],
            ['Interpretation', $r['meta']['boundary']]];
    }

    private function csv(array $r, array $tables, string $path): void
    {
        $f = fopen($path, 'wb');
        fwrite($f, "\xEF\xBB\xBF");
        $write = function (array $values) use ($f) {
            fputcsv($f, array_map([$this, 'safeText'], $values), ',', '"', '');
        };
        foreach ($this->metadata($r) as $row) {
            $write($row);
        }
        foreach ($tables as $t) {
            $write([]);
            $write([$t['name']]);
            $write($t['headers']);
            foreach ($t['rows'] as $row) {
                $write($row);
            }
        }
        $write([]);
        $write(['End of report', ReportBrand::INSTITUTION.' · Bontoc Campus']);
        $write(['Generated PHT', $this->date($r['meta']['generatedAt'])]);
        $write(['Institutional values', ReportBrand::VALUES]);
        fclose($f);
    }

    private function xml(array $r, array $tables, string $path): void
    {
        $x = new XMLWriter;
        $x->openURI($path);
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('aquadissectReport');
        $x->writeAttribute('schemaVersion', '1');
        $x->startElement('header');
        foreach ($this->metadata($r) as [$name, $value]) {
            $x->startElement('field');
            $x->writeAttribute('name', $name);
            $x->text((string) $value);
            $x->endElement();
        }
        $x->endElement();
        $x->startElement('sections');
        foreach ($tables as $table) {
            $x->startElement('section');
            $x->writeAttribute('name', $table['name']);
            foreach ($table['rows'] as $row) {
                $x->startElement('row');
                foreach ($row as $i => $value) {
                    $x->startElement('cell');
                    $x->writeAttribute('name', $table['headers'][$i]);
                    if ($value === null) {
                        $x->writeAttribute('missing', 'true');
                    } else {
                        $x->text((string) $value);
                    } $x->endElement();
                }
                $x->endElement();
            }
            $x->endElement();
        }
        $x->endElement();
        $x->startElement('footer');
        $x->writeElement('institution', ReportBrand::INSTITUTION);
        $x->writeElement('campus', 'Bontoc Campus');
        $x->writeElement('generatedAt', $r['meta']['generatedAt']);
        $x->writeElement('values', ReportBrand::VALUES);
        $x->endElement();
        $x->endElement();
        $x->endDocument();
        $x->flush();
    }

    private function pdf(array $r, array $tables, array $charts, string $layout, string $path): void
    {
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans',
            'tempDir' => dirname($path), 'fontCache' => dirname($path)]);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', $layout === 'detailed' ? 'landscape' : 'portrait');
        $pdf->loadHtml(view('reports.generated', ['report' => $r, 'tables' => $tables, 'charts' => $charts, 'layout' => $layout])->render(), 'UTF-8');
        $pdf->render();
        foreach (['Title' => 'AquaDissect Learning Analytics Report', 'Author' => 'AquaDissect', 'Creator' => 'AquaDissect'] as $key => $value) {
            $pdf->addInfo($key, $value);
        }
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 108, $canvas->get_height() - 25, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7);
        file_put_contents($path, $pdf->output());
    }

    private function docx(array $r, array $tables, array $charts, string $layout, string $path): void
    {
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $word->getDocInfo()->setCreator('AquaDissect')->setLastModifiedBy('AquaDissect')->setTitle('AquaDissect Learning Analytics Report');
        $word->addTitleStyle(0, ['size' => 18, 'bold' => true, 'color' => '000000'], ['spaceAfter' => 160]);
        $word->addTitleStyle(1, ['size' => 13, 'bold' => true, 'color' => '000000'], ['spaceBefore' => 140, 'spaceAfter' => 100, 'keepNext' => true]);
        $word->addTableStyle('ReportTable', ['borderSize' => 4, 'borderColor' => 'D8E0EC', 'cellMargin' => 80]);
        $settings = ['paperSize' => 'A4', 'marginTop' => 2400, 'marginBottom' => 1600, 'marginLeft' => 750, 'marginRight' => 750, 'headerHeight' => 200, 'footerHeight' => 200];
        $section = $word->addSection($settings);
        $this->wordBrand($section, $r);
        $section->addTitle('Learning Analytics Report', 0);
        foreach ([ReportBrand::period($r), ReportBrand::scope($r), 'Generated '.$this->date($r['meta']['generatedAt'])] as $text) {
            $section->addText($text, ['size' => 9], ['spaceAfter' => 70]);
        }
        $section->addText($r['meta']['statusBasis'], ['size' => 9], ['spaceAfter' => 120]);
        $this->wordTable($section, $tables[0]);
        $section->addTitle('Forecast interpretation', 1);
        $section->addText($r['forecast']['message'], ['size' => 10]);
        if (isset($r['forecast']['assumption'])) {
            $section->addText($r['forecast']['assumption'], ['size' => 9]);
        }
        $section->addText($r['meta']['boundary'], ['size' => 9]);
        $section->addText('Score review threshold: '.($r['filters']['scoreThreshold'] ?? 'Disabled').'.', ['size' => 9]);
        foreach ($charts as $chart) {
            $section->addPageBreak();
            $section->addTitle($chart['title'], 1);
            $section->addImage($chart['image'], ['width' => 490, 'ratio' => true, 'wrappingStyle' => 'inline']);
            $section->addText($chart['note'], ['size' => 9]);
        }
        // Numeric source tables make every graph auditable in the exported document.
        $section = $word->addSection($settings + ['orientation' => 'landscape']);
        $this->wordBrand($section, $r);
        foreach (array_slice($tables, 1) as $table) {
            if (! $table['rows']) {
                continue;
            }
            $section->addTitle($table['name'], 1);
            $this->wordTable($section, $table);
        }
        $section->addText('Missing values are shown as Not recorded. Zero is a recorded numeric value.', ['size' => 9]);
        IOFactory::createWriter($word, 'Word2007')->save($path);
    }

    private function wordBrand($section, array $r): void
    {
        $header = $section->addHeader();
        $width = $section->getStyle()->getPageSizeW() - 1500;
        $t = $header->addTable(['cellMargin' => 0, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'borderStyle' => 'nil']);
        $t->addRow();
        $t->addCell($width - 1600)->addImage(ReportBrand::path('university'), ['width' => 290, 'ratio' => true]);
        $t->addCell(1600)->addImage(ReportBrand::path('bagong-pilipinas'), ['width' => 62, 'ratio' => true, 'alignment' => 'right']);
        $header->addText(ReportBrand::CAMPUS, ['size' => 8], ['spaceAfter' => 0]);
        $header->addText(ReportBrand::EMAIL.' | '.ReportBrand::WEBSITE, ['size' => 8], ['spaceAfter' => 0]);
        $header->addText(ReportBrand::VALUES, ['size' => 6], ['spaceAfter' => 0]);
        $footer = $section->addFooter();
        $t = $footer->addTable(['cellMargin' => 0, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'borderStyle' => 'nil']);
        $t->addRow();
        $t->addCell($width - 1800)->addImage(ReportBrand::path('qs-stars'), ['width' => 150, 'ratio' => true]);
        $t->addCell(1800)->addImage(ReportBrand::path('socotec'), ['width' => 110, 'ratio' => true, 'alignment' => 'right']);
        $footer->addPreserveText('AquaDissect | Bontoc Campus | Page {PAGE} of {NUMPAGES}', ['size' => 7]);
        $footer->addText('Generated '.$this->date($r['meta']['generatedAt']).' | '.ucfirst(str_replace('_', ' ', $r['meta']['role'])), ['size' => 7], ['spaceAfter' => 0]);
    }

    private function wordTable($section, array $data): void
    {
        $table = $section->addTable('ReportTable');
        $table->addRow(null, ['tblHeader' => true]);
        $count = count($data['headers']);
        $width = $section->getStyle()->getPageSizeW() - 1500;
        foreach ($data['headers'] as $name) {
            $table->addCell((int) ($width / $count), ['bgColor' => 'EAF0FA'])->addText($name, ['bold' => true, 'size' => $count > 7 ? 8 : 9], ['spaceAfter' => 0]);
        }
        foreach ($data['rows'] as $row) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach ($row as $value) {
                $table->addCell((int) ($width / $count))->addText($value === null ? 'Not recorded' : (string) $value, ['size' => $count > 7 ? 8 : 9], ['spaceAfter' => 0]);
            }
        }
    }

    private function xlsx(array $r, array $tables, array $charts, string $path): void
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('AquaDissect')->setLastModifiedBy('AquaDissect')->setTitle('AquaDissect Learning Analytics Report');
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $headerPath = $path.'.header.png';
        $this->excelHeaderImage($headerPath);
        try {
            $summary = $book->getActiveSheet()->setTitle('Summary');
            $this->sheetBrand($summary, $r, $headerPath);
            $drawing = new Drawing;
            $drawing->setPath($headerPath)->setCoordinates('A1')->setWidth(900)->setWorksheet($summary);
            $summary->getRowDimension(1)->setRowHeight(150);
            $row = 3;
            foreach ($this->metadata($r) as [$key, $value]) {
                $this->cellValue($summary, 1, $row, $key);
                $this->cellValue($summary, 2, $row, $value);
                $summary->getRowDimension($row)->setRowHeight(max(15, (int) ceil(mb_strwidth((string) $value) / 95) * 13));
                $row++;
            }
            $summary->getColumnDimension('A')->setWidth(42);
            $summary->getColumnDimension('B')->setWidth(105);
            $summary->getStyle('B3:B'.$row)->getAlignment()->setWrapText(true);
            foreach ($tables[0]['rows'] as $values) {
                foreach ($values as $col => $value) {
                    $this->cellValue($summary, $col + 1, $row, $value);
                } $row++;
            }
            $summary->getPageSetup()->setPrintArea('A3:B'.($row - 1))->setFitToHeight(1);
            foreach (array_slice($tables, 1) as $i => $table) {
                if (! $table['rows']) {
                    continue;
                }
                $sheet = $book->createSheet()->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $table['name']), 0, 31));
                $this->sheetBrand($sheet, $r, $headerPath);
                $this->writeSheetTable($sheet, $table);
            }
            $chartSheet = $book->createSheet()->setTitle('Charts');
            $this->sheetBrand($chartSheet, $r, $headerPath);
            $chartSheet->getDefaultColumnDimension()->setWidth(7);
            $chartSheet->getDefaultRowDimension()->setRowHeight(18);
            $chartSheet->getPageSetup()->setPrintArea('A1:N'.max(23, count($charts) * 23));
            $dataSheet = $book->createSheet()->setTitle('Chart data');
            $this->sheetBrand($dataSheet, $r, $headerPath);
            $sourceRow = 1;
            foreach ($charts as $i => $definition) {
                $start = $sourceRow;
                $this->cellValue($dataSheet, 1, $sourceRow, $definition['title']);
                foreach ($definition['series'] as $j => $series) {
                    $this->cellValue($dataSheet, $j + 2, $sourceRow, $series['name']);
                }
                foreach ($definition['labels'] as $k => $label) {
                    $this->cellValue($dataSheet, 1, $sourceRow + 1 + $k, $label);
                    foreach ($definition['series'] as $j => $series) {
                        $this->cellValue($dataSheet, $j + 2, $sourceRow + 1 + $k, $series['values'][$k]);
                    }
                }
                $end = $sourceRow + count($definition['labels']);
                $seriesLabels = [];
                $seriesValues = [];
                foreach ($definition['series'] as $j => $series) {
                    $column = Coordinate::stringFromColumnIndex($j + 2);
                    $seriesLabels[] = new DataSeriesValues('String', "'Chart data'!\$".$column.'$'.$start, null, 1);
                    $seriesValues[] = new DataSeriesValues('Number', "'Chart data'!\$".$column.'$'.($start + 1).':$'.$column.'$'.$end, null, count($definition['labels']));
                }
                $categories = [new DataSeriesValues('String', "'Chart data'!\$A$".($start + 1).':$A$'.$end, null, count($definition['labels']))];
                $series = new DataSeries($definition['type'] === 'line' ? DataSeries::TYPE_LINECHART : DataSeries::TYPE_BARCHART,
                    $definition['type'] === 'line' ? null : DataSeries::GROUPING_CLUSTERED, range(0, count($seriesValues) - 1), $seriesLabels, $categories, $seriesValues);
                $chart = new Chart('reportChart'.$i, new Title($definition['title']), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$series]), true, 'gap');
                $chart->setTopLeftPosition('A'.(1 + $i * 23))->setBottomRightPosition('N'.(22 + $i * 23));
                $chartSheet->addChart($chart);
                if ($i > 0) {
                    $chartSheet->setBreak('A'.($i * 23), Worksheet::BREAK_ROW);
                }
                $sourceRow = $end + 3;
            }
            if (! $charts) {
                $chartSheet->setCellValue('A1', 'No chart data is available for this selection.');
            }
            $book->setActiveSheetIndex(0);
            $writer = new Xlsx($book);
            $writer->setIncludeCharts(true);
            $writer->save($path);
            $book->disconnectWorksheets();
        } finally {
            @unlink($headerPath);
        }
    }

    private function sheetBrand(Worksheet $sheet, array $r, string $headerPath): void
    {
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(1.15)->setBottom(.95)->setLeft(.3)->setRight(.3)->setHeader(.15)->setFooter(.15);
        $image = new HeaderFooterDrawing;
        $image->setPath($headerPath)->setName('SLSU Bontoc header')->setWidth(340);
        $sheet->getHeaderFooter()->addImage($image, HeaderFooter::IMAGE_HEADER_RIGHT)->setOddHeader('&L&"Arial,Bold"&10'.ReportBrand::INSTITUTION."\n".'&"Arial,Regular"&9'.ReportBrand::CAMPUS."\n".'&8'.ReportBrand::EMAIL."\n".ReportBrand::WEBSITE.'&R&G');
        foreach ([['qs-stars', HeaderFooter::IMAGE_FOOTER_LEFT, 130], ['socotec', HeaderFooter::IMAGE_FOOTER_RIGHT, 100]] as [$name, $position, $width]) {
            $mark = new HeaderFooterDrawing;
            $mark->setPath(ReportBrand::path($name))->setName('SLSU institutional footer')->setWidth($width);
            $sheet->getHeaderFooter()->addImage($mark, $position);
        }
        $sheet->getHeaderFooter()->setOddFooter('&L&G&C&8AquaDissect · Bontoc Campus'."\n".'Generated '.$this->date($r['meta']['generatedAt'])."\n".'Page &P of &N&R&G');
        $sheet->getDefaultColumnDimension()->setWidth(22);
        $sheet->freezePane('A2');
    }

    private function writeSheetTable(Worksheet $sheet, array $table): void
    {
        $widths = [];
        foreach ($table['headers'] as $j => $heading) {
            $widths[$j] = match ($heading) {
                'Activity' => 28, 'Learner' => 22, 'Student ID' => 16, 'Section' => 26, 'Review reasons' => 46,
                'Recorded PHT' => 24, 'Score', 'Total' => 8, 'Practice XP' => 10, 'Measure', 'Instrument' => 42,
                'Value' => 70, default => 18,
            };
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($j + 1))->setWidth($widths[$j]);
        }
        foreach ([$table['headers'], ...$table['rows']] as $i => $row) {
            $lines = 1;
            foreach ($row as $j => $value) {
                if ($i > 0 && $value !== null && in_array($table['headers'][$j], ['Date', 'Period starting', 'Recorded PHT'], true)) {
                    $date = Carbon::parse(str_replace(' PHT', '', $value), 'Asia/Manila');
                    $sheet->setCellValue([$j + 1, $i + 1], Date::dateTimeToExcel($date));
                    $sheet->getStyle([$j + 1, $i + 1])->getNumberFormat()->setFormatCode($table['headers'][$j] === 'Recorded PHT' ? 'yyyy-mm-dd hh:mm' : 'yyyy-mm-dd');
                } else {
                    $this->cellValue($sheet, $j + 1, $i + 1, $value);
                }
                $lines = max($lines, (int) ceil(mb_strwidth((string) $value) / max(1, $widths[$j] - 3)));
            }
            $sheet->getRowDimension($i + 1)->setRowHeight(max($i ? 18 : 30, $lines * 14 + 4));
        }
        $lastColumn = Coordinate::stringFromColumnIndex(count($table['headers']));
        $last = max(1, count($table['rows']) + 1);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF173D74');
        $sheet->getStyle('A1:'.$lastColumn.$last)->getAlignment()->setVertical('top')->setWrapText(true);
        $sheet->setAutoFilter('A1:'.$lastColumn.$last);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
        $sheet->getPageSetup()->setPrintArea('A1:'.$lastColumn.$last);
    }

    private function cellValue(Worksheet $sheet, int $column, int $row, $value): void
    {
        if (is_int($value) || is_float($value)) {
            $sheet->setCellValue([$column, $row], $value);
        } elseif ($value !== null) {
            $sheet->setCellValueExplicit([$column, $row], (string) $value, DataType::TYPE_STRING);
        }
    }

    private function excelHeaderImage(string $path): void
    {
        $im = imagecreatetruecolor(1400, 290);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $white);
        $ink = imagecolorallocate($im, 23, 61, 116);
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        foreach ([['university', 10, 0, 650], ['bagong-pilipinas', 1250, 5, 120]] as [$name, $x, $y, $width]) {
            $src = imagecreatefrompng(ReportBrand::path($name));
            $height = (int) round(imagesy($src) * $width / imagesx($src));
            imagecopyresampled($im, $src, $x, $y, 0, 0, $width, $height, imagesx($src), imagesy($src));
            imagedestroy($src);
        }
        foreach ([['Bontoc Campus', 690, 60, 23], ['San Ramon, Bontoc, Southern Leyte', 690, 101, 17], [ReportBrand::EMAIL, 690, 139, 17], [ReportBrand::WEBSITE, 690, 175, 17], [ReportBrand::VALUES, 12, 263, 12]] as [$text, $x, $y, $size]) {
            imagettftext($im, $size, 0, $x, $y, $ink, $font, $text);
        }
        imagepng($im, $path);
        imagedestroy($im);
    }

    public function safeText($value)
    {
        return is_string($value) && preg_match('/^[\s\x{FEFF}]*[=+@-]/u', $value) ? "'".$value : $value;
    }

    private function date(?string $date): string
    {
        return $date ? Carbon::parse($date)->timezone('Asia/Manila')->format('Y-m-d H:i').' PHT' : 'Not recorded';
    }
}
