<h1 align="center">Mobilidade App Backend</h1>

## Sobre o App

Este app é o backend da aplicação de mobilidade urbana desenvolvido pela equipe de TI da Pen6.

## Tecnologias utilizadas

- PHP 8.5
- Laravel 13

## Requisitos:

- PHP 8.5
- MySQL ou MariaDB
- Redis ou Valet
- Docker instalado

> Em ambientes Windows, é necessário ter instalado o WSL2 e alguma distribuição Linux compatível.

Para o ambiente de desenvolvimento, também é requisito:

- RustFS (Storage S3)
- Mailpit (Servidor de email para testes)

## Iniciando o desenvolvimento

Antes de iniciar o ambiente de desenvolvimento, certifique-se de que o arquivo `.env` está devidamente configurado.

Para iniciar a estrutura do ambiente de desenvolvimento pela primeira vez, inicialize o ambiente Docker:

```sh
docker compose up -d --build
```

O serviço `composer` instala automaticamente as dependências do `composer.lock` antes de iniciar o PHP. Ele termina com código 0 após a instalação; isso é esperado.

As dependências ficam no volume Docker `sail-vendor`, compartilhado entre a API e o scheduler. Isso evita a lentidão de carregar milhares de arquivos PHP pelo compartilhamento do Windows. O código da aplicação continua na pasta do projeto, com as edições disponíveis no contêiner.

Para executar comandos pelo terminal do sistema, use:

```sh
docker compose exec laravel.test php artisan route:list
docker compose exec laravel.test composer install
```

Depois de um pull que altere o `composer.lock`, execute o segundo comando antes de continuar. A instalação deve ocorrer dentro do contêiner, pois o `vendor` da máquina não é usado pelos serviços Docker.

Para iniciar o desenvolvimento do código, com o projeto aberto no VSCode basta executar o comando do VSCode "Dev Containers: Reopen in Container".

O devcontainer facilita a manter o ambiente local limpo e padronizado.

## Pós configuração do ambiente

Após configurar o ambiente de desenvolvimento, é necessário executar alguns comandos para preparar o Laravel para funcionar:

```sh
# Caso esteja dentro do devcontainer
php artisan jwt:secret

# Caso esteja no terminal do sistema
docker compose exec laravel.test php artisan jwt:secret
```
