# High-Performance Asynchronous Email System with Redis

## Overview

This document describes the complete implementation of a high-performance asynchronous email processing system using Redis as a message queue and dedicated worker containers.

**Benefits**:
- ⚡ **Web Interface Stays Fast** - Email sending doesn't slow down API responses
- 📧 **Reliable Delivery** - Messages queued in Redis, retried on failure
- 🔄 **Scalable** - Can run multiple worker containers for high volume
- 📊 **Monitorable** - Redis provides visibility into queue depth and throughput
- 💪 **Resilient** - Workers automatically restart on failure

---

## Architecture

### Component Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                        Web Request                          │
│  (User → gRPC → AuthService.ForgotPassword)                │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────┐
│            PHP Container (Web/API)                          │
│  - Processes gRPC requests                                  │
│  - Creates email message                                    │
│  - Queues to Redis (instant return)                         │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   │ Serializes SendEmailMessage
                   ▼
┌─────────────────────────────────────────────────────────────┐
│                   Redis Container                            │
│  - Stores message queue ("messages" stream)                 │
│  - Provides persistence (AOF enabled)                       │
│  - Memory limit: 256MB with LRU eviction                    │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   │ Worker consumes messages
                   ▼
┌─────────────────────────────────────────────────────────────┐
│              Worker Container                                │
│  - Runs: messenger:consume async                            │
│  - Deserializes messages                                    │
│  - Sends emails via Gmail SMTP                              │
│  - Marks messages as complete                               │
│  - Auto-restarts on failure                                 │
└─────────────────────────────────────────────────────────────┘
```

### Message Flow

1. **Request Phase** (~10ms)
   ```
   User requests password reset
   → AuthService creates email
   → Symfony serializes to Redis
   → Returns success immediately
   ```

2. **Processing Phase** (async, ~2-5 seconds)
   ```
   Worker pulls message from Redis
   → Deserializes email
   → Connects to Gmail SMTP
   → Sends email
   → Acknowledges to Redis
   ```

3. **Retry on Failure**
   ```
   If sending fails:
   → Retry after 1 second
   → Then after 2 seconds
   → Then after 4 seconds
   → Max 3 retries
   → Move to failed queue if all retries exhausted
   ```

---

## Docker Infrastructure

### Services Added/Modified

#### 1. Redis Service (NEW)

**File**: `docker-compose.yml` (lines 56-72)

```yaml
redis:
  image: redis:7-alpine
  container_name: project2-redis-1
  ports:
    - "6379:6379"
  volumes:
    - redis_data:/data
  networks:
    - symfony_network
  command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 10s
    timeout: 3s
    retries: 3
    start_period: 5s
```

**Configuration Explained**:
- `redis:7-alpine` - Lightweight Redis 7.x image
- `--appendonly yes` - Enables AOF persistence (messages survive restarts)
- `--maxmemory 256mb` - Limits memory usage
- `--maxmemory-policy allkeys-lru` - Evicts least recently used keys when full
- `healthcheck` - Docker ensures Redis is ready before starting dependent services

#### 2. PHP Container (MODIFIED)

**Changes**:
```yaml
environment:
  MESSENGER_TRANSPORT_DSN: "redis://redis:6379/messages"  # NEW
depends_on:
  redis:
    condition: service_healthy  # NEW
  mysql:
    condition: service_started
