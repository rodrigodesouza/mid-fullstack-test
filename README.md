# Desafio Full Stack · 3Pontos Tech

Bem-vindo(a) ao processo seletivo da 3Pontos Tech. Este desafio avalia **modelagem de dados**, **decisões técnicas registradas** e domínio da nossa stack.

| Item          | Descrição                              |
|---------------|----------------------------------------|
| **Linguagem** | PHP 8.4                                |
| **Framework** | Laravel 13 + FilamentPHP 5             |
| **Banco**     | PostgreSQL via Docker (já configurado) |
| **Front-end** | Blade, Livewire e Tailwind v4          |
| **Testes**    | Pest 4                                 |

> [!WARNING]
> Pode usar IA. Toda linha entregue é sua, e você precisa saber explicá-la. O `MODEL.md` tem uma seção obrigatória sobre o que você **descartou**.

> [!WARNING]
> Não use plugins do Filament além dos que já vêm neste template. Bibliotecas de infraestrutura são permitidas, desde que justificadas no `MODEL.md`. Modelagem, regras, painel e organização do código são seus.

---

## O produto

**Passa** é um cartão corporativo pré-pago. A empresa deposita um saldo. Cada funcionário tem um cartão com limite mensal e regras. A **rede do cartão** é a bandeira, como Visa ou Mastercard: ela recebe a compra da maquininha e pergunta ao emissor do cartão, o Passa, se pode aprovar. Depois, informa por webhook o que foi de fato cobrado ou cancelado. O financeiro acompanha em um painel.

### Dados fixos da Acme

Usados pelos cenários de avaliação. Precisam existir no seu seed.

|                       |                                                        |
|-----------------------|--------------------------------------------------------|
| Saldo inicial         | R$ 10.000,00                                           |
| Fuso horário          | America/Sao_Paulo                                      |
| Gestora do painel     | `marina@acme.test` · senha `password` (já vem no seed) |

| Cartão | `card_token` | Login do portador   | Regras                                                                  |
|--------|--------------|---------------------|-------------------------------------------------------------------------|
| Ana    | `tok_ana`    | `ana@acme.test`     | Limite mensal R$ 2.000,00. Teto R$ 800,00 por compra. MCC 7995 bloqueado |
| Bruno  | `tok_bruno`  | `bruno@acme.test`   | Limite mensal R$ 500,00                                                 |
| Carla  | `tok_carla`  | `carla@acme.test`   | Cartão bloqueado pela empresa                                           |
| Diego  | `tok_diego`  | `diego@acme.test`   | Limite mensal R$ 50.000,00                                              |

Senha de todos os portadores: `password`. Esses usuários são criados pelo **seu** seed.

### Visão geral das etapas

| Etapa | O que é | Quem usa | Aceite |
|---|---|---|---|
| 1 · Authorization | **API**: endpoint chamado pela rede a cada compra | Rede | S1, S2, S3 |
| 2 · Events | **API**: webhook que a rede chama depois da compra | Rede | S4, S5 |
| 3 · Transactions e consultas | **Modelo** de ledger + **API** de leitura (available e statement) | Rede e avaliação | Invariante, em todos os cenários |
| 4 · Painel | **Filament** em `/admin` | Marina, financeiro da Acme | Avaliação manual |
| 5 · Área do funcionário | **Front** em Livewire, Blade e Tailwind, fora do Filament | Portador do cartão | Avaliação manual + teste de escopo |

As etapas 1 a 3 não têm interface: são a integração com a rede. As etapas 4 e 5 são a interface, lendo o mesmo modelo.

### Regras gerais da API da rede

