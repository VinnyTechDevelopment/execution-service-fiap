variable "aws_region" {
  type    = string
  default = "us-east-1"
}

variable "tf_state_bucket" {
  description = "Bucket S3 com o state do infra-kubernetes-fiap"
  type        = string
}

variable "environment" {
  description = "production | homolog — prefixo das filas SQS e tabelas DynamoDB"
  type        = string
  default     = "production"

  validation {
    condition     = contains(["production", "homolog"], var.environment)
    error_message = "environment precisa ser \"production\" ou \"homolog\"."
  }
}

variable "namespace" {
  type    = string
  default = "execution"
}

variable "image_repository" {
  type    = string
  default = "ghcr.io/vinnytechdevelopment/execution-service-fiap"
}

variable "image_tag" {
  type    = string
  default = "latest"
}

variable "rollout_id" {
  type    = string
  default = ""
}

variable "app_key" {
  type      = string
  sensitive = true
}

variable "jwt_secret" {
  description = "Mesmo JWT_SECRET do os-service (valida o token de funcionário)"
  type        = string
  sensitive   = true
}

variable "newrelic_license_key" {
  type      = string
  sensitive = true
}

variable "ghcr_username" {
  type = string
}

variable "ghcr_token" {
  type      = string
  sensitive = true
}
