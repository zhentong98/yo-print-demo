<div
    x-data="{
        isDragging: false,
        fileInputRef: null,
        
        handleDragOver(e) {
            e.preventDefault();
            this.isDragging = true;
        },
        
        handleDragLeave(e) {
            e.preventDefault();
            this.isDragging = false;
        },
        
        isValidFileType(file) {
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
        },
        
        handleDrop(e) {
            e.preventDefault();
            this.isDragging = false;
            
            if (isUploading) return;
            
            const files = Array.from(e.dataTransfer.files);
            const validFiles = files.filter(f => this.isValidFileType(f));
            
            if (validFiles.length === 0) {
                alert('Please upload Excel files only (CSV, XLSX, XLS)');
                return;
            }
            
            if (validFiles.length < files.length) {
                alert(`${files.length - validFiles.length} file(s) were skipped. Only Excel files are allowed.`);
            }
            
            if (validFiles.length > 0) {
                handleFilesSelected(validFiles);
            }
        },
        
        handleFileSelect(e) {
            if (isUploading) return;
            
            const files = Array.from(e.target.files || []);
            const validFiles = files.filter(f => this.isValidFileType(f));
            
            if (validFiles.length === 0) {
                alert('Please upload Excel files only (CSV, XLSX, XLS)');
                e.target.value = '';
                return;
            }
            
            if (validFiles.length < files.length) {
                alert(`${files.length - validFiles.length} file(s) were skipped. Only Excel files are allowed.`);
            }
            
            if (validFiles.length > 0) {
                handleFilesSelected(validFiles);
                e.target.value = '';
            }
        },
        
        handleClick() {
            if (!isUploading) {
                this.$refs.fileInput.click();
            }
        }
    }"
    @click="handleClick()"
    @dragover="handleDragOver($event)"
    @dragleave="handleDragLeave($event)"
    @drop="handleDrop($event)"
    :class="{
        'border-blue-500 bg-blue-50 shadow-2xl scale-[1.02] ring-4 ring-blue-200': isDragging,
        'border-gray-300 hover:border-gray-400 bg-white hover:shadow-xl hover:scale-[1.01]': !isDragging
    }"
    class="relative border-3 border-dashed rounded-2xl p-16 cursor-pointer transition-all duration-300 ease-out"
>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div 
                :class="isDragging ? 'bg-blue-500' : 'bg-gray-100'"
                class="w-16 h-16 rounded-xl flex items-center justify-center transition-colors duration-300"
            >
                <svg
                    :class="isDragging ? 'text-white' : 'text-gray-600'"
                    class="w-8 h-8"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                    />
                </svg>
            </div>
            <div>
                <p class="text-gray-900 text-xl font-semibold mb-1" x-text="isDragging ? 'Drop your files here' : 'Select file / Drag and drop'"></p>
                <p class="text-gray-500 text-sm">
                    Excel files only (CSV, XLSX, XLS) • Max 100MB per file
                </p>
            </div>
        </div>
        <button
            type="button"
            :disabled="isUploading"
            :class="isUploading ? 'bg-gray-400 cursor-not-allowed' : 'bg-gray-900 text-white hover:bg-gray-800 hover:shadow-xl active:scale-95'"
            class="px-8 py-3 font-semibold rounded-xl transition-all duration-200 shadow-lg"
        >
            <span x-show="isUploading" class="flex items-center gap-2">
                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Uploading...
            </span>
            <span x-show="!isUploading">Upload File</span>
        </button>
    </div>
    
    <input
        x-ref="fileInput"
        type="file"
        multiple
        accept=".csv,.xlsx,.xls,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
        @change="handleFileSelect($event)"
        class="hidden"
    />

    <div x-show="isDragging" class="absolute inset-0 flex items-center justify-center pointer-events-none">
        <div class="text-blue-500 font-bold text-2xl animate-pulse">
            Release to upload
        </div>
    </div>
</div>