- Endpoints `/api/network/*` não usam sessão nem token de usuário. A única autenticação é a assinatura.
- Toda requisição da rede traz `X-Network-Timestamp` (Unix, segundos) e `X-Network-Signature: sha256=<hex minúsculo do HMAC-SHA256 de "<timestamp>.<corpo bruto>", chave NETWORK_SECRET>`. Requisição sem corpo assina `"<timestamp>."`, com o corpo vazio. Assinatura inválida ou timestamp com mais de 5 minutos de diferença: `401`.
- Payload que não segue o contrato: `422`. A rede **não reenvia** respostas `4xx`.
- A rede considera entregue qualquer `2xx`. Diante de `5xx` ou timeout, reenvia. O mesmo `id` pode chegar mais de uma vez.
- Valores sempre em **centavos inteiros** (`*_cents`). `currency` é sempre `BRL` neste desafio.
- "Mês" é o mês calendário no fuso da Acme, `America/Sao_Paulo`.
- Não existe nenhum sistema externo neste desafio. A rede é um papel do domínio: nos seus testes, você faz esse papel; na avaliação, os nossos cenários fazem. Nada chama os seus endpoints por conta própria.

---

## Etapa 1 · Authorization

`POST /api/network/authorizations`

```json
{
  "id": "aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y",
  "card_token": "tok_ana",
  "amount_cents": 12990,
  "currency": "BRL",
  "mcc": "5812",
  "merchant": { "name": "Restaurante Bom Prato", "city": "Porto Alegre", "country": "BR" },
  "occurred_at": "2026-09-17T14:03:22Z"
}
```

```json
{ "decision": "approved" }
{ "decision": "declined", "reason": "<identificador seu, estável>" }
```

**Requisitos**

1. Responder em até **2 segundos**.
2. Negar, com `reason`, quando: token inexistente · cartão bloqueado · MCC bloqueado para o cartão · valor acima do teto por compra · valor acima do que resta do limite mensal · valor acima do saldo disponível da empresa.
3. Ao aprovar, reservar `amount_cents` no cartão e no saldo da empresa até a captura ou o cancelamento.
4. Mesmo `id` recebido de novo: mesma `decision`, sem nova reserva.
5. Requisições simultâneas no mesmo cartão nunca aprovam além do disponível.

**Aceite:** S1, S2, S3.

---

## Etapa 2 · Events

`POST /api/network/events`

```json
{
  "id": "evt_01J8KR9B4C7D1E2F3G5H6J8K9L",
  "type": "capture",
  "occurred_at": "2026-09-17T18:40:00Z",
  "authorization_id": "aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y",
  "amount_cents": 30000,
  "currency": "BRL",
  "sequence": 2,
  "final": false
}
```

`type` é `capture` ou `cancellation`. Em `cancellation` só vêm `id`, `type`, `occurred_at` e `authorization_id`. Responda `200` ou `202` assim que o evento estiver persistido ou enfileirado.

**Requisitos**

1. `capture` registra o valor cobrado. Pode vir em várias partes (`sequence` crescente, `final: true` na última) e com valor diferente do autorizado. Nos MCC 7011, 5812 e 5541, até **20% acima** do autorizado é esperado.
2. `cancellation` libera o que estava reservado e não foi capturado.
3. Mesmo `id` de evento recebido de novo: nenhum efeito duplicado.
4. Evento fora de ordem ou referenciando `authorization_id` desconhecido: comportamento é decisão sua (seção **Decisões**), nunca `5xx`, nunca corrompe o disponível.
5. Após qualquer sequência de eventos, o sistema reconstrói a história de cada compra na ordem em que aconteceu.

**Aceite:** S4, S5.

---

## Etapa 3 · Transactions e consultas

**Requisitos**

1. Toda movimentação que altera o limite restante de um cartão ou o saldo da empresa é uma **transaction**. Uma transaction registrada não é alterada nem apagada.
2. **Limit remaining** é o que sobra do limite mensal do cartão depois das reservas e capturas do mês. **Available** é o menor entre o limit remaining e o saldo disponível da empresa. Os dois, e o saldo da empresa, são **derivados das transactions**.
3. `GET /api/network/cards/{card_token}/available`

    ```json
    { "available_cents": 143000, "limit_remaining_cents": 143000 }
    ```