```

**Why**: PHP container now connects to Redis for queueing messages.

#### 3. Worker Container (NEW)

**File**: `docker-compose.yml` (lines 25-56)

```yaml
worker:
  build:
    context: ./docker/php
    dockerfile: Dockerfile
  volumes:
    - ./:/var/www/symfony
  networks:
    - symfony_network
  environment:
    DATABASE_URL: "mysql://symfony2:symfony@mysql:3306/symfony2?serverVersion=8.0"
    MAILER_DSN: "gmail+smtp://asem4o%40gmail.com:njdrjpllwjoyggdc@default"
    MESSENGER_TRANSPORT_DSN: "redis://redis:6379/messages"
    APP_URL: "http://localhost"
    MAILER_FROM_EMAIL: "asem4o@gmail.com"
    MAILER_FROM_NAME: "My Symfony App"
  depends_on:
    redis:
      condition: service_healthy
    mysql:
      condition: service_started
    php:
      condition: service_started
  command: >
    sh -c "
      echo '🚀 Worker container starting...' &&
      echo 'Waiting for dependencies...' &&
      sleep 5 &&
      echo '📧 Starting Symfony Messenger worker for async email processing...' &&
      php bin/console messenger:consume async -vv --time-limit=3600 --memory-limit=128M
    "
  restart: unless-stopped
```

**Worker Command Explained**:
- `messenger:consume async` - Processes messages from "async" transport (Redis)
- `-vv` - Very verbose output (shows each message processed)
- `--time-limit=3600` - Worker restarts after 1 hour (prevents memory leaks)
- `--memory-limit=128M` - Worker restarts if memory exceeds 128MB
- `restart: unless-stopped` - Docker auto-restarts worker on crashes

---

## PHP Configuration

### Dockerfile Changes

**File**: `docker/php/Dockerfile` (lines 35-37)

```dockerfile
# Install Redis extension via PECL
# This enables PHP to communicate with Redis for queue management
RUN pecl install redis && docker-php-ext-enable redis
```

**What This Does**:
- Downloads Redis PHP extension from PECL
- Compiles it for PHP 8.4
- Enables it in php.ini
- Required for Symfony Redis Messenger transport

### Composer Package

**File**: `composer.json` (line 26)

```json
"symfony/redis-messenger": "7.1.*"
```

**What This Provides**:
- `RedisTransport` class for Symfony Messenger
- Serialization/deserialization for Redis streams
- Connection management and error handling

---

## Symfony Configuration

### Messenger Transport

**File**: `config/packages/messenger.yaml` (lines 5-20)

```yaml
transports:
  # Redis transport for high-performance async processing
  async:
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    options:
      # Stream options for Redis
      stream: 'messages'
      group: 'symfony'
      consumer: 'consumer'
    retry_strategy:
      max_retries: 3
      delay: 1000  # 1 second
      multiplier: 2
      max_delay: 0
    serializer: 'messenger.transport.symfony_serializer'
```

**Configuration Explained**:
- `stream: 'messages'` - Redis stream name (like a queue)
- `group: 'symfony'` - Consumer group (allows multiple workers)
- `consumer: 'consumer'` - Consumer name within group
- `retry_strategy` - Exponential backoff: 1s → 2s → 4s
- `serializer` - How messages are encoded/decoded

### Email Routing

**File**: `config/packages/messenger.yaml` (lines 33-44)

```yaml
routing:
  # ASYNC EMAIL PROCESSING via Redis
  Symfony\Component\Mailer\Messenger\SendEmailMessage: async
  Symfony\Component\Notifier\Message\ChatMessage: async
  Symfony\Component\Notifier\Message\SmsMessage: async
```

**What This Does**:
- When `$mailer->send()` is called, creates `SendEmailMessage`
- Routes it to "async" transport (Redis)
- Returns immediately (doesn't wait for email to send)
- Worker picks it up and sends asynchronously

### Mailer Configuration

**File**: `config/packages/mailer.yaml` (lines 4-6)

```yaml
# Enable async email processing via Redis
# Emails will be queued and processed by dedicated worker containers
# message_bus: false  # OLD: Synchronous sending (disabled for high-performance async)
```

**Key Change**: Removed `message_bus: false`, enabling async via Messenger.

---

## How to Use

### Starting the System

```bash
# Stop existing containers
docker-compose down

# Rebuild with Redis extension
docker-compose build --no-cache php

