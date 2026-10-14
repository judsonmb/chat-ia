# Fluxo da coleta

## Ordem sugerida e pulo

A ordem sugerida é nome e sobrenome, CPF, CEP, o que faltar do endereço, telefone e e-mail. Essa ordem só vale quando a pessoa não indica outro caminho.

Se a pessoa quiser pular o campo atual, disser que informa depois, ou já mandar outro dado, o agente segue o que ela pediu. O dado que ela acabou de dizer é gravado na hora com a ferramenta correspondente. O campo pulado não é cobrado de novo nesse turno.

Campos obrigatórios que ficaram para trás voltam uma única vez, antes de encerrar. E-mail e complemento são opcionais o tempo todo.

## Encerramento da conversa

Encerrar acontece quando a pessoa pede ou quando os dados obrigatórios estão completos. Antes de encerrar, se algum obrigatório foi pulado, o agente avisa uma vez o que falta e pergunta se ela quer informar agora ou encerrar mesmo assim.

Obrigatórios para manter o contato: nome, sobrenome, CPF, telefone com DDD, CEP, rua, número, bairro, cidade, estado e país. E-mail e complemento não entram nessa lista.

Se faltar algum obrigatório no encerramento, a conversa fecha e o contato é apagado com soft delete. Se estiver tudo preenchido, o contato permanece e a conversa também fecha. A mensagem seguinte abre uma conversa nova.
