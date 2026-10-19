<h1 align="center">Mobilidade App Backend</h1>

## Sobre o App

Este app é o backend da aplicação de mobilidade urbana desenvolvido pela equipe de TI da Pen6.

## Tecnologias utilizadas

- PHP 8.5
- Laravel 13

## Requisitos:

- PHP 8.5
- MySQL ou MariaDB
- Redis ou Valet
- Docker instalado

> Em ambientes Windows, é necessário ter instalado o WSL2 e alguma distribuição Linux compatível.

Para o ambiente de desenvolvimento, também é requisito:

- RustFS (Storage S3)
- Mailpit (Servidor de email para testes)

## Iniciando o desenvolvimento

Antes de iniciar o ambiente de desenvolvimento, certifique-se de que o arquivo `.env` está devidamente configurado.

Para iniciar a estrutura do ambiente de desenvolvimento pela primeira vez, inicialize o ambiente Docker:

```sh
docker compose up -d --build
```

O serviço `composer` instala automaticamente as dependências do `composer.lock` antes de iniciar o PHP. Ele termina com código 0 após a instalação; isso é esperado.

As dependências ficam no volume Docker `sail-vendor`, compartilhado entre a API e o scheduler. Isso evita a lentidão de carregar milhares de arquivos PHP pelo compartilhamento do Windows. O código da aplicação continua na pasta do projeto, com as edições disponíveis no contêiner.

Para executar comandos pelo terminal do sistema, use:

```sh
docker compose exec laravel.test php artisan route:list
docker compose exec laravel.test composer install
```

Depois de um pull que altere o `composer.lock`, execute o segundo comando antes de continuar. A instalação deve ocorrer dentro do contêiner, pois o `vendor` da máquina não é usado pelos serviços Docker.

Para iniciar o desenvolvimento do código, com o projeto aberto no VSCode basta executar o comando do VSCode "Dev Containers: Reopen in Container".

O devcontainer facilita a manter o ambiente local limpo e padronizado.

## Pós configuração do ambiente

Após configurar o ambiente de desenvolvimento, é necessário executar alguns comandos para preparar o Laravel para funcionar:

```sh
# Caso esteja dentro do devcontainer
php artisan jwt:secret

# Caso esteja no terminal do sistema
docker compose exec laravel.test php artisan jwt:secret
```


## Transferências na Central de ajuda

Tickets novos entram sem responsável, na fila de entrada do Atendimento. O perfil
`atendimento` consulta essa entrada e os tickets atribuídos a si. Depois de
designar para outro operador, perde o acesso ao atendimento. `gestao` e `admin`
consultam a fila global, podem assumir qualquer atendimento e acessar a aba de histórico.

| Método | Rota | Uso |
| --- | --- | --- |
| GET | `/api/gestao/ajuda/operadores` | Buscar operadores não arquivados com acesso à central; aceita `search`, `page` e `exclude_user_id`. |
| POST | `/api/gestao/ajuda/tickets/{ticket}/transfer` | Designar ou transferir, com `{ "to_user_id": 2, "note": "Observação opcional" }`. |
| POST | `/api/gestao/ajuda/tickets/{ticket}/assumir` | Assumir; Gestão e Admin também podem assumir de outro responsável. |
| GET | `/api/gestao/ajuda/tickets/{ticket}/transferencias` | Histórico paginado, do mais recente para o mais antigo; exclusivo de Gestão e Admin. |

A listagem de tickets aceita `fila=todos`, `fila=entrada` ou `fila=meus`, sempre
respeitando o perfil. A seleção de quem está capacitado é feita pelo Atendimento;
a lista de operadores não representa presença online.

A migration `2026_10_15_140000_create_ticket_transfers_table.php` cria
`ticket_transfers`. Os campos de operador (`transferred_by_user_id`, `from_user_id`
e `to_user_id`) referenciam **gestores**, que são as contas do painel. O `user_id`
do ticket continua identificando o cliente. O histórico preserva os nomes da
época mesmo se o operador for renomeado ou excluído.

Cada atribuição, transferência, assunção pela gestão e retorno à fila registra
auditoria e mensagem de sistema na mesma transação. Os tipos são
`queue_distribution`, `manual_transfer`, `takeover_by_manager` e `queue_return`.
O destino é nulo no retorno à fila. Ticket finalizado (`resolved`) é somente
consulta: não pode ser transferido nem reaberto, e um novo assunto pede um novo
ticket.

