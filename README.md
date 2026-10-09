# Chat IA

Chat para coletar dados de contato conversando com um modelo de linguagem. A pessoa fala em texto corrido. O modelo pergunta o que falta, consulta o CEP e grava nome, CPF, endereço, e-mail e telefone.

O projeto usa os conceitos de uma aplicação com IA generativa: prompt de sistema, histórico da conversa e ferramentas que o modelo chama para agir no sistema.

## O que a IA faz aqui

O modelo não grava nada sozinho. Cada mensagem passa por três peças:

1. **Prompt de sistema.** Define o papel do assistente: coletar os dados com educação, a ordem das perguntas e as regras de nome, CPF, endereço e encerramento. O prompt também recebe os campos que já estão salvos, para o modelo não pedir de novo.
2. **Histórico.** As mensagens da conversa aberta vão junto no pedido, na ordem em que aconteceram. Assim a resposta seguinte continua o assunto em vez de começar do zero.
3. **Ferramentas (tools).** Funções PHP que o modelo pode chamar quando a pessoa informa um dado. Validar o CPF, consultar o ViaCEP e gravar o telefone são tools. O modelo decide quando chamar. O PHP é quem valida e persiste.

Uma resposta pode usar até três passos. Isso cabe, no mesmo turno, gravar um campo, encerrar a conversa e escrever a resposta.

O modelo usado por padrão é o `gpt-4o-mini`, via [Prism](https://github.com/prism-php/prism) e a API da OpenAI. O histórico, o prompt e as tools são montados pela aplicação. O cliente do modelo só recebe esses três arrays prontos.

## O que é coletado

| Dado | Obrigatório para encerrar |
| --- | --- |
| Nome e sobrenome | Sim |
| CPF | Sim |
| Telefone com DDD | Sim |
| CEP, rua, número, bairro, cidade, estado e país | Sim |
| Complemento | Não |
| E-mail | Não |

O endereço começa pelo CEP. O [ViaCEP](https://viacep.com.br) preenche rua, bairro, cidade, estado e país. Número e complemento ficam com a pessoa. Se ela não quiser informar o complemento ou o e-mail, a conversa segue.

Se a pessoa encerrar sem os dados obrigatórios, o contato é excluído com soft delete e a conversa fica fechada. Com tudo obrigatório preenchido, o contato permanece e a conversa também fecha. A próxima mensagem abre uma conversa nova.

## Páginas

| Endereço | O que mostra |
| --- | --- |
| `/` e `/login` | Entrar |
| `/register` | Criar conta |
| `/chat` | Conversa |

O navegador guarda o token do Passport em `localStorage`. Ao abrir o chat, a página carrega as conversas da conta. Uma conversa fechada aparece com a linha **Conversa encerrada**.

## API

Todas as rotas ficam em `/api`. As protegidas usam `Authorization: Bearer {token}`.

| Método | Rota | Auth | O que faz |
| --- | --- | --- | --- |
| `POST` | `/api/register` | Não | Cria a conta e devolve o token |
| `POST` | `/api/login` | Não | Entra e devolve o token |
| `GET` | `/api/user` | Sim | Devolve o usuário autenticado |
| `GET` | `/api/chats` | Sim | Lista as conversas e as mensagens da conta |
| `POST` | `/api/chat` | Sim | Envia uma mensagem e devolve a resposta. O JSON traz `message` e `status` (`open` ou `closed`) |

O cadastro pede nome, e-mail e senha. A senha tem no mínimo 8 caracteres e precisa da confirmação. Login e cadastro aceitam 5 tentativas por minuto.

## Ferramentas do modelo

| Tool | Quando entra |
| --- | --- |
| `saveName` | Nome e sobrenome |
| `saveCpf` | CPF, com ou sem pontos e traços |
| `lookupZipcode` | CEP. Consulta o ViaCEP e grava o que a consulta devolver, menos número e complemento |
| `saveAddress` | Número, complemento e o que o CEP não trouxe |
| `saveEmail` | E-mail, ou a recusa de informar |
| `savePhone` | Telefone com DDD, 10 ou 11 dígitos |
| `closeChat` | Encerra a conversa |

## Requisitos

- PHP 8.3 ou superior
- Composer
- Uma chave da [OpenAI](https://platform.openai.com/api-keys)
- SQLite, ou MySQL se preferir

Node não é necessário para as páginas. O HTML, o CSS e o JavaScript ficam em `resources/views` e `public`.

## Configuração

```bash
composer install
cp .env.example .env
php artisan key:generate
```

No `.env`, preencha a chave e, se quiser, o modelo:

```dotenv
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

`OPENAI_MODEL` já vem como `gpt-4o-mini`. `OPENAI_ORGANIZATION`, `OPENAI_PROJECT` e `OPENAI_URL` só entram se a conta da OpenAI exigir. Estão comentadas no `.env.example`.

O exemplo usa SQLite, que não pede servidor. Para MySQL, troque o bloco `DB_*` no `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat
DB_USERNAME=root
DB_PASSWORD=
```

Crie o banco antes de migrar. No SQLite, o arquivo é criado na migração se `DB_DATABASE` apontar para `database/database.sqlite` e o arquivo existir. O `.env.example` usa `DB_CONNECTION=sqlite` sem caminho, que no Laravel aponta para `database/database.sqlite`. Crie o arquivo se ele não existir:

```bash
touch database/database.sqlite
```

Depois prepare o banco e o Passport. O login emite personal access token, então o cliente pessoal precisa existir.

```bash
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal --name="Chat IA" --provider=users
php artisan serve
```

Abra `http://localhost:8000`, crie uma conta e comece a conversa.

As chaves do Passport ficam em `storage/oauth-private.key` e `storage/oauth-public.key`. Elas não vão para o Git. Se preferir variável de ambiente, o Passport também lê `PASSPORT_PRIVATE_KEY` e `PASSPORT_PUBLIC_KEY`.

## Testes

Os testes de ponta usam SQLite em memória e não chamam a OpenAI. A resposta do modelo é substituída por um fake do Prism.

```bash
php artisan test
```

## Estrutura

```text
app/Actions/Auth          Login e cadastro
app/Actions/Chat          Envio da mensagem
app/Prompts               Prompt de sistema da coleta
app/Services              Histórico, tools e chamada ao Prism
app/Tools                 Funções que o modelo pode chamar
resources/views           Login, cadastro e chat
public/css e public/js    Estilo e comportamento das páginas
```

`SendMessage` abre ou reaproveita a conversa aberta da pessoa, grava a mensagem, pede o texto ao modelo e grava a resposta. `AIAgentService` monta o histórico e a lista de tools. `PrismService` só envia o que já veio pronto.
