# 🚀 Koyeb Deployment Guide - Images & Email Fix

## ✅ What We Fixed

### 1. **Broken Images Issue**
- **Problem:** Images uploaded to `/uploads/gallery/` are lost on container restart (ephemeral storage)
- **Solution:** Integrated Cloudinary for persistent cloud storage with CDN
- **New URL Pattern:** `https://res.cloudinary.com/your-cloud/image/upload/v123/gallery/image.jpg`

### 2. **Email Delivery Issue**
- **Problem:** Gmail SMTP blocks automated emails from containers
- **Solution:** Use Resend (free 3,000 emails/month) for reliable transactional email delivery

---

## 📋 Prerequisites

Before deploying to Koyeb, you need:

1. ✅ **Cloudinary Account** (Free tier: 25 GB storage)
2. ✅ **Resend Account** (Free tier: 3,000 emails/month)
3. ✅ **Koyeb Account** with your app configured

---

## 🖼️ STEP 1: Setup Cloudinary (Image Storage)

### 1.1 Create Cloudinary Account

1. Go to [https://cloudinary.com/users/register_free](https://cloudinary.com/users/register_free)
2. Sign up for free account
3. After login, go to **Dashboard**

### 1.2 Get Your Cloudinary URL

On the Cloudinary Dashboard, you'll see:

```
Cloud Name: your-cloud-name
API Key: 123456789012345
API Secret: abcdefghijklmnopqrstuvwxyz
```

Your **CLOUDINARY_URL** will be:
```
cloudinary://API_KEY:API_SECRET@CLOUD_NAME
```

Example:
```
cloudinary://123456789012345:abcdefghijklmnopqrstuvwxyz@your-cloud-name
```

### 1.3 Add to Koyeb Environment Variables

In your Koyeb service settings, add:

```bash
CLOUDINARY_URL=cloudinary://YOUR_API_KEY:YOUR_API_SECRET@YOUR_CLOUD_NAME
```

---

## 📧 STEP 2: Setup Resend (Email Delivery)

### 2.1 Create Resend Account

1. Go to [https://resend.com/signup](https://resend.com/signup)
2. Sign up for free account (3,000 emails/month)
3. Verify your email address

### 2.2 Add and Verify Domain (Optional but Recommended)

**Option A: Use Verified Domain (Recommended)**

1. Go to **Domains** in Resend dashboard
2. Click **Add Domain**
3. Enter your domain (e.g., `yourdomain.com`)
4. Add the DNS records shown to your domain provider
5. Wait for verification (usually 5-30 minutes)

**Option B: Use Resend Test Domain (Quick Start)**

For testing, you can use `onboarding@resend.dev` as sender

### 2.3 Get Your API Key

1. Go to **API Keys** in Resend dashboard
2. Click **Create API Key**
3. Name it "Production" or "Koyeb"
4. Copy the key (starts with `re_...`)

### 2.4 Update Koyeb Environment Variables

In your Koyeb service settings, update/add:

```bash
# For Resend with verified domain
MAILER_DSN=resend+api://YOUR_RESEND_API_KEY@default

# Sender email (use your verified domain)
MAILER_FROM_EMAIL=noreply@yourdomain.com
MAILER_FROM_NAME="Your Salon Name"
```

**Example:**
```bash
MAILER_DSN=resend+api://re_AbCdEf123456789@default
MAILER_FROM_EMAIL=noreply@elegantsalon.com
MAILER_FROM_NAME="Elegant Hair Salon"
```

---

## 📦 STEP 3: Install Dependencies

```bash
composer require cloudinary/cloudinary_php
composer require symfony/resend-mailer
```

---

## 🔧 STEP 4: Update `.env` for Local Development

Add to your `.env` file:

```bash
# Cloudinary Configuration
CLOUDINARY_URL=cloudinary://YOUR_API_KEY:YOUR_API_SECRET@YOUR_CLOUD_NAME

# Resend Configuration (for local testing)
MAILER_DSN=resend+api://YOUR_RESEND_API_KEY@default
MAILER_FROM_EMAIL=noreply@yourdomain.com
MAILER_FROM_NAME="Your Salon Name"
```

---

## 🧪 STEP 5: Test Locally

### Test Image Upload

```bash
# Start containers
docker-compose up -d

# Access admin panel
http://localhost/admin/gallery

# Upload a new image - should go to Cloudinary
```

### Test Email Delivery

```bash
# Trigger password reset (uses email)
docker-compose exec php bin/console app:test-email your@email.com

# Check logs
docker-compose logs php | grep -i "email"
```

---

## 🌐 STEP 6: Koyeb Environment Variables (Complete List)

Set these in your Koyeb service:

```bash
# Application
APP_ENV=prod
APP_SECRET=<your-32-char-random-secret>
PORT=8000

# Database (use Koyeb database or external)
DATABASE_URL=mysql://user:password@host:port/dbname?serverVersion=8

# JWT Configuration
JWT_SECRET_KEY=/var/www/html/config/jwt/private.pem
JWT_PUBLIC_KEY=/var/www/html/config/jwt/public.pem
JWT_PASSPHRASE=

# Cloudinary (Image Storage)
CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME

# Resend (Email Delivery)
MAILER_DSN=resend+api://YOUR_RESEND_API_KEY@default
MAILER_FROM_EMAIL=noreply@yourdomain.com
MAILER_FROM_NAME="Your Salon Name"

# Google OAuth
GOOGLE_CLIENT_ID=<your-google-client-id>
GOOGLE_CLIENT_SECRET=<your-google-client-secret>

# ReCAPTCHA
RECAPTCHA_SITE_KEY=<your-site-key>
RECAPTCHA_SECRET_KEY=<your-secret-key>
```

---

## 📤 STEP 7: Deploy to Koyeb

```bash
# Commit all changes
git add .
git commit -m "Add Cloudinary image storage and Resend email delivery

- Replace local file uploads with Cloudinary
- Replace Gmail SMTP with Resend for reliable email delivery
- Add image_url Twig filter for backward compatibility
- Update templates to support both local and cloud URLs"

git push origin main
```

Koyeb will automatically detect the push and redeploy.

---

## ✅ STEP 8: Verify Deployment

### Check Images Work

1. Go to your Koyeb app URL: `https://your-app.koyeb.app`
2. Navigate to gallery section
3. Images should load from `https://res.cloudinary.com/...`

### Check Admin Upload

1. Login to admin: `https://your-app.koyeb.app/admin/gallery`
2. Upload a new image
3. Should see success message
4. Image should appear immediately

### Check Email Delivery

1. Test password reset: `https://your-app.koyeb.app/forgot-password`
2. Enter your email
3. Check inbox (including spam)
4. Should receive email from your verified domain

---

## 🔍 Troubleshooting

### Images Not Showing

**Check Cloudinary URL:**
```bash
# In Koyeb logs
echo $CLOUDINARY_URL
# Should show: cloudinary://...
```

**Check Database:**
```bash
# New uploads should have full Cloudinary URLs
SELECT filename FROM gallery_image ORDER BY created_at DESC LIMIT 5;
# Should show: https://res.cloudinary.com/...
```

### Email Not Delivered

**Check Resend Dashboard:**
1. Go to **Logs** in Resend
2. Check if email was sent
3. Look for errors

**Check Sender Address:**
```bash
# Must match verified domain
MAILER_FROM_EMAIL=noreply@yourdomain.com  # ✅ Verified
MAILER_FROM_EMAIL=noreply@gmail.com       # ❌ Won't work
```

### Old Images Not Showing

Old images stored locally will be broken. Options:

**Option 1: Manual Migration**
```bash
# Download old images from container
docker-compose exec php tar czf /tmp/uploads.tar.gz public/uploads/gallery/

# Upload to Cloudinary manually via dashboard
```

**Option 2: Create Migration Script**
```php
// src/Command/MigrateImagesToCloudinaryCommand.php
// Upload all old local images to Cloudinary and update database
```

---

## 📊 Monitoring

### Cloudinary Usage

- Dashboard: [https://cloudinary.com/console](https://cloudinary.com/console)
- Free tier: 25 GB storage, 25 GB bandwidth/month
- Monitor transformations, storage, bandwidth

### Resend Usage

- Dashboard: [https://resend.com/emails](https://resend.com/emails)
- Free tier: 3,000 emails/month
- View delivery status, open rates, bounces

---

## 🎉 Success Checklist

- ✅ Cloudinary account created and configured
- ✅ Resend account created with verified domain
- ✅ Environment variables set in Koyeb
- ✅ Dependencies installed (`cloudinary_php`, `symfony/resend-mailer`)
- ✅ Code deployed to Koyeb
- ✅ New images upload to Cloudinary
- ✅ Images display correctly on frontend
- ✅ Emails deliver successfully
- ✅ Password reset emails received

---

## 💡 Pro Tips

### Cloudinary

- Use transformations for automatic image optimization
- Enable automatic format conversion (WebP for modern browsers)
- Set up automatic quality adjustment
- Use responsive breakpoints for mobile optimization

### Resend

- Set up webhooks to track email delivery status
- Use email templates for consistent branding
- Monitor bounce rates and adjust sender reputation
- Enable DKIM/SPF for better deliverability

---

## 🆘 Need Help?

- **Cloudinary Docs:** https://cloudinary.com/documentation
- **Resend Docs:** https://resend.com/docs
- **Koyeb Support:** https://www.koyeb.com/docs

---

Your Symfony app is now production-ready with persistent image storage and reliable email delivery! 🚀
