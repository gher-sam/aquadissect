<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class RosterFileReader
{
    public function inspect(UploadedFile $file): array
    {
        $format = strtolower($file->getClientOriginalExtension());
        $this->check(in_array($format, ['csv', 'tsv', 'json', 'xml', 'xls', 'xlsx'], true), 'Convert this file to CSV, TSV, XLSX, XLS, JSON or XML first. PDF and image lists must be exported as a table.');
        $this->check($file->getSize() <= 5 * 1024 * 1024, 'Files must be no larger than 5 MB.');
        try {
            $path = $file->getRealPath();
            $text = in_array($format, ['xml', 'json', 'csv', 'tsv']) ? file_get_contents($path) : '';
            if ($format === 'xml') {
                $this->check(mb_check_encoding($text, 'UTF-8') && ! str_contains($text, "\0"), 'Export XML using UTF-8 encoding.');
                $this->check(! preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $text), 'XML declarations of entities or document types are not allowed.');
            }
            $excelXml = $format === 'xml' && str_contains($text, 'urn:schemas-microsoft-com:office:spreadsheet');
            if (in_array($format, ['xls', 'xlsx']) || $excelXml) {
                $sheets = $this->excel($path, $excelXml ? 'Xml' : ucfirst($format));
            } elseif ($format === 'json') {
                $records = json_decode($text, true, 20, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                if (! array_is_list($records ?? [])) {
                    $records = $records['students'] ?? $records['records'] ?? null;
                }
                $this->check(is_array($records) && array_is_list($records), 'JSON must contain a list of records, or a students/records list. Quote student numbers to retain leading zeros.');
                $sheets = [$this->records('Records', $records)];
            } elseif ($format === 'xml') {
                $this->check(! preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $text), 'XML declarations of entities or document types are not allowed.');
                $previous = libxml_use_internal_errors(true);
                try {
                    $document = new \DOMDocument;
                    $this->check($document->loadXML($text, LIBXML_NONET | LIBXML_NOBLANKS), 'Malformed XML. Export a record-based XML file and try again.');
                    $groups = [];
                    $this->check($document->getElementsByTagName('*')->length <= 70000, 'XML is too complex. Export a flat student table.');
                    foreach ($document->getElementsByTagName('*') as $element) {
                        $this->check(count($groups) <= 64, 'XML has too many record types. Export only the student table.');
                        $fields = [];
                        foreach ($element->attributes as $attribute) {
                            $fields['@'.$attribute->nodeName] = $attribute->nodeValue;
                        }
                        foreach ($element->childNodes as $child) {
                            if ($child instanceof \DOMElement) {
                                if ($child->getElementsByTagName('*')->length || isset($fields[$child->nodeName])) {
                                    $fields = [];
                                    break;
                                }
                                $fields[$child->nodeName] = $child->textContent;
                            }
                        }
                        if ($fields) {
                            $key = preg_replace('/\[\d+\]/', '', $element->getNodePath());
                            $groups[$key][] = $fields;
                            $this->check(count($groups[$key]) <= 1000, 'A record element may contain at most 1,000 students.');
                        }
                    }
                    $sheets = [];
                    foreach ($groups as $name => $records) {
                        $sheets[] = $this->records($name, $records);
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            } else {
                $this->check(mb_check_encoding($text, 'UTF-8') && ! str_contains($text, "\0"), 'Use UTF-8 delimited text, or export as XLSX.');
                $quoted = false;
                for ($offset = 0, $length = strlen($text); $offset < $length; $offset++) {
                    if ($text[$offset] === '"') {
                        if ($quoted && ($text[$offset + 1] ?? '') === '"') {
                            $offset++;
                        } else {
                            $quoted = ! $quoted;
                        }
                    }
                }
                $this->check(! $quoted, 'A quoted field is not closed. Save the table again as CSV or TSV.');
                $handle = fopen($path, 'rb');
                $rows = [];
                try {
                    while (($row = fgetcsv($handle, 0, $format === 'tsv' ? "\t" : ',', '"', '')) !== false) {
                        $rows[] = $row;
                        $this->bounds($rows);
                    }
                } finally {
                    fclose($handle);
                }
                $sheets = [['name' => 'Table', 'rows' => $rows, 'hasHeader' => true]];
            }
            $this->check(count($sheets) > 0 && count($sheets) <= 20, 'Export between one and 20 student tables per file.');
            foreach ($sheets as &$sheet) {
                $this->bounds($sheet['rows']);
                foreach ($sheet['rows'] as $row) {
                    $this->check(count($row) <= 64, 'Use at most 64 columns.');
                    foreach ($row as $value) {
                        $this->check(strlen((string) $value) <= 2000 && mb_check_encoding((string) $value, 'UTF-8'), 'Fields must be UTF-8 text no longer than 2,000 bytes.');
                    }
                }
                $sheet['rows'] = array_map(fn ($row) => array_map(fn ($value) => str_starts_with((string) $value, "\xEF\xBB\xBF") ? substr((string) $value, 3) : (string) ($value ?? ''), $row), $sheet['rows']);
            }

            return ['format' => $format, 'sheets' => $sheets];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['roster' => 'This file is malformed, encrypted or unsupported. Save an unencrypted copy in a supported format and try again.']);
        }
    }

    private function excel(string $path, string $type): array
    {
        if ($type === 'Xlsx') {
            $zip = new \ZipArchive;
            $this->check($zip->open($path) === true, 'Cannot read this Excel workbook.');
            try {
                $expanded = 0;
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $expanded += $zip->statIndex($index)['size'];
                }
                $this->check($expanded <= 32 * 1024 * 1024 && $zip->numFiles <= 1000, 'Workbook is too complex. Export only the student table as CSV.');
            } finally {
                $zip->close();
            }
        }
        $reader = IOFactory::createReader($type);
        $this->check($reader->canRead($path), 'The workbook does not match its file extension, or is encrypted.');
        $info = $reader->listWorksheetInfo($path);
        $this->check(count($info) <= 20, 'Use at most 20 worksheets.');
        foreach ($info as $sheet) {
            $this->check($sheet['totalRows'] <= 1100 && $sheet['totalColumns'] <= 64, 'Export at most 1,000 students, 100 header rows and 64 columns.');
        }
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= 1100 && Coordinate::columnIndexFromString($columnAddress) <= 64;
            }
        });
        $book = $reader->load($path);
        try {
            $sheets = [];
            foreach ($book->getAllSheets() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $values = [];
                    foreach ($row->getCellIterator('A', $sheet->getHighestDataColumn()) as $cell) {
                        $this->check($cell->getDataType() !== DataType::TYPE_FORMULA, 'Formulas are not accepted. Paste values only, then upload again.');
                        $values[] = $cell->getFormattedValue();
                    }
                    $rows[] = $values;
                }
                $sheets[] = ['name' => $sheet->getTitle(), 'rows' => $rows, 'hasHeader' => true];
            }

            return $sheets;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function records(string $name, array $records): array
    {
        $this->check(count($records) > 0 && count($records) <= 1000, 'Provide between one and 1,000 student records.');
        $columns = [];
        foreach ($records as $record) {
            $this->check(is_array($record) && ! array_is_list($record), 'Each record must contain named scalar fields.');
            foreach ($record as $key => $value) {
                $this->check(is_scalar($value) || $value === null, 'Nested fields are not supported. Export a flat student table.');
                $columns[(string) $key] = true;
            }
            $this->check(count($columns) <= 64, 'Use at most 64 fields.');
        }
        $columns = array_keys($columns);

        return ['name' => $name, 'hasHeader' => false, 'rows' => [$columns, ...array_map(fn ($row) => array_map(fn ($column) => $row[$column] ?? '', $columns), $records)]];
    }

    private function bounds(array $rows): void
    {
        $this->check(count($rows) <= 1100, 'Use at most 1,000 students and 100 header rows.');
        foreach ($rows ? [end($rows)] : [] as $row) {
            $this->check(count($row) <= 64, 'Use at most 64 columns.');
            foreach ($row as $value) {
                $this->check(strlen((string) $value) <= 2000, 'A field exceeds 2,000 bytes. Export a smaller student table.');
            }
        }
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['roster' => $message]);
        }
    }
}
