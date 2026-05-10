# DTI Website Database Documentation

## Overview
The DTI (Department of Trade and Industry) management system database is built on MySQL/MariaDB and uses the `utf8mb4` character set for full Unicode support. The database contains 9 main tables for managing employee information, activities, requests, and system operations.

---

## Database Configuration
- **Database Name**: `dti`
- **Host**: `127.0.0.1` (localhost)
- **User**: `root` (default, no password for XAMPP)
- **Port**: `3306`
- **Character Set**: `utf8mb4` with `utf8mb4_unicode_ci` collation
- **Engine**: InnoDB (for foreign key support and transactions)

---

## Table Structure

### 1. **users** - Employee/User Information
Stores all employee/user accounts for the system.

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | User unique identifier |
| first_name | VARCHAR(191) | | Employee first name |
| last_name | VARCHAR(191) | | Employee last name |
| middle_name | VARCHAR(191) | | Employee middle name (optional) |
| suffix | VARCHAR(64) | | Name suffix like Jr., Sr. (optional) |
| birthdate | DATE | | Employee birth date |
| email | VARCHAR(191) | UNIQUE | Employee email (must be unique) |
| password | VARCHAR(255) | | Hashed password |
| division | VARCHAR(191) | INDEX | Employee division assignment (comma-separated for multi-division) |
| created_at | DATETIME | INDEX | Account creation timestamp |
| avatar | VARCHAR(255) | | Path to profile avatar image |

**Indexes**: `email`, `division`, `created_at`
**Sample Divisions**: Admin Division, Business Development, Consumer Protection, etc.

---

### 2. **activities** - Calendar/Indicative Events
Stores all calendar activities and indicative events for employees.

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | Activity unique identifier |
| user_id | INT | FK | References `users.id` |
| purpose | VARCHAR(255) | | Purpose of activity (e.g., "Conference", "Meeting") |
| destination | VARCHAR(255) | | Location/destination of activity |
| start_datetime | DATETIME | INDEX | Activity start date and time |
| end_datetime | DATETIME | INDEX | Activity end date and time |
| is_global | TINYINT(1) | | Flag: 1 = global event, 0 = personal activity |
| division_scope | VARCHAR(120) | INDEX | Division scope for division-level events |
| created_at | DATETIME | INDEX | When activity was created |

**Relationships**: 
- `user_id` → `users.id` (one user can have many activities)

---

### 3. **supply_requests** - Office Supply Requests
Tracks employee requests for office supplies and materials.

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | Request unique identifier |
| user_id | INT | FK | References `users.id` |
| item | VARCHAR(191) | | Supply item name |
| variant | VARCHAR(191) | | Item variant/specifications |
| quantity | INT | | Quantity requested |
| unit | VARCHAR(64) | | Unit of measurement (pcs, boxes, reams, etc.) |
| created_at | DATETIME | INDEX | When request was created |

**Relationships**: 
- `user_id` → `users.id` (one user can submit many requests)

---

### 4. **vehicle_requests** - Transportation/Vehicle Requests
Stores employee requests for company vehicle use.

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | Request unique identifier |
| user_id | INT | FK | Requesting employee |
| date_application | DATE | | Date request was submitted |
| date_use | DATE | INDEX | Date vehicle will be used |
| departure_date | DATE | | Actual departure date |
| departure_time | TIME | | Departure time |
| expected_arrival_date | DATE | | Expected return date |
| expected_arrival_time | TIME | | Expected return time |
| vehicle_plate_no | VARCHAR(191) | | License plate number of vehicle |
| destination | VARCHAR(255) | | Travel destination |
| purpose | TEXT | | Purpose of travel |
| driver_name | VARCHAR(191) | | Name of assigned driver |
| transportation_incharge | VARCHAR(191) | | Person in charge of transportation |
| status | VARCHAR(32) | INDEX | Request status: pending, approved, rejected |
| approved_by | INT | FK | User ID who approved (if approved) |
| approved_at | DATETIME | | When approval was granted |
| created_at | DATETIME | INDEX | Request creation timestamp |

**Relationships**:
- `user_id` → `users.id` (requester)
- `approved_by` → `users.id` (approver, nullable)
- One vehicle request can have many passengers (`passengers` table)

---

### 5. **passengers** - Vehicle Request Passengers
Stores additional passenger information for vehicle requests.

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | Passenger record ID |
| request_id | INT | FK | References `vehicle_requests.id` |
| passenger_name | VARCHAR(191) | | Name of passenger |

**Relationships**:
- `request_id` → `vehicle_requests.id` (one request can have many passengers)

---

### 6. **leave_requests** - Employee Leave/Time-Off Requests
Tracks employee vacation, sick leave, and other time-off requests.

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | Request unique identifier |
| user_id | INT | FK | Employee requesting leave |
| start_date | DATE | INDEX | First day of leave |
| end_date | DATE | INDEX | Last day of leave |
| leave_type | VARCHAR(64) | | Type: vacation, sick, emergency, bereavement, etc. |
| notes | TEXT | | Additional notes about the leave |
| status | VARCHAR(32) | INDEX | Request status: pending, approved, rejected |
| created_at | DATETIME | INDEX | When request was submitted |
| updated_at | DATETIME | | Last update timestamp |

**Relationships**:
- `user_id` → `users.id` (one employee, many leave requests)

**Status Values**: pending, approved, rejected, cancelled

---

