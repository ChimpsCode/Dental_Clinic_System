<?php

// Mailer configuration for sending reset codes via Gmail SMTP.
// IMPORTANT:
// - Use a Gmail "App password" (recommended) instead of your main account password.
// - Enable 2-step verification on the Gmail account, then create an App password for "Mail".
// - Update the values below to match your Gmail account and desired "From" name.

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // TLS
define('SMTP_USER', 'jessarose123321@gmail.com');       // TODO: change to your Gmail
define('SMTP_PASS', 'hsrhvjwbkbfsbtnl');     // TODO: change to your Gmail App password

define('MAIL_FROM', 'jessarose123321@gmail.com');       // Usually same as SMTP_USER
define('MAIL_FROM_NAME', 'RF Dental Clinic');      // Display name for emails

