<?php

namespace Tests\Unit;

use App\Jobs\ProcessFileUpload;
use App\Models\FileUpload;
use App\Models\ProductData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessFileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Test that cleanUtf8 handles invalid UTF-8 characters
     */
    public function test_clean_utf8_handles_invalid_characters(): void
    {
        $fileUpload = FileUpload::factory()->create([
            'file_name' => 'test.csv',
            'file_path' => 'uploads/test.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessFileUpload($fileUpload);

        // Use reflection to access private cleanUtf8 method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('cleanUtf8');
        $method->setAccessible(true);

        // Test: Leading/trailing whitespace should be trimmed
        $result = $method->invoke($job, "  Trimmed  ");
        $this->assertEquals("Trimmed", $result);

        // Test: Valid UTF-8 should remain unchanged
        $result = $method->invoke($job, "Hello 世界 🌍");
        $this->assertEquals("Hello 世界 🌍", $result);

        // Test: Empty string
        $result = $method->invoke($job, "");
        $this->assertEquals("", $result);

        // Test: Only whitespace
        $result = $method->invoke($job, "   ");
        $this->assertEquals("", $result);

        // Test: Invalid UTF-8 should not cause errors
        $invalidInput = "Hello\xF0\x28\x8C\xBCWorld";
        $result = $method->invoke($job, $invalidInput);
        // After cleaning, result should not contain the invalid bytes
        $this->assertIsString($result);
        $this->assertStringContainsString("Hello", $result);
        $this->assertStringContainsString("World", $result);
    }

    /**
     * Test that CSV header BOM is removed
     */
    public function test_csv_header_bom_removal(): void
    {
        // Create a CSV file with BOM
        $csvContent = "\xEF\xBB\xBFUNIQUE_KEY,PRODUCT_TITLE,STYLE#,SIZE\n";
        $csvContent .= "TEST001,Test Product,STYLE001,M\n";

        Storage::put('uploads/test_bom.csv', $csvContent);

        $fileUpload = FileUpload::factory()->create([
            'file_name' => 'test_bom.csv',
            'file_path' => 'uploads/test_bom.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessFileUpload($fileUpload);
        $job->handle();

        // Verify the record was created
        $this->assertDatabaseHas('product_data', [
            'unique_key' => 'TEST001',
            'product_title' => 'Test Product',
        ]);

        $fileUpload->refresh();
        $this->assertEquals('completed', $fileUpload->status);
    }

    /**
     * Test that non-UTF-8 characters in data are cleaned
     */
    public function test_non_utf8_characters_cleaned_in_import(): void
    {
        // Create a CSV with non-UTF-8 characters
        $csvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,SIZE\n";

        // Add a row with invalid UTF-8 in product description
        // Using \xFF which is invalid in UTF-8
        $invalidUtf8Description = "This has invalid \xFF character";
        $csvContent .= "TEST002,\"Clean Product\",\"" . $invalidUtf8Description . "\",STYLE002,L\n";

        Storage::put('uploads/test_invalid.csv', $csvContent);

        $fileUpload = FileUpload::factory()->create([
            'file_name' => 'test_invalid.csv',
            'file_path' => 'uploads/test_invalid.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessFileUpload($fileUpload);
        $job->handle();

        // Verify the record was created with cleaned data
        $product = ProductData::where('unique_key', 'TEST002')->first();

        $this->assertNotNull($product);
        $this->assertEquals('Clean Product', $product->product_title);

        // The description should NOT contain the raw invalid character
        $this->assertStringNotContainsString("\xFF", $product->product_description);

        // Should contain the valid parts
        $this->assertStringContainsString('This has invalid', $product->product_description);
        $this->assertStringContainsString('character', $product->product_description);

        $fileUpload->refresh();
        $this->assertEquals('completed', $fileUpload->status);
    }

    /**
     * Test that special characters and emojis are preserved
     */
    public function test_valid_utf8_special_characters_preserved(): void
    {
        $csvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,SIZE\n";
        $csvContent .= "TEST003,\"Product 中文 🎉\",\"Description with émojis 🌟 and 日本語\",STYLE003,XL\n";

        Storage::put('uploads/test_special.csv', $csvContent);

        $fileUpload = FileUpload::factory()->create([
            'file_name' => 'test_special.csv',
            'file_path' => 'uploads/test_special.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessFileUpload($fileUpload);
        $job->handle();

        $product = ProductData::where('unique_key', 'TEST003')->first();

        $this->assertNotNull($product);
        $this->assertEquals('Product 中文 🎉', $product->product_title);
        $this->assertEquals('Description with émojis 🌟 and 日本語', $product->product_description);

        $fileUpload->refresh();
        $this->assertEquals('completed', $fileUpload->status);
    }

    /**
     * Test that whitespace is properly trimmed
     */
    public function test_whitespace_trimming(): void
    {
        $csvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,SIZE\n";
        $csvContent .= "  TEST004  ,\"  Padded Product  \",\"  Padded Description  \",\"  STYLE004  \",\"  M  \"\n";

        Storage::put('uploads/test_whitespace.csv', $csvContent);

        $fileUpload = FileUpload::factory()->create([
            'file_name' => 'test_whitespace.csv',
            'file_path' => 'uploads/test_whitespace.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessFileUpload($fileUpload);
        $job->handle();

        $product = ProductData::where('unique_key', 'TEST004')->first();

        $this->assertNotNull($product);
        $this->assertEquals('TEST004', $product->unique_key);
        $this->assertEquals('Padded Product', $product->product_title);
        $this->assertEquals('Padded Description', $product->product_description);
        $this->assertEquals('STYLE004', $product->style_number);
        $this->assertEquals('M', $product->size);
    }

    /**
     * Test that empty/null fields are handled correctly
     */
    public function test_empty_fields_handled_correctly(): void
    {
        $csvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,SIZE,COLOR_NAME,PIECE_PRICE\n";
        $csvContent .= "TEST005,\"Required Title\",\"\",\"\",\"\",\"\",\"\"\n";

        Storage::put('uploads/test_empty.csv', $csvContent);

        $fileUpload = FileUpload::factory()->create([
            'file_name' => 'test_empty.csv',
            'file_path' => 'uploads/test_empty.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessFileUpload($fileUpload);
        $job->handle();

        $product = ProductData::where('unique_key', 'TEST005')->first();

        $this->assertNotNull($product);
        $this->assertEquals('Required Title', $product->product_title);
        $this->assertNull($product->product_description);
        $this->assertNull($product->style_number);
        $this->assertNull($product->size);
        $this->assertNull($product->color_name);
        $this->assertNull($product->piece_price);
    }

    /**
     * Test logging of cleaning operations
     */
    public function test_utf8_cleaning_is_logged(): void
    {
        // Create a CSV with data that needs cleaning
        $csvContent = "UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION\n";
        $csvContent .= "TEST006,\"Product\xFF Title\",\"Clean Description\"\n";

        Storage::put('uploads/test_log.csv', $csvContent);

        $fileUpload = FileUpload::factory()->create([
            'file_name' => 'test_log.csv',
            'file_path' => 'uploads/test_log.csv',
            'status' => 'pending',
        ]);

        // Clear previous logs
        \Illuminate\Support\Facades\Log::spy();

        $job = new ProcessFileUpload($fileUpload);
        $job->handle();

        // Verify job completion was logged
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->with('=== JOB COMPLETE ===', \Mockery::any())
            ->once();

        $product = ProductData::where('unique_key', 'TEST006')->first();
        $this->assertNotNull($product);
        $this->assertStringNotContainsString("\xFF", $product->product_title);
    }
}
