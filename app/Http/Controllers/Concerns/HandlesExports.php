<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HandlesExports
{
    /**
     * Export collection data in the requested format.
     */
    protected function exportData(Collection $records, string $format, string $filename, array $headings): Response|StreamedResponse
    {
        $rows = $records->map(function ($record) use ($headings) {
            return collect($headings)->map(function ($heading, $key) use ($record) {
                $value = data_get($record, $key);

                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }

                if (is_array($value)) {
                    return json_encode($value);
                }

                return $value;
            })->values()->all();
        });

        return match ($format) {
            'csv' => $this->exportCsv($headings, $rows, $filename),
            'pdf' => $this->exportPdf($headings, $rows, $filename),
            default => $this->exportExcel($headings, $rows, $filename),
        };
    }

    protected function exportCsv(array $headings, Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_values($headings));

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function exportExcel(array $headings, Collection $rows, string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $export = new class($headings, $rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(
                protected array $headings,
                protected Collection $rows
            ) {}

            public function array(): array
            {
                return $this->rows->all();
            }

            public function headings(): array
            {
                return array_values($this->headings);
            }
        };

        return Excel::download($export, $filename.'.xlsx');
    }

    protected function exportPdf(array $headings, Collection $rows, string $filename): Response
    {
        $pdf = app('dompdf.wrapper');
        $html = view('exports.table', [
            'title' => $filename,
            'headings' => array_values($headings),
            'rows' => $rows,
        ])->render();

        $pdf->loadHTML($html);

        return $pdf->download($filename.'.pdf');
    }
}
