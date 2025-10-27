import { useState, useEffect } from 'react';
import { FileListTable } from '@/components/file-list-table';
import { FileUploadZone } from '@/components/file-upload-zone';
import { UploadedFile } from '@/types/file-upload';

export default function Home() {
    const [files, setFiles] = useState<UploadedFile[]>([]);
    const [isUploading, setIsUploading] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);

    // Load files from database on mount
    useEffect(() => {
        loadFiles();
    }, []);

    const loadFiles = async () => {
        setIsRefreshing(true);

        // Add minimum delay to show loading animation
        const [response] = await Promise.all([
            fetch('/api/file-uploads'),
            new Promise(resolve => setTimeout(resolve, 500)) // Minimum 500ms delay
        ]);

        try {
            const result = await response.json();

            if (result.success) {
                const loadedFiles: UploadedFile[] = result.data.map((item: any) => ({
                    id: item.id.toString(),
                    name: item.file_name,
                    uploadedAt: new Date(item.created_at),
                    updatedAt: new Date(item.updated_at),
                    status: item.status as UploadedFile['status']
                }));

                setFiles(loadedFiles);

                // Start polling for any processing files
                loadedFiles.forEach((file) => {
                    if (file.status === 'processing' || file.status === 'pending') {
                        pollFileStatus(parseInt(file.id));
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load files:', error);
        } finally {
            setIsLoading(false);
            setIsRefreshing(false);
        }
    };

    const handleFilesSelected = async (selectedFiles: File[]) => {
        setIsUploading(true);

        for (const file of selectedFiles) {
            try {
                // Create FormData
                const formData = new FormData();
                formData.append('file', file);

                // Upload to backend
                const response = await fetch('/api/file-uploads', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    const fileId = result.data.id.toString();
                    
                    // Check if file already exists in the list
                    const existingFileIndex = files.findIndex(f => f.id === fileId);
                    
                    if (existingFileIndex >= 0) {
                        // Update existing file status
                        setFiles((prev) =>
                            prev.map((f) =>
                                f.id === fileId
                                    ? {
                                        ...f,
                                        status: result.data.status as UploadedFile['status'],
                                        uploadedAt: new Date(result.data.created_at),
                                        updatedAt: new Date(),
                                    }
                                    : f
                            )
                        );
                    } else {
                        // Add new file to UI
                        const newFile: UploadedFile = {
                            id: fileId,
                            name: result.data.file_name,
                            uploadedAt: new Date(result.data.created_at),
                            updatedAt: new Date(result.data.created_at),
                            status: result.data.status as UploadedFile['status']
                        };
                        setFiles((prev) => [newFile, ...prev]);
                    }

                    // Start polling for status updates
                    pollFileStatus(result.data.id);
                } else {
                    console.error('Upload failed:', result.message);
                    alert(`Upload failed: ${result.message}`);
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Upload failed. Please try again.');
            }
        }

        setIsUploading(false);
    };

    const pollFileStatus = async (fileId: number) => {
        const maxAttempts = 60; // Poll for max 5 minutes (5 seconds interval)
        let attempts = 0;

        const poll = async () => {
            try {
                const response = await fetch(`/api/file-uploads/${fileId}`);
                const result = await response.json();

                if (result.success) {
                    setFiles((prev) =>
                        prev.map((f) =>
                            f.id === fileId.toString()
                                ? {
                                    ...f,
                                    status: result.data.status as UploadedFile['status'],
                                    updatedAt: new Date()
                                }
                                : f
                        )
                    );

                    // Continue polling if still processing
                    if (
                        result.data.status === 'processing' ||
                        result.data.status === 'pending'
                    ) {
                        attempts++;
                        if (attempts < maxAttempts) {
                            setTimeout(poll, 5000); // Poll every 5 seconds
                        }
                    }
                }
            } catch (error) {
                console.error('Status polling error:', error);
            }
        };

        // Start polling after 2 seconds
        setTimeout(poll, 2000);
    };

    if (isLoading) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center">
                <div className="text-center">
                    <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-gray-900"></div>
                    <p className="mt-4 text-gray-600">Loading...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-8">
            <div className="max-w-7xl mx-auto space-y-8">
                <div className="text-center mb-8">
                    <h1 className="text-4xl font-bold text-gray-900 mb-2">
                        CSV File Upload Center
                    </h1>
                    <p className="text-gray-600">
                        Upload CSV files and track processing status in real-time
                    </p>
                </div>

                <FileUploadZone
                    onFilesSelected={handleFilesSelected}
                    isUploading={isUploading}
                />
                <FileListTable files={files} onRefresh={loadFiles} isRefreshing={isRefreshing} />
            </div>
        </div>
    );
}
