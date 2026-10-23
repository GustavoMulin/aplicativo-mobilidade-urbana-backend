# Operação, sandbox e validação

> A validação descrita neste documento faz parte da substituição do AbacatePay pela API do Banco do Brasil. O corte só pode ocorrer quando os fluxos atualmente atendidos pelo AbacatePay estiverem homologados no BB.

## Sandbox

O Portal Developers BB informa que o ambiente de teste permite validar integrações antes da produção. Use exclusivamente as credenciais de teste e a origem OAuth `https://oauth.hm.bb.com.br` durante o desenvolvimento.

Sequência de homologação:

1. Criar aplicação e gerar credenciais de teste.
2. Assinar a API desejada no catálogo.
3. Baixar e arquivar o OpenAPI do produto em `especificacoes/`.
4. Implementar token, cliente e adaptador com testes HTTP simulados.
5. Executar cenários de sucesso, falha, expiração, duplicidade e timeout no sandbox.
6. Registrar callback/webhook público de homologação se a API requerer.
7. Validar reconciliação de cobranças pendentes.
8. Solicitar habilitação e credenciais de produção no fluxo do BB.

## Webhooks

O contrato do produto é a referência para nome de evento, autenticação, assinatura, certificado e política de reentrega. O receptor Laravel deve:

- responder rapidamente com `2xx` depois de validar e enfileirar;
- persistir `evento_id` ou chave equivalente com índice único;
- processar o evento em transação/queue;
- validar autenticidade antes de alterar cobrança ou corrida;
- manter a carga bruta protegida e com retenção definida;
- retornar `4xx` para payload inválido; não marcar cobrança como paga por payload incompleto;
- suportar eventos fora de ordem consultando o provedor quando necessário.

## Observabilidade

Registre em log estruturado:

```json
{
  "gateway": "bb",
  "operacao": "criar_cobranca",
  "corrida_id": 123,
  "cobranca_id": 456,
  "referencia_externa": "mascarada-ou-hash",
  "http_status": 201,
  "tentativa": 1,
  "duracao_ms": 241
}
```

Nunca registre access token, refresh token, `client_secret`, Basic, app key, BR Code integral, documento, dados de conta ou payload financeiro completo.

Métricas mínimas:

- latência e erro por operação BB;
- token obtido/renovado/falhou;
- cobranças por estado;
- eventos recebidos, duplicados e inválidos;
- divergências de reconciliação;
- estornos pendentes e falhos.

## Critérios de aceite

- [ ] Token reutilizado conforme `expires_in`.
- [ ] 401 refaz token uma única vez antes de falhar.
- [ ] Criação de cobrança idempotente.
- [ ] Evento duplicado não duplica saldo/corrida.
- [ ] Corrida só sai de `aguardando_pagamento` após confirmação.
- [ ] Timeout externo não resulta em nova cobrança sem reconciliação.
- [ ] Estorno tem auditoria e não é duplicado.
- [ ] Testes não usam credencial real.
- [ ] Produção usa credenciais e URLs de produção distintas.
- [ ] Revisão de segurança aprovada antes de expor callback/webhook.

## Incidentes

Em falha do provedor, mantenha a cobrança em estado pendente e apresente status recuperável ao passageiro. Evite cancelar ou confirmar automaticamente sem consultar o estado externo quando existir risco de pagamento já realizado.

Em rotação de credenciais, publique as novas credenciais no secret manager, invalide o cache de token, valide no sandbox quando aplicável e então revogue as antigas segundo o processo do BB. O portal informa que gerar uma credencial nova desativa as anteriores.
