# RF Dental Clinic - Login System

A modern, professional login system for RF Dental Clinic built with PHP, HTML, CSS, and JavaScript.

## Features

- **Modern UI Design**: Clean and professional login interface matching the RF Dental Clinic branding
- **Secure Authentication**: Password hashing using PHP's `password_hash()` function
- **Form Validation**: Client-side and server-side validation
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Password Visibility Toggle**: Eye icon to show/hide password
- **Session Management**: Secure session handling for logged-in users
- **Additional Pages**: Registration and password reset pages

## Installation

1. **Place files in your web server directory** (e.g., `C:\xampp\htdocs\DENTAL MANAGEMENT\`)

2. **Initialize the database**:
   - Open your browser and navigate to: `http://localhost/DENTAL%20MANAGEMENT/config/init_database.php`
   - Or run it via command line: `php config/init_database.php`
   - This will create the database and a default admin user

3. **Default Login Credentials**:
   - Username: `admin`
   - Password: `admin123`

4. **Access the login page**:
   - Navigate to: `http://localhost/DENTAL%20MANAGEMENT/login.php`

## File Structure

```
DENTAL MANAGEMENT/
├── login.php                 # Main login page
├── register.php              # User registration page
├── forgot-password.php       # Password reset page
├── dashboard.php             # Dashboard (after login)
├── logout.php                # Logout handler
├── index.php                 # Redirects to login
├── config/
│   ├── database.php          # Database connection
│   └── init_database.php     # Database initialization
├── assets/
│   ├── css/
│   │   └── login.css         # Stylesheet
│   └── js/
│       └── login.js          # JavaScript functionality
└── README.md                 # This file
```

## Database Configuration

Edit `config/database.php` to match your database settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'dental_management');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## Customization

- **Logo**: Edit the SVG logo in `login.php` (lines with `<svg class="logo">`)
- **Colors**: Modify the color scheme in `assets/css/login.css` (search for `#2563eb` for the primary blue color)
- **Clinic Name**: Change "RF Dental Clinic" in the HTML files

## Security Notes

- Passwords are hashed using `password_hash()` with default algorithm (bcrypt)
- Prepared statements are used to prevent SQL injection
- Session management is implemented for secure authentication
- Input validation is performed on both client and server side

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## License

This project is open source and available for use.