# Start all services
docker-compose up -d
```

**Containers Started**:
- `project2-php-1` - Web/API container
- `project2-worker-1` - Email processing worker
- `project2-redis-1` - Redis queue
- `project2-mysql-1` - Database
- `project2-nginx-1` - Web server

### Monitoring

#### Check Worker Status

```bash
# View worker logs (real-time)
docker logs -f project2-worker-1
```

**Expected Output**:
```
🚀 Worker container starting...
Waiting for dependencies...
📧 Starting Symfony Messenger worker for async email processing...

[OK] Consuming messages from transport "async".

 // The worker will automatically exit once it has received a stop signal via the messenger:stop-workers command.

 // Quit the worker with CONTROL-C.

 09:13:27 INFO      [messenger] Received message Symfony\Component\Mailer\Messenger\SendEmailMessage ["class" => "Symfony\Component\Mailer\Messenger\SendEmailMessage"]
 09:13:29 INFO      [messenger] Message Symfony\Component\Mailer\Messenger\SendEmailMessage handled by Symfony\Component\Mailer\Messenger\MessageHandler::__invoke ["class" => "Symfony\Component\Mailer\Messenger\SendEmailMessage","handler" => "Symfony\Component\Mailer\Messenger\MessageHandler::__invoke"]
 09:13:29 INFO      [messenger] Symfony\Component\Mailer\Messenger\SendEmailMessage was handled successfully (acknowledging to transport). ["class" => "Symfony\Component\Mailer\Messenger\SendEmailMessage"]
```

#### Check Redis Queue

```bash
# Connect to Redis
docker exec -it project2-redis-1 redis-cli

# View queue length
> XLEN messages
(integer) 0  # 0 = all messages processed

# View pending messages
> XPENDING messages symfony
1) (integer) 0  # No pending messages

# View consumer group info
> XINFO GROUPS messages
1) 1) "name"
   2) "symfony"
   3) "consumers"
   4) (integer) 1  # 1 worker connected
```

#### Check Queue Stats

```bash
# View messenger statistics
docker exec project2-php-1 php bin/console messenger:stats

# Expected output:
#  async
# -------
#
#  Options
#  ------------- -----------------------------------------
#  DSN           redis://redis:6379/messages
#  Options
#  ------------- -----------------------------------------
#
#  Messages Waiting: 0
#  Messages Handled: 5
#  Messages Failed:  0
```

###test Async Email

```bash
# This will queue the email and return immediately
docker exec project2-php-1 php bin/console app:test-email asem4o@gmail.com
```

**Timeline**:
1. Command completes in ~50ms (just queues to Redis)
2. Worker picks up message within ~100ms
3. Email sends via SMTP in ~2-3 seconds
4. Worker logs success

**Check Worker Logs**:
```bash
docker logs project2-worker-1 --tail 20

# Should show:
# [messenger] Received message SendEmailMessage
# [messenger] Message handled successfully
```

### Password Reset Flow (gRPC)

```protobuf
// Call ForgotPassword
request = {
  "email": "asem4o@gmail.com"
}

// Response (immediate, ~10ms)
response = {
  "success": true,
  "message": "If an account exists with this email, a password reset link has been sent."
}

// Email queued to Redis
// Worker processes in background (2-5 seconds)
// User receives email
```

---

## Performance Comparison

### Before (Synchronous)

```
User Request → Generate Email → Connect SMTP → Send Email → Return Response
|____________________________________________________________|
                    Total: 2-5 seconds
```

**Issues**:
- User waits for SMTP connection
- Single failure blocks web interface
- High latency during SMTP issues
- Resource intensive (holds PHP worker)

### After (Asynchronous with Redis)

```
User Request → Generate Email → Queue to Redis → Return Response
|_______________________________________________|
             Total: 10-50ms

             Background (Worker):
             Redis → Consume → Send Email
             |_____________________|
                  2-5 seconds
```

**Benefits**:
- User gets instant response
- SMTP failures don't affect web interface
- Workers can retry automatically
- Scalable (multiple workers)
- Resource efficient

---

## Scaling

### Running Multiple Workers

**Edit** `docker-compose.yml`:

```yaml
worker:
  # ... existing config ...
  deploy:
    replicas: 3  # Run 3 workers
