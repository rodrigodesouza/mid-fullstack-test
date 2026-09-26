# MODEL.md

Este arquivo documenta o modelo de domínio, as decisões tomadas antes da implementação, as expectativas para os cenários de avaliação e as mudanças realizadas durante a modelagem.

## 1. O modelo

### [↳ Diagrama e descrição dos atributos](DIAGRAM.md)

### Company

Representa a empresa que possui o saldo utilizado pelos cartões.

Existe porque o Passa é um cartão corporativo pré-pago: a empresa deposita um saldo e as compras dos funcionários também precisam respeitar o saldo disponível da empresa.

O saldo da empresa é derivado das `transactions`, evitando manter um saldo financeiro independente do ledger.

O saldo inicial da Acme é R$ 10.000,00 e deve existir no seed.

### User

Representa os usuários da aplicação.

Um usuário pode ser:

* gestora/financeiro da empresa, como Marina;
* portador de um cartão, como Ana, Bruno, Carla e Diego.

O `User` existe porque o desafio possui tanto a área administrativa quanto a área autenticada do funcionário.

O usuário portador possui um cartão associado e só pode consultar os dados desse próprio cartão.

### Card

Representa o cartão corporativo de um funcionário.

Possui as regras necessárias para autorização, como:

* `card_token`;
* `limite mensal`;
* `status do cartão`.

As regras de MCC podem ser configuradas separadamente, pois são regras de negócio e não precisam ser armazenadas individualmente em cada cartão quando não houver exceção específica.

### Authorization

Representa a tentativa de compra recebida da rede em:

`POST /api/network/authorizations`.

Guarda:

* identificador externo da authorization;
* cartão;
* empresa;
* valor;
* MCC;
* merchant;
* momento em que a compra ocorreu;
* decisão;
* motivo da recusa, quando houver.

Uma authorization aprovada cria a reserva financeira.

A mesma authorization recebida novamente não cria nova reserva.

### Event

Representa um evento posterior enviado pela rede em:

`POST /api/network/events`.

Os tipos previstos pelo desafio são:

* `capture`;
* `cancellation`.

Um event possui seu próprio identificador e é idempotente.

O event também guarda `sequence`, `final` e `occurred_at`, pois uma compra pode possuir várias captures e os eventos podem chegar fora de ordem.

Eventos desconhecidos ou recebidos antes da authorization podem ser persistidos como pendentes para posterior processamento.

### Transaction

É o ledger financeiro do sistema.

Toda movimentação que altera o limite restante de um cartão ou o saldo da empresa é representada por uma transaction.

Uma transaction registrada nunca é alterada nem apagada.

Os tipos adotados são:

* `reserve` — reserva o valor autorizado;
* `release` — libera uma reserva que será convertida em capture;
* `capture` — registra o valor efetivamente consumido;
* `cancellation` — libera a reserva restante quando a compra é cancelada;
* `deposit` — adiciona saldo à empresa.

A `reference` identifica o `id` da authorization ou do event que originou a movimentação.

### `occurred_at` e `limit_month`

A transaction possui dois conceitos diferentes:

* `occurred_at`: quando o fato aconteceu;
* `limit_month`: qual mês do limite do cartão é afetado financeiramente.

Essa distinção é necessária porque uma authorization pode ocorrer no final de um mês e sua capture ocorrer no mês seguinte.

Por exemplo, uma authorization de R$ 1.000 em 31/10, com tolerância de 20%, pode ser capturada por R$ 1.150 em 01/11. Os R$ 150 adicionais estão dentro da tolerância da authorization e continuam vinculados ao limite de outubro.

Assim, a transaction pode ter:

```text
occurred_at  = 01/11
limit_month  = 10/2026
```

Se uma capture ultrapassar o teto da authorization mais a tolerância, somente o excedente será atribuído ao `limit_month` do mês da capture.

### Available e limit remaining

`limit_remaining` é o que sobra do limite mensal do cartão depois das reservas e captures atribuídas àquele mês.

`available` é o menor entre:

```text
limit_remaining
saldo disponível da empresa
```

Os valores são derivados das transactions.

Assim, o ledger permanece como fonte única de verdade financeira.

### Concorrência

A autorização precisa verificar e reservar, de forma atômica:

* limite restante do cartão;
* saldo disponível da empresa.

Isso evita que duas authorizations simultâneas consumam o mesmo disponível.

---

## 2. Decisões

O README apresenta oito questões de decisão. Elas foram agrupadas nas cinco decisões abaixo, mantendo todas as oito respostas explicitamente documentadas.

