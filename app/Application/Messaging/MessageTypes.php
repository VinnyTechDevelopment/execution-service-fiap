<?php

namespace App\Application\Messaging;

/**
 * Contrato de mensagens da Saga de Ordem de Serviço (orquestrada pelo
 * os-service). Arquivo idêntico nos três repositórios.
 *
 * Comandos: os-service -> billing/execution (fila "<serviço>-commands").
 * Respostas/eventos: billing/execution -> os-service (fila "os-saga-replies").
 */
final class MessageTypes
{
    // Filas (nome lógico; o nome físico recebe o prefixo do ambiente).
    public const QUEUE_OS_SAGA_REPLIES = 'os-saga-replies';

    public const QUEUE_BILLING_COMMANDS = 'billing-commands';

    public const QUEUE_EXECUTION_COMMANDS = 'execution-commands';

    // Comandos para o billing-service
    public const BILLING_CREATE_QUOTE = 'billing.CreateQuote';

    public const BILLING_CANCEL_QUOTE = 'billing.CancelQuote';

    public const BILLING_REFUND_PAYMENT = 'billing.RefundPayment';

    // Respostas/eventos do billing-service
    public const BILLING_QUOTE_CREATED = 'billing.QuoteCreated';

    public const BILLING_QUOTE_CREATION_FAILED = 'billing.QuoteCreationFailed';

    public const BILLING_QUOTE_APPROVED = 'billing.QuoteApproved';

    public const BILLING_QUOTE_REJECTED = 'billing.QuoteRejected';

    public const BILLING_QUOTE_CANCELLED = 'billing.QuoteCancelled';

    public const BILLING_PAYMENT_CONFIRMED = 'billing.PaymentConfirmed';

    public const BILLING_PAYMENT_FAILED = 'billing.PaymentFailed';

    public const BILLING_PAYMENT_REFUNDED = 'billing.PaymentRefunded';

    public const BILLING_PAYMENT_REFUND_FAILED = 'billing.PaymentRefundFailed';

    // Comandos para o execution-service
    public const EXECUTION_RESERVE_AND_ENQUEUE = 'execution.ReservePartsAndEnqueue';

    public const EXECUTION_CANCEL = 'execution.CancelExecution';

    // Respostas/eventos do execution-service
    public const EXECUTION_QUEUED = 'execution.ExecutionQueued';

    public const EXECUTION_RESERVATION_FAILED = 'execution.PartsReservationFailed';

    public const EXECUTION_STATUS_CHANGED = 'execution.ExecutionStatusChanged';

    public const EXECUTION_FINISHED = 'execution.ExecutionFinished';

    public const EXECUTION_CANCELLED = 'execution.ExecutionCancelled';
}
