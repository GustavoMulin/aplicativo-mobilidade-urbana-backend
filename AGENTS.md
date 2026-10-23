# Backend — Diretrizes para Agentes

## Stack
- Laravel 13 / PHP 8.5
- Docker Sail (MySQL 8.4 porta 3306, Valkey porta 6379, Mailpit portas 8025/1025, RustFS S3 portas 9000/9001)
- Laravel Reverb (WebSocket) na porta 8080

## Endpoints e Portas
- No Docker Sail: `http://10.199.53.233/api` (porta 80)
- No `php artisan serve`: `http://10.199.53.233:8000/api` (porta 8000)
- Sempre verifique se o Sail está ativo (`docker ps`) antes de definir a URL de teste.

## Comandos Chave
- Executar testes: `docker compose exec laravel.test php artisan test` (ou `php artisan test`)
- Linter: `docker compose exec laravel.test ./vendor/bin/pint`
- Análise Estática: `docker compose exec laravel.test ./vendor/bin/phpstan analyse --memory-limit=1G`
- Migrations: `docker compose exec laravel.test php artisan migrate`

## Convenções
- Controllers no padrão RESTful com FormRequests dedicados para validação.
- Não suba `storage/logs/laravel.log` ou credenciais do `.env`.
- Mantenha respostas e documentação em português.
