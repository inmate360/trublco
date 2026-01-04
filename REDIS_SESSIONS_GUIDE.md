# Configuring Redis for PHP Session Handling

Using Redis for session handling improves performance and allows for better scalability, especially in load-balanced environments.

## 1. Prerequisites
The `Dockerfile` I provided already installs the necessary PHP Redis extension:
```dockerfile
RUN pecl install redis && docker-php-ext-enable redis
```

## 2. Configuration Methods

### Method A: Via `php.ini` (Recommended for Docker)
You can update your `Dockerfile` or a custom `php.ini` to set these values globally. In your `docker-compose.yml`, you can also pass these as environment variables to the PHP container:

Update the `app` service in `docker-compose.yml`:
```yaml
  app:
    # ... existing config ...
    environment:
      - PHP_INI_SCAN_DIR=:/usr/local/etc/php/conf.d
      # Note: Some PHP images allow setting these directly via env
```

Or add this to your `Dockerfile`:
```dockerfile
RUN echo "session.save_handler = redis" >> /usr/local/etc/php/conf.d/docker-php-ext-redis.ini \
    && echo "session.save_path = \"tcp://redis:6379\"" >> /usr/local/etc/php/conf.d/docker-php-ext-redis.ini
```

### Method B: Via PHP Code (Application Level)
If you want to handle it within your PHP code (e.g., in a common include file like `includes/config.php`), add this before calling `session_start()`:

```php
<?php
// Configure Redis session handler
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis:6379');

// Optional: Add password if your Redis is secured
// ini_set('session.save_path', 'tcp://redis:6379?auth=yourpassword');

session_start();
```

## 3. Verifying the Setup
Once configured and the containers are running, you can verify that sessions are being stored in Redis:

1.  Enter the Redis container:
    ```bash
    docker exec -it trublco-redis redis-cli
    ```
2.  List all keys:
    ```bash
    keys *
    ```
3.  You should see keys starting with `PHPREDIS_SESSION:`.

## 4. Benefits
- **Speed:** Redis stores sessions in memory, which is much faster than disk-based file storage.
- **Persistence:** Sessions persist even if the PHP container restarts (thanks to the `appendonly yes` flag in our `docker-compose.yml`).
- **Scalability:** Multiple PHP containers can share the same Redis service to maintain user sessions across a cluster.