Os avisos `ticket.atualizado` e `ticket.transferido` atualizam as filas pelo
Reverb. Operadores recebem avisos sem conteúdo e consultam as mensagens pela
API, que revalida o responsável atual. O canal do cliente continua entregando
`ticket.mensagem`. Nenhuma mensagem posterior é entregue ao antigo operador.

O histórico dedicado registra alterações feitas após instalar a migration.
As mensagens anteriores do chat permanecem preservadas.

## Contas para desenvolvimento

O comando `php artisan migrate:fresh --seed` recria o banco e executa os seeders de precificação, gestores e cadastro de desenvolvimento. No terminal do Dev Container:

```bash
php artisan migrate:fresh --seed
```

| E-mail | Perfil |
| --- | --- |
| admin@example.com | Admin |
| gestao@example.com | Gestão |
| atendimento@example.com | Atendimento |
| suporte.tecnico@example.com | Suporte técnico |
| passageiro@example.com | Passageiro |
| motorista@example.com | Motorista |

Para atualizar somente os e-mails antigos (`gestor1`, `gestor2`, `gestor3`) nas contas existentes, execute `php artisan db:seed --class=GestoresSeeder`. Os IDs, senhas, perfis e vínculos são preservados.

Todas essas contas fictícias usam a senha `password`. O motorista fica ativo, com veículo vinculado e CNH, CRLV, Nada Consta e seguro obrigatório aprovados, acompanhados de PDFs fictícios para testes. Passageiro e motorista entram pela API do aplicativo; os gestores entram pelo painel.

O comando `migrate:fresh --seed` apaga os dados existentes. Depois da recriação, encerre as sessões abertas e entre novamente.

Atendimento recebe os tickets novos e pode encaminhá-los ao Suporte técnico para investigar bugs e problemas de TI. O perfil `suporte_tecnico` acessa apenas os tickets atribuídos a ele, sem acesso à fila de entrada nem aos tickets de outros operadores. Gestão e Admin mantêm a visão global. Os perfis Atendimento e Suporte técnico possuem permissões de consulta da operação, sem alterar cadastros ou aprovar documentos.

## Fila de atendimento por operador

Atribuir ou transferir um ticket coloca-o em `waiting` (Aguardando atendimento). O operador deve clicar em **Iniciar atendimento**; cada operador pode manter vários tickets em atendimento ao mesmo tempo, escolhendo quais iniciar. Finalizar, transferir ou devolver um atendimento reorganiza as posições, sem iniciar outro ticket automaticamente.

Tickets finalizados (`resolved`) saem da fila de Atendimento/Suporte técnico e do filtro `fila=meus`. A fila global de Gestão/Admin preserva esses atendimentos para consulta. O cliente pode enviar informações enquanto aguarda; o operador precisa iniciar para responder (HTTP 409 em caso de tentativa fora da vez).

O mesmo aviso de sistema (`tipo_sistema=fila_atendimento`) atualiza a posição. Sem ninguém à frente, informa que o operador ainda deve iniciar. Apenas após iniciar muda para "Chegou sua vez! Seu atendimento foi iniciado.". O painel exibe a espera sem cronômetro. A API mantém os campos de duração por compatibilidade e usa `fila_entrada_em` para ordenar a fila. `fila.posicao=0` identifica o ativo ou o primeiro aguardando; verifique `status` e `fila.pode_iniciar`.

O evento `ticket.mensagem` entrega ao cliente o aviso com o mesmo ID para mesclar. A rota de mensagens também retorna `atualizadas` para recuperar edições depois de desconexões, sem alterar a paginação.

### Estados dos tickets

A Central utiliza somente `open` (Aberto), `waiting` (Aguardando atendimento), `in_progress` (Em atendimento) e `resolved` (Finalizado). Os filtros e o endpoint `PATCH /api/gestao/ajuda/tickets/{ticket}/status` rejeitam `pending` e `closed` com HTTP 422. Iniciar usa `POST /api/gestao/ajuda/tickets/{ticket}/assumir`; finalizar envia `{"status":"resolved"}` ao endpoint de status. `waiting` é definido automaticamente ao designar/transferir e não pode ser enviado manualmente ao endpoint de status.

A finalização é definitiva: o histórico continua disponível, mas não é possível reabrir, transferir, iniciar novamente ou enviar novas mensagens. Para continuar, o cliente abre outro ticket. O reenvio idempotente de uma mensagem já salva continua retornando o registro original.

