<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CSV File Upload Center</title>

    @vite(['resources/css/app.css'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
    <div
        x-data="{
            files: [],
            isUploading: false,
            isLoading: true,
            isRefreshing: false,

            async init() {
                await this.loadFiles();
            },

            async loadFiles() {
                this.isRefreshing = true;

                try {
                    const [response] = await Promise.all([
                        fetch('/api/file-uploads'),
                        new Promise(resolve => setTimeout(resolve, 500))
                    ]);

                    const result = await response.json();

                    if (result.success) {
                        this.files = result.data.map(item => ({
                            id: item.id.toString(),
                            name: item.file_name,
                            uploadedAt: new Date(item.created_at),
                            updatedAt: new Date(item.updated_at),
                            status: item.status
                        }));

                        // Start polling for processing files
                        this.files.forEach(file => {
                            if (file.status === 'processing' || file.status === 'pending') {
                                this.pollFileStatus(parseInt(file.id));
                            }
                        });
                    }
                } catch (error) {
                    console.error('Failed to load files:', error);
                } finally {
                    this.isLoading = false;
                    this.isRefreshing = false;
                }
            },

            async handleFilesSelected(selectedFiles) {
                this.isUploading = true;

                for (const file of selectedFiles) {
                    try {
                        const formData = new FormData();
                        formData.append('file', file);

                        const response = await fetch('/api/file-uploads', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                            }
                        });

                        const result = await response.json();

                        if (result.success) {
                            const fileId = result.data.id.toString();
                            const existingIndex = this.files.findIndex(f => f.id === fileId);

                            const newFile = {
                                id: fileId,
                                name: result.data.file_name,
                                uploadedAt: new Date(result.data.created_at),
                                updatedAt: new Date(result.data.created_at),
                                status: result.data.status
                            };

                            if (existingIndex >= 0) {
                                this.files[existingIndex] = newFile;
                            } else {
                                this.files.unshift(newFile);
                            }

                            this.pollFileStatus(result.data.id);
                        } else {
                            console.error('Upload failed:', result.message);
                            alert(`Upload failed: ${result.message}`);
                        }
                    } catch (error) {
                        console.error('Upload error:', error);
                        alert('Upload failed. Please try again.');
                    }
                }

                this.isUploading = false;
            },

            async pollFileStatus(fileId) {
                const maxAttempts = 60;
                let attempts = 0;

                const poll = async () => {
                    try {
                        const response = await fetch(`/api/file-uploads/${fileId}`);
                        const result = await response.json();

                        if (result.success) {
                            const index = this.files.findIndex(f => f.id === fileId.toString());
                            if (index >= 0) {
                                this.files[index].status = result.data.status;
                                this.files[index].updatedAt = new Date();
                            }

                            if (result.data.status === 'processing' || result.data.status === 'pending') {
                                attempts++;
                                if (attempts < maxAttempts) {
                                    setTimeout(poll, 5000);
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Status polling error:', error);
                    }
                };

                setTimeout(poll, 2000);
            }
        }"
        x-init="init()"
    >
        <!-- Loading State -->
        <div x-show="isLoading" class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-gray-900"></div>
                <p class="mt-4 text-gray-600">Loading...</p>
            </div>
        </div>

        <!-- Main Content -->
        <div x-show="!isLoading" class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-8">
            <div class="max-w-7xl mx-auto space-y-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">
                        CSV File Upload Center
                    </h1>
                    <p class="text-gray-600">
                        Upload CSV files and track processing status in real-time
                    </p>
                </div>

                <!-- File Upload Zone Component -->
                @include('components.file-upload-zone')

                <!-- File List Table Component -->
                @include('components.file-list-table')
            </div>
        </div>
    </div>
</body>
</html>
