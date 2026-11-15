locals {
  labels = { app = "execution-app" }
  image  = "${var.image_repository}:${var.image_tag}"

  # "args" (não "command") preserva o ENTRYPOINT que injeta a License Key do New Relic.
  # Sem outbox aqui (não há banco relacional): os handlers são idempotentes.
  worker_processes = {
    consumer = ["php", "artisan", "messaging:consume"]
  }
}

resource "kubernetes_namespace_v1" "execution" {
  metadata {
    name   = var.namespace
    labels = { service = "execution-service", environment = var.environment }
  }
}

resource "kubernetes_config_map_v1" "config" {
  metadata {
    name      = "execution-config"
    namespace = kubernetes_namespace_v1.execution.metadata[0].name
  }

  data = {
    APP_NAME               = "execution-service"
    APP_ENV                = var.environment
    APP_DEBUG              = "false"
    LOG_CHANNEL            = "stack"
    LOG_STACK              = "stderr"
    DB_CONNECTION          = "sqlite"
    CACHE_STORE            = "file"
    SESSION_DRIVER         = "array"
    QUEUE_CONNECTION       = "sync"
    AWS_DEFAULT_REGION     = var.aws_region
    DYNAMODB_TABLE_PREFIX  = local.table_prefix
    MESSAGING_DRIVER       = "sqs"
    MESSAGING_QUEUE_PREFIX = var.environment
    NEWRELIC_APPNAME       = "execution-service"
  }
}

resource "kubernetes_secret_v1" "secret" {
  metadata {
    name      = "execution-secret"
    namespace = kubernetes_namespace_v1.execution.metadata[0].name
  }

  data = {
    APP_KEY              = var.app_key
    JWT_SECRET           = var.jwt_secret
    NEWRELIC_LICENSE_KEY = var.newrelic_license_key
  }
}

resource "kubernetes_secret_v1" "ghcr" {
  metadata {
    name      = "ghcr-secret"
    namespace = kubernetes_namespace_v1.execution.metadata[0].name
  }

  type = "kubernetes.io/dockerconfigjson"

  data = {
    ".dockerconfigjson" = jsonencode({
      auths = {
        "ghcr.io" = {
          username = var.ghcr_username
          password = var.ghcr_token
          auth     = base64encode("${var.ghcr_username}:${var.ghcr_token}")
        }
      }
    })
  }
}

resource "kubernetes_deployment_v1" "app" {
  metadata {
    name      = "execution-app"
    namespace = kubernetes_namespace_v1.execution.metadata[0].name
    labels    = local.labels
  }

  spec {
    replicas = 2

    selector {
      match_labels = local.labels
    }

    template {
      metadata {
        labels      = local.labels
        annotations = { "postech.dev/rollout-id" = var.rollout_id }
      }

      spec {
        image_pull_secrets {
          name = kubernetes_secret_v1.ghcr.metadata[0].name
        }

        container {
          name              = "app"
          image             = local.image
          image_pull_policy = "Always"

          port {
            container_port = 8000
          }

          env_from {
            config_map_ref {
              name = kubernetes_config_map_v1.config.metadata[0].name
            }
          }
          env_from {
            secret_ref {
              name = kubernetes_secret_v1.secret.metadata[0].name
            }
          }

          readiness_probe {
            http_get {
              path = "/up"
              port = 8000
            }
            initial_delay_seconds = 10
            period_seconds        = 10
          }

          liveness_probe {
            http_get {
              path = "/up"
              port = 8000
            }
            initial_delay_seconds = 30
            period_seconds        = 15
          }

          resources {
            requests = { cpu = "100m", memory = "192Mi" }
            limits   = { cpu = "500m", memory = "384Mi" }
          }
        }
      }
    }
  }

  depends_on = [aws_dynamodb_table.items, aws_dynamodb_table.jobs]
}

resource "kubernetes_deployment_v1" "worker" {
  metadata {
    name      = "execution-worker"
    namespace = kubernetes_namespace_v1.execution.metadata[0].name
    labels    = { app = "execution-worker" }
  }

  spec {
    replicas = 1

    strategy {
      type = "Recreate"
    }

    selector {
      match_labels = { app = "execution-worker" }
    }

    template {
      metadata {
        labels      = { app = "execution-worker" }
        annotations = { "postech.dev/rollout-id" = var.rollout_id }
      }

      spec {
        image_pull_secrets {
          name = kubernetes_secret_v1.ghcr.metadata[0].name
        }

        dynamic "container" {
          for_each = local.worker_processes

          content {
            name              = container.key
            image             = local.image
            image_pull_policy = "Always"
            args              = container.value

            env {
              name  = "NEWRELIC_APPNAME"
              value = "execution-service (worker)"
            }

            env_from {
              config_map_ref {
                name = kubernetes_config_map_v1.config.metadata[0].name
              }
            }
            env_from {
              secret_ref {
                name = kubernetes_secret_v1.secret.metadata[0].name
              }
            }

            resources {
              requests = { cpu = "50m", memory = "128Mi" }
              limits   = { cpu = "250m", memory = "256Mi" }
            }
          }
        }
      }
    }
  }

  depends_on = [aws_sqs_queue.commands, aws_dynamodb_table.processed_messages]
}

resource "kubernetes_service_v1" "app" {
  metadata {
    name      = "execution-app"
    namespace = kubernetes_namespace_v1.execution.metadata[0].name
    annotations = {
      "service.beta.kubernetes.io/aws-load-balancer-type" = "nlb"
    }
  }

  spec {
    type     = "LoadBalancer"
    selector = local.labels

    port {
      name        = "http"
      port        = 8080
      target_port = 8000
    }
  }
}

resource "kubernetes_horizontal_pod_autoscaler_v2" "app" {
  metadata {
    name      = "execution-app-hpa"
    namespace = kubernetes_namespace_v1.execution.metadata[0].name
  }

  spec {
    min_replicas = 2
    max_replicas = 6

    scale_target_ref {
      api_version = "apps/v1"
      kind        = "Deployment"
      name        = kubernetes_deployment_v1.app.metadata[0].name
    }

    metric {
      type = "Resource"
      resource {
        name = "cpu"
        target {
          type                = "Utilization"
          average_utilization = 70
        }
      }
    }
  }
}
