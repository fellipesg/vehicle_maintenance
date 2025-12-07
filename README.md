# Vehicle Maintenance - Backend API

API Laravel para gerenciamento de manutenções e histórico veicular.

## 🚀 Tecnologias

- **Laravel 12** (PHP 8.4)
- **MySQL 8.0**
- **Redis 7**
- **Laravel Sanctum** (Autenticação)
- **Laravel Socialite** (OAuth)
- **Docker** & **Docker Compose**
- **Xdebug 3.4** (Debugging)
- **Laravel Telescope** (Observabilidade)
- **Laravel Debugbar** (Debugging)
- **Rector** (Refatoração de código)

## 📋 Pré-requisitos

- Docker e Docker Compose
- Git

## 🔧 Instalação

1. Clone o repositório:
```bash
git clone https://github.com/fellipesg/vehicle_maintenance.git
cd vehicle_maintenance
```

2. Execute o script de setup:
```bash
chmod +x docker-setup.sh
./docker-setup.sh
```

3. Inicie os containers:
```bash
docker compose up -d
```

4. Instale as dependências:
```bash
docker compose exec app composer install
```

5. Gere a chave da aplicação:
```bash
docker compose exec app php artisan key:generate
```

6. Execute as migrations:
```bash
docker compose exec app php artisan migrate
```

7. (Opcional) Execute os seeders:
```bash
docker compose exec app php artisan db:seed
```

## 🧪 Testes

Execute os testes com PHPUnit:
```bash
docker compose exec app php artisan test
```

## 📚 Documentação

- [DEVELOPMENT.md](../DEVELOPMENT.md) - Guia de desenvolvimento
- [TESTING.md](../TESTING.md) - Guia de testes

## 🔗 Endpoints da API

### Autenticação
- `POST /api/v1/register` - Registrar novo usuário
- `POST /api/v1/login` - Login
- `POST /api/v1/logout` - Logout
- `GET /api/v1/me` - Dados do usuário autenticado

### Veículos
- `GET /api/v1/vehicles` - Listar veículos
- `POST /api/v1/vehicles` - Criar veículo
- `GET /api/v1/vehicles/{id}` - Detalhes do veículo
- `PUT /api/v1/vehicles/{id}` - Atualizar veículo
- `DELETE /api/v1/vehicles/{id}` - Deletar veículo
- `GET /api/v1/vehicles/{id}/maintenances` - Manutenções do veículo
- `GET /api/v1/vehicles/{id}/export-pdf` - Exportar PDF

### Manutenções
- `GET /api/v1/maintenances` - Listar manutenções
- `POST /api/v1/maintenances` - Criar manutenção
- `GET /api/v1/maintenances/{id}` - Detalhes da manutenção
- `PUT /api/v1/maintenances/{id}` - Atualizar manutenção
- `DELETE /api/v1/maintenances/{id}` - Deletar manutenção

### Faturas
- `POST /api/v1/invoices/upload` - Upload de fatura
- `GET /api/v1/invoices/{id}/download` - Download de fatura
- `DELETE /api/v1/invoices/{id}` - Deletar fatura

## 🛠️ Ferramentas de Desenvolvimento

### Xdebug
- Porta: 9003
- Configure seu IDE para escutar na porta 9003
- Logs: `storage/logs/xdebug.log`

### Laravel Telescope
- Acesse: http://localhost:8080/telescope
- Explore requisições, queries, jobs, etc.

### Rector
```bash
# Ver mudanças propostas
docker compose exec app vendor/bin/rector process --dry-run

# Aplicar refatorações
docker compose exec app vendor/bin/rector process
```

## 📝 Licença

MIT