### Decisão 1 — Como representar authorization, capture, cancellation, compra e transaction; e como representar captures parciais

**Perguntas cobertas: 1, 2 e 6.**

#### Decisão

Separar `Authorization`, `Event` e `Transaction`.

A `Authorization` representa a decisão inicial da compra.

Um `Event` representa o que a rede informa posteriormente sobre aquela compra.

A `Transaction` representa o efeito financeiro no ledger.

Uma authorization aprovada gera uma transaction `reserve`.

Uma capture parcial gera:

1. `release` da parte da reserva correspondente;
2. `capture` do valor efetivamente consumido.

Exemplo:

```text
Authorization: R$ 800

reserve       -800
release       +300
capture       -300
```

Ainda permanecem R$ 500 reservados.

Se outra capture de R$ 300 ocorrer:

```text
release       +300
capture       -300
```

Permanecem R$ 200 reservados.

Se posteriormente ocorrer uma cancellation:

```text
cancellation  +200
```

A cancellation libera somente o que ainda estava reservado.

#### Capture menor que o autorizado sem `final: true`

Se a capture for menor que o autorizado e `final` for `false`, a diferença continua reservada.

Exemplo:

```text
Authorization: R$ 800
Capture:       R$ 300
final:         false
```

Resultado:

```text
R$ 300 capturados
R$ 500 continuam reservados
```

Não interpretamos `final: false` como cancelamento da reserva restante.

#### Alternativa rejeitada

Representar a compra como uma única entidade com campos mutáveis, como:

```text
authorized_amount
captured_amount
reserved_amount
status
```

e usar esses campos como fonte principal do saldo.

#### Motivo

O desafio exige histórico financeiro, múltiplas captures, cancellation, idempotência e transactions imutáveis. Um ledger explícito permite reconstruir a história sem sobrescrever fatos anteriores.

---

### Decisão 2 — Capture acima da tolerância de 20%

**Pergunta coberta: 3.**

#### Decisão

Para os MCCs `7011`, `5812` e `5541`, é permitida uma tolerância de até 20% acima do valor autorizado.

O limite máximo da capture daquela authorization é:

```text
authorized_amount + 20%
```

Exemplo:

```text
Authorization: R$ 1.000
Tolerância:    20%
Máximo:        R$ 1.200
```

Portanto:

```text
Capture R$ 1.000 → aceita
Capture R$ 1.150 → aceita
Capture R$ 1.200 → aceita
Capture R$ 1.200,01 → excede a tolerância
```

Para MCC sem tolerância, uma capture acima do valor autorizado é tratada como excedente.

As regras de MCC serão centralizadas em configuração, e não espalhadas pelo código.

Quando uma capture ultrapassar o teto da authorization, o valor excedente será tratado separadamente conforme a regra da Decisão 5 quando houver mudança de mês.

#### Alternativa rejeitada

Permitir qualquer valor acima da tolerância ou criar um limite arbitrário muito maior.

#### Motivo

O README informa que nos MCCs 7011, 5812 e 5541 até 20% acima do autorizado é esperado. Não existe justificativa no domínio para permitir uma extrapolação arbitrária além desse valor.

---

### Decisão 3 — Event desconhecido e capture antes da authorization

**Perguntas cobertas: 4 e 5.**

#### Decisão

Um event que referencia uma `authorization_id` ainda desconhecida não gera `5xx` e não produz uma movimentação financeira incorreta.

O event será persistido como pendente.

Quando a authorization correspondente estiver disponível, o event poderá ser processado.

Isso também resolve o caso de uma `capture` chegar antes da `authorization`.

A ordem de chegada das requisições não será considerada a ordem dos acontecimentos.

A ordem da história da compra será determinada pelo `occurred_at` informado pela rede, respeitando também `sequence` para as captures.

#### Exemplo

Se chegar:

```text
01/10 10:01 → capture
01/10 10:00 → authorization
```

a capture pode ser recebida primeiro pelo sistema, mas a história da compra continuará sendo:

```text
10:00 → authorization
10:01 → capture
```

#### Idempotência

Se o mesmo event for recebido novamente, seu `external_id` será reconhecido e nenhuma nova transaction será criada.

O mesmo vale para uma authorization reenviada com o mesmo `id`.

#### Alternativa rejeitada

Retornar `5xx`, descartar o evento ou simplesmente assumir que a authorization sempre chegará antes da capture.

#### Motivo

O README permite explicitamente eventos fora de ordem e reenvios. Descartar o evento poderia perder um fato financeiro válido; processá-lo sem controle poderia corromper o saldo.

