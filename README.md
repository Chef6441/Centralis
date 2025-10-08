# Centralis

Backbone For Small Business Apps

## Requirements

- PHP 8.1+
- SQLite3

## Getting started

1. Install dependencies (PHP and SQLite must be available on your machine).
2. Create the SQLite database and seed it with example data:

```bash
mkdir -p data
sqlite3 data/centralis.db < database/schema.sql
sqlite3 data/centralis.db < database/seed.sql
```

3. Start the PHP development server:

```bash
php -S localhost:8000 -t public
```

4. Visit [http://localhost:8000](http://localhost:8000) in your browser to create new reports or view existing ones.

## Project structure

```
├── database
│   ├── schema.sql        # Database schema
│   └── seed.sql          # Sample data that mirrors the example report
├── includes
│   ├── db.php            # Database bootstrap
│   └── helpers.php       # Formatting helpers shared by the views
├── public
│   ├── css
│   │   └── style.css     # Application styling
│   ├── create_report.php # Data entry form for new reports
│   ├── index.php         # Lists existing reports
│   └── report.php        # Printable report view
└── README.md
```

The report view mirrors the structure of the original Word mail merge output and can be printed or saved as a PDF using the browser's print dialog.
