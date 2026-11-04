# Fila de entrada do execution-service: comandos da saga enviados pelo
# orquestrador (ReservePartsAndEnqueue, CancelExecution).

resource "aws_sqs_queue" "commands_dlq" {
  name                      = "${var.environment}-execution-commands-dlq"
  message_retention_seconds = 1209600
}

resource "aws_sqs_queue" "commands" {
  name                       = "${var.environment}-execution-commands"
  visibility_timeout_seconds = 60
  message_retention_seconds  = 345600
  receive_wait_time_seconds  = 20

  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.commands_dlq.arn
    maxReceiveCount     = 5
  })
}