```

**Or manually**:

```bash
# Scale to 3 workers
docker-compose up -d --scale worker=3

# Check running workers
docker-compose ps worker
```

**Load Balancing**:
- Redis consumer groups automatically distribute messages
- Each worker gets unique messages
- No duplication, no conflicts

### Monitoring Performance

```bash
# Redis throughput
docker exec project2-redis-1 redis-cli INFO stats | grep instantaneous_ops_per_sec

# Worker memory usage
docker stats project2-worker-1

# Queue backlog
docker exec project2-redis-1 redis-cli XLEN messages
```

---

## Failure Handling

### Scenarios

#### 1. Worker Crashes

**What Happens**:
- Docker automatically restarts worker (`restart: unless-stopped`)
- In-flight messages return to pending state
- New worker picks them up

**Recovery Time**: ~5 seconds

#### 2. Redis Crashes

**What Happens**:
- Messages in queue are persisted to disk (AOF enabled)
- Web interface queues to Redis fail gracefully
- Worker waits for Redis to recover

**Mitigation**:
```bash
# Check Redis persistence
docker exec project2-redis-1 redis-cli CONFIG GET appendonly
# Should return "yes"
```

#### 3. SMTP Failure

**What Happens**:
- Worker retries 3 times with exponential backoff
- After max retries, moves to `failed` transport
- Failed messages queryable for manual retry

**View Failed Messages**:
```bash
docker exec project2-php-1 php bin/console messenger:failed:show

# Retry failed messages
docker exec project2-php-1 php bin/console messenger:failed:retry
```

#### 4. Network Issues

**What Happens**:
- Worker loses connection to Redis/MySQL/SMTP
- Attempt times out
- Message returns to pending
- Different worker can pick it up

**Auto-Recovery**: Yes, via retry mechanism

---

## Monitoring & Debugging

### Debug Mode

Enable very verbose output:

```bash
# Temporary: Run worker manually with debug
docker exec -it project2-php-1 php bin/console messenger:consume async -vvv
```

Output shows:
- Exact time each message received
- Handler execution time
- Success/failure status
- Full stack traces on errors

### Log Configuration

**Add to** `config/packages/monolog.yaml`:

```yaml
when@dev:
  monolog:
    handlers:
      messenger:
        type: stream
        path: "%kernel.logs_dir%/messenger.log"
        level: debug
        channels: ["messenger"]
```

**View Logs**:
```bash
docker exec project2-php-1 tail -f var/log/messenger.log
```

### Performance Metrics

**Track**:
- Messages processed per second
- Average processing time
- Error rate
- Queue depth over time

**Tools**:
- Redis MONITOR command
- Symfony Profiler (dev mode)
- Custom Prometheus exporter

---

## Security Considerations

### Redis Access

**Current**: Redis exposed on `localhost:6379`

**Production**:
```yaml
redis:
  # Don't expose port publicly
  # ports:
  #   - "6379:6379"  # REMOVE in production

  command: redis-server --requirepass "STRONG_PASSWORD_HERE"
```

**Update DSN**:
```bash
MESSENGER_TRANSPORT_DSN=redis://:STRONG_PASSWORD_HERE@redis:6379/messages
```

### Message Encryption

For sensitive data in messages:

```yaml
# config/packages/messenger.yaml
transports:
  async:
    serializer: 'App\Messenger\EncryptedSerializer'
