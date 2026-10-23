# Configuração e credenciais

> Esta configuração será usada na migração que substituirá o AbacatePay como provedor de pagamentos. As variáveis do AbacatePay só devem ser removidas depois que o fluxo BB estiver homologado.

## Variáveis propostas

As credenciais pertencem ao backend. Elas nunca devem ser incluídas nos aplicativos mobile, no repositório, em logs ou em documentação preenchida.

```dotenv
# Banco do Brasil — use sandbox até a homologação estar concluída
BB_ENVIRONMENT=sandbox
BB_OAUTH_BASE_URL=https://oauth.hm.bb.com.br
BB_API_BASE_URL=
BB_DEVELOPER_APPLICATION_KEY=
BB_CLIENT_ID=
BB_CLIENT_SECRET=

# Preencha somente se o OpenAPI da API contratada exigir mTLS.
BB_MTLS_CERT_PATH=
BB_MTLS_KEY_PATH=
BB_MTLS_KEY_PASSPHRASE=

# Preencha somente se o contrato de webhook do produto definir uma assinatura.
BB_WEBHOOK_SECRET=
```

Em produção, `BB_OAUTH_BASE_URL` passa a `https://oauth.bb.com.br`. `BB_API_BASE_URL` deve conter exclusivamente a URL de produção publicada no OpenAPI da API contratada.

## Configuração Laravel sugerida

```php
// config/services.php
'bb' => [
    'environment' => env('BB_ENVIRONMENT', 'sandbox'),
    'oauth_base_url' => env('BB_OAUTH_BASE_URL'),
    'api_base_url' => env('BB_API_BASE_URL'),
    'developer_application_key' => env('BB_DEVELOPER_APPLICATION_KEY'),
    'client_id' => env('BB_CLIENT_ID'),
    'client_secret' => env('BB_CLIENT_SECRET'),
    'mtls_cert_path' => env('BB_MTLS_CERT_PATH'),
    'mtls_key_path' => env('BB_MTLS_KEY_PATH'),
    'mtls_key_passphrase' => env('BB_MTLS_KEY_PASSPHRASE'),
],
```

O trecho é referência de arquitetura; não configura a integração sozinho.

## Aplicação das credenciais

O Portal BB informa que uma aplicação gera `developer_application_key`, `client_id`, `client_secret`, credencial Basic e token de registro. O `client_secret` é mostrado apenas uma vez. Ao gerar novas credenciais, as anteriores são desativadas.

| Dado | Uso | Regra |
| --- | --- | --- |
| `client_id` | Identifica a aplicação no OAuth | Pode aparecer em parâmetros públicos de autorização. |
| `client_secret` | Autentica o backend no servidor OAuth | Nunca expor ao mobile ou ao navegador. |
| `developer_application_key` | Chave da aplicação em APIs que a exigem | Enviar somente como definido pelo OpenAPI. |
| Basic | `base64(client_id:client_secret)` | Gerar em memória; não salvar o valor codificado em arquivo. |
| Certificado/chave mTLS | Autenticação de transporte quando exigida | Armazenar fora do repositório e montar por secret manager. |

## Cliente HTTP

O cliente deve ter timeouts, retry somente em falhas transitórias e logs sem segredos:

```php
$response = Http::baseUrl(config('services.bb.api_base_url'))
    ->acceptJson()
    ->timeout(15)
    ->connectTimeout(5)
    ->withToken($accessToken)
    ->withOptions($mtlsOptions) // apenas se o produto exigir mTLS
    ->get($resourcePath, [
        // use a app key apenas se o OpenAPI a exigir neste local
        'gw-app-key' => config('services.bb.developer_application_key'),
    ]);
```

Não faça retry cego em criação de cobrança, pagamento, estorno ou transferência. Para operações mutáveis, use a chave de idempotência definida no OpenAPI. Se o contrato não fornecer uma, controle a idempotência no banco antes de chamar o BB.

## Cache de token

O token deve ser reutilizado até perto de `expires_in`; não solicitar um token em cada cobrança.

- Chave sugerida: `bb:oauth:access-token:<escopo>:<ambiente>`.
- Guardar até `expires_in - 60` segundos, no mínimo.
- Bloquear renovação concorrente (`Cache::lock`) para impedir várias chamadas simultâneas.
- Em `401`, invalidar o token em cache e tentar obter um novo **uma vez**.
- Não registrar `access_token`, `refresh_token`, Basic ou cabeçalho Authorization.
