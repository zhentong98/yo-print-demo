import { useRef, useState } from 'react';

interface FileUploadZoneProps {
    onFilesSelected: (files: File[]) => void;
}

export function FileUploadZone({ onFilesSelected }: FileUploadZoneProps) {
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

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);

        const files = Array.from(e.dataTransfer.files);
        if (files.length > 0) {
            onFilesSelected(files);
        }
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(e.target.files || []);
        if (files.length > 0) {
            onFilesSelected(files);
        }
    };

    const handleClick = () => {
        fileInputRef.current?.click();
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
                            Support for multiple files • Max 100MB per file
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    className="px-8 py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-all duration-200 shadow-lg hover:shadow-xl active:scale-95"
                >
                    Upload File
                </button>
            </div>
            <input
                ref={fileInputRef}
                type="file"
                multiple
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
