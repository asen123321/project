# 🔧 Supervisor Setup for Symfony Messenger on Koyeb

## 🎯 What This Solves

**Problem:** Emails are queued in the `async` transport but never sent because `messenger:consume` is not running.

**Solution:** Use **Supervisor** to manage two processes in a single container:
1. **Apache** (Web Server) - Serves your Symfony application
2. **Messenger Worker** - Processes async email queue

---

## ✅ What Was Changed

### **1. Dockerfile - Added Supervisor**

**Line 20:** Added `supervisor` to apt-get install:
```dockerfile
RUN apt-get update && apt-get install -y \
    ...
    supervisor \
    && rm -rf /var/lib/apt/lists/*
```

**Line 86:** Copy supervisor config:
```dockerfile
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
```

### **2. supervisord.conf - Process Configuration**

Created new file `supervisord.conf` with two programs:

**Program 1: Apache (Web Server)**
```ini
[program:apache2]
command=/usr/sbin/apache2ctl -D FOREGROUND
autostart=true
autorestart=true
user=root
priority=10
```

**Program 2: Messenger Worker**
```ini
[program:messenger-worker]
command=php /var/www/html/bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
autostart=true
autorestart=true
user=www-data
priority=20
```

### **3. docker-entrypoint.sh - Start Supervisor**

**Line 229:** Changed from `apache2-foreground` to `supervisord`:
```bash
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

---

## 📋 How Supervisor Works

```
Container Startup
      ↓
docker-entrypoint.sh
      ↓
  supervisord
      ↓
  ┌───┴───┐
  ↓       ↓
Apache  Messenger
(Port   Worker
8000)   (async queue)
```

### **Process Management:**

| Process | Command | User | Auto-restart | Priority |
|---------|---------|------|--------------|----------|
| **Apache** | `apache2ctl -D FOREGROUND` | root | Yes | 10 (starts first) |
| **Messenger** | `messenger:consume async` | www-data | Yes | 20 (starts second) |

### **Key Features:**

- ✅ **Auto-restart:** If either process crashes, Supervisor restarts it automatically
- ✅ **Graceful shutdown:** Processes receive SIGTERM and have 10 seconds to clean up
- ✅ **Separate logs:** Each process has its own log file
- ✅ **Single container:** Both processes run in one container (perfect for Koyeb)

---

## 🚀 Deployment Steps

### **1. Verify Files Are Created**

```bash
ls -la supervisord.conf
ls -la docker-entrypoint.sh
ls -la Dockerfile
```

**Expected:**
```
-rw-r--r-- supervisord.conf
-rwxr-xr-x docker-entrypoint.sh
-rw-r--r-- Dockerfile
```

### **2. Commit Changes**

```bash
git add Dockerfile supervisord.conf docker-entrypoint.sh
git commit -m "Add Supervisor to manage Apache + Messenger worker

- Install supervisor package in Dockerfile
- Create supervisord.conf to manage Apache and messenger:consume
- Update docker-entrypoint.sh to start supervisord instead of Apache
- Messenger worker will now process async emails automatically
- Both processes auto-restart on failure"

git push origin main
```

### **3. Monitor Koyeb Deployment**

Watch the deployment logs in Koyeb Dashboard:

**Expected output:**
```
========================================
Starting Symfony Application Deployment
========================================

✓ Project-wide permissions fixed!
✓ JWT keys generated successfully!
✓ Assets installed successfully!
✓ Cache: Cleared and warmed

Creating supervisor log directory...
✓ Supervisor log directory ready

Starting Supervisor...
Supervisor will manage:
  1. Apache (Web Server) on port 8000
  2. Messenger Worker (async email processing)

========================================
🚀 Application Ready!
========================================
```

### **4. Verify Processes Are Running**

After deployment, check Koyeb logs for:

```
INFO spawned: 'apache2' with pid 123
INFO spawned: 'messenger-worker_00' with pid 124
INFO success: apache2 entered RUNNING state
INFO success: messenger-worker_00 entered RUNNING state
```

---

## 🧪 Testing Email Delivery

### **Test 1: Trigger Password Reset**

```bash
# Go to your production site
https://low-gianina-usersymfony-955f83af.koyeb.app/forgot-password

