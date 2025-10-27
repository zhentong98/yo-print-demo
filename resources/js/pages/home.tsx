import { useState } from 'react';
import { FileListTable } from '@/components/file-list-table';
import { FileUploadZone } from '@/components/file-upload-zone';
import { UploadedFile } from '@/types/file-upload';

export default function Home() {
    const [files, setFiles] = useState<UploadedFile[]>([]);

    const handleFilesSelected = (selectedFiles: File[]) => {
        const newFiles: UploadedFile[] = selectedFiles.map((file) => ({
            id: Math.random().toString(36).substr(2, 9),
            name: file.name,
            uploadedAt: new Date(),
            status: 'pending' as const
        }));

        setFiles((prev) => [...newFiles, ...prev]);

        // Simulate processing
        newFiles.forEach((newFile) => {
            setTimeout(() => {
                setFiles((prev) =>
                    prev.map((f) =>
                        f.id === newFile.id ? { ...f, status: 'processing' as const } : f
                    )
                );
            }, 1000);

            setTimeout(() => {
                setFiles((prev) =>
                    prev.map((f) =>
                        f.id === newFile.id
                            ? { ...f, status: Math.random() > 0.3 ? ('completed' as const) : ('failed' as const) }
                            : f
                    )
                );
            }, 3000);
        });
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-8">
            <div className="max-w-7xl mx-auto space-y-8">
                <div className="text-center mb-8">
                    <h1 className="text-4xl font-bold text-gray-900 mb-2">File Upload Center</h1>
                    <p className="text-gray-600">Upload and manage your files with ease</p>
                </div>

                <FileUploadZone onFilesSelected={handleFilesSelected} />
                <FileListTable files={files} />
            </div>
        </div>
    );
}