---

### Decisão 4 — Limit remaining, available e saldo da empresa

**Pergunta coberta: 7.**

#### Decisão

As transactions serão a fonte única de verdade.

Os valores financeiros serão derivados delas.

`limit_remaining` será calculado a partir das transactions atribuídas ao mês do limite.

`available` será:

```text
min(
    limit_remaining,
    saldo_disponivel_da_empresa
)
```

O saldo da empresa também será derivado das transactions.

Não manteremos um saldo financeiro independente que possa divergir do ledger.

Para consultas, podemos utilizar projeções/queries otimizadas posteriormente, mas elas serão derivadas do ledger e não substituirão as transactions como fonte de verdade.

#### Concorrência

Durante uma authorization, a verificação do disponível e a criação da reserva precisam ocorrer atomicamente.

Isso garante que vinte authorizations simultâneas de R$ 100 em um cartão com R$ 500 disponíveis resultem em exatamente cinco aprovações.

#### Alternativa rejeitada

Manter somente campos como `current_balance` e `available_limit` e atualizá-los diretamente a cada operação, sem derivação pelo ledger.

#### Motivo

Isso poderia criar divergência entre o saldo armazenado e o histórico de transactions. O README determina que saldo da empresa, `available` e `limit_remaining` sejam derivados das transactions.

---

### Decisão 5 — Authorization aprovada em um mês e capturada no mês seguinte

**Pergunta coberta: 8.**

#### Decisão

A authorization pertence ao mês em que ocorreu.

A reserva criada por ela continua vinculada àquele mês mesmo se a capture ocorrer posteriormente.

A tolerância de MCC também pertence à authorization e acompanha essa authorization.

Portanto, uma capture realizada no mês seguinte **não passa automaticamente a consumir o limite do mês seguinte**.

#### Caso 1 — capture igual à authorization

```text
31/10
Limite disponível: R$ 1.000
Authorization:     R$ 1.000

01/11
Capture:           R$ 1.000
```

Resultado:

```text
Outubro → R$ 1.000
Novembro → R$ 0
```

A capture apenas liquida a reserva de outubro.

---

#### Caso 2 — capture dentro da tolerância

```text
31/10
Authorization: R$ 1.000
MCC: 20%
Teto da authorization: R$ 1.200

01/11
Capture: R$ 1.150
```

Resultado:

```text
R$ 1.000 → cobertos pela authorization de outubro
R$   150 → over-capture permitido pela tolerância
```

Os R$ 150 **não consomem o limite de novembro**.

A transaction ocorreu em 01/11, mas seu `limit_month` continua sendo 10/2026.

---

#### Caso 3 — capture ultrapassa a tolerância

```text
31/10
Authorization: R$ 1.000
Tolerância:    20%
Teto:          R$ 1.200

01/11
Capture:       R$ 1.500
```

A authorization consegue absorver:

```text
R$ 1.200
```

O excedente é:

```text
R$ 1.500 - R$ 1.200 = R$ 300
```

Então o tratamento será:

```text
Outubro:
R$ 1.200 → authorization + tolerância

Novembro:
R$ 300 → excedente
```

Os R$ 300 passam a depender do limite disponível de novembro.

Se novembro tiver R$ 300 disponíveis, o excedente pode ser absorvido.

Se novembro não tiver R$ 300 disponíveis, o excedente não pode ser absorvido.

O novo mês **não aumenta a tolerância da authorization**. Ele somente pode fornecer limite para o valor que ultrapassar o teto da authorization.

#### Alternativa rejeitada

Fazer toda capture recebida em novembro consumir o limite de novembro.

#### Motivo

A authorization já reservou o valor no mês original. Fazer a capture inteira consumir novamente o limite do novo mês duplicaria conceitualmente o consumo do limite e produziria um comportamento incorreto na virada do mês.

---

## 3. O que eu esperava dos cenários

### S2

A rede envia quatro authorizations:

1. Ana com MCC `7995`;
2. Ana com R$ 850,00;
3. Carla com qualquer valor;
4. `card_token` inexistente.

#### Resultado esperado

Todas devem ser `declined`, cada uma com um `reason` estável e identificável.

```text
Ana + MCC 7995
→ declined
→ motivo: MCC bloqueado

Ana + R$ 850
→ declined
→ motivo: valor acima do teto de R$ 800 por compra

Carla + qualquer valor
→ declined
→ motivo: cartão bloqueado

card_token inexistente
→ declined
→ motivo: cartão não encontrado
```