4. `GET /api/network/cards/{card_token}/statement` — transactions do mês corrente, em ordem cronológica, com o limite restante após cada uma:

    ```json
    {
      "transactions": [
        {
          "occurred_at": "2026-09-17T14:03:22Z",
          "type": "<identificador seu>",
          "amount_cents": -12990,
          "reference": "aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y",
          "limit_remaining_after_cents": 187010
        }
      ]
    }
    ```

    `amount_cents` negativo reduz o limite restante, positivo devolve. `reference` é o `id` da authorization ou do event de origem.

5. **Invariante:** `limit_remaining_after_cents` da última transaction do statement é igual a `limit_remaining_cents` da consulta, e `available_cents` nunca passa de `limit_remaining_cents`. A avaliação confere ao final de cada cenário.

Ambos os endpoints são assinados como os demais. São os únicos endpoints de leitura com contrato fixo.

---

## Etapa 4 · Painel

Filament em `/admin`, login da Marina. O painel não cria endpoints: lê o mesmo modelo das etapas 1 a 3.

**Requisitos**

1. Cartões com o disponível atual.
2. Statement de cada cartão: transactions do mês com o limite restante após cada uma.
3. História de cada compra: authorization (`decision` e `reason`), captures e cancellation, com horários, na ordem.
4. Statement da empresa: depósitos e capturas, com o saldo resultante.
5. Authorizations negadas, com `reason`.
6. Uma única ação de escrita: **registrar um depósito** (`deposit`) para a empresa.

---

## Etapa 5 · Área do funcionário

Fora do Filament. Livewire, Blade e Tailwind escritos por você.

**Requisitos**

1. `GET /login`: formulário de e-mail e senha para o portador, com `Auth::attempt`. Sem cadastro, sem recuperação de senha.
2. `GET /my-card`, autenticado: disponível do próprio cartão, statement do mês e história de cada compra própria, com `decision`, `reason`, captures e cancellation na ordem.
3. A tela reflete uma nova authorization ou um novo event em até **5 segundos, sem recarregar a página**. Polling do Livewire basta.
4. Um portador nunca vê dados de outro. Qualquer tentativa por URL ou parâmetro devolve `403` ou `404`, com teste cobrindo.
5. Nenhum componente do Filament nesta área. Layout livre, sem Figma. Estados de vazio e de carregamento contam mais que acabamento visual.

---

## Decisões

Não há resposta certa. Há resposta **registrada no `MODEL.md` e coerente com o código**. Os cenários não listados testam essa coerência.

| # | Decisão |
|---|---|
| 1 | Como representar authorization, capture, cancellation, compra e transaction. Nenhuma entidade, tabela ou estrutura de pastas é imposta |
| 2 | A reserva de uma authorization aprovada aparece como transaction no statement, ou só a capture? Como aparece uma capture em partes |
| 3 | Capture acima da tolerância de 20%, ou acima do autorizado em MCC sem tolerância |
| 4 | Event referenciando `authorization_id` desconhecido |
| 5 | Capture que chega **antes** da authorization. O statement segue `occurred_at` da rede ou a ordem de chegada |
| 6 | Capture menor que o autorizado sem `final: true`: o que continua reservado |
| 7 | Limit remaining, available e saldo recalculados a cada consulta, mantidos como projeção atualizada a cada transaction, ou os dois |
| 8 | Authorization aprovada num mês e capturada no mês seguinte: conta no limite de qual mês |

Desempate: **na dúvida, aprove e registre o alerta**. Bloquear alguém no caixa é a última opção.

---

## Cenários de avaliação

Só S1 e S3 trazem o resultado esperado. Para S2, S4 e S5, escreva no `MODEL.md`, **antes de implementar**, o resultado que você espera e por quê.