Execute `php artisan migrate` no terminal do backend para aplicar `2026_10_16_120000_simplifica_status_dos_tickets.php`. A migration converte `pending` em `waiting` quando há responsável e em `open` quando não há; converte `closed` em `resolved`. Preserva as datas, os vínculos e as mensagens/transferências de auditoria, mantendo `fechado_em` como dado histórico. A normalização não desfaz os valores ao executar rollback, pois não há como recuperar os estados originais sem inventar histórico. Não requer recriar o banco nem executar seeders.

## Mensagens não lidas

A migration `2026_10_15_180000_create_ticket_leituras_table.php` cria `ticket_leituras`, com cursor por ticket/gestor. Execute `php artisan migrate` no terminal do container. A listagem da gestão retorna `mensagens_nao_lidas` (somente mensagens do cliente) e `ultima_mensagem_cliente_id`.

`POST /api/gestao/ajuda/tickets/{ticket}/leitura` recebe `{ "ultima_mensagem_id": 123 }` e marca somente o intervalo visto. O cursor nunca regride; ler em uma conta não limpa o contador de outra. O painel marca quando a conversa está na aba Mensagens e a janela está visível/com foco. O evento `ticket.lido` atualiza outras sessões da mesma conta no canal privado `ajuda.operador.{id}`, sem provocar atualizações na fila de outros operadores.

A identificação da conta substitui o botão Novo ticket no cabeçalho da Central; a criação de tickets continua disponível pela API.

## Consulta de saldo dos motoristas

A página Motoristas saldo utiliza GET /api/gestao/motoristas-saldo, GET /api/gestao/motoristas-saldo/{movimentacao} e GET /api/gestao/motoristas-saldo/{movimentacao}/corrida/mensagens. Requer JWT de Gestor e financeiro.consultar (Gestão/Admin). Aceita search (nome/CPF/ID), motorista_id, tipo, status, data_inicio, data_fim, timezone, page e per_page (máximo 50). O status vem de detalhes.status (confirmado por padrão, processando, falhou ou cancelado).

A migration 2026_10_16_140000_create_saldo_motoristas_table.php cria saldo_motoristas com os valores anterior/posterior e detalhes JSON. Execute php artisan migrate; não é necessário recriar o banco. A tabela começa vazia. Este módulo não grava movimentações automaticamente e não expõe cadastro, alteração, exclusão ou resgate.

O detalhe retorna os saldos gravados e verifica a igualdade em centavos. O saldo atual é consultado em CarteiraMotoristaService, preservando as regras de corridas pagas, dinheiro em mãos e saques. A composição usa detalhes.financeiro quando existir um snapshot; campos ausentes nesse snapshot ficam nulos. Sem snapshot, consulta os valores atuais de corrida_financeiros. Taxa de cancelamento da corrida e dívida de terceiros permanecem separadas. Valores ausentes retornam nulos.

Para visualizar um exemplo completo de Motoristas saldo em desenvolvimento, execute php artisan db:seed --class=SaldoMotoristasDemonstracaoSeeder. Cria uma conta separada Motorista financeiro de teste (motorista.financeiro@example.com), quatro corridas fictícias, dois resgates simulados e seis lançamentos, com saldo final de R$ 54,37. Não chama gateways nem altera as contas já existentes. Exclusivo de local/testing e pode ser repetido sem duplicar os dados. Este seeder é manual e não é executado pelo DatabaseSeeder.

A aba Histórico da corrida no financeiro reutiliza os detalhes, mapa e conversa motorista/passageiro da Central de ajuda. O histórico é vinculado ao lançamento, sem exigir ticket, e permanece somente leitura (pode_enviar=false). Aceita before_id ou after_id e limit de 1 a 100. O canal privado corrida.{id}.chat.financeiro requer financeiro.consultar e lançamento vinculado; o evento contém apenas corrida_id e mensagem_id, e a API revalida acesso. O seeder de demonstração também completa os trajetos e conversas fictícias das quatro corridas, sem alterar saldos.


## Demonstrativo de ganhos dos motoristas

A migration 2026_10_16_150000_add_categoria_to_saldo_motoristas.php cria a coluna categoria na tabela existente saldo_motoristas, indexada por categoria/motorista/data. Copia as classificações do JSON (ganho_corrida → valor_corrida, bonus → recompensa) e preserva campos anteriores, valores, saldos e datas. A coluna tem prioridade; a leitura mantém compatibilidade com detalhes.categoria quando ela está nula. Ao gravar pelo model, a categoria é normalizada e preenchida automaticamente. Escritas diretas devem preencher a coluna explicitamente.

