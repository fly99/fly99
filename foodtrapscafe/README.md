# Foodtraps Café PHP version

This is a PHP and SQLite conversion of the supplied Google Apps Script project. It keeps the customer ordering, front desk, kitchen, admin, rider, and order tracking screens. The original project folder has not been modified.

## Run locally

Requirements: PHP 8.1 or newer with PDO SQLite enabled. From this folder, run:

```sh
php -S localhost:8000
```

Open `http://localhost:8000/`. Staff pages are available at `?page=frontdesk`, `?page=kitchen`, `?page=admin`, and `?page=rider`. Tracking is at `?page=track&order=FT-0001`.

The first API request creates `data/foodtraps.sqlite` and loads the included sample menu. The web server needs write access to `data/` and `uploads/` for database and menu image storage.

## Default staff logins

- Admin: `admin` / `123456`
- Front desk: `frontdesk` / `111111`
- Kitchen: `kitchen` / `222222`
- Rider: `rider` / `333333`

Change these before exposing the site publicly. The PHP API uses server sessions for staff authentication.

## Data and hosting notes

SQLite replaces Google Sheets and stores new orders, menu edits, riders, locations, and audit entries locally. The included `menu.json` imports the sample menu from the Apps Script source when the database is first created. Existing live Google Sheet records are not included; export and import them separately if you need to retain them.

Deploy the whole folder to a PHP host with SQLite enabled. Use HTTPS for live customer and staff traffic. Leaflet and map tiles still load from their existing external providers. Online payment processing is not connected, matching the source project.

## Conversion notes

The existing HTML screens are retained and their `google.script.run` calls now use `api.php`. Core order, staff, menu, sales, rider, and tracking flows are implemented in PHP. The Apps Script daily-sales worksheet is replaced by sales summaries calculated from the SQLite order records.
