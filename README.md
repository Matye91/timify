# Timify

A small Clockify-style time tracker built with JavaScript, PHP, and MySQL.

## Features

- Register and log in with PHP sessions.
- Start and stop a running timer.
- Select a project for every time entry.
- Add an optional description to each time entry.
- Add, rename, recolor, and delete projects.
- Keep projects and entries separated per user.
- See report totals and a project chart.

## Setup

1. Create a MySQL database named `timify`.
2. Import `database/schema.sql`.
3. Update the database credentials in `config/database.php`.
4. Serve the project with PHP, for example:

```bash
php -S localhost:8000
```

5. Open `http://localhost:8000/public/`.

On shared hosting, upload the project files, import the SQL schema through your hosting control panel, and adjust `config/database.php` to match the provider's MySQL host, database name, user, and password.

If your domain points to the project root, keep `index.php` in the root folder. It redirects visitors to `public/`. If your host lets you choose the document root, point it directly to the `public` folder.

Do not use a WordPress `.htaccess` for this app unless the app is installed inside an existing WordPress site on purpose. The included `.htaccess.timify-example` shows safe Apache rules for this standalone app.

## Notes

New accounts automatically receive three starter projects: Client Work, Internal Admin, and Learning. These can be renamed or deleted.
