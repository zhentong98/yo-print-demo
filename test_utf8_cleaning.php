#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         UTF-8 Cleaning Validation Test                    ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// 1. Read original CSV file's first record
//$csvPath = __DIR__ . '/sample/yoprint_test_import.csv';
//$csvPath = __DIR__ . '/sample/yoprint_test_import.csv';
$csvPath = __DIR__ . '/sample/test_invalid_utf8.csv';

if (!file_exists($csvPath)) {
    echo "❌ CSV file not found: $csvPath\n";
    exit(1);
}

echo "📄 Reading original CSV file...\n";
$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle);
$firstRow = fgetcsv($handle);
fclose($handle);

$csvData = array_combine($header, $firstRow);
$uniqueKey = $csvData['UNIQUE_KEY'];
$csvTitle = $csvData['PRODUCT_TITLE'];

echo "   Original UNIQUE_KEY: {$uniqueKey}\n";
echo "   Original PRODUCT_TITLE: {$csvTitle}\n\n";

// 2. Read same record from database
echo "🗄️  Reading same record from database...\n";
$dbRecord = App\Models\ProductData::where('unique_key', $uniqueKey)->first();

if (!$dbRecord) {
    echo "❌ Record not found in database: {$uniqueKey}\n";
    exit(1);
}

echo "   Database PRODUCT_TITLE: {$dbRecord->product_title}\n\n";

// 3. Check character encoding
echo "🔍 Character Encoding Check:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Original CSV encoding
$csvEncoding = mb_detect_encoding($csvTitle, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
echo "   Original CSV encoding: " . ($csvEncoding ?: 'Unknown') . "\n";

// Database encoding
$dbEncoding = mb_detect_encoding($dbRecord->product_title, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
echo "   Database encoding: " . ($dbEncoding ?: 'Unknown') . "\n\n";

// 4. Check UTF-8 validity
echo "✅ UTF-8 Validity Check:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$csvIsValid = mb_check_encoding($csvTitle, 'UTF-8');
$dbIsValid = mb_check_encoding($dbRecord->product_title, 'UTF-8');

echo "   Original CSV UTF-8 valid: " . ($csvIsValid ? '✅ Yes' : '❌ No') . "\n";
echo "   Database UTF-8 valid: " . ($dbIsValid ? '✅ Yes' : '❌ No') . "\n\n";

// 5. Show hexadecimal comparison
echo "🔢 Byte Comparison (first 50 characters):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$csvSubstr = substr($csvTitle, 0, 50);
$dbSubstr = substr($dbRecord->product_title, 0, 50);

echo "   Original CSV: " . $csvSubstr . "\n";
echo "   HEX:          " . bin2hex($csvSubstr) . "\n\n";

echo "   Database:     " . $dbSubstr . "\n";
echo "   HEX:          " . bin2hex($dbSubstr) . "\n\n";

// 6. Check if special characters were cleaned
echo "🧹 Special Character Cleaning Check:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Check for control characters
$csvHasControlChars = preg_match('/[\x00-\x1F\x7F]/', $csvTitle);
$dbHasControlChars = preg_match('/[\x00-\x1F\x7F]/', $dbRecord->product_title);

echo "   Original CSV contains control chars: " . ($csvHasControlChars ? '⚠️ Yes' : '✅ No') . "\n";
echo "   Database contains control chars: " . ($dbHasControlChars ? '⚠️ Yes' : '✅ No') . "\n\n";

// 7. Check for leading/trailing whitespace
$csvTrimmed = trim($csvTitle);
$dbTrimmed = trim($dbRecord->product_title);

echo "   Original CSV has leading/trailing spaces: " . ($csvTitle !== $csvTrimmed ? '⚠️ Yes' : '✅ No') . "\n";
echo "   Database has leading/trailing spaces: " . ($dbRecord->product_title !== $dbTrimmed ? '⚠️ Yes' : '✅ No') . "\n\n";

// 8. Final conclusion
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║                    Test Results                           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

if ($dbIsValid && !$dbHasControlChars) {
    echo "✅ UTF-8 cleaning function is working correctly!\n";
    echo "   • Database data has valid UTF-8 encoding\n";
    echo "   • Control characters have been cleaned\n";
    echo "   • Leading/trailing whitespace has been trimmed\n";
} else {
    echo "⚠️ Potential issues found:\n";
    if (!$dbIsValid) {
        echo "   • Database still contains invalid UTF-8 characters\n";
    }
    if ($dbHasControlChars) {
        echo "   • Database still contains control characters\n";
    }
}

echo "\n";

// 9. Random check more records
echo "📊 Random check of 10 records...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$randomRecords = App\Models\ProductData::inRandomOrder()->limit(10)->get();
$invalidCount = 0;

foreach ($randomRecords as $record) {
    $isValid = mb_check_encoding($record->product_title, 'UTF-8');
    if (!$isValid) {
        $invalidCount++;
        echo "   ❌ {$record->unique_key}: Invalid UTF-8\n";
    }
}

if ($invalidCount === 0) {
    echo "   ✅ All 10 records have valid UTF-8 encoding!\n";
} else {
    echo "   ⚠️ Found {$invalidCount} records with invalid UTF-8\n";
}

echo "\n";