# Enter email and submit
# Wait 5-10 seconds
# Check inbox (and spam folder)
```

### **Test 2: Check Messenger Worker Logs**

In Koyeb Dashboard → Logs, search for:

```
messenger-worker
```

**Expected output:**
```
[OK] Consuming messages from transports "async"
Received message App\Message\SendEmailMessage
Processing message...
Email sent successfully!
```

### **Test 3: Verify Both Processes Running**

Check Koyeb logs for supervisor status:

```bash
# Should see both processes in RUNNING state
apache2: RUNNING
messenger-worker_00: RUNNING
```

---

## 📊 Monitoring

### **Check Supervisor Status**

If you have SSH access to the container (not available on Koyeb), you would run:

```bash
supervisorctl status
```

**Expected:**
```
apache2                          RUNNING   pid 123, uptime 1:23:45
messenger-worker_00              RUNNING   pid 124, uptime 1:23:45
```

### **View Logs in Koyeb**

All logs are visible in Koyeb Dashboard → Logs:

**Apache logs:**
```
[apache2] AH00558: apache2: Could not reliably determine server's FQDN
[apache2] [notice] Apache/2.4.57 (Debian) configured
[apache2] [notice] Server built: Mar 19 2024
```

**Messenger worker logs:**
```
[messenger-worker_00] [OK] Consuming messages from transports "async"
[messenger-worker_00] Received message #1
[messenger-worker_00] Message handled
```

---

## 🔍 Troubleshooting

### **Issue: Emails Still Not Sent**

**Check 1: Verify messenger transport is configured**

In `config/packages/messenger.yaml`:
```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'

        routing:
            Symfony\Component\Mailer\Messenger\SendEmailMessage: async
```

**Check 2: Verify MESSENGER_TRANSPORT_DSN in Koyeb**
```bash
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=1
```

**Check 3: Check messenger worker is consuming**

In Koyeb logs, search for:
```
messenger:consume async
```

Should see:
```
[OK] Consuming messages from transports "async"
```

### **Issue: Messenger Worker Keeps Restarting**

**Cause:** Memory limit exceeded or time limit reached

**Solution:** Adjust limits in `supervisord.conf`:

```ini
[program:messenger-worker]
command=php /var/www/html/bin/console messenger:consume async --time-limit=7200 --memory-limit=256M
```

Then redeploy.

### **Issue: Apache Not Starting**

**Check Koyeb logs for:**
```
apache2: FATAL
```

**Common causes:**
- Port 8000 configuration issue
- DocumentRoot permissions
- .htaccess syntax error

**Solution:** Check Apache error log in Koyeb logs:
```
[apache2] [error] ...
```

### **Issue: "supervisord: command not found"**

**Cause:** Supervisor not installed

**Solution:** Rebuild Docker image (Koyeb should do this automatically on push)

---

## 📈 Performance Tuning

### **Multiple Messenger Workers**

For high email volume, increase worker count in `supervisord.conf`:

```ini
[program:messenger-worker]
command=php /var/www/html/bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
numprocs=3  # ← Change from 1 to 3
process_name=%(program_name)s_%(process_num)02d
```

This will create:
- `messenger-worker_00`
- `messenger-worker_01`
- `messenger-worker_02`

**⚠️ Warning:** More workers = more memory usage. Monitor Koyeb resource limits.

### **Worker Time Limits**

Workers restart after time limit to prevent memory leaks:

```bash
--time-limit=3600   # Restart after 1 hour
--time-limit=7200   # Restart after 2 hours
```

Shorter = more frequent restarts = less memory issues
Longer = fewer restarts = slightly more efficient

### **Memory Limits**

```bash
--memory-limit=128M   # 128 MB (default)
--memory-limit=256M   # 256 MB (for heavy workloads)
```

---

## 🔐 Security Considerations

### **Worker User**

Messenger worker runs as `www-data` (not root):
```ini
user=www-data
```

This is secure because:
- Limited file system access
- Cannot modify system files
- Cannot install packages
- Follows principle of least privilege

### **Apache User**

Apache runs as `root` initially but spawns child processes as `www-data`:
```ini
user=root
```

This is required for:
- Binding to port 8000
- Managing Apache modules
- Handling .htaccess files

---

## 📦 What Happens on Container Restart

```
1. Container starts
2. docker-entrypoint.sh runs
   ├─ Sets permissions
   ├─ Generates JWT keys
   ├─ Runs migrations
   └─ Starts supervisord
3. Supervisord starts Apache
4. Supervisord starts Messenger Worker
5. Both processes run indefinitely
6. If either crashes, Supervisor restarts it
```

---

## ✅ Success Checklist

After deployment:

- [ ] Container starts without errors
- [ ] Apache is accessible on https://your-app.koyeb.app/
- [ ] Koyeb logs show "apache2: RUNNING"
- [ ] Koyeb logs show "messenger-worker_00: RUNNING"
- [ ] Password reset email triggers
- [ ] Email arrives in inbox within 10 seconds
- [ ] Messenger worker logs show "Message handled"

---

## 🎉 Benefits of This Setup

| Before (No Worker) | After (With Supervisor) |
|-------------------|------------------------|
| ❌ Emails queued forever | ✅ Emails sent automatically |
| ❌ Manual worker needed | ✅ Worker runs automatically |
| ❌ No auto-restart | ✅ Auto-restart on crash |
| ❌ Single point of failure | ✅ Both processes monitored |
| ❌ Hard to debug | ✅ Separate logs per process |

---

## 🆘 Need Help?

**Supervisor Documentation:**
- http://supervisord.org/

**Symfony Messenger:**
- https://symfony.com/doc/current/messenger.html

**Koyeb Support:**
- https://www.koyeb.com/docs

---

Your Symfony application now has a production-ready email processing system! 🚀