Nenhuma delas deve criar uma `reserve` ou outra transaction financeira.

#### Por quê

Todas as quatro condições são motivos de recusa definidos antes da autorização financeira. Como não existe uma authorization aprovada, não deve existir reserva.

---

### S4

A rede envia:

```text
Authorization:
R$ 800
MCC 7011

Capture:
R$ 300
R$ 300
R$ 260
final: true
```

O MCC 7011 permite 20% acima do autorizado:

```text
R$ 800 × 1,20 = R$ 960
```

O total capturado será:

```text
R$ 300 + R$ 300 + R$ 260 = R$ 860
```

Portanto, a sequência deve ser aceita.

O histórico financeiro esperado é:

```text
reserve       -800
release       +300
capture       -300
release       +300
capture       -300
release       +200
capture       -260
```

Resultado:

```text
Total capturado:  R$ 860
Reserva restante: R$ 0
```

O `statement` deve continuar refletindo corretamente o limite restante após cada transaction.

---

### S5

#### Capture antes da authorization

A capture deve ser persistida como pendente.

Ela não deve gerar `5xx` nem uma movimentação financeira sem uma authorization válida.

Quando a authorization correspondente estiver disponível, a capture deve ser processada.

#### Event repetido três vezes

Somente o primeiro event deve produzir efeito financeiro.

Os outros dois devem ser reconhecidos pela idempotência do identificador do event.

#### Authorization reenviada com o mesmo `id`

A primeira requisição cria a authorization e, se aprovada, sua reserva.

As requisições seguintes devem retornar a mesma decisão sem criar nova reserva.

#### Cancellation depois de uma capture parcial

Exemplo:

```text
Authorization: R$ 800

reserve       -800
release       +300
capture       -300
```

Ainda existem R$ 500 reservados.

A cancellation deve liberar somente esses R$ 500:

```text
cancellation  +500
```

O R$ 300 já capturado permanece consumido.

#### Resultado esperado

O histórico final deve representar:

```text
R$ 300 capturados
R$ 500 liberados por cancellation
R$ 0 reservados
```

---

## 4. O que mudou e o que foi descartado

### Reserva separada do ledger

Foi considerada a possibilidade de manter a reserva em uma estrutura financeira separada.

Foi descartada.

A reserva altera imediatamente o limite disponível e, portanto, deve ser representada por uma `transaction`.

### `release` genérico para qualquer liberação

Foi inicialmente considerado usar somente `release` para representar toda liberação.

Foi descartado.

Passamos a utilizar:

```text
reserve
release
capture
cancellation
```

Isso deixa explícito no ledger se uma reserva foi liberada para uma capture ou se foi efetivamente cancelada.

### Modelo baseado em saldo mutável

Foi considerada a possibilidade de manter saldo e limite restante como valores diretamente atualizados.

Foi descartada.

O ledger de transactions é a fonte de verdade; os saldos são derivados dele.

### Eventos obrigatoriamente ordenados

Foi considerado assumir que a authorization sempre chegaria antes da capture.

Foi descartado.

O domínio precisa aceitar eventos fora de ordem, inclusive uma capture antes da authorization.

### Expiração de reserva

Não será implementada.

A expiração automática de reservas está explicitamente fora do escopo do desafio.

### Split de mês

Foi considerada inicialmente a possibilidade de qualquer over-capture após a virada do mês ser imediatamente atribuído ao novo mês.

Foi descartada.

A tolerância pertence à authorization.

Assim:

```text
Authorization outubro: R$ 1.000
Tolerância:             20%
Teto:                   R$ 1.200

Capture novembro:       R$ 1.150
```

continua pertencendo ao limite da authorization de outubro.

Somente o valor que ultrapassar R$ 1.200 será atribuído ao mês da capture.

### Sugestão de arquitetura contábil mais complexa

Foi considerada uma modelagem com múltiplas contas intermediárias para representar reservas, disponibilidade e liquidação.

Não foi adotada.

O ledger de transactions imutáveis é suficiente para representar os requisitos do desafio sem introduzir complexidade que não é necessária para o domínio apresentado.

### Regras de MCC

A regra de tolerância de MCC não será espalhada em condições hardcoded pelo código.

Ela será centralizada em configuração para facilitar alteração e manutenção.

---

## Observação sobre evolução

Este documento foi escrito antes da implementação das etapas 1 a 3.

Após os testes, a seção 3 deverá ser atualizada indicando o que realmente aconteceu nos cenários S2, S4 e S5 e qualquer diferença entre o comportamento esperado e o implementado.
