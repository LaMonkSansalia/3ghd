<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetParser
{
    /**
     * Parse a CSV/Excel file and return headers, a 5-row preview, and total row count.
     */
    public function parse(string $absolutePath): array
    {
        $rows = $this->readFile($absolutePath);
        return $this->structure($rows);
    }

    /**
     * Parse a CSV/Excel file and return ALL data rows as associative arrays (header => value).
     */
    public function allRows(string $absolutePath): array
    {
        $rows = $this->readFile($absolutePath);
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map('strval', $rows[0]);
        $dataRows = array_slice($rows, 1);

        return array_map(
            fn($row) => array_combine(
                $headers,
                array_pad(array_map('strval', $row), count($headers), '')
            ),
            $dataRows
        );
    }

    private function readFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $ext === 'csv' ? $this->readCsv($path) : $this->readExcel($path);
    }

    private function readCsv(string $path): array
    {
        $rows = $this->readCsvWithDelimiter($path, ',');

        // If only 1 column detected, try semicolon as delimiter (common in European Excel exports)
        if (count($rows) > 0 && count($rows[0]) === 1) {
            $rows2 = $this->readCsvWithDelimiter($path, ';');
            if (count($rows2) > 0 && count($rows2[0]) > 1) {
                return $rows2;
            }
        }

        return $rows;
    }

    private function readCsvWithDelimiter(string $path, string $delimiter): array
    {
        $rows = [];
        if (($fh = fopen($path, 'r')) !== false) {
            // Skip BOM if present
            $bom = fread($fh, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($fh);
            }
            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }
            fclose($fh);
        }
        return $rows;
    }

    private function readExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $raw = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        // Filter out fully-empty rows
        return array_values(array_filter(
            $raw,
            fn($r) => count(array_filter($r, fn($c) => $c !== null && $c !== '')) > 0
        ));
    }

    private function structure(array $rows): array
    {
        if (empty($rows)) {
            return ['headers' => [], 'preview' => [], 'total_rows' => 0];
        }

        $headers = array_map('strval', $rows[0]);
        $data = array_slice($rows, 1);

        $preview = array_map(
            fn($row) => array_combine(
                $headers,
                array_pad(array_map('strval', $row), count($headers), '')
            ),
            array_slice($data, 0, 5)
        );

        return [
            'headers'    => $headers,
            'preview'    => $preview,
            'total_rows' => count($data),
        ];
    }
}
