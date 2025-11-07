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

/**
 * Job to process uploaded CSV files and import product data
 *
 * This job handles large CSV files efficiently by:
 * - Streaming files to minimize memory usage
 * - Processing data in batches for performance
 * - Cleaning UTF-8 encoding to ensure data integrity
 * - Tracking duplicate entries via unique keys
 */
class ProcessFileUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum execution time (30 minutes)
     * Prevents job from running indefinitely
     */
    public $timeout = 1800;

    /**
     * Number of retry attempts if job fails
     */
    public $tries = 3;

    /**
     * Number of rows to process in each batch
     * Balances memory usage with database performance
     */
    private const BATCH_SIZE = 500;

    /**
     * Stores count of how many times each UNIQUE_KEY appears in CSV
     * Used to track duplicate entries
     */
    private array $uniqueKeyCounts = [];

    /**
     * Create a new job instance
     *
     * @param FileUpload $fileUpload The file upload record to process
     */
    public function __construct(
        public FileUpload $fileUpload
    )
    {
    }

    /**
     * Main job execution method
     * Processes CSV file and imports data into database
     *
     * @return void
     * @throws \Exception if file processing fails
     */
    public function handle(): void
    {
        Log::info("=== JOB START ===", [
            'id' => $this->fileUpload->id,
            'file' => $this->fileUpload->file_name,
            'batch_size' => self::BATCH_SIZE,
        ]);

        try {
            // Update status to processing
            $this->fileUpload->update(['status' => 'processing']);

            $filePath = $this->fileUpload->file_path;

            // Verify file exists in storage
            if (!Storage::exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            $fullPath = Storage::path($filePath);
            Log::info("Opening file for streaming", ['path' => $fullPath]);

            // Open file for streaming (memory efficient approach)
            // Using fopen instead of file_get_contents to handle large files
            $handle = fopen($fullPath, 'r');
            if (!$handle) {
                throw new \Exception("Cannot open file: {$filePath}");
            }

            // Read and validate CSV header
            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                throw new \Exception("Invalid CSV: no header found");
            }

            // Clean header: remove BOM (Byte Order Mark) and trim whitespace
            // BOM is sometimes added by Excel and can cause column matching issues
            $header = array_map(function ($h) {
                // Remove UTF-8 BOM (EF BB BF) if present at start of file
                $h = str_replace("\xEF\xBB\xBF", '', $h);
                return trim($h);
            }, $header);
            Log::info("CSV header", ['columns' => $header]);

            // First pass: Analyze CSV to count total rows and track duplicates
            // This helps us provide accurate progress updates and handle duplicates
            $totalRows = 0;
            $uniqueKeyIndex = array_search('UNIQUE_KEY', $header);
            $uniqueKeyCounts = [];

            while (($row = fgetcsv($handle)) !== false) {
                $totalRows++;

                // Track how many times each UNIQUE_KEY appears
                if ($uniqueKeyIndex !== false && isset($row[$uniqueKeyIndex])) {
                    $uniqueKey = $row[$uniqueKeyIndex];
                    $uniqueKeyCounts[$uniqueKey] = ($uniqueKeyCounts[$uniqueKey] ?? 0) + 1;
                }
            }

            // Rewind file pointer to beginning for second pass
            rewind($handle);
            fgetcsv($handle); // Skip header row

            $duplicateKeys = array_filter($uniqueKeyCounts, fn($count) => $count > 1);
            Log::info("CSV analysis complete", [
                'total_rows' => $totalRows,
                'unique_keys' => count($uniqueKeyCounts),
                'keys_with_duplicates' => count($duplicateKeys),
            ]);

            // Update file upload record with total row count
            $this->fileUpload->update([
                'total_rows' => $totalRows,
                'processed_rows' => 0,
            ]);

            // Record current database state for comparison later
            $recordsBeforeImport = ProductData::count();
            Log::info("Starting import", [
                'total_csv_rows' => $totalRows,
                'db_records_before' => $recordsBeforeImport,
            ]);

            // Store unique key counts for use in mapRowData
            $this->uniqueKeyCounts = $uniqueKeyCounts;

            // Second pass: Process and import data in batches
            $batch = [];
            $processedCount = 0;
            $rowNumber = 0;
            $batchNumber = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                // Validate row has correct number of columns
                if (count($row) !== count($header)) {
                    Log::warning("Row column count mismatch", ['row' => $rowNumber]);
                    continue; // Skip malformed rows
                }

                // Combine header with row data to create associative array
                $rowData = array_combine($header, $row);
                $batch[] = $this->mapRowData($rowData);

                // Process batch when it reaches BATCH_SIZE
                if (count($batch) >= self::BATCH_SIZE) {
                    $batchNumber++;
                    $this->processBatch($batch);
                    $processedCount += count($batch);

                    // Update progress in database for UI tracking
                    $this->fileUpload->update(['processed_rows' => $processedCount]);

                    // Log progress for monitoring
                    $percentage = round(($processedCount / $totalRows) * 100, 1);
                    Log::info("Batch {$batchNumber} processed", [
                        'batch_size' => count($batch),
                        'processed' => $processedCount,
                        'total' => $totalRows,
                        'percentage' => $percentage . '%',
                    ]);

                    // Clear batch array to free memory
                    $batch = [];
                }
            }

            // Process any remaining rows that didn't fill a complete batch
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

            // Calculate and log final statistics
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

    /**
     * Clean and normalize UTF-8 encoded strings
     *
     * This method ensures all data is valid UTF-8 by:
     * 1. Converting encoding (handles mixed encodings)
     * 2. Removing invalid UTF-8 byte sequences
     * 3. Trimming whitespace
     *
     * Why this is necessary:
     * - CSV files may contain invalid UTF-8 from various sources
     * - Database requires valid UTF-8 to prevent import errors
     * - Protects against encoding-related bugs
     *
     * @param string $value The string to clean
     * @return string UTF-8 cleaned string with trimmed whitespace
     */
    private function cleanUtf8(string $value): string
    {
        // Step 1: mb_convert_encoding fixes invalid byte sequences
        // Converting UTF-8 to UTF-8 may seem redundant, but it normalizes the encoding
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        // Step 2: iconv with //IGNORE flag removes any remaining invalid sequences
        // Invalid bytes are replaced with '?' or removed depending on the system
        // This is a safety net for edge cases mb_convert_encoding might miss
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        // Step 3: Remove leading and trailing whitespace
        // Ensures consistency and prevents issues with string matching
        return trim($value);
    }

    /**
     * Map CSV row data to database column structure
     *
     * Transforms raw CSV data into the format expected by the database:
     * - Cleans all text fields for UTF-8 validity
     * - Handles optional fields with null values
     * - Converts numeric fields to appropriate types
     * - Adds occurrence count for duplicate tracking
     *
     * @param array $row Associative array of CSV row data (column => value)
     * @return array Mapped data ready for database insertion
     */
    private function mapRowData(array $row): array
    {
        $uniqueKey = $row['UNIQUE_KEY'] ?? '';

        // Get the count of how many times this unique key appears in the CSV
        // Used to track which products have multiple variants (size/color)
        $occurrenceCount = $this->uniqueKeyCounts[$uniqueKey] ?? 1;

        return [
            // Required fields - always cleaned
            'unique_key' => $this->cleanUtf8($uniqueKey),
            'csv_occurrence_count' => $occurrenceCount,
            'product_title' => $this->cleanUtf8($row['PRODUCT_TITLE'] ?? ''),

            // Optional fields - only clean if not empty, otherwise store as null
            // This preserves the distinction between empty string and no data
            'product_description' => !empty($row['PRODUCT_DESCRIPTION'])
                ? $this->cleanUtf8($row['PRODUCT_DESCRIPTION'])
                : null,
            'style_number' => !empty($row['STYLE#'])
                ? $this->cleanUtf8($row['STYLE#'])
                : null,
            'sanmar_mainframe_color' => !empty($row['SANMAR_MAINFRAME_COLOR'])
                ? $this->cleanUtf8($row['SANMAR_MAINFRAME_COLOR'])
                : null,
            'size' => !empty($row['SIZE'])
                ? $this->cleanUtf8($row['SIZE'])
                : null,
            'color_name' => !empty($row['COLOR_NAME'])
                ? $this->cleanUtf8($row['COLOR_NAME'])
                : null,

            // Numeric field - convert to float, default to null if empty
            'piece_price' => !empty($row['PIECE_PRICE'])
                ? (float)$row['PIECE_PRICE']
                : null,

            // Timestamps for tracking record creation and updates
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Insert or update a batch of records in the database
     *
     * Uses Laravel's upsert method for efficient batch processing:
     * - If unique_key exists: updates the record
     * - If unique_key is new: inserts a new record
     * - All done in a single database query for performance
     *
     * @param array $batch Array of mapped row data to insert/update
     * @return void
     */
    private function processBatch(array $batch): void
    {
        ProductData::upsert(
            $batch,
            ['unique_key'], // Column to check for existing records
            [
                // Columns to update if record already exists
                'product_title',
                'product_description',
                'style_number',
                'sanmar_mainframe_color',
                'size',
                'color_name',
                'piece_price',
                'csv_occurrence_count',
                'updated_at'
            ]
        );
    }

    /**
     * Handle job failure after all retry attempts exhausted
     *
     * This method is called automatically by Laravel's queue system
     * when the job fails permanently after max retries
     *
     * @param \Throwable $exception The exception that caused the failure
     * @return void
     */
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
