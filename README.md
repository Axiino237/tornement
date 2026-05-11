# FireCrown - Gaming Tournament Platform

FireCrown is a web-based platform for organizing and participating in gaming tournaments. The platform supports various games including Among Us, Minecraft, Free Fire, and BGMI.

## 🌐 Live Website

[FireCrown](http://firecrown.free.nf)

## 🎯 Keywords

gaming tournament, gaming tournament platform, esports PHP website, Free Fire tournament app, BGMI online matches, Minecraft event organizer, Among Us PHP site, tournament registration system, paid tournament PHP, online gaming competition, esports platform PHP MySQL

## 🎮 Features

- **Tournament Management**

  - Create and manage tournaments
  - Set tournament details (date, time, prize pool)
  - Manage participants and approvals
  - Announce winners

- **User Features**

  - User registration and authentication
  - Join tournaments
  - View tournament details
  - Track participation status

- **Game Support**

  - Among Us
  - Minecraft
  - Free Fire
  - BGMI

- **Payment Integration**
  - Support for paid tournaments
  - Registration fee collection
  - Prize distribution

## 🛠️ Technical Stack

- **Frontend**

  - HTML5
  - CSS3
  - Bootstrap 5
  - JavaScript
  - Font Awesome Icons

- **Backend**
  - PHP
  - MySQL Database
  - PDO for database operations

## 📋 Requirements

- XAMPP Server (Apache + MySQL + PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- mod_rewrite enabled (for clean URLs)

## 🔧 Installation (XAMPP)

1. **XAMPP Setup**

   - Download and install XAMPP from [Apache Friends](https://www.apachefriends.org/)
   - Start Apache and MySQL services from XAMPP Control Panel

2. **Project Setup**

   - Place the project folder in `C:\xampp\htdocs\firecrown`
   - Ensure the folder structure is:
     ```
     C:\xampp\htdocs\firecrown\
     ├── assets/
     ├── config/
     ├── includes/
     ├── uploads/
     └── *.php
     ```

3. **Database Setup**

   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `firecrown`
   - Import the provided SQL file into the database

4. **Configuration**

   - Open `config/database.php`
   - Update database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'firecrown';
     $username = 'root';
     $password = ''; // Default XAMPP MySQL password is empty
     ```

5. **Access the Website**
   - Open your browser
   - Navigate to: `http://localhost/firecrown`

## 🚀 Deployment to Free Hosting

1. **Prepare Files**

   - Ensure all files are in the correct structure
   - Check database credentials for production

2. **Upload to Free Hosting**

   - Login to your free hosting account
   - Upload all files to the public_html directory
   - Set file permissions:
     - Folders: 755
     - Files: 644
     - Uploads folder: 777

3. **Database Setup**

   - Create database on free hosting
   - Import the SQL file
   - Update database credentials in `config/database.php`

4. **Domain Setup**
   - Point firecrown.free.nf to your hosting
   - Wait for DNS propagation

## �� Project Structure

```
firecrown/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│       └── games/
├── config/
│   └── database.php
├── includes/
│   ├── header.php
│   └── footer.php
├── uploads/
└── *.php
```

## 🔒 Security

- Password hashing using PHP's password_hash()
- Prepared statements for all database queries
- Input validation and sanitization
- Session management
- CSRF protection

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request


