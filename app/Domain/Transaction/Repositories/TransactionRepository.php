<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Repositories;

use App\Domain\Transaction\Entity\Transaction;

interface TransactionRepository
{
    public function save(Transaction $transaction): void;

    /**
     * A10: Mantém uma reserva de forma atômica, respeitando os limites financeiros disponíveis.
     * Retorna falso quando a reserva não puder ser realizada porque
     * o saldo disponível da empresa/cartão foi consumido simultaneamente.
     */
    public function reserve(Transaction $transaction): bool;
}
