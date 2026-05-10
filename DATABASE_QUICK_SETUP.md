# DTI Database Quick Setup Guide

## Quick Start

### Prerequisites
- XAMPP installed and running
- MySQL service running
- phpMyAdmin accessible

### Method 1: Automatic Setup (PHP will create tables on first run)
The application will automatically create all database tables when you include `includes/init.php`. No manual setup needed!

**What happens automatically**:
1. Connects to MySQL server
2. Creates database `dti` if it doesn't exist
3. Creates all 9 tables with proper structure
4. Adds necessary indexes
5. Applies backward compatibility migrations

Just access the application and it will initialize everything.

### Method 2: Manual SQL Import
If you want to pre-create tables:

**Using phpMyAdmin (Visual)**:
1. Open http://localhost/phpmyadmin
2. Create database `dti` (charset: utf8mb4)
3. Go to "Import" tab
4. Browse to `database_schema.sql`
5. Click "Go"

**Using Command Line**:
```bash
C:\xampp\mysql\bin\mysql -u root dti < "C:\xampp\htdocs\dti\database_schema.sql"
```

---

## Database Tables Summary

### Core Tables (9)
1. **users** - Employee profiles
2. **activities** - Calendar events & activities
3. **supply_requests** - Supply requests
4. **vehicle_requests** - Vehicle/transport requests
5. **passengers** - Vehicle passengers
6. **(removed)** - Time-off requests feature removed
7. **notifications** - System notifications
8. **employee_events** - Current employee status
9. **user_login_logs** - Login audit trail

---

## Key Features

✅ **Auto-increment IDs** - All tables use auto-increment primary keys
✅ **Foreign Keys** - Referential integrity maintained
✅ **Indexes** - Performance-optimized queries
✅ **Timestamps** - Auto-managed created_at & updated_at
✅ **Unicode Support** - Full UTF-8mb4 support
✅ **Cascading Deletes** - Clean data removal
✅ **Null Handling** - Proper optional/required fields
✅ **Status Tracking** - Request workflows (pending, approved, rejected)

---

## Database Configuration (from includes/db.php)

```
Host: 127.0.0.1 (localhost)
Port: 3306
Username: root
Password: (empty for default XAMPP)
Database: dti
Charset: utf8mb4
```

---

## First User Setup

After database creation, add a first user (admin):

### Using phpMyAdmin:
1. Go to `users` table
2. Click "Insert" tab
3. Fill in:
   - first_name: Admin
   - last_name: User
   - email: admin@example.com
   - password: `password_hash('password123', PASSWORD_DEFAULT)` (PHP)
   - division: Admin Division
   - birthdate: 1990-01-01
4. Submit

### Using SQL:
```sql
INSERT INTO users (first_name, last_name, email, password, division, birthdate)
VALUES ('Admin', 'User', 'admin@example.com', 
        '$2y$10$...hashed_password...', 'Admin Division', '1990-01-01');
```

**Note**: For real passwords, use PHP to hash:
```php
$hashed = password_hash('your_password', PASSWORD_DEFAULT);
```

---

## Testing Database Connection

Create a test file `test_db.php`:

```php
<?php
require_once 'includes/init.php';

try {
    $result = $mysqli->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'dti'");
    $row = $result->fetch_assoc();
    echo "✓ Database connected successfully!\n";
    echo "Tables found: " . $row['table_count'] . "\n";
    
    $tables = ['users', 'activities', 'supply_requests', 'vehicle_requests', 
               'passengers', 'notifications', 'employee_events', 
               'user_login_logs'];
    
    foreach ($tables as $table) {
        $check = $mysqli->query("SHOW TABLES LIKE '$table'");
        echo ($check->num_rows > 0 ? "✓" : "✗") . " $table\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
?>
```

Run: `C:\xampp\php\php.exe test_db.php`

---

## Backup & Restore

### Backup all data:
```bash
C:\xampp\mysql\bin\mysqldump -u root dti > dti_backup_$(date +%Y%m%d).sql
```

### Backup to file (Windows):
```powershell
C:\xampp\mysql\bin\mysqldump -u root dti > dti_backup.sql
```

### Restore:
```bash
C:\xampp\mysql\bin\mysql -u root dti < dti_backup.sql
```

---

## Common Commands

### Check database size:
```sql
SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
FROM information_schema.tables WHERE table_schema = 'dti';
```

### View table structure:
```sql
DESCRIBE users;
DESCRIBE activities;
-- etc.
```

### Check indexes:
```sql
SHOW INDEX FROM users;
```

### Reset auto-increment:
```sql
ALTER TABLE users AUTO_INCREMENT = 1;
```

### Clear all data (WARNING):
```sql
TRUNCATE TABLE user_login_logs;
TRUNCATE TABLE notifications;
TRUNCATE TABLE employee_events;
TRUNCATE TABLE passengers;
TRUNCATE TABLE vehicle_requests;
TRUNCATE TABLE supply_requests;
TRUNCATE TABLE activities;
```

---

## Troubleshooting

### "Database doesn't exist"
Solution: Run `database_schema.sql` or let the application create it automatically

### "Table already exists"
Solution: This is normal - the schema uses `CREATE TABLE IF NOT EXISTS`

### "Connection refused"
Solution: Make sure MySQL service is running:
```bash
C:\xampp\start_xampp.exe
```

### "Permission denied"
Solution: Check MySQL user has proper permissions

### "Special characters not showing"
Solution: Verify UTF-8mb4 charset is set (already configured in schema)

---

## Files Included

- `database_schema.sql` - Complete SQL schema
- `DATABASE_DOCUMENTATION.md` - Detailed documentation  
- `DATABASE_QUICK_SETUP.md` - This file

---

**Setup Time**: ~2 minutes
**Database Size**: ~1-2 MB (empty)
**Maintenance**: Backup weekly, optimize monthly