A migration 2026_10_16_151000_create_ganhos_motoristas_resumo_table.php cria o resumo diário por motorista/data/serviço. Ambas são aplicadas por php artisan migrate, sem migrate:fresh. O módulo de consulta não registra ganhos na carteira, não solicita saques e não altera corridas. Somente o resumo derivado é reconstruído por upsert ao consultar um período, refletindo alterações na origem sem duplicar os valores.

Rotas GET protegidas por JWT de Gestor e financeiro.consultar (Gestão/Admin):

- /api/gestao/motoristas-ganhos/motoristas: seletor paginado por search (nome/CPF) e page; retorna id, nome e foto, inclusive arquivados.
- /api/gestao/motoristas-ganhos/resumo: motorista_id, data (YYYY-MM-DD), periodo (diario, semanal, mensal, anual) e tipo_servico (todos, corrida, entrega). Retorna motorista, período, totais, produtividade, gráfico e avisos em data.
- /api/gestao/motoristas-ganhos/detalhes: mesmos filtros, mais visao (solicitacoes, demonstrativo, valor_solicitacoes, recompensas, gorjetas, compensacoes, outros), page e per_page (até 50). Retorna data e meta.

Ganho total = Valor das solicitações + Recompensas + Gorjetas + Compensações + Outros. Corridas finalizadas e pagas fornecem o valor líquido. Os créditos de corrida do extrato não são somados novamente; seu snapshot pode discriminar bonus_incentivo, gorjeta, compensacao e outros_ganhos já incluídos no líquido. As parcelas saem de Valor das solicitações e entram nas categorias, preservando o total. Lançamentos adicionais confirmados das categorias recompensa/bonus, gorjeta, compensacao, outro e ganhos_integrais entram com sinal conforme entrada/saída. Transferências e taxas/ajustes genéricos de carteira ficam fora dos ganhos. detalhes.incluido_no_valor_corrida=true impede somar novamente uma parcela já incluída. detalhes.campanha identifica a missão/meta quando existir.

Entregas usam produtos_corridas.estrategia_precificacao=entrega. Adicionais sem corrida podem informar detalhes.tipo_servico; os não identificados entram somente em Tudo. Ganhos são classificados como dinheiro/app pelo método registrado; pagamentos sem método ficam em total_nao_identificado. O fuso fixo de fechamento vem de GANHOS_TIMEZONE (padrão America/Porto_Velho). Semana começa segunda-feira. Gráfico diário usa as horas de origem; semanal e mensal usam dias; anual cobre janeiro a dezembro com 12 barras mensais. A consulta anual inclui anos bissextos e apresenta somente totais, composição, gráfico mensal e estatísticas; não carrega passageiros ou trajetos e não permite listagens detalhadas (GET detalhes com periodo=anual retorna 422). Não exige nova migration. Campos ausentes não são estimados e médias sem denominador ficam nulas.


## Ganhos e resultado da plataforma

O módulo administrativo /plataforma-ganhos separa comissões a receber, recebimento online ainda não comprovado e resultado operacional com custos conciliados. O pagamento do passageiro não comprova depósito na empresa; corridas em dinheiro não geram lucro realizado automaticamente.

A migration 2026_10_19_140000_create_plataforma_lucros_tables.php cria plataforma_lucros e sua auditoria. Execute php artisan migrate, sem recriar o banco. GET /api/gestao/plataforma-lucros consulta resumo, gráfico e lista; GET /{id} detalha a DRE; POST /sincronizar atualiza apenas retratos de corridas; PATCH /{id}/custos concilia valores; POST /{id}/recebimento registra comprovante de quitação integral; POST /custos-avulsos registra despesas já pagas e não alocadas. Escritas exigem motivo, referência e UUID; conciliações também exigem a versão atual. financeiro.consultar permite consulta/sincronização e financeiro.conciliar permite conferências para Gestão/Admin.

Importação por comando: php artisan plataforma:sincronizar --inicio=2026-10-01 --fim=2026-10-31. Não altera carteiras, cobranças nem saques. Não integra automaticamente settlement ou quitação de comissão do motorista. Custos ausentes ficam pendentes, sem lucro presumidamente fechado. O seeder manual PlataformaLucrosDemonstracaoSeeder cria exemplos identificados apenas em local/testing, desligados por padrão na interface.

Contratos completos, fórmulas, períodos, diferenças entre caixa e apuração e exemplos de payload: [docs/plataforma-lucros.md](docs/plataforma-lucros.md).