```

### Worker Security

**Principle of Least Privilege**:
- Worker needs: Redis read/write, SMTP send, DB read
- Worker doesn't need: HTTP listen, SSH access

**Implement**:
```dockerfile
# In worker Dockerfile
USER www-data
RUN rm -rf /usr/bin/ssh /usr/bin/curl  # Remove unnecessary tools
```

---

## Troubleshooting

### Problem: Worker not processing messages

**Check 1**: Worker running?
```bash
docker ps | grep worker
```

**Check 2**: Worker connected to Redis?
```bash
docker logs project2-worker-1 | grep "Consuming messages"
```

**Check 3**: Messages in queue?
```bash
docker exec project2-redis-1 redis-cli XLEN messages
```

**Fix**: Restart worker
```bash
docker-compose restart worker
```

### Problem: Messages stuck in pending

**Symptom**: `XPENDING messages symfony` shows old messages

**Cause**: Worker crashed mid-processing

**Fix**: Claim pending messages
```bash
docker exec project2-redis-1 redis-cli XCLAIM messages symfony worker 0 $(redis-cli XPENDING messages symfony | head -1 | awk '{print $2}')
```

### Problem: Redis memory full

**Symptom**: `docker logs project2-redis-1` shows OOM errors

**Fix 1**: Increase memory limit
```yaml
command: redis-server --maxmemory 512mb  # Was 256mb
```

**Fix 2**: Process messages faster (add workers)
```bash
docker-compose up -d --scale worker=3
```

### Problem: High latency

**Check**: Queue backlog
```bash
docker exec project2-redis-1 redis-cli XLEN messages
```

**If > 100**: Add more workers
**If < 10**: Check SMTP performance

---

## Maintenance

### Daily

```bash
# Check worker health
docker ps | grep worker

# Check queue depth
docker exec project2-redis-1 redis-cli XLEN messages
```

### Weekly

```bash
# Review failed messages
docker exec project2-php-1 php bin/console messenger:failed:show

# Check Redis memory
docker exec project2-redis-1 redis-cli INFO memory | grep used_memory_human
```

### Monthly

```bash
# Restart workers (clear memory)
docker-compose restart worker

# Backup Redis data
docker cp project2-redis-1:/data/appendonly.aof ./backup/redis-$(date +%Y%m%d).aof
```

---

## Configuration Reference

### Environment Variables

| Variable | Value | Purpose |
|----------|-------|---------|
| `MESSENGER_TRANSPORT_DSN` | `redis://redis:6379/messages` | Redis connection for queue |
| `MAILER_DSN` | `gmail+smtp://user:pass@default` | SMTP for sending emails |
| `APP_URL` | `http://localhost` | Base URL for reset links |
| `MAILER_FROM_EMAIL` | `asem4o@gmail.com` | Sender email address |
| `MAILER_FROM_NAME` | `My Symfony App` | Sender display name |

### Worker Tuning

| Parameter | Default | Purpose |
|-----------|---------|---------|
| `--time-limit` | 3600 | Restart worker after N seconds |
| `--memory-limit` | 128M | Restart if memory exceeds limit |
| `--limit` | None | Stop after N messages |
| `-vv` | Off | Enable verbose output |

### Redis Tuning

| Parameter | Default | Purpose |
|-----------|---------|---------|
| `--maxmemory` | 256mb | Max memory for queue |
| `--maxmemory-policy` | allkeys-lru | Eviction policy when full |
| `--appendonly` | yes | Persist messages to disk |

---

## Next Steps

1. **Monitor in Production**
   - Set up alerts for queue depth
   - Track message processing time
   - Monitor worker restarts

2. **Optimize Performance**
   - Add more workers if needed
   - Tune retry strategy
   - Implement batching for high volume

3. **Enhanced Logging**
   - Add custom middleware
   - Track business metrics
   - Integrate with APM tools

4. **Consider Alternatives**
   - RabbitMQ for complex routing
   - Amazon SQS for managed service
   - Kafka for high throughput

---

## Summary

✅ **Redis service** added for queue management
✅ **Worker container** processes emails in background
✅ **PHP Redis extension** installed
✅ **Symfony Messenger** configured for async
✅ **Monitoring tools** available
✅ **Auto-restart** on failures
✅ **Scalable** to multiple workers

**Result**: Fast web interface + reliable email delivery

---

**Last Updated**: November 25, 2025
**Author**: Development Team
**Version**: 1.0
