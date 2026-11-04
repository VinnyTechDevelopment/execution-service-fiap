terraform {
  required_version = ">= 1.6.0"

  required_providers {
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.31"
    }
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  # bucket/key/region/dynamodb_table via -backend-config (ver deploy-eks.yml)
  backend "s3" {}
}

provider "aws" {
  region = var.aws_region
}

data "aws_eks_cluster_auth" "this" {
  name = data.terraform_remote_state.cluster.outputs.cluster_name
}

provider "kubernetes" {
  host                   = data.terraform_remote_state.cluster.outputs.cluster_endpoint
  cluster_ca_certificate = base64decode(data.terraform_remote_state.cluster.outputs.cluster_certificate_authority_data)
  token                  = data.aws_eks_cluster_auth.this.token
}

# Só a VPC/EKS vêm de outro repositório (infra-kubernetes-fiap). As tabelas
# DynamoDB são deste serviço — nenhum outro microsserviço tem acesso a elas.
data "terraform_remote_state" "cluster" {
  backend = "s3"
  config = {
    bucket = var.tf_state_bucket
    key    = "infra-kubernetes/terraform.tfstate"
    region = var.aws_region
  }
}
