# Ganhos da plataforma

Página `/plataforma-ganhos`, menu **Ganhos da plataforma**. A visão é da empresa: separa resultado operacional conciliado, comissões com motoristas e recebimentos online ainda não comprovados. Não representa saldo bancário total nem apuração tributária. Valores e custos ausentes ficam identificados, sem presumir lucro fechado.

## Acesso e interface

Gestão/Admin usam `financeiro.consultar` para consultar e sincronizar os retratos das corridas. `financeiro.conciliar` permite conferir custos, registrar comprovantes de recebimento e despesas avulsas. Atendimento/Suporte técnico não recebem essas permissões. A API valida o acesso independentemente dos botões visíveis.

O painel esquerdo contém períodos Hoje (data de referência), Semanal, Mensal e Personalizado, serviço Todos/Corridas/Entregas, apuração Todos/Consolidado/Estimado, indicadores e lista paginada. Sem operação selecionada, o painel direito apresenta o gráfico detalhado, com total realizado acima, período, escala em reais, valores nas barras, melhor intervalo, média e quantidade de operações conciliadas. Selecionar uma barra mostra o valor e a quantidade daquele intervalo; setas e rolagem permitem consultar séries extensas sem sobrepor rótulos. Resultados negativos aparecem abaixo do zero, em vermelho, e os positivos em azul. O gráfico permanece disponível mesmo sem movimentos, com os intervalos zerados.

Selecionar uma operação substitui o gráfico pela DRE, pendências, situação de recebimento, histórico de conferências e auditoria da corrida original. **Ver gráfico** limpa a seleção e retorna à visão do período. No celular, o gráfico fica abaixo dos indicadores, antes da lista; ao abrir uma operação, aparece somente seu detalhamento, com botão de retorno.

O filtro **Resultado** oferece **Todos (ganhos e saídas)**, **Ganhos (positivos)** e **Saídas (negativos)**. O padrão Todos preserva a visão completa. A seleção considera o sinal de `lucro_liquido` de cada operação, antes da paginação e do agrupamento por hora/dia, e vale para lista, indicadores, contagens e gráfico. Assim, valores positivos e negativos do mesmo horário podem ser consultados separadamente. Valores zero e desconhecidos (`null`) aparecem somente em Todos; os intervalos temporais sem resultado continuam zerados no gráfico. Custos pendentes continuam estimados e não passam a integrar o total realizado apenas por serem positivos. Os títulos e o marcador do gráfico identificam quando a visão está filtrada.

Os quatro indicadores monetários são distintos:

- **Lucro líquido realizado · consolidado:** somente recebimentos/despesas comprovados no período e custos integralmente conferidos. Pode ser negativo.
- **Comissões a receber · dinheiro:** comissão prevista ainda com o motorista; nunca é somada ao lucro realizado.
- **Online aguardando recebimento:** o passageiro pagou, mas o recebimento da comissão pela plataforma não foi conciliado.
- **Estimativa recebida:** a operação tem recebimento conferido, porém custos pendentes. Permanece fora do lucro consolidado.

O take rate é a razão ponderada entre o resultado consolidado (incluindo custos avulsos do período) e o GMV das mesmas operações recebidas e consolidadas. Não é média simples dos percentuais das corridas. Sem GMV retorna `null` e a interface mostra `—`.

## Fontes e segregação de caixa

`PlataformaLucrosService::sincronizar` importa retratos de corridas **finalizadas e pagas** no intervalo. A origem única `corrida:{id}` impede duplicação. O financeiro fornece GMV, repasse e comissão; o serviço não calcula uma taxa percentual fixa. Produto com estratégia `entrega` identifica entregas; serviço não identificado aparece apenas em Todos.

Um pagamento `pago` ou uma cobrança `PAID` confirma pagamento do passageiro, não depósito bancário da empresa. Assim:

Cobranças pagas com `dev_mode=true` no PIX/cartão são identificadas como demonstração e excluídas dos indicadores reais por padrão, mesmo que a corrida tenha status `pago`.

- `dinheiro` começa em `a_receber_motorista`;
- `pix`/`cartao` começam em `aguardando_liquidacao`;
- método não identificado permanece pendente, sem inventar origem;
- `realizado_em_conta` exige conferência explícita com referência, valor da comissão e data do recebimento;
- origem antes importada que deixa de ser finalizada/paga recebe `estornado` e sai do resultado realizado.

