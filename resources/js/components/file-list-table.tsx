import { UploadedFile } from '@/types/file-upload';

interface FileListTableProps {
    files: UploadedFile[];
    onRefresh?: () => void;
}

export function FileListTable({ files, onRefresh }: FileListTableProps) {
    const formatTime = (date: Date) => {
        const now = new Date();
        const diff = Math.floor((now.getTime() - date.getTime()) / 1000 / 60);

        if (diff < 1) return 'just now';
        if (diff < 60) return `${diff} minutes ago`;
        if (diff < 1440) return `${Math.floor(diff / 60)} hours ago`;

        return date.toLocaleString('en-US', {
            month: 'numeric',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    };

    const getStatusBadge = (status: UploadedFile['status']) => {
        const styles = {
            pending: 'bg-yellow-100 text-yellow-700 border-yellow-200',
            processing: 'bg-blue-100 text-blue-700 border-blue-200',
            failed: 'bg-red-100 text-red-700 border-red-200',
            completed: 'bg-green-100 text-green-700 border-green-200'
        };

        const icons = {
            pending: '⏳',
            processing: '⚙️',
            failed: '❌',
            completed: '✓'
        };

        return (
            <span className={`inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold border ${styles[status]}`}>
                <span>{icons[status]}</span>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        );
    };

    const getFileIcon = (filename: string) => {
        const ext = filename.split('.').pop()?.toLowerCase();
        const iconColors = {
            pdf: 'text-red-500',
            doc: 'text-blue-500',
            docx: 'text-blue-500',
            xls: 'text-green-500',
            xlsx: 'text-green-500',
            zip: 'text-purple-500',
            fig: 'text-pink-500'
        };

        const color = ext ? (iconColors[ext as keyof typeof iconColors] || 'text-gray-500') : 'text-gray-500';

        return (
            <svg className={`w-6 h-6 ${color}`} fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
            </svg>
        );
    };

    return (
        <div className="border-2 border-gray-200 rounded-2xl overflow-hidden bg-white shadow-xl">
            {onRefresh && (
                <div className="px-8 py-4 bg-gray-50 border-b-2 border-gray-200 flex justify-between items-center">
                    <h2 className="text-lg font-bold text-gray-900">Upload History</h2>
                    <button
                        onClick={onRefresh}
                        className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>
                </div>
            )}
            <table className="w-full">
                <thead>
                <tr className="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
                    <th className="text-left px-8 py-5 font-bold text-gray-700">
                        <div className="flex items-center gap-2">
                            Time
                            <svg className="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                            </svg>
                        </div>
                    </th>
                    <th className="text-left px-8 py-5 font-bold text-gray-700 border-l-2 border-gray-200">
                        <div className="flex items-center gap-2">
                            File Name
                            <svg className="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                            </svg>
                        </div>
                    </th>
                    <th className="text-left px-8 py-5 font-bold text-gray-700 border-l-2 border-gray-200">
                        Status
                    </th>
                </tr>
                </thead>
                <tbody>
                {files.length === 0 ? (
                    <tr>
                        <td colSpan={3} className="px-8 py-16 text-center">
                            <div className="flex flex-col items-center gap-3">
                                <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                                    <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p className="text-gray-500 font-medium">No files uploaded yet</p>
                                <p className="text-gray-400 text-sm">Upload your first file to get started</p>
                            </div>
                        </td>
                    </tr>
                ) : (
                    files.map((file, index) => (
                        <tr
                            key={file.id}
                            className={`
                                    border-t border-gray-100 hover:bg-gray-50 transition-colors duration-150
                                    ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50/30'}
                                `}
                        >
                            <td className="px-8 py-5">
                                <div className="flex items-center gap-2">
                                    <svg className="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd" />
                                    </svg>
                                    <span className="text-gray-600 text-sm font-medium">
                                            {formatTime(file.uploadedAt)}
                                        </span>
                                </div>
                            </td>
                            <td className="px-8 py-5 border-l border-gray-100">
                                <div className="flex items-center gap-3">
                                    {getFileIcon(file.name)}
                                    <span className="font-semibold text-gray-900">{file.name}</span>
                                </div>
                            </td>
                            <td className="px-8 py-5 border-l border-gray-100">
                                {getStatusBadge(file.status)}
                            </td>
                        </tr>
                    ))
                )}
                </tbody>
            </table>
        </div>
    );
}
