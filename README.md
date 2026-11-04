# execution-service — Execução e Produção

[![Laravel CI](https://github.com/VinnyTechDevelopment/execution-service-fiap/actions/workflows/laravel-ci.yml/badge.svg)](https://github.com/VinnyTechDevelopment/execution-service-fiap/actions/workflows/laravel-ci.yml)
[![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=VinnyTechDevelopment_execution-service-fiap&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=VinnyTechDevelopment_execution-service-fiap)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=VinnyTechDevelopment_execution-service-fiap&metric=coverage)](https://sonarcloud.io/summary/new_code?id=VinnyTechDevelopment_execution-service-fiap)

Microsserviço de **execução e produção** da oficina (Tech Challenge FIAP, Fase 4): catálogo de serviços e peças, **estoque**, **fila de execução** da OS, diagnóstico e reparo. Participa da Saga orquestrada pelo [os-service](https://github.com/VinnyTechDevelopment/tech-challenge-fiap).

| | |
|---|---|
| Stack | PHP 8.4, Laravel 13, arquitetura DDD |
| Banco | **Amazon DynamoDB** (NoSQL), com tabelas próprias que nenhum outro serviço acessa |
| Mensageria | Consome `execution-commands` (SQS); publica em `os-saga-replies` |
| Síncrono | `POST /api/catalog/lookup`, usado pelo os-service ao abrir a OS |
| Deploy | EKS, namespace `execution` / `execution-homolog` (Terraform em [`infra/`](infra)) |

## Por que DynamoDB

- Os acessos são **por chave** (OS, peça) e **por índice** (status da fila, código da peça), sem joins.
- A fila de execução é um índice `status-index` (PK `status`, SK `queued_at`), que lista em ordem de chegada sem scan.
- A **reserva de peças é atômica** com `TransactWriteItems`: grava o job e todas as baixas de estoque, cada uma com `ConditionExpression stock_quantity = :previous` (controle otimista). Ou tudo é aplicado, ou nada; em caso de corrida há retry.
- Cobrança por requisição, sem instância para gerenciar; atende o requisito de **ao menos um banco NoSQL** da fase.

| Tabela (`<ambiente>-execution-*`) | Chave | Índices |
|---|---|---|
| `items` | `id` | `code-index` |
| `services` | `id` | — |
| `stock_movements` | `item_id` + `created_at_id` | — |
| `jobs` | `service_order_id` | `status-index` (`status`, `queued_at`) |
| `processed_messages` | `message_id` (TTL `expires_at`) | — |

## Responsabilidades

- **Gerenciar a fila de execução:** `execution.ReservePartsAndEnqueue` reserva as peças e coloca a OS na fila (`ExecutionQueued`); sem estoque, responde `PartsReservationFailed`, o que dispara o estorno no orquestrador.
- **Atualizar status durante diagnóstico e reparo:** `PATCH /execution/{osId}/diagnosis | repair`, que publica `ExecutionStatusChanged`.
- **Comunicar a finalização ao OS Service:** `PATCH /execution/{osId}/finish`, que publica `ExecutionFinished`.
- **Compensação:** `execution.CancelExecution` devolve ao estoque o que foi reservado (movimentação de entrada "estorno") e cancela o job.
- Catálogo e estoque (CRUD de peças/serviços, entradas e saídas, movimentações) e a métrica de tempo médio de execução por serviço.

Os handlers são **idempotentes**: um comando reentregue não reserva estoque duas vezes, só reenvia a resposta.

## API

Contrato completo em [`openapi.yaml`](openapi.yaml). Todas as rotas exigem o JWT de funcionário emitido pelo os-service (`POST /api/auth/login`).

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/catalog/lookup` | valida e precifica serviços/peças (os-service) |
| GET/POST/PUT/DELETE | `/api/catalog/items[/{id}]` | peças |
| POST | `/api/catalog/items/{id}/stock/entry` · `/withdrawal` | movimentação de estoque |
| GET/POST/PUT/DELETE | `/api/catalog/services[/{id}]` | serviços (mão de obra) |
| GET | `/api/execution/queue?status=` | fila de execução |
| PATCH | `/api/execution/{osId}/diagnosis` · `/repair` · `/finish` | avança a execução e notifica o os-service |
| PATCH | `/api/execution/{osId}/services/{serviceId}/start` · `/finish` | apontamento por serviço |
| GET | `/api/execution/metrics/average-execution-time` | tempo médio por serviço |

## Executando

```bash
cp .env.example .env && composer install && php artisan key:generate
php artisan dynamodb:create-tables     # LocalStack (local)
php artisan serve --port=8083
php artisan messaging:consume          # worker
```

Para o ambiente completo, use o `docker-compose.microservices.yml` do [os-service](https://github.com/VinnyTechDevelopment/tech-challenge-fiap).

## Testes e cobertura

```bash
php vendor/bin/phpunit   # não precisa de banco: DynamoDB é testado com Aws\MockHandler + repositórios em memória
make coverage            # coverage.xml + gate de 80%
```

| | |
|---|---|
| Testes | 170 (domínio, reserva atômica, handlers da saga, API e repositórios DynamoDB) |
| **Cobertura de linhas** | **98,0%** (mínimo exigido no CI: 80%) |

CI: Pint → PHPUnit + gate de cobertura → SonarCloud → kubeconform → build. Em `main`, a imagem vai para o GHCR e o `deploy-eks.yml` aplica o Terraform: tabelas DynamoDB, fila SQS + DLQ, Deployments da API (HPA + NLB) e do worker.

## Acesso à AWS em produção

Os pods usam as credenciais da `LabRole` do node group (IMDS). O AWS Academy não permite IRSA nem a criação de roles. Localmente, `AWS_ENDPOINT_URL` aponta para o LocalStack.
