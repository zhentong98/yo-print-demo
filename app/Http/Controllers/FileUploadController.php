<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFileUpload;
use App\Models\FileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    /**
     * Upload a file and queue processing
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:102400', // Max 100MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            
            // Calculate file hash for idempotency
            $fileHash = hash_file('sha256', $file->getRealPath());
            
            // Check if this file was already uploaded
            $existingUpload = FileUpload::where('file_hash', $fileHash)->first();
            
            if ($existingUpload) {
                // File already exists, reprocess it
                $existingUpload->update([
                    'status' => 'pending',
                    'error_message' => null,
                    'processed_rows' => 0,
                ]);
                
                // Dispatch job to reprocess
                ProcessFileUpload::dispatch($existingUpload);
                
                return response()->json([
                    'success' => true,
                    'message' => 'File already exists, reprocessing...',
                    'data' => [
                        'id' => $existingUpload->id,
                        'file_name' => $existingUpload->file_name,
                        'status' => $existingUpload->status,
                        'created_at' => $existingUpload->created_at,
                    ],
                ], 200);
            }
            
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Store file in storage/app/uploads
            $filePath = $file->storeAs('uploads', $fileName, 'local');

            // Create file upload record
            $fileUpload = FileUpload::create([
                'file_name' => $file->getClientOriginalName(),
                'file_hash' => $fileHash,
                'file_path' => $filePath,
                'status' => 'pending',
            ]);

            // Dispatch job to queue
            ProcessFileUpload::dispatch($fileUpload);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully and queued for processing',
                'data' => [
                    'id' => $fileUpload->id,
                    'file_name' => $fileUpload->file_name,
                    'status' => $fileUpload->status,
                    'created_at' => $fileUpload->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get upload status
     */
    public function status(int $id): JsonResponse
    {
        $fileUpload = FileUpload::find($id);

        if (!$fileUpload) {
            return response()->json([
                'success' => false,
                'message' => 'File upload not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $fileUpload->id,
                'file_name' => $fileUpload->file_name,
                'status' => $fileUpload->status,
                'total_rows' => $fileUpload->total_rows,
                'processed_rows' => $fileUpload->processed_rows,
                'progress_percentage' => $fileUpload->getProgressPercentage(),
                'error_message' => $fileUpload->error_message,
                'created_at' => $fileUpload->created_at,
                'updated_at' => $fileUpload->updated_at,
            ],
        ]);
    }

    /**
     * List all file uploads
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');

        $query = FileUpload::query()->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $fileUploads = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $fileUploads->items(),
            'pagination' => [
                'total' => $fileUploads->total(),
                'per_page' => $fileUploads->perPage(),
                'current_page' => $fileUploads->currentPage(),
                'last_page' => $fileUploads->lastPage(),
            ],
        ]);
    }

    /**
     * Get product data for a file upload
     */
    public function products(int $id, Request $request): JsonResponse
    {
        $fileUpload = FileUpload::find($id);

        if (!$fileUpload) {
            return response()->json([
                'success' => false,
                'message' => 'File upload not found',
            ], 404);
        }

        $perPage = $request->input('per_page', 50);
        $products = $fileUpload->productData()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Delete a file upload and its data
     */
    public function destroy(int $id): JsonResponse
    {
        $fileUpload = FileUpload::find($id);

        if (!$fileUpload) {
            return response()->json([
                'success' => false,
                'message' => 'File upload not found',
            ], 404);
        }

        try {
            // Delete file from storage
            if (Storage::exists($fileUpload->file_path)) {
                Storage::delete($fileUpload->file_path);
            }

            // Delete record (cascade will delete product data)
            $fileUpload->delete();

            return response()->json([
                'success' => true,
                'message' => 'File upload deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file upload: ' . $e->getMessage(),
            ], 500);
        }
    }
}