A sincronização não altera corridas, pagamentos, saques nem `saldo_motoristas`. O botão Atualizar importa apenas os retratos da apuração. Não registra quitação da dívida na carteira nem executa PIX ou transferência: a conferência registra uma quitação já ocorrida e comprovada. A integração automática com settlement do adquirente ou compensação da carteira exige um evento confiável desses módulos; nenhum é presumido a partir do status da corrida.

Uma alteração no retrato de origem incrementa a versão, registra auditoria e exige novas conferências de custos/recebimento. A consulta preserva o histórico dessas alterações. Custos corporativos sem corrida podem ser registrados com comprovante único, após confirmar que não foram incluídos em outra apuração. O módulo não transforma automaticamente toda recompensa/compensação do motorista em custo da empresa: primeiro é preciso identificar quem financiou o valor e sua eventual alocação a uma corrida.

As escritas também revalidam a corrida no momento da conferência. Se os valores/status mudaram desde a sincronização, retornam 409 e pedem atualização; não basta a versão do formulário continuar igual.

## DRE e custos incompletos

Valores monetários são armazenados em decimal e calculados em centavos inteiros:

```text
Margem bruta reclassificada = GMV − repasse líquido + custos já incluídos no repasse
Resultado da operação = margem bruta reclassificada − gateway − subsídio − bônus − compensação
```

A reclassificação evita descontar o mesmo custo duas vezes. Exemplo: GMV `30,00`, repasse `32,00` incluindo bônus `5,00`, comissão `3,00` e gateway `0,50`. A margem reclassificada é `3,00`; o resultado é `−2,50`. Descontar o bônus novamente sem a reclassificação produziria um resultado incorreto. A DRE exibe a linha adicional quando necessária.

Custos desconhecidos são `null`. Um valor informado, inclusive zero, só é confirmado quando seu campo aparece em `custos_confirmados`. A conciliação também informa `custos_incluidos_repasse` e `composicao_conferida`. Para comissão de corrida, divergência acima de um centavo entre composição e comissão de origem mantém a operação pendente. Correção dos valores da corrida deve ocorrer no módulo de origem e ser sincronizada; não se altera o GMV da corrida para forçar um fechamento.

`status_custos=consolidado` exige valores de origem conhecidos, composição conferida e os quatro custos confirmados. O cliente não informa diretamente `lucro_liquido`, `margem_bruta_retida` ou status. Custos conhecidos entram na estimativa, mas os desconhecidos continuam explicitamente pendentes. Uma operação pode ter custos consolidados e continuar fora do caixa, ou ter recebimento comprovado com custos pendentes.

Campanhas/despesas avulsas confirmadas geram operações negativas sem GMV, evitando criar uma corrida fictícia para representar uma despesa. O comprovante não pode ser cadastrado novamente. Valores já alocados no DRE de uma corrida não devem ser cadastrados como custo avulso.

## Períodos

Usa `GANHOS_TIMEZONE`, padrão `America/Porto_Velho`. Semana de segunda a domingo; mês civil; personalizado limitado a 366 dias, inclusivo na data final. A API trabalha com limites UTC inclusivo/exclusivo.

Lucro realizado e gráfico usam **recebido_em**, a data da conferência do recebimento/pagamento efetivo informado. Uma corrida de sexta recebida na segunda entra no caixa da segunda. A lista inclui operações ocorridas no intervalo ou recebidas nele. Comissões pendentes são as ainda não quitadas das operações do intervalo; não representam o saldo global de todas as dívidas históricas. O resumo respeita os mesmos filtros de serviço, status e demonstração da lista.

Em **Hoje**, o gráfico cobre as 24 horas locais (`00:00` a `23:00`) da data de referência e exibe a data completa acima. Semanal, Mensal e Personalizado continuam agrupados por dia, incluindo dias sem resultado. A média considera todos os intervalos exibidos, inclusive os zerados; a contagem do gráfico considera somente operações recebidas e com custos consolidados, incluindo despesas avulsas. A soma das barras corresponde ao lucro realizado do resumo, independentemente da página da lista.

## Banco e implantação

Migration `2026_10_19_140000_create_plataforma_lucros_tables.php` cria somente:

- `plataforma_lucros`: campos da DRE, origem única, corrida opcional, serviço, estado do caixa/custos, pendências, snapshot e hash da origem, data de ocorrência e de recebimento, comprovante, versão e marcador de demonstração.
- `plataforma_lucros_auditoria`: antes/depois, ação, gestor/nomes históricos, motivo, referência, UUID idempotente e versão. Sem rotas de alteração/exclusão da auditoria.

