## Patente Quiz Crawler & Tester

### Local (Docker Compose)

1. docker compose up --build
2. Open http://localhost:8081/test.php

App env (can override):
- DB_HOST=db
- DB_PORT=3306
- DB_NAME=patente
- DB_USER=admin
- DB_PASS=admin

### Railway Deployment

1. Create a new project on Railway and add a MySQL service.
2. Note the MySQL service variables and expose them to the app service:
    - DB_HOST
    - DB_PORT
    - DB_NAME
    - DB_USER
    - DB_PASS
3. Deploy this repo as a service using the Dockerfile.
4. In the app service, set the environment variables to the MySQL service values.
5. On first boot, ensure the schema exists. Either:
    - Run the contents of schema.sql on the Railway MySQL, or
    - Temporarily mount/run schema.sql from a one-off container.

Endpoints:
- /index.php → crawler (triggers fetch-and-save)
- /test.php → quiz UI


