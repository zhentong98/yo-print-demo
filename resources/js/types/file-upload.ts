export type FileStatus = 'pending' | 'processing' | 'failed' | 'completed';

export interface UploadedFile {
    id: string;
    name: string;
    uploadedAt: Date;
    status: FileStatus;
}
