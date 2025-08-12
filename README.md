# iswift

## Setup
1. Run database schema:
   `mysql -u <user> -p < database/schema.sql`
2. Edit `config.php` and update database credentials.
3. Run seeders:
   `php admin/seed_create_admin.php`
   `php admin/seed_sample_products.php`

Next steps: admin login/auth pages and dashboard.
