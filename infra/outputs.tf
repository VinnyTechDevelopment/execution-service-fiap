output "load_balancer_hostname" {
  description = "NLB do execution-service — usar como EXECUTION_BACKEND_DOMAIN no API Gateway (lambda-auth-cpf-fiap)"
  value       = try(kubernetes_service_v1.app.status[0].load_balancer[0].ingress[0].hostname, null)
}

output "internal_url" {
  description = "URL interna usada pelo os-service (consulta síncrona ao catálogo)"
  value       = "http://${kubernetes_service_v1.app.metadata[0].name}.${kubernetes_namespace_v1.execution.metadata[0].name}.svc.cluster.local:8080"
}

output "commands_queue_url" {
  value = aws_sqs_queue.commands.url
}

output "dynamodb_tables" {
  value = [for t in [aws_dynamodb_table.items, aws_dynamodb_table.services, aws_dynamodb_table.stock_movements, aws_dynamodb_table.jobs, aws_dynamodb_table.processed_messages] : t.name]
}
