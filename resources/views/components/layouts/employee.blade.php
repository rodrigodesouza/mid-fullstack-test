<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Passa - Área do funcionário</title>

    @vite (['resources/css/app.css'])
</head>

<body class="min-h-screen bg-gray-100">
    <header class="bg-white shadow">
        <div class="mx-auto flex max-w-5xl justify-between p-4">
            <strong> Passa </strong>

            <form method="POST" action="/logout">
                @csrf

                <button class="text-sm text-red-600">Sair</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-5xl p-6">{{ $slot }}</main>
</body>
</html>
