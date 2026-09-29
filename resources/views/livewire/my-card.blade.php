<div wire:poll.5s="loadCard">
    <h1 class="text-2xl font-bold">Olá, {{ auth()->user()->name }}</h1>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-sm text-gray-500">Disponível</h2>

            <p class="mt-2 text-3xl font-bold">
                R$ {{ number_format(
                    $available['available_cents'] / 100,
                    2,
                    ',',
                    '.'
                ) }}
            </p>
        </div>

        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-sm text-gray-500">Limite restante</h2>

            <p class="mt-2 text-3xl font-bold">
                R$ {{ number_format(
                    $available['limit_remaining_cents'] / 100,
                    2,
                    ',',
                    '.'
                ) }}
            </p>
        </div>
    </div>

    <div class="mt-6 rounded-lg bg-white p-6 shadow">
        <h2 class="text-xl font-bold">Extrato</h2>

        <div class="mt-4 space-y-3">
            @forelse ($statement['transactions'] ?? [] as $transaction)
                <div class="border-b pb-3">
                    <div class="flex justify-between">
                        <span> {{ $transaction['type'] }} </span>

                        <span>
                            R$ {{ number_format(
                                abs($transaction['amount_cents']) / 100,
                                2,
                                ',',
                                '.'
                            ) }}
                        </span>
                    </div>

                    <small class="text-gray-500"> {{ $transaction['reference'] }} </small>
                </div>

            @empty
                <p>Sem transações.</p>

            @endforelse
        </div>
    </div>
</div>
