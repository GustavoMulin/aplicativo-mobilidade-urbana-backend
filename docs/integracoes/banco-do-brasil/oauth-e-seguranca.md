# OAuth2 e segurança

## Escolha do fluxo

A API selecionada no Portal BB determina o fluxo OAuth e seus escopos.

| Cenário | Fluxo |
| --- | --- |
| API atua em nome do cliente BB e pede consentimento | `authorization_code`. |
| API atua em nome da empresa/sistema | Confirmar no OpenAPI da API se usa `client_credentials` ou outro grant. |
| Renovação disponível | `refresh_token`, somente quando o escopo/API o emitir. |

Para Login BB e autorização de débito automático, o BB documenta `authorization_code`. Não assuma que todas as APIs seguem esse fluxo.

## Authorization Code

### 1. Gerar e persistir estado

Antes do redirecionamento, o backend cria um `state` criptograficamente aleatório, vinculado à sessão e à URL de retorno. Ele expira em até 10 minutos e é consumido uma única vez.

### 2. Redirecionar o cliente

```text
GET https://oauth.bb.com.br/oauth/authorize
  ?response_type=code
  &client_id={BB_CLIENT_ID}
  &redirect_uri={URL_CADASTRADA_NO_PORTAL}
  &scope={ESCOPO_DO_OPENAPI}
  &state={STATE_ALEATORIO}
  &profiles=pf
```

No sandbox, troque a origem por `https://oauth.hm.bb.com.br`. Os escopos são case-sensitive e separados por um único espaço. `profiles=pf` é opcional e está documentado para a interface de Pessoa Física.

### 3. Receber callback

O BB redireciona para a URL registrada com `code` e `state`. O código de autorização tem validade de **600 segundos** segundo a documentação oficial.

O callback deve:

1. exigir HTTPS em produção;
2. comparar o `state` recebido com o valor guardado;
3. rejeitar código repetido ou expirado;
4. trocar o código pelo token no backend;
5. não enviar o `code`, access token ou refresh token ao aplicativo mobile.

### 4. Trocar code por token

```bash
curl --request POST 'https://oauth.bb.com.br/oauth/token' \
  --header "Authorization: Basic $(printf '%s' "$BB_CLIENT_ID:$BB_CLIENT_SECRET" | base64 -w 0)" \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=authorization_code' \
  --data-urlencode 'code=CODIGO_RECEBIDO_NO_CALLBACK' \
  --data-urlencode 'redirect_uri=https://api.exemplo.com/integracoes/bb/callback'
```

A resposta contém ao menos `access_token`, `token_type` e `expires_in`; pode conter `refresh_token` conforme a API/escopo.

### 5. Consumir recurso

O exemplo oficial mostra Bearer token e `gw-app-key` na consulta. O OpenAPI do produto indica se ambos são exigidos, onde a app key deve ir e quais escopos usar.

```bash
curl --request GET "$BB_API_BASE_URL/RECURSO_DEFINIDO_NO_OPENAPI?gw-app-key=$BB_DEVELOPER_APPLICATION_KEY" \
  --header "Authorization: Bearer $BB_ACCESS_TOKEN" \
  --header 'Accept: application/json'
```

## Descoberta e validação de ID token

Para APIs que retornem ID token, obtenha a configuração OIDC e a URI JWKS pelo documento discovery do ambiente. Valide assinatura, `iss`, `aud`, prazo de validade, `nonce` quando aplicável e demais claims exigidas antes de confiar nos dados.

- Teste: `https://oauth.hm.bb.com.br/oauth/.well-known/openid-configuration`
- Produção: `https://oauth.bb.com.br/oauth/.well-known/openid-configuration`

## Checklist de segurança

- [ ] Segredos somente no backend/secret manager.
- [ ] `state` imprevisível, curto e de uso único.
- [ ] Callback e webhook publicados em HTTPS.
- [ ] Tokens e dados bancários mascarados em logs e exceções.
- [ ] URL de retorno validada contra lista fixa; nunca usar URL recebida do cliente.
- [ ] TLS validado; desabilitar `verify=false`.
- [ ] mTLS habilitado se o OpenAPI exigir.
- [ ] Escopos mínimos necessários.
- [ ] Token em cache e renovação concorrente controlada.
- [ ] Auditoria com ID interno, ID externo e status, sem payload sensível.

## Referências oficiais

- [Authorization Code](https://apoio.developers.bb.com.br/guias-e-tutoriais/seguranca/authorization-code)
- [Credenciais](https://apoio.developers.bb.com.br/guias-e-tutoriais/primeiros-passos/credenciais)