Em relação ao SQL inicial, há adições necessárias: `aguardando_liquidacao` evita declarar online como caixa automaticamente; origem `nao_identificada`; custos/resultado nullable para valores desconhecidos; data própria de ocorrência/recebimento; rastreamento dos componentes embutidos; controle de versão e auditoria separada.

No terminal do backend dentro do container:

```shell
php artisan migrate
php artisan plataforma:sincronizar --inicio=2026-10-01 --fim=2026-10-31
```

O comando é repetível e limitado a 366 dias. Não usa `migrate:fresh` e não preenche a carteira dos motoristas.

## Rotas e contratos para o backend/apps

Todas exigem JWT de **Gestor**, `Authorization: Bearer <token>`, `Accept: application/json`. Escritas em JSON. Prefixo completo `/api/gestao`.

| Método | Rota                                             | Função                                                                                                            |
| ------ | ------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------- |
| GET    | `/api/gestao/plataforma-lucros`                  | Lista, resumo executivo, gráfico e período efetivo.                                                               |
| POST   | `/api/gestao/plataforma-lucros/sincronizar`      | Atualiza retratos de corridas do período, sem movimentar dinheiro.                                                |
| GET    | `/api/gestao/plataforma-lucros/{id}`             | DRE, pendências, versão, corrida, snapshot, referências e últimas 30 conferências.                                |
| PATCH  | `/api/gestao/plataforma-lucros/{id}/custos`      | Concilia custos com auditoria. Requer `financeiro.conciliar`.                                                     |
| POST   | `/api/gestao/plataforma-lucros/{id}/recebimento` | Registra conferência de recebimento integral da comissão; não cobra nem paga. Requer `financeiro.conciliar`.      |
| POST   | `/api/gestao/plataforma-lucros/custos-avulsos`   | Registra subsídio, bônus ou compensação já pagos e não alocados em outra operação. Requer `financeiro.conciliar`. |

Lista e sincronização recebem `periodo=hoje|semanal|mensal|personalizado`, `data=YYYY-MM-DD`, `tipo_servico=todos|corrida|entrega`, `status_custos=consolidado|custos_pendentes` opcional e `incluir_demonstracao=0|1`. Personalizado exige `data_inicio` e `data_fim`. Lista aceita `page` e `per_page` (máximo 50); retorna `{data, meta, resumo, periodo, grafico}`. Detalhe/escritas retornam `{data}`. O identificador da rota é o ID de `plataforma_lucros`, não da corrida.

A lista também retorna `grafico_granularidade: "hora" | "dia"`. Cada ponto de `grafico` contém `{data, valor, quantidade}`: em Hoje, `data` é a hora local `HH:00`; nos demais períodos é `YYYY-MM-DD`. `valor` é decimal com sinal e `quantidade` conta as operações recebidas e consolidadas daquele intervalo. Os horários usam `periodo.timezone`, não o fuso do navegador. Exemplo: `{ "data": "09:00", "valor": "37.26", "quantidade": 2 }`.

Lista e sincronização também aceitam o filtro opcional `resultado=todos|positivo|negativo`. Ausência ou `todos` mantém todas as operações; `positivo` aplica `lucro_liquido > 0`, e `negativo`, `lucro_liquido < 0`. Valores diferentes retornam 422. A sincronização continua importando as fontes elegíveis do período inteiro; o filtro afeta a consulta da apuração, sem modificar os registros. Combina com período, serviço, status de custos e demonstração. A paginação não limita os totais nem o gráfico do conjunto filtrado.

Conciliação de custos:

```json
{
  "versao": 1,
  "client_request_id": "568dd176-2cf0-4c40-aebb-90be80438d33",
  "custo_gateway": "0.50",
  "subsidio_aplicado": "0.00",
  "bonus_alocado": "0.00",
  "compensacao_paga": "0.00",
  "custos_confirmados": ["custo_gateway", "subsidio_aplicado", "bonus_alocado", "compensacao_paga"],
  "custos_incluidos_repasse": [],
  "composicao_conferida": true,
  "referencia": "Extrato adquirente / operação 123",
  "motivo": "Conferência dos custos e da composição desta operação."
}
```

Recebimento usa `versao`, UUID, `referencia`, `motivo`, `valor` igual à comissão integral e `recebido_em` ISO com fuso. Pode ser registrado antes de terminar a conciliação dos custos; o resultado continua estimado até que estejam completos. Esta etapa não encerra recebimentos parciais: mantém a operação fora do lucro realizado até a comprovação integral. A data não pode ser futura nem anterior à ocorrência.

