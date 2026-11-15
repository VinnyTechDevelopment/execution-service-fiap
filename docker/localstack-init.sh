#!/bin/sh
# Cria as filas SQS da saga no LocalStack (ambiente "local"), com DLQ.
set -e
for queue in os-saga-replies billing-commands execution-commands; do
  dlq_url=$(awslocal sqs create-queue --queue-name "local-${queue}-dlq" --query QueueUrl --output text)
  dlq_arn=$(awslocal sqs get-queue-attributes --queue-url "$dlq_url" --attribute-names QueueArn --query Attributes.QueueArn --output text)
  awslocal sqs create-queue --queue-name "local-${queue}" \
    --attributes "{\"VisibilityTimeout\":\"60\",\"RedrivePolicy\":\"{\\\"deadLetterTargetArn\\\":\\\"${dlq_arn}\\\",\\\"maxReceiveCount\\\":\\\"5\\\"}\"}" >/dev/null
  echo "fila criada: local-${queue}"
done
