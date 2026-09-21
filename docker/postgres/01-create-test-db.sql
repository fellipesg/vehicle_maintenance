-- Runs once, on first init of the db_data volume.
-- Separate database for `php artisan test --configuration=phpunit.pgsql.xml`.
CREATE DATABASE vehicle_maintenance_test;
