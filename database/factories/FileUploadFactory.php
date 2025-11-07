<?php

namespace Database\Factories;

use App\Models\FileUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FileUpload>
 */
class FileUploadFactory extends Factory
{
    protected $model = FileUpload::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_name' => fake()->word() . '.csv',
            'file_hash' => fake()->sha256(),
            'file_path' => 'uploads/' . fake()->word() . '.csv',
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the file upload is processing.
     */
    public function processing(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'processing',
            'total_rows' => fake()->numberBetween(100, 1000),
            'processed_rows' => fake()->numberBetween(0, 500),
        ]);
    }

    /**
     * Indicate that the file upload is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalRows = fake()->numberBetween(100, 1000);
            return [
                'status' => 'completed',
                'total_rows' => $totalRows,
                'processed_rows' => $totalRows,
            ];
        });
    }

    /**
     * Indicate that the file upload has failed.
     */
    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }
}
