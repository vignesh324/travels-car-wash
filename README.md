# Travels & Car Wash Business Management

A complete, production-ready, mobile-first web application designed for small business owners managing dual business operations:
1. **Travels & Fleet Business**: 6–20 vehicles, daily trip tracking, driver details, odometer readings, and vehicle expenses.
2. **Car Wash Business**: Multi-service catalogue, walk-in billing, and transaction tracking.
3. **Shared Infrastructure**: Unified customer base, Indian GST invoice generation, PDF receipts/invoices, XLSX exports, payment collection, advance ledger, and financial reporting.

---

## 1. Technology Stack

- **Backend**: Laravel 12 on PHP 8.3 / 8.4
- **Database**: MySQL 8 (InnoDB, UTF8mb4)
- **Frontend**: Blade templates, Tailwind CSS (CDN), Chart.js (CDN), vanilla JS
- **Tabular Data**: Yajra Laravel DataTables (server-side pagination, sorting, search, column filters)
- **Audit & Activity**: Spatie Laravel Activitylog
- **PDF Generation**: Barryvdh Laravel DomPDF
- **Excel Export**: Maatwebsite Laravel Excel / PhpSpreadsheet
- **Authentication**: Laravel Sanctum (Stateful session & Bearer API token)

---

## 2. Core Architecture & Standards

### 2.1 Primary Keys and Public UUIDs
- **Primary Keys**: Every business table strictly utilizes an auto-incrementing integer `id` as primary key for optimal database foreign key performance and indexing.
- **Public Identifiers**: A separate unique, indexed `uuid` column is generated automatically via the `HasUuid` trait on record creation. Route model binding seamlessly resolves both UUIDs and numeric IDs.

### 2.2 Audit Fields & Soft Deletes
- All applicable master and transaction tables feature `created_by`, `updated_by`, and `deleted_by` foreign keys managed automatically via the `HasAuditFields` trait.
- Soft deletion (`SoftDeletes`) is applied to preserve audit trails. Issued invoices and financial payments are protected from deletion; they use controlled cancellation and reversal workflows.

### 2.3 Financial Calculation Strategy
- **Authoritative Server Calculations**: All financial calculations are executed on the backend with `DECIMAL(10,2)` or `DECIMAL(12,2)` precision. Frontend submitted totals are never trusted.
- **GST Tax Rules**: Automatically calculates intra-state (equal CGST + SGST) or inter-state (IGST) tax based on business profile state vs customer / place of supply state.
- **Distinct Financial Flows**: Clearly isolates Invoiced Revenue from Actual Collections, Advance Credits, and Estimated Operating Profit.

---

## 3. Database Schema Overview

| Table | Description |
|---|---|
| `users` | Business owner credentials, Sanctum tokens |
| `business_profiles` | GSTIN, address, bank/UPI, invoice/receipt numbering |
| `vehicles` | Fleet records (registration, fuel, odometer, document expiries) |
| `customers` | Individual, Corporate, and Walk-in customers with ledger |
| `trips` | Daily trip log, start/end odometer, fare, tax, invoiced flag |
| `expense_categories` | Categorization for vehicle and business expenses |
| `vehicle_expenses` | Fuel, repairs, maintenance, wages linked to vehicles |
| `business_expenses` | Rent, electricity, car wash supplies, general overhead |
| `invoices` | Tax invoices with historical customer and business snapshots |
| `invoice_items` | Line items retaining source (`travels` vs `carwash`) |
| `invoice_trip_links` | Pivot table linking trips to invoices |
| `invoice_sequences` | Atomic sequence counter for FY-specific invoice numbering |
| `payments` | Customer payment receipts with advance unallocated balance |
| `payment_allocations` | Allocation mapping payments to outstanding invoices |
| `car_wash_services` | Service catalogue with default prices and GST rates |
| `car_wash_transactions` | Walk-in and registered car wash sales |
| `car_wash_transaction_items` | Multi-service line items per car wash transaction |
| `activity_log` | Spatie audit log tracking all model and user events |

---

## 4. Local Development Setup

### Prerequisites
- PHP >= 8.3 (with `pdo_mysql`, `gd`, `bcmath`, `mbstring`, `xml`, `curl`)
- Composer >= 2.x
- MySQL >= 8.0

### Step-by-Step Installation

1. **Clone and Install PHP Dependencies**:
   ```bash
   composer install
   ```

2. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update `.env` with your local database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=travels_car_wash
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Run Migrations and Seeders**:
   ```bash
   php artisan migrate:fresh --seed
   ```
   *Default Login Credentials*:
   - Email: `admin@example.com`
   - Password: `password`

4. **Start the Local App**:
   Start Apache and MySQL from Laragon. Ensure the Laragon virtual host points
   to this project's `public` directory; if you add or change the host while
   Laragon is running, reload Apache from the Laragon menu. Open
   `http://travels-car-wash.localhost` in your browser. If you prefer
   `travels-car-wash.test`, add that name to the Windows hosts file as
   `127.0.0.1 travels-car-wash.test`.

   Frontend assets are served via CDN (Tailwind CSS, Chart.js) — no build step required.

5. **Run Test Suite**:
   ```bash
   php artisan test
   ```

---

## 5. Production VPS Deployment Guide (Ubuntu / Debian)

### 5.1 System Packages
```bash
sudo apt update && sudo apt install -y nginx mysql-server php8.4-fpm php8.4-mysql \
    php8.4-gd php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath \
    git curl unzip supervisor certbot python3-certbot-nginx
```

### 5.2 Directory & Permissions
Deploy code to `/var/www/travels-car-wash`:
```bash
sudo chown -R www-data:www-data /var/www/travels-car-wash
sudo find /var/www/travels-car-wash -type d -exec chmod 755 {} \;
sudo find /var/www/travels-car-wash -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/travels-car-wash/storage /var/www/travels-car-wash/bootstrap/cache
```

### 5.3 Production Optimization
Set `APP_ENV=production`, `APP_DEBUG=false`, and the production domain in
`APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, and `FRONTEND_URL`
before caching configuration.

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### 5.4 Nginx Configuration
Create `/etc/nginx/sites-available/travels-car-wash`:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/travels-car-wash/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # Static Assets Cache
    location ~* \.(css|js|png|jpg|jpeg|gif|svg|ico|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site and obtain SSL:
```bash
sudo ln -s /etc/nginx/sites-available/travels-car-wash /etc/nginx/sites-enabled/
sudo certbot --nginx -d yourdomain.com
sudo systemctl restart nginx
```

---

## 6. Background Queue & Scheduler

### 6.1 Laravel Scheduler (Cron)
Add to `/etc/crontab` or `crontab -e -u www-data`:
```bash
* * * * * cd /var/www/travels-car-wash && php artisan schedule:run >> /dev/null 2>&1
```

### 6.2 Supervisor Queue Worker
Create `/etc/supervisor/conf.d/travels-worker.conf`:
```ini
[program:travels-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/travels-car-wash/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/travels-car-wash/storage/logs/worker.log
stopwaitsecs=3600
```
Update Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start travels-worker:*
```

---

## 7. Database Backup & Disaster Recovery

### 7.1 Automated Daily Backup Script
Create `/usr/local/bin/backup-travels-db.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/travels-car-wash"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
mkdir -p "$BACKUP_DIR"

mysqldump -u your_db_user -p'your_db_password' --single-transaction --quick travels_car_wash | gzip > "$BACKUP_DIR/db_$TIMESTAMP.sql.gz"

# Retain last 30 days of backups
find "$BACKUP_DIR" -type f -name "db_*.sql.gz" -mtime +30 -delete
```
Make executable and schedule in crontab at 02:00 AM daily:
```bash
chmod +x /usr/local/bin/backup-travels-db.sh
0 2 * * * /usr/local/bin/backup-travels-db.sh
```

### 7.2 Database Restoration
```bash
gunzip < /var/backups/travels-car-wash/db_YYYYMMDD_HHMMSS.sql.gz | mysql -u your_db_user -p'your_db_password' travels_car_wash
```

---

## 8. Mobile Quick Actions

- Instant 1-tap entry shortcuts for: `Add Trip`, `Add Expense`, `Create Invoice`, `Record Payment`, and `Create Car Wash Bill`.

## 9. Security Checklist

- [x] Application key generated (`APP_KEY`)
- [x] Debug mode disabled (`APP_DEBUG=false`) in production
- [x] Database credentials restricted with least privileges
- [x] Authentication rate-limited (`throttle:10,1` on login)
- [x] Route model binding guarded against SQL injection
- [x] Issued invoices and payments preserved from hard deletion
- [x] Audit trails tracked via Spatie Activitylog
- [x] HTTPS enforced via SSL and Nginx 301 redirection
- [x] Storage link secured and protected from directory traversal