| Cenário | O que a rede faz | Resultado esperado |
|---|---|---|
| **S1** | Cinco compras na Ana (129,90 · 45,00 · 300,00 · 80,10 · 15,00), cada uma capturada no valor exato com `final: true` | Ana: `available_cents` 143000, `limit_remaining_cents` 143000. Diego: `available_cents` 943000, `limit_remaining_cents` 5000000. Statement da Ana fecha em 143000 |
| **S2** | MCC 7995 na Ana · R$ 850,00 na Ana · qualquer valor na Carla · `card_token` inexistente | Você diz |
| **S3** | Vinte authorizations de R$ 100,00 **simultâneas** no Bruno | Exatamente cinco `approved`. Bruno `available_cents` 0 |
| **S4** | Authorization de R$ 800,00, MCC 7011, na Ana. Captures de 300,00 · 300,00 · 260,00 (`final: true`) | Você diz |
| **S5** | Capture chega antes da authorization · um event repetido três vezes · uma authorization reenviada com o mesmo `id` · cancellation depois de uma capture parcial | Você diz |

Em todos os cenários a avaliação confere o invariante da Etapa 3. Existem cenários não listados: eles testam as decisões que você registrou.

Para o S3 valer, o servidor precisa atender em paralelo. O `composer dev` já sobe com vários workers. Dispare as requisições simultâneas com a ferramenta que preferir.

---

## Fora do escopo

Estorno, fechamento do mês, exportação, comprovante, aprovação de despesa, centro de custo, moeda estrangeira, expiração de reserva por tempo. Não implemente. Se a sua modelagem deixar porta aberta, anote no `MODEL.md`.

---

## Entrega

1. **`MODEL.md`** (esqueleto no repositório), quatro seções:
    - o modelo, em Mermaid ou imagem, e por que cada coisa existe;
    - **decisões**: de três a cinco, cada uma com a alternativa rejeitada e o motivo;
    - **previsão de S2, S4 e S5**, escrita antes de implementar, e depois o que bateu ou mudou;
    - **o que mudou e o que foi descartado**, inclusive o que a IA propôs e você não aceitou.
2. Testes Pest cobrindo as etapas 1 a 3, o invariante do statement e o escopo de acesso da Etapa 5.
3. `make check` verde: Pint, Larastan, Rector.
4. Seed com os dados fixos da Acme, executado pelo `php artisan db:seed` padrão.
5. `DEVELOPMENT.md` com suas anotações. `README.md` seu: como rodar, como testar, suposições.
6. Commits incrementais, **sem squash**, em _Conventional Commits_ em inglês.

---

## Avaliação

| Critério | Peso |
|---|---|
| Modelagem e decisões registradas | 35 |
| Integração com a rede: cenários, inclusive os não listados | 20 |
| Arquitetura e uso da stack | 15 |
| Testes | 10 |
| Painel e área do funcionário | 15 |
| Comunicação: MODEL, README, commits | 5 |

Modelo completo com implementação parcial vale mais que implementação completa com modelo raso. Se o tempo apertar, entregue as etapas na ordem e documente o que ficou de fora.

---

## Como começar

```bash
make env-up        # Postgres, Redis e Mailpit via Docker
composer setup     # dependências, .env, chave, migrations, seed, assets
composer dev       # servidor com vários workers, fila, logs e Vite
```

Painel em `http://127.0.0.1:8000/admin`. A área do funcionário fica em `/login` e `/my-card` depois que você a construir. Testes com `make test`, qualidade com `make check`, o resto em `make help`. Os mesmos alvos existem no `Taskfile.yml`.

---

## Submissão

1. Crie um repositório a partir deste template ("Use this template", no topo da página).
2. Desenvolva em uma branch `develop`, com commits incrementais.
3. Abra um Pull Request de `develop` para `main` **no seu repositório**.
4. Envie um e-mail para `maria.luiza@3pontos.com` com uma breve apresentação e o link do Pull Request.

> Estimativa: dez a doze horas de trabalho.

---

## Materiais de apoio

- [Filament Docs](https://filament.com/docs) · [Filament Brasil](https://filament.com.br)
- [Laravel Docs](https://laravel.com/docs) · [Pest Docs](https://pestphp.com)
- [Livewire](https://livewire.laravel.com)
