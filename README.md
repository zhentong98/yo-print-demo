# YoPrint Demo - File Upload & Product Data Management System

A Laravel 11 + React 19 + Inertia.js application for uploading and managing product data from Excel/CSV files with background processing.

## Features

- 📤 File Upload with drag & drop support (CSV, XLSX, XLS)
- 🔄 Idempotent uploads (SHA256 hash-based)
- 🔁 UPSERT functionality based on UNIQUE_KEY
- ⚡ Background processing with Redis queue
- 🧹 UTF-8 cleaning and BOM removal
- 📊 Real-time progress tracking

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- Redis
- Docker & Docker Compose (optional)

---

## Installation

```bash
# Clone repository
git clone <repository-url>
cd yo-print-demo

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Create database (SQLite)
touch database/database.sqlite

# Run migrations
php artisan migrate

# Create storage link
php artisan storage:link
```

---

## Running the Application

You need **3 terminal windows**:

### Terminal 1: Laravel Server
```bash
php artisan serve
```

### Terminal 2: Frontend Development Server
```bash
npm run dev
```

### Terminal 3: Queue Worker
```bash
php artisan queue:work redis --verbose
```

**Access**: `http://localhost:8000`

---

## Running with Docker

```bash
# Start containers
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec node npm install

# Setup application
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app php artisan storage:link

# Start frontend build
docker-compose exec node npm run dev

# Start queue worker
docker-compose exec app php artisan queue:work redis --verbose
```

**Access**: `http://localhost:8080`

---

## File Format

CSV/Excel file should include:

| Column | Required | Description |
|--------|----------|-------------|
| UNIQUE_KEY | ✅ | Unique identifier for upsert |
| PRODUCT_TITLE | ✅ | Product name |
| PRODUCT_DESCRIPTION | ❌ | Product description |
| STYLE# | ❌ | Style number |
| SANMAR_MAINFRAME_COLOR | ❌ | Color code |
| SIZE | ❌ | Product size |
| COLOR_NAME | ❌ | Color name |
| PIECE_PRICE | ❌ | Unit price |

**Example:**
```csv
UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,PIECE_PRICE,SIZE,COLOR_NAME
62822,Hanes EcoSmart Jersey,Description here,18.08,S,White
62823,Another Product,Another description,25.50,M,Black
```

---

## How It Works

### Idempotent Uploads
- Files are identified by SHA256 hash
- Same file won't create duplicate `file_uploads` records
- Reprocessing existing records if file hash matches

### UPSERT Logic
- Uses `UNIQUE_KEY` to match existing records
- Updates all fields if record exists
- Creates new record if `UNIQUE_KEY` is new
- `created_at` preserved, `updated_at` updated

### Background Processing
- Files processed in background queue
- Batch processing (500 rows per batch)
- Automatic retry (3 attempts)
- 30-minute timeout for large files

---

## Troubleshooting

### Queue not working
```bash
php artisan queue:restart
php artisan queue:flush
```

### Redis not running
```bash
# macOS
brew services start redis

# Linux
sudo systemctl start redis

# Check connection
redis-cli ping
```

### Clear caches
```bash
php artisan cache:clear
php artisan config:clear
```

---

## Database Schema

### `file_uploads`
- id, file_name, file_hash (SHA256), file_path
- status (pending/processing/completed/failed)
- total_rows, processed_rows
- created_at, updated_at

### `product_data`
- id, unique_key (UNIQUE)
- product_title, product_description, style_number
- sanmar_mainframe_color, size, color_name, piece_price
- csv_occurrence_count
- created_at, updated_at
