<?php

namespace App\Jobs;

use App\Models\FileUpload;
use App\Models\ProductData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessFileUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;

    private const BATCH_SIZE = 500; // Process 500 rows at a time

    private array $uniqueKeyCounts = [];

    public function __construct(
        public FileUpload $fileUpload
    )
    {
    }

    public function handle(): void
    {
        Log::info("=== JOB START ===", [
            'id' => $this->fileUpload->id,
            'file' => $this->fileUpload->file_name,
            'batch_size' => self::BATCH_SIZE,
        ]);

        try {
            $this->fileUpload->update(['status' => 'processing']);

            $filePath = $this->fileUpload->file_path;

            if (!Storage::exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            $fullPath = Storage::path($filePath);
            Log::info("Opening file for streaming", ['path' => $fullPath]);

            // Open file for streaming (memory efficient)
            $handle = fopen($fullPath, 'r');
            if (!$handle) {
                throw new \Exception("Cannot open file: {$filePath}");
            }

            // Read header
            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                throw new \Exception("Invalid CSV: no header found");
            }

            // Clean header: remove BOM and trim whitespace
            $header = array_map(function ($h) {
                // Remove UTF-8 BOM if present
                $h = str_replace("\xEF\xBB\xBF", '', $h);
                return trim($h);
            }, $header);
            Log::info("CSV header", ['columns' => $header]);

            // First pass: Count total rows and UNIQUE_KEY occurrences
            $totalRows = 0;
            $uniqueKeyIndex = array_search('UNIQUE_KEY', $header);
            $uniqueKeyCounts = [];

            while (($row = fgetcsv($handle)) !== false) {
                $totalRows++;
                if ($uniqueKeyIndex !== false && isset($row[$uniqueKeyIndex])) {
                    $uniqueKey = $row[$uniqueKeyIndex];
                    $uniqueKeyCounts[$uniqueKey] = ($uniqueKeyCounts[$uniqueKey] ?? 0) + 1;
                }
            }
            rewind($handle);
            fgetcsv($handle); // Skip header again

            $duplicateKeys = array_filter($uniqueKeyCounts, fn($count) => $count > 1);
            Log::info("CSV analysis complete", [
                'total_rows' => $totalRows,
                'unique_keys' => count($uniqueKeyCounts),
                'keys_with_duplicates' => count($duplicateKeys),
            ]);

            $this->fileUpload->update([
                'total_rows' => $totalRows,
                'processed_rows' => 0,
            ]);

            $recordsBeforeImport = ProductData::count();
            Log::info("Starting import", [
                'total_csv_rows' => $totalRows,
                'db_records_before' => $recordsBeforeImport,
            ]);

            // Store unique key counts for later use
            $this->uniqueKeyCounts = $uniqueKeyCounts;

            // Process in batches
            $batch = [];
            $processedCount = 0;
            $rowNumber = 0;
            $batchNumber = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($header)) {
                    Log::warning("Row column count mismatch", ['row' => $rowNumber]);
                    continue;
                }

                $rowData = array_combine($header, $row);
                $batch[] = $this->mapRowData($rowData);

                // Process batch when it reaches BATCH_SIZE
                if (count($batch) >= self::BATCH_SIZE) {
                    $batchNumber++;
                    $this->processBatch($batch);
                    $processedCount += count($batch);

                    $this->fileUpload->update(['processed_rows' => $processedCount]);

                    $percentage = round(($processedCount / $totalRows) * 100, 1);
                    Log::info("Batch {$batchNumber} processed", [
                        'batch_size' => count($batch),
                        'processed' => $processedCount,
                        'total' => $totalRows,
                        'percentage' => $percentage . '%',
                    ]);

                    $batch = [];
                }
            }

            // Process remaining rows
            if (!empty($batch)) {
                $batchNumber++;
                $this->processBatch($batch);
                $processedCount += count($batch);
                $this->fileUpload->update(['processed_rows' => $processedCount]);

                Log::info("Final batch {$batchNumber} processed", [
                    'batch_size' => count($batch),
                    'processed' => $processedCount,
                ]);
            }

            fclose($handle);

            $recordsAfterImport = ProductData::count();
            $newRecords = $recordsAfterImport - $recordsBeforeImport;
            $updatedRecords = $processedCount - $newRecords;

            $this->fileUpload->update(['status' => 'completed']);

            Log::info("=== JOB COMPLETE ===", [
                'csv_rows_processed' => $processedCount,
                'csv_total_rows' => $totalRows,
                'db_records_before' => $recordsBeforeImport,
                'db_records_after' => $recordsAfterImport,
                'new_records_created' => $newRecords,
                'existing_records_updated' => $updatedRecords,
                'batches_processed' => $batchNumber,
            ]);

        } catch (\Exception $e) {
            Log::error("=== JOB FAILED ===", [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            $this->fileUpload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function cleanUtf8(string $value): string
    {
        // Remove non-UTF-8 characters
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        // Remove any invalid UTF-8 sequences
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
        return trim($value);
    }

    private function mapRowData(array $row): array
    {
        $uniqueKey = $row['UNIQUE_KEY'] ?? '';
        $occurrenceCount = $this->uniqueKeyCounts[$uniqueKey] ?? 1;

        return [
            'unique_key' => $this->cleanUtf8($uniqueKey),
            'csv_occurrence_count' => $occurrenceCount,
            'product_title' => $this->cleanUtf8($row['PRODUCT_TITLE'] ?? ''),
            'product_description' => !empty($row['PRODUCT_DESCRIPTION']) ? $this->cleanUtf8($row['PRODUCT_DESCRIPTION']) : null,
            'style_number' => !empty($row['STYLE#']) ? $this->cleanUtf8($row['STYLE#']) : null,
            'sanmar_mainframe_color' => !empty($row['SANMAR_MAINFRAME_COLOR']) ? $this->cleanUtf8($row['SANMAR_MAINFRAME_COLOR']) : null,
            'size' => !empty($row['SIZE']) ? $this->cleanUtf8($row['SIZE']) : null,
            'color_name' => !empty($row['COLOR_NAME']) ? $this->cleanUtf8($row['COLOR_NAME']) : null,
            'piece_price' => !empty($row['PIECE_PRICE']) ? (float)$row['PIECE_PRICE'] : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function processBatch(array $batch): void
    {
        // Use upsert for batch insert/update (Laravel 8+)
        ProductData::upsert(
            $batch,
            ['unique_key'], // Unique key to check
            ['product_title', 'product_description', 'style_number',
                'sanmar_mainframe_color', 'size', 'color_name', 'piece_price',
                'csv_occurrence_count', 'updated_at'] // All columns to update if exists
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->fileUpload->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error("Job failed permanently", [
            'id' => $this->fileUpload->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
