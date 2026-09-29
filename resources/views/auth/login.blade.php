<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Passa - Login</title>

    @vite (['resources/css/app.css'])
</head>

<body class="flex min-h-screen items-center justify-center bg-gray-100">
    <div class="w-full max-w-md rounded-xl bg-white p-8 shadow">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold">Passa</h1>

            <p class="mt-2 text-gray-500">Área do funcionário</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>

        @endif

        <form method="POST" action="/login" class="space-y-5">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium"> Email </label>

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    class="w-full rounded-lg border px-3 py-2"
                />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium"> Senha </label>

                <input type="password" name="password" required class="w-full rounded-lg border px-3 py-2" />
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700"
            >
                Entrar
            </button>
        </form>
    </div>
</body>
</html>
