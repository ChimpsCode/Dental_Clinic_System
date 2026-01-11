# Database Setup Guide

This guide will help you set up the database for the Dental Clinic Management System.

## Prerequisites

- XAMPP (or any PHP/MySQL environment) installed and running
- MySQL server running
- PHP 7.4 or higher

## Method 1: Using PHP Script (Recommended)

This is the easiest and recommended method as it properly handles password hashing.

### Steps:

1. Open your terminal/command prompt
2. Navigate to the project directory:
   ```bash
   cd c:\xampp\htdocs\Dental_Clinic_System
   ```

3. Run the initialization script:
   ```bash
   php config/init_database.php
   ```

4. You should see output like:
   ```
   ✓ Database 'dental_management' created successfully!
   ✓ Users table created successfully!
   ✓ Patients table created successfully!
   ...
   ```

## Method 2: Using SQL File (phpMyAdmin)

If you prefer using phpMyAdmin:

1. Open phpMyAdmin in your browser (usually `http://localhost/phpmyadmin`)
2. Click on "Import" tab
3. Choose the file `database.sql` from the project root
4. Click "Go" to import

**Note:** If using this method, you'll need to update user passwords manually using PHP's `password_hash()` function, as the SQL file contains placeholder hashes.

## Method 3: Manual SQL Import

1. Open MySQL command line or any MySQL client
2. Run the SQL file:
   ```sql
   source c:/xampp/htdocs/Dental_Clinic_System/database.sql
   ```

## Default Login Credentials

After setup, you can login with these accounts:

### Admin Account
- **Username:** `admin`
- **Password:** `admin123`
- **Role:** Administrator

### Dentist Account
- **Username:** `dentist`
- **Password:** `dentist123`
- **Role:** Dentist

### Staff Account
- **Username:** `staff`
- **Password:** `staff123`
- **Role:** Staff

**⚠️ Important:** Change these default passwords after first login!

## Database Structure

The database includes the following tables:

1. **users** - System users (admin, dentist, staff)
2. **patients** - Patient information and demographics
3. **appointments** - Patient appointments and scheduling
4. **dental_history** - Patient dental history and records
5. **medical_history** - Patient medical history and conditions
6. **treatments** - Dental treatments and procedures performed
7. **services** - Available dental services and procedures
8. **billing** - Patient billing and invoices
9. **payments** - Payment transactions and records

## Troubleshooting

### Error: "Access denied for user 'root'@'localhost'"
- Check your MySQL root password
- Update `config/database.php` with correct credentials

### Error: "Database already exists"
- This is normal if the database was already created
- The script will continue and create/update tables

### Error: "Table already exists"
- Tables are created with `IF NOT EXISTS`, so this shouldn't happen
- If it does, the script will skip existing tables

## Configuration

Database settings are configured in `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'dental_management');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Update these values if your MySQL configuration differs.

## Next Steps

After database setup:
1. Verify you can login with default credentials
2. Change default passwords
3. Start adding patients and appointments
4. Customize services and settings as needed
