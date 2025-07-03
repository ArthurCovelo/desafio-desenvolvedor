<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['csv', 'xlsx', 'xls', 'txt'])
                    ->max(100 * 1024),
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'É necessário enviar um arquivo',
            'file.mimes' => 'O arquivo deve ser do tipo: CSV, Excel ou TXT',
            'file.max' => 'O arquivo não pode ser maior que 100MB',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->hasFile('file')) {
            $file = $this->file('file');
            $this->merge([
                'file_extension' => strtolower($file->getClientOriginalExtension()),
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType()
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('file')) {
                $file = $this->file('file');
                if (strtolower($file->getClientOriginalExtension()) === 'csv') {
                    $this->validateCsvFile($file, $validator);
                }
                if ($file->getSize() > 50 * 1024 * 1024) {
                    $this->validateLargeFile($validator);
                }
            }
        });
    }

    protected function validateCsvFile($file, $validator): void
    {
        try {
            $content = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if (empty($content)) {
                throw new \RuntimeException("Arquivo CSV vazio");
            }

            // Pular linha de status
            $startLine = 0;
            if (str_contains($content[0], 'Status do Arquivo')) {
                $startLine = 1;
            }

            // Processar cabeçalho
            $header = str_getcsv($content[$startLine], ";");
            $normalizedHeader = $this->normalizeHeader($header);

            // Verificar colunas essenciais
            $requiredColumns = ['RPTDT', 'TCKRSYMB', 'ISIN', 'CRPNNM'];
            $foundColumns = [];

            foreach ($requiredColumns as $col) {
                if (in_array($col, $normalizedHeader)) {
                    $foundColumns[] = $col;
                }
            }

            $missingColumns = array_diff($requiredColumns, $foundColumns);

            if (!empty($missingColumns)) {
                $validator->errors()->add(
                    'file',
                    'Cabeçalho incompleto. Faltam colunas: ' . implode(', ', $missingColumns)
                );
            }

        } catch (\Exception $e) {
            $validator->errors()->add('file', 'Erro ao validar arquivo CSV: ' . $e->getMessage());
        }
    }

    protected function normalizeHeader(array $header): array
    {
        return array_map(function($column) {
            $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $column);
            $normalized = preg_replace('/[^a-zA-Z0-9]/', '', $normalized);
            return strtoupper($normalized);
        }, $header);
    }

    protected function validateLargeFile($validator): void
    {
        if (!ini_get('auto_detect_line_endings')) {
            $validator->errors()->add(
                'file',
                'Configuração do servidor inadequada para arquivos grandes'
            );
        }
    }
}