### 7. **notifications** - In-App System Notifications
Stores notifications shown to users within the application.

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | Notification unique identifier |
| user_id | INT | FK | User receiving notification (NULL = system-wide) |
| type | VARCHAR(64) | INDEX | Notification category: activity, request, approval, etc. |
| ref_id | INT | | ID of related resource (activity_id, request_id, etc.) |
| title | VARCHAR(255) | | Notification title |
| body | TEXT | | Notification message body |
| is_read | TINYINT(1) | INDEX | Flag: 1 = read, 0 = unread |
| created_at | DATETIME | INDEX | When notification was created |

**Relationships**:
- `user_id` → `users.id` (nullable for system-wide notifications)

**Common Types**: activity_created, request_approved, request_rejected, leave_approved, etc.

---

### 8. **employee_events** - Employee Status Events
Tracks real-time employee status (office, traveling, on leave, etc.).

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | Event unique identifier |
| user_id | INT | FK | Employee (UNIQUE per user) |
| event_type | VARCHAR(32) | INDEX | Status: office, travel, business, leave |
| start_datetime | DATETIME | INDEX | When status became active |
| end_datetime | DATETIME | INDEX | When status ends (NULL = ongoing) |
| notes | VARCHAR(255) | | Additional status notes |
| created_at | DATETIME | | When record was created |
| updated_at | DATETIME | | When record was last updated |

**Relationships**:
- `user_id` → `users.id` (one current status per employee)

**Constraint**: UNIQUE on `user_id` (only one active status per employee)

**Event Types**: office, travel, business, sick_leave, vacation, etc.

---

### 9. **user_login_logs** - Login Activity Audit Trail
Records every user login for security and analytics.

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT | PRIMARY | Log entry unique identifier |
| user_id | INT | FK | User who logged in |
| login_at | DATETIME | INDEX | When login occurred |

**Relationships**:
- `user_id` → `users.id` (one user, many login records)

**Usage**: Generate login statistics, track user activity, security audits

---

## Database Relationships Diagram

```
users (1) ──── (M) activities
users (1) ──── (M) supply_requests
users (1) ──── (M) vehicle_requests (1) ──── (M) passengers
users (1) ──── (M) leave_requests
users (1) ──── (M) notifications
users (1) ──── (1) employee_events [UNIQUE user_id]
users (1) ──── (M) user_login_logs
users (1) ──── (M) vehicle_requests [via approved_by field]
```

---

## Common Queries

### Get user profile with divisions
```sql
SELECT id, first_name, last_name, email, division, created_at
FROM users
WHERE id = 1;
```

### Get all activities for a user in date range
```sql
SELECT * FROM activities
WHERE user_id = 1 
  AND start_datetime >= '2026-01-01'
  AND end_datetime <= '2026-12-31'
ORDER BY start_datetime;
```

### Get pending vehicle requests
```sql
SELECT vr.*, u.first_name, u.last_name
FROM vehicle_requests vr
JOIN users u ON vr.user_id = u.id
WHERE vr.status = 'pending'
ORDER BY vr.created_at DESC;
```

### Get unread notifications
```sql
SELECT * FROM notifications
WHERE user_id = 1 AND is_read = 0
ORDER BY created_at DESC;
```

### Get user's current status
```sql
SELECT * FROM employee_events
WHERE user_id = 1
  AND (end_datetime IS NULL OR end_datetime > NOW());
```

### Get login activity for past 30 days
```sql
SELECT user_id, COUNT(*) as login_count
FROM user_login_logs
WHERE login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY user_id
ORDER BY login_count DESC;
```

### Get active leave requests
```sql
SELECT lr.*, u.first_name, u.last_name
FROM leave_requests lr
JOIN users u ON lr.user_id = u.id
WHERE lr.status = 'approved'
  AND lr.start_date <= CURDATE()
  AND lr.end_date >= CURDATE()
ORDER BY lr.start_date;
```

---

## Database Setup Instructions

### Option 1: Using SQL File (Recommended)
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Click "Import" tab
3. Select the `database_schema.sql` file
4. Click "Go"

### Option 2: Using MySQL Command Line
```bash
cd C:\xampp\mysql\bin
mysql -u root < "C:\xampp\htdocs\dti\database_schema.sql"
```

### Option 3: Manual Setup
1. Launch phpMyAdmin
2. Create new database named `dti`
3. Select the database
4. Paste content of `database_schema.sql` into SQL tab
5. Execute

---

## Database Maintenance

### Backup Database
```bash
mysqldump -u root dti > dti_backup.sql
```

### Restore Database
```bash
mysql -u root dti < dti_backup.sql
```

### Check Table Status
```sql
SHOW TABLE STATUS FROM dti;
```

### Optimize Tables (after heavy usage)
```sql
OPTIMIZE TABLE users, activities, supply_requests, vehicle_requests, leave_requests, notifications, employee_events, user_login_logs;
```

### View Database Size
```sql
SELECT table_name, 
       ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.tables 
WHERE table_schema = 'dti'
ORDER BY (data_length + index_length) DESC;
```

---

## Security Notes

1. **Passwords**: All passwords are hashed using PHP before storage (use `password_hash()`)
2. **Email Uniqueness**: Email addresses are unique to prevent duplicate accounts
3. **Foreign Keys**: Enabled for data integrity and cascading deletes
4. **Character Set**: UTF-8mb4 supports all Unicode characters including emojis
5. **Indexes**: Strategic indexes on frequently queried columns for performance

---

## Additional Information

- **Auto-increment**: All primary keys auto-increment starting from 1
- **Timestamps**: All `created_at` and `updated_at` fields auto-populate with current timestamp
- **Default Values**: Some fields have sensible defaults (e.g., `status = 'pending'`)
- **Nullable Fields**: Optional fields can be NULL; required fields cannot be NULL
- **Cascade Operations**: Deleting a user automatically deletes related records

---

**Last Updated**: 2026-03-31
**Database Version**: 1.0