Custo avulso usa UUID, `descricao`, `tipo_operacao=subsidio_tarifa|recompensa_bonus|compensacao_reembolso`, `tipo_servico=corrida|entrega|outro`, `origem_pagamento=online_pix|online_cartao|nao_identificada`, `valor` positivo, `pago_em` ISO, `referencia` única, `motivo` e `nao_alocado=true`. O backend calcula o resultado negativo.

UUID repetido com conteúdo idêntico não duplica a conferência. UUID usado com outro conteúdo ou versão desatualizada retorna 409. Não há envio de um lucro calculado pelo navegador. Erros: 401 sem sessão, 403 sem permissão, 404 sem operação, 409 por conflito de versão/identificador ou origem estornada, 422 por validação e 429 por excesso de escritas.

## Demonstração local e verificação

```shell
php artisan db:seed --class=PlataformaLucrosDemonstracaoSeeder
```

Somente local/testing e repetível. Mantém os quatro exemplos iniciais: comissão PIX recebida com ganho `2,50`, comissão em dinheiro a receber `5,00`, cartão com comissão `4,00` aguardando depósito/custo de gateway, e custo de campanha `−6,00`. Somente esses quatro exemplos produzem resultado realizado `−3,50`, para demonstrar que prejuízo não é ocultado.

Também executa `PlataformaLucrosMassaDemonstracaoSeeder`, que acrescenta **360 operações**: 24 situações em 15 datas distribuídas por 90 dias. São 300 corridas/entregas vinculadas, com financeiro e trajeto, e 60 despesas avulsas. Os códigos começam com `DEMO-P`, e há três motoristas e três passageiros exclusivos desta demonstração. Para executar somente essa massa:

```shell
php artisan db:seed --class=PlataformaLucrosMassaDemonstracaoSeeder
```

Situações disponíveis:

- PIX/cartão com lucro fechado, dinheiro ainda a receber e quitação simulada da comissão.
- Lucro positivo, resultado zero e prejuízo; subsídio, bônus e compensação incluídos no repasse, sem desconto duplicado.
- Recebimento comprovado com custos pendentes, gateway desconhecido, valores zero não confirmados e composição divergente.
- Operação estornada, informações incompletas e recebimento oito dias depois da corrida.
- Subsídio, campanha, reembolso de pedágio e ajuste administrativo sem corrida.
- Gateway de desenvolvimento: 15 cobranças PIX simuladas, identificadas com `dev_mode=true`, sem chamar serviços de pagamento.

O exemplo de comprovante parcial continua **aguardando liquidação**; não representa suporte a recebimento parcial confirmado. São 240 operações com custos consolidados e 120 com custos pendentes. No caixa: 285 recebidas, 15 a receber do motorista, 45 aguardando liquidação e 15 estornadas. Todas são fictícias, com referências e auditorias identificadas como simulação.

A data-base é fixada na primeira execução (no banco local, **19/10/2026**). Repetir o seeder não desloca as datas, duplica operações nem desfaz conferências manuais nos exemplos. A sincronização dos retratos também preserva os cenários enquanto a origem permanecer igual. Os registros anteriores são preservados; não são criados lançamentos em `saldo_motoristas` nem saques.

Abra Ganhos da plataforma e ative **Mostrar demonstração**. Use a referência **19/10/2026** para os exemplos mais recentes, Mensal para outubro ou Personalizado de **21/07/2026 a 19/10/2026** para a massa completa. O cenário de recebimento posterior pode ter ocorrência oito dias antes desse intervalo, mas permanece listado pela data de recebimento. Os retratos das corridas demonstrativas existentes também recebem o marcador e podem acrescentar registros conforme o período selecionado. A demonstração começa desligada e nunca é misturada silenciosamente ao indicador real.

Testes de backend em `tests/Feature/PlataformaLucrosTest.php`, com proteção para executar apenas em `APP_ENV=testing` e banco `testing`. Cobrem separação de caixa, custos incompletos, perdas, não duplicação de bônus, auditoria/idempotência/concorrência por versão, estornos, períodos, permissões, custos avulsos e a preservação dos 360 cenários ao repetir a carga e sincronizar as fontes.

O `.gitignore` atual do backend ignora novos arquivos em `tests/`; essa suíte permanece local, assim como os testes de navegador em `.local-tests`. A regra de versionamento do repositório foi preservada.
