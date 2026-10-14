# Validações dos dados

## Validação do CPF

O CPF aceita pontos e traços. A validação tira tudo que não é dígito e exige 11 números. Sequências com o mesmo dígito onze vezes, como 111.111.111-11, são recusadas.

Os dois últimos números são dígitos verificadores. Para cada um, soma-se cada dígito anterior multiplicado por um peso que começa no tamanho do bloco mais um e cai de um em um. O dígito esperado é o resto de (10 vezes essa soma) dividido por 11, e depois o resto dessa conta dividido por 10. Se um dos dois não bater, o CPF é inválido e nada é gravado. O valor salvo fica só com os 11 dígitos, na coluna identity_number.

## Busca do CEP

O CEP precisa ter 8 dígitos, com ou sem hífen. A busca chama o ViaCEP em https://viacep.com.br/ws/{cep}/json/, com 3 segundos para conectar, 5 segundos de resposta e até 2 novas tentativas em falha de rede, erro 5xx ou 429.

A resposta preenche rua (logradouro), bairro, cidade (localidade), estado (uf) e país Brasil. Número e complemento não vêm dessa busca. O número é pedido em voz alta junto com o complemento. O complemento é opcional, mas precisa ser nomeado para a pessoa saber que pode informar, como um apartamento, ou seguir sem ele. CEP inexistente, com o campo erro, não grava endereço. O que a consulta não trouxe é o único pedaço perguntado depois.

## Nome, telefone e e-mail

Nome e sobrenome precisam ter pelo menos 2 letras, no máximo 80, e só letras, espaço, hífen ou apóstrofo. Nome composto conhecido, como João Paulo ou Maria Eduarda, fica no nome. Sobrenomes, inclusive mais de um, ficam no sobrenome, com as partículas de, da, do, das e dos.

O telefone perde tudo que não é dígito e precisa ter 10 ou 11 números, já com DDD. Fora disso, não grava.

O e-mail é opcional, no máximo 255 caracteres, e precisa parecer um e-mail. Se a pessoa recusar, o campo fica vazio e não é pedido de novo.
