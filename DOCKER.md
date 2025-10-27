# Docker Setup Guide

This project uses Docker Compose to manage Redis for queue and cache management.

## Services Included

- **Redis** (Port 6379) - In-memory data store for queues and cache
- **Redis Commander** (Port 8088) - Web-based Redis management UI

## Quick Start

### 1. Start Services

```bash
# Start all services
docker compose up -d

# View logs
docker compose logs -f

# Check service status
docker compose ps
```

### 2. Configure Environment

Copy `.env.example` to `.env` if you haven't already:

```bash
cp .env.example .env
```

Make sure these values are set in your `.env`:

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 3. Start Queue Worker

```bash
# Run queue worker
php artisan queue:work redis

# Or use Supervisor for production
php artisan queue:work redis --daemon
```

## Useful Commands

### Docker Compose

```bash
# Stop services
docker compose stop

# Restart services
docker compose restart

# Stop and remove containers
docker compose down

# Remove containers and volumes (⚠️ deletes Redis data)
docker compose down -v

# View Redis logs
docker compose logs redis

# Access Redis CLI
docker compose exec redis redis-cli
```

### Laravel Queue Commands

```bash
# Process queue jobs
php artisan queue:work

# Process jobs with options
php artisan queue:work redis --tries=3 --timeout=60

# List failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear all jobs
php artisan queue:clear redis
```

## Redis Commander

Access Redis Commander web UI at: **http://localhost:8088**

Features:
- Browse Redis keys
- View queue jobs
- Monitor memory usage
- Execute Redis commands

## Monitoring

### Check Redis Connection

```bash
# Test Redis connection
php artisan tinker
>>> Redis::ping()
// Should return: "PONG"
```

### Monitor Queue

```bash
# Monitor queue in real-time
php artisan queue:monitor

# View queue statistics
php artisan horizon:list  # If using Horizon
```

## Production Deployment

For production, consider:

1. **Add Redis password**:
   ```yaml
   services:
     redis:
       command: redis-server --appendonly yes --requirepass yourpassword
   ```

2. **Use Supervisor** to keep queue workers running
3. **Consider Laravel Horizon** for advanced queue management
4. **Set up monitoring** with tools like Redis Insights

## Troubleshooting

### Redis Connection Issues

```bash
# Check if Redis is running
docker compose ps

# Check Redis logs
docker compose logs redis

# Restart Redis
docker compose restart redis
```

### Queue Not Processing

```bash
# Make sure queue worker is running
php artisan queue:work redis --verbose

# Check for failed jobs
php artisan queue:failed

# Clear cache
php artisan cache:clear
php artisan config:clear
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `REDIS_HOST` | `127.0.0.1` | Redis host address |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | `null` | Redis password |
| `QUEUE_CONNECTION` | `redis` | Queue driver |
| `CACHE_STORE` | `redis` | Cache driver |

## Notes

- Redis data is persisted in a Docker volume (`redis_data`)
- Redis uses AOF (Append Only File) persistence
- Default memory limit is set by Redis (no limit)
- Network name: `yo-print-network`
