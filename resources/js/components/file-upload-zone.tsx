import { useRef, useState } from 'react';

interface FileUploadZoneProps {
    onFilesSelected: (files: File[]) => void;
    isUploading?: boolean;
}

export function FileUploadZone({ onFilesSelected, isUploading = false }: FileUploadZoneProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
    };

    const isValidFileType = (file: File): boolean => {
        const validExtensions = ['.csv', '.xlsx', '.xls'];
        const validMimeTypes = [
            'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        const fileName = file.name.toLowerCase();
        const hasValidExtension = validExtensions.some(ext => fileName.endsWith(ext));
        const hasValidMimeType = validMimeTypes.includes(file.type);
        
        return hasValidExtension || hasValidMimeType;
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);

        if (isUploading) return;

        const files = Array.from(e.dataTransfer.files);
        const validFiles = files.filter(isValidFileType);
        
        if (validFiles.length === 0) {
            alert('Please upload Excel files only (CSV, XLSX, XLS)');
            return;
        }
        
        if (validFiles.length < files.length) {
            alert(`${files.length - validFiles.length} file(s) were skipped. Only Excel files are allowed.`);
        }
        
        if (validFiles.length > 0) {
            onFilesSelected(validFiles);
        }
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (isUploading) return;
        
        const files = Array.from(e.target.files || []);
        const validFiles = files.filter(isValidFileType);
        
        if (validFiles.length === 0) {
            alert('Please upload Excel files only (CSV, XLSX, XLS)');
            // Reset input
            if (e.target) {
                e.target.value = '';
            }
            return;
        }
        
        if (validFiles.length < files.length) {
            alert(`${files.length - validFiles.length} file(s) were skipped. Only Excel files are allowed.`);
        }
        
        if (validFiles.length > 0) {
            onFilesSelected(validFiles);
            // Reset input so same file can be selected again
            if (e.target) {
                e.target.value = '';
            }
        }
    };

    const handleClick = () => {
        if (!isUploading) {
            fileInputRef.current?.click();
        }
    };

    return (
        <div
            onClick={handleClick}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
            className={`
                relative border-3 border-dashed rounded-2xl p-16 cursor-pointer
                transition-all duration-300 ease-out
                ${isDragging
                ? 'border-blue-500 bg-blue-50 shadow-2xl scale-[1.02] ring-4 ring-blue-200'
                : 'border-gray-300 hover:border-gray-400 bg-white hover:shadow-xl hover:scale-[1.01]'
            }
            `}
        >
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`
                        w-16 h-16 rounded-xl flex items-center justify-center transition-colors duration-300
                        ${isDragging ? 'bg-blue-500' : 'bg-gray-100'}
                    `}>
                        <svg
                            className={`w-8 h-8 ${isDragging ? 'text-white' : 'text-gray-600'}`}
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                            />
                        </svg>
                    </div>
                    <div>
                        <p className="text-gray-900 text-xl font-semibold mb-1">
                            {isDragging ? 'Drop your files here' : 'Select file / Drag and drop'}
                        </p>
                        <p className="text-gray-500 text-sm">
                            Excel files only (CSV, XLSX, XLS) • Max 100MB per file
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    disabled={isUploading}
                    className={`px-8 py-3 font-semibold rounded-xl transition-all duration-200 shadow-lg ${
                        isUploading
                            ? 'bg-gray-400 cursor-not-allowed'
                            : 'bg-gray-900 text-white hover:bg-gray-800 hover:shadow-xl active:scale-95'
                    }`}
                >
                    {isUploading ? (
                        <span className="flex items-center gap-2">
                            <svg className="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Uploading...
                        </span>
                    ) : (
                        'Upload File'
                    )}
                </button>
            </div>
            <input
                ref={fileInputRef}
                type="file"
                multiple
                accept=".csv,.xlsx,.xls,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                onChange={handleFileSelect}
                className="hidden"
            />

            {isDragging && (
                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div className="text-blue-500 font-bold text-2xl animate-pulse">
                        Release to upload
                    </div>
                </div>
            )}
        </div>
    );
}
