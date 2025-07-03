<?php

namespace App\Jobs;

use App\Models\FileUpload;
use App\Models\FinancialData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessFinancialFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected FileUpload $fileUpload;

    private const REQUIRED_COLUMNS = [
        'RptDt'      => 'report_date',
        'TckrSymb'   => 'ticker_symbol',
        'MktNm'      => 'market_name',
        'SctyCtgyNm' => 'security_category',
        'ISIN'       => 'isin',
        'CrpnNm'     => 'corporate_name',
    ];

    public function __construct(FileUpload $fileUpload)
    {
        $this->fileUpload = $fileUpload;
    }

    public function handle()
    {
        Log::channel('processing')->info("Iniciando processamento do arquivo: {$this->fileUpload->original_name}");

        $this->fileUpload->status = 'processing';
        $this->fileUpload->save();

        $path = Storage::disk('local')->path('uploads/' . $this->fileUpload->stored_name);
        Log::channel('processing')->info("Tentando abrir arquivo em: {$path}");

        if (!file_exists($path)) {
            $this->fileUpload->status = 'failed';
            $this->fileUpload->save();
            Log::channel('processing')->error("Arquivo não encontrado: {$path}");
            return;
        }

        try {
            $extension = strtolower($this->fileUpload->getExtension());

            if (in_array($extension, ['csv'])) {
                $rows = $this->loadCsv($path);
            } elseif (in_array($extension, ['xls', 'xlsx'])) {
                $rows = $this->loadExcel($path);
            } else {
                throw new \Exception("Extensão de arquivo não suportada: {$extension}");
            }

            $this->processRows($rows);

            $this->fileUpload->status = 'processed';
            $this->fileUpload->save();
            Log::channel('processing')->info("Arquivo processado com sucesso: {$this->fileUpload->original_name}");

        } catch (\Exception $e) {
            $this->fileUpload->status = 'failed';
            $this->fileUpload->save();
            Log::channel('processing')->error("Erro no processamento do arquivo {$this->fileUpload->original_name}: {$e->getMessage()}");
        }
    }

    private function normalize(string $str): string
    {
        return strtolower(preg_replace('/\s+/', '', $str));
    }

    private function mapColumns(array $header): array
    {
        $normalizedHeaders = array_map([$this, 'normalize'], $header);
        $map = [];

        foreach (self::REQUIRED_COLUMNS as $expected => $fieldName) {
            $normalizedExpected = $this->normalize($expected);
            $index = array_search($normalizedExpected, $normalizedHeaders);
            if ($index === false) {
                throw new \Exception("Coluna obrigatória não encontrada: {$expected}");
            }
            $map[$fieldName] = $index;
        }

        return $map;
    }

    private function loadCsv(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $i => $line) {
        if (stripos($line, 'Status do Arquivo') !== false) {
            unset($lines[$i]);
        }
    }

    $lines = array_values($lines);

    if (count($lines) < 2) {
        throw new \Exception("Arquivo CSV sem dados suficientes.");
    }

    $converted = [];
    foreach ($lines as $line) {
        $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        $utf8Line = mb_convert_encoding($line, 'UTF-8', $encoding ?: 'UTF-8');
        $converted[] = $utf8Line;
    }

    $header = str_getcsv(array_shift($converted), ';');
    $rows = [];

    foreach ($converted as $line) {
        $row = str_getcsv($line, ';');
        $rows[] = $row;
    }

    return [
        'header' => $header,
        'rows'   => $rows,
    ];
}


    private function loadExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        foreach ($rows as $i => $row) {
            if (isset($row[0]) && stripos($row[0], 'Status do Arquivo') !== false) {
                unset($rows[$i]);
            }
        }

        $rows = array_values($rows);

        if (count($rows) < 2) {
            throw new \Exception("Arquivo Excel sem dados suficientes.");
        }

        $header = array_map('trim', array_shift($rows));
        return [
            'header' => $header,
            'rows'   => $rows,
        ];
    }

   private function processRows(array $data)
{
    $header = $data['header'];
    $rows = $data['rows'];

    $mapColumns = $this->mapColumns($header);

    $batch = [];
    $batchSize = 1000;
    $processed = 0;

    foreach ($rows as $row) {
        $data = $this->processRow($row, $mapColumns);

        if ($data) {
            $batch[] = $data;
            $processed++;
        }

        if (count($batch) >= $batchSize) {
            FinancialData::insert($batch);
            $batch = [];
        }
    }

    if (count($batch)) {
        FinancialData::insert($batch);
    }

    $this->fileUpload->total_records = $processed;
    $this->fileUpload->save();

    Log::channel('processing')->info("Linhas processadas: {$processed}");
}


    private function processRow(array $row, array $mapColumns): ?array
    {
        $reportDateRaw = $row[$mapColumns['report_date']] ?? null;
        $ticker = $row[$mapColumns['ticker_symbol']] ?? null;

        if (empty($reportDateRaw) || empty($ticker)) {
            return null;
        }

        $date = \DateTime::createFromFormat('d/m/Y', $reportDateRaw)
            ?: \DateTime::createFromFormat('Y-m-d', $reportDateRaw);

        if (!$date) {
            return null;
        }

        return [
            'report_date'       => $date->format('Y-m-d'),
            'ticker_symbol'     => trim($ticker),
            'market_name'       => $row[$mapColumns['market_name']] ?? null,
            'security_category' => $row[$mapColumns['security_category']] ?? null,
            'isin'              => $row[$mapColumns['isin']] ?? null,
            'corporate_name'    => $row[$mapColumns['corporate_name']] ?? null,
            'file_upload_id'    => $this->fileUpload->id,
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
    }
}
