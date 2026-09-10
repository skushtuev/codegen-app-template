-- Kratos keeps its own tables in a separate database, never mixed with the application schema.
-- Runs only when the Postgres data volume is created from scratch. For an existing volume run:
--   docker compose exec postgres psql -U user -d postgres -c 'CREATE DATABASE kratos OWNER "user";'
CREATE DATABASE kratos OWNER "user";
