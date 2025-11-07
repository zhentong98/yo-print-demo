<div
    x-data="{
        formatTime(date) {
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
        },
        
        getStatusClass(status) {
            const styles = {
                pending: 'bg-yellow-100 text-yellow-700 border-yellow-200',
                processing: 'bg-blue-100 text-blue-700 border-blue-200',
                failed: 'bg-red-100 text-red-700 border-red-200',
                completed: 'bg-green-100 text-green-700 border-green-200'
            };
            return styles[status] || '';
        },
        
        getStatusIcon(status) {
            const icons = {
                pending: '⏳',
                processing: '⚙️',
                failed: '❌',
                completed: '✓'
            };
            return icons[status] || '';
        },
        
        getFileIconColor(filename) {
            const ext = filename.split('.').pop()?.toLowerCase();
            const iconColors = {
                pdf: 'text-red-500',
                doc: 'text-blue-500',
                docx: 'text-blue-500',
                xls: 'text-green-500',
                xlsx: 'text-green-500',
                csv: 'text-green-500',
                zip: 'text-purple-500',
                fig: 'text-pink-500'
            };
            return iconColors[ext] || 'text-gray-500';
        },
        
        formatDateTime(date) {
            return new Date(date).toLocaleString('en-MY', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }
    }"
    class="border-2 border-gray-200 rounded-2xl overflow-hidden bg-white shadow-xl"
>
    <!-- Header with Refresh Button -->
    <div class="px-8 py-4 bg-gray-50 border-b-2 border-gray-200 flex justify-between items-center">
        <h2 class="text-lg font-bold text-gray-900">Upload History</h2>
        <button
            @click="loadFiles()"
            :disabled="isRefreshing"
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <svg
                :class="{ 'animate-spin': isRefreshing }"
                class="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span x-text="isRefreshing ? 'Refreshing...' : 'Refresh'"></span>
        </button>
    </div>

    <!-- Table -->
    <table class="w-full">
        <thead>
            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
                <th class="text-left px-8 py-5 font-bold text-gray-700">
                    <div class="flex items-center gap-2">
                        File Name
                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                        </svg>
                    </div>
                </th>
                <th class="text-left px-8 py-5 font-bold text-gray-700 border-l-2 border-gray-200">
                    Status
                </th>
                <th class="text-left px-8 py-5 font-bold text-gray-700 border-l-2 border-gray-200">
                    Last Modified
                </th>
                <th class="text-left px-8 py-5 font-bold text-gray-700 border-l-2 border-gray-200">
                    Created
                </th>
            </tr>
        </thead>
        <tbody>
            <!-- Empty State -->
            <template x-if="files.length === 0">
                <tr>
                    <td colspan="4" class="px-8 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <p class="text-gray-500 font-medium">No files uploaded yet</p>
                            <p class="text-gray-400 text-sm">Upload your first file to get started</p>
                        </div>
                    </td>
                </tr>
            </template>

            <!-- File Rows -->
            <template x-for="(file, index) in files" :key="file.id">
                <tr
                    :class="{
                        'bg-white': index % 2 === 0,
                        'bg-gray-50/30': index % 2 !== 0
                    }"
                    class="border-t border-gray-100 hover:bg-gray-50 transition-colors duration-150"
                >
                    <!-- File Name -->
                    <td class="px-8 py-5">
                        <div class="flex items-center gap-3">
                            <svg :class="getFileIconColor(file.name)" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-semibold text-gray-900" x-text="file.name"></span>
                        </div>
                    </td>

                    <!-- Status -->
                    <td class="px-8 py-5 border-l border-gray-100">
                        <span 
                            :class="getStatusClass(file.status)"
                            class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold border"
                        >
                            <span x-text="getStatusIcon(file.status)"></span>
                            <span x-text="file.status.charAt(0).toUpperCase() + file.status.slice(1)"></span>
                        </span>
                    </td>

                    <!-- Last Modified -->
                    <td class="px-8 py-5 border-l border-gray-100">
                        <div class="flex flex-col">
                            <span class="text-gray-600 text-sm font-medium" x-text="formatTime(file.updatedAt)"></span>
                            <span class="text-gray-400 text-xs mt-1" x-text="formatDateTime(file.updatedAt)"></span>
                        </div>
                    </td>

                    <!-- Created -->
                    <td class="px-8 py-5 border-l border-gray-100">
                        <div class="flex flex-col">
                            <span class="text-gray-600 text-sm font-medium" x-text="formatTime(file.uploadedAt)"></span>
                            <span class="text-gray-400 text-xs mt-1" x-text="formatDateTime(file.uploadedAt)"></span>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>
