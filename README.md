# Chat IA

A chat that collects contact details by talking to a language model. The person writes in plain sentences. The model asks for whatever is still missing, looks up the postal code, and stores the name, CPF, address, email, and phone number.

The project uses the building blocks of a generative-AI application: a system prompt, the conversation history, and tools the model calls when it needs to act on the system.

## What the AI does here

The model does not write to the database by itself. Every message goes through three pieces:

1. **System prompt.** Sets the assistant's role: collect the data politely, follow the question order, and apply the rules for names, CPF, address, and closing. The prompt also receives the fields already saved, so the model does not ask for them again.
2. **History.** Messages from the open conversation are sent with the request, in the order they happened. The next reply continues the thread instead of starting over.
3. **Tools.** PHP functions the model can call when the person provides a value. Validating a CPF, querying ViaCEP, and storing a phone number are tools. The model decides when to call one. PHP validates and persists.

One reply can take up to three steps. That is enough, in a single turn, to save a field, close the conversation, and write the answer.

The default model is `gpt-4o-mini`, through [Prism](https://github.com/prism-php/prism) and the OpenAI API. The application builds the history, the prompt, and the tools. The model client only receives those three ready-made arrays.

## What is collected

| Field | Required to close |
| --- | --- |
| First name and last name | Yes |
| CPF | Yes |
| Phone number with area code | Yes |
| Postal code, street, number, neighborhood, city, state, and country | Yes |
| Address line 2 (complement) | No |
| Email | No |

The address starts with the postal code. [ViaCEP](https://viacep.com.br) fills in the street, neighborhood, city, state, and country. The building number and the complement stay with the person. If they decline the complement or the email, the conversation continues.

If the person closes before the required fields are filled, the contact is soft-deleted and the conversation is closed. When every required field is present, the contact is kept and the conversation is closed. The next message starts a new conversation.

## Pages

| URL | What it shows |
| --- | --- |
| `/` and `/login` | Sign in |
| `/register` | Create an account |
| `/chat` | The conversation |

The browser stores the Passport token in `localStorage`. Opening the chat loads that account's conversations. A closed conversation shows the line **Conversa encerrada**.

## API

Every route lives under `/api`. Protected routes use `Authorization: Bearer {token}`.

| Method | Route | Auth | What it does |
| --- | --- | --- | --- |
| `POST` | `/api/register` | No | Creates the account and returns a token |
| `POST` | `/api/login` | No | Signs in and returns a token |
| `GET` | `/api/user` | Yes | Returns the authenticated user |
| `GET` | `/api/chats` | Yes | Lists the account's conversations and messages |
| `POST` | `/api/chat` | Yes | Sends a message and returns the reply. The JSON includes `message` and `status` (`open` or `closed`) |

Registration asks for a name, an email, and a password. The password must be at least 8 characters and must be confirmed. Sign-in and registration allow 5 attempts per minute.

## Model tools

| Tool | When it runs |
| --- | --- |
| `saveName` | First name and last name |
| `saveCpf` | CPF, with or without dots and dashes |
| `lookupZipcode` | Postal code. Queries ViaCEP and stores everything the lookup returns, except the building number and the complement |
| `saveAddress` | Building number, complement, and anything the postal code did not return |
| `saveEmail` | Email, or a refusal to provide one |
| `savePhone` | Phone number with area code, 10 or 11 digits |
| `closeChat` | Closes the conversation |

## Requirements

- PHP 8.3 or newer
- Composer
- An [OpenAI](https://platform.openai.com/api-keys) API key
- SQLite, or MySQL if you prefer

Node is not required for the pages. The HTML, CSS, and JavaScript live in `resources/views` and `public`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

In `.env`, set the key and, if you want, the model:

```dotenv
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

`OPENAI_MODEL` already defaults to `gpt-4o-mini`. Set `OPENAI_ORGANIZATION`, `OPENAI_PROJECT`, and `OPENAI_URL` only if the OpenAI account requires them. They are commented out in `.env.example`.

The example uses SQLite, which needs no database server. `DB_CONNECTION=sqlite` with no path points Laravel at `database/database.sqlite`. Create that file if it does not exist:

```bash
touch database/database.sqlite
```

For MySQL, replace the `DB_*` block in `.env` and create the database before migrating:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat
DB_USERNAME=root
DB_PASSWORD=
```

Then prepare the database and Passport. Sign-in issues a personal access token, so the personal access client has to exist.

```bash
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal --name="Chat IA" --provider=users
php artisan serve
```

Open `http://localhost:8000`, create an account, and start the conversation.

Passport keys are written to `storage/oauth-private.key` and `storage/oauth-public.key`. They are not committed. If you prefer environment variables, Passport also reads `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`.

## Tests

The endpoint tests use SQLite in memory and do not call OpenAI. The model reply is replaced with a Prism fake.

```bash
php artisan test
```

## Layout

```text
app/Actions/Auth          Sign-in and registration
app/Actions/Chat          Sending a message
app/Prompts               System prompt for the collection
app/Services              History, tools, and the Prism call
app/Tools                 Functions the model can call
resources/views           Sign-in, registration, and chat
public/css and public/js  Page style and behavior
```

`SendMessage` opens or reuses the person's open conversation, stores the message, asks the model for the text, and stores the reply. `AIAgentService` builds the history and the tool list. `PrismService` only sends what it was already given.
