# Banco NÃO relacional PRÓPRIO do execution-service (database per service).
# Mesmas tabelas/índices de app/Infrastructure/Persistence/DynamoDb/DynamoDbSchema.php.
#
# Por que DynamoDB aqui: a fila de execução e o catálogo/estoque são acessados
# por chave (OS, peça) e por índice (status, código), com escrita intensa e
# sem joins. A reserva de peças usa TransactWriteItems com condição
# "stock_quantity = :previous", garantindo atomicidade sem banco relacional.

locals {
  table_prefix = "${var.environment}-execution"
}

resource "aws_dynamodb_table" "items" {
  name         = "${local.table_prefix}-items"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "id"

  attribute {
    name = "id"
    type = "S"
  }

  attribute {
    name = "code"
    type = "S"
  }

  global_secondary_index {
    name            = "code-index"
    hash_key        = "code"
    projection_type = "ALL"
  }

  point_in_time_recovery {
    enabled = true
  }
}

resource "aws_dynamodb_table" "services" {
  name         = "${local.table_prefix}-services"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "id"

  attribute {
    name = "id"
    type = "S"
  }
}

resource "aws_dynamodb_table" "stock_movements" {
  name         = "${local.table_prefix}-stock-movements"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "item_id"
  range_key    = "created_at_id"

  attribute {
    name = "item_id"
    type = "S"
  }

  attribute {
    name = "created_at_id"
    type = "S"
  }
}

resource "aws_dynamodb_table" "jobs" {
  name         = "${local.table_prefix}-jobs"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "service_order_id"

  attribute {
    name = "service_order_id"
    type = "S"
  }

  attribute {
    name = "status"
    type = "S"
  }

  attribute {
    name = "queued_at"
    type = "S"
  }

  global_secondary_index {
    name            = "status-index"
    hash_key        = "status"
    range_key       = "queued_at"
    projection_type = "ALL"
  }

  point_in_time_recovery {
    enabled = true
  }
}

resource "aws_dynamodb_table" "processed_messages" {
  name         = "${local.table_prefix}-processed-messages"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "message_id"

  attribute {
    name = "message_id"
    type = "S"
  }

  ttl {
    attribute_name = "expires_at"
    enabled        = true
  }
}
