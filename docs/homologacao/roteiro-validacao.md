# Roteiro de validação da Entrega 1 — Gestão de Prestadores

Este roteiro serve para a equipe da ABL PRIME conferir a Entrega 1 no ambiente de
homologação, antes de aprová-la. São 6 critérios, os mesmos combinados no plano da
entrega. Cada um traz os passos na tela, o resultado esperado e um espaço para anotar.

**Endereço:** `https://<dominio-de-homologacao>`. Todas as telas mostram uma faixa amarela
"Ambiente de homologação". Os dados desse ambiente servem para testar e podem ser apagados.

**Usuários:** cada pessoa recebeu um login e uma senha temporária. Troque a senha no
primeiro acesso (menu do seu nome › Meu perfil).

Todos veem o "Dashboard". Além dele:

| Perfil | O que deve ver no menu |
| --- | --- |
| Administrador | Tudo, inclusive Configurações (usuários, perfis, auditoria) |
| RH | Pessoas (Pessoas físicas, Pessoas jurídicas, Carregar planilha), Empresas, Obras |
| Gestor | Pessoas (Pessoas físicas, Pessoas jurídicas), Empresas, Obras |
| Financeiro | Pessoas (Pessoas físicas, Pessoas jurídicas), Empresas, Obras, Integrações |
| Colaborador | Nada além do Dashboard (o portal do colaborador chega na Entrega 5) |

---

## Critério 1 — Cada pessoa vê só o que o seu perfil permite

**Quem faz:** um usuário de cada perfil e o Administrador.

1. Entre com cada usuário e compare o menu com a tabela acima.
2. Com o Administrador, em Configurações › Perfis, crie o perfil "Auditor de teste" e
   marque só "Ver monitoramento das integrações".
3. Em Configurações › Usuários, crie um usuário com esse perfil e entre com ele.

**Esperado:**
- cada menu bate com a tabela;
- o usuário do perfil novo vê só "Integrações", sem ninguém mexer no sistema;
- quem não tem a permissão e digita o endereço de uma tela direto recebe "acesso negado".

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 2 — Empresas e obras ativas batem com o UAU

**Quem faz:** quem conhece o cadastro do UAU (Financeiro ou Controladoria).

1. Escolha no UAU de homologação 5 empresas e 10 obras ativas, de empresas diferentes.
2. No sistema, em Empresas, procure cada empresa pela razão social ou pelo CNPJ (com ou sem pontos).
   Confira o código, o CNPJ e o número de obras ativas. Abra "Detalhes" e confira as obras.
3. Em Obras, filtre pela empresa e procure cada obra pelo código ou pela descrição.
   Confira a descrição e as datas.

**Esperado:** tudo igual ao UAU. Uma empresa ou obra inativa no UAU aparece com o filtro
"Inativos", e não com "Ativos".

**Observação:** a coluna "Status UAU" mostra o código do status (ex.: "Código 0") até a
ABL PRIME informar o significado de cada código. Depois disso, passa a mostrar o nome.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 3 — Carga da planilha dos prestadores

**Quem faz:** RH.

1. Em Pessoas › Carregar planilha, clique em "Baixar modelo".
2. Preencha com os CPFs e CNPJs dos prestadores atuais: uma linha por prestador, com o
   CPF, o CNPJ ou os dois. Pode usar pontos e traços ou não.
3. Carregue o arquivo. A tela de resultado mostra o andamento e se atualiza sozinha.
4. Quando terminar, confira:
   - a contagem por resultado;
   - a lista "Só com problema".
5. Carregue **o mesmo arquivo** de novo.

**Esperado:**
- cada linha tem um resultado. "Importada" ou "Já existia" quando o UAU tem a pessoa;
  "Não encontrada" quando não tem; "Inativa" quando a pessoa está inativa no UAU;
  "Inválida" quando o CPF/CNPJ tem dígito errado (essa linha nem é consultada);
- linhas com CPF e CNPJ das duas pessoas criam um "vínculo sugerido", que aparece no
  detalhe da pessoa física e da jurídica;
- na segunda carga, tudo vira "Já existia" e nada é duplicado.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 4 — Uma pessoa alterada no UAU aparece alterada no sistema

**Quem faz:** RH (no UAU) e Financeiro (no sistema).

1. No UAU de homologação, altere o e-mail ou o telefone de uma pessoa que já está no sistema.
2. No sistema, com o Financeiro, em Integrações, clique em "Sincronizar agora".
3. Acompanhe o quadro "Sincronizações" até "Concluída". As consultas de pessoas continuam
   por alguns minutos depois disso e aparecem na lista abaixo do quadro.
4. Abra a pessoa em Pessoas físicas (ou jurídicas) › Detalhes.

**Esperado:**
- o dado alterado aparece no detalhe;
- sem clicar no botão, a alteração aparece no dia seguinte, depois da sincronização automática das 05:00;
- clicar duas vezes seguidas em "Sincronizar agora" mostra o aviso de que já há uma em andamento.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 5 — Uma falha aparece no monitoramento e se resolve sozinha

**Quem faz:** a equipe técnica simula a falha; o Financeiro acompanha.

1. A equipe técnica deixa o UAU inacessível para o sistema por alguns minutos (por exemplo,
   com um endereço errado na configuração) e dispara uma sincronização.
2. O Financeiro olha o sino no topo da tela e a tela Integrações com o filtro "Erro sem solução".
3. A equipe técnica desfaz a falha.
4. Espere a próxima tentativa automática (1 a 5 minutos) ou use "Reprocessar" na linha da falha.

**Esperado:**
- durante a falha, o sino mostra o número de falhas e a lista mostra cada uma, com o detalhe do erro;
- depois da nova tentativa, a falha passa a "Erro resolvido", sai do sino, e os dados ficam corretos.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 6 — Recuperação de senha por e-mail

**Quem faz:** qualquer usuário.

1. Na tela de login, clique em "Esqueci minha senha" e informe o seu e-mail.
2. Abra o e-mail recebido e use o link para criar uma senha nova (pelo menos 8 caracteres, com letras e números).
3. Entre com a senha nova.

**Esperado:**
- o e-mail chega em poucos minutos, na caixa de entrada (não no spam);
- a senha nova funciona;
- uma senha só com números, como 12345678, é recusada.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

---

## O que não faz parte desta entrega

Para não ser anotado como defeito:

- **Nada é gravado no UAU.** Nesta entrega o sistema só lê do UAU. A gravação chega na Entrega 5.
- **Sem aviso instantâneo do UAU.** O UAU da ABL PRIME não avisa o sistema quando algo muda
  (não oferece webhook). Por isso há a sincronização diária às 05:00 e o botão "Sincronizar agora".
- **A obra faz o papel de centro de custo.**
- **A busca de uma pessoa nova direto no UAU, pelo CPF/CNPJ, chega na Entrega 2.** Nesta entrega,
  as pessoas entram pela planilha.
- **Os cadastros não se editam.** As telas de pessoas, empresas e obras são só de consulta;
  a edição de pessoas, o regime tributário e os documentos chegam na Entrega 2.
- **Vínculo sugerido é só sugestão.** Aceitar ou descartar um vínculo chega na Entrega 2.

## Aprovação

| | Nome | Data | Assinatura |
| --- | --- | --- | --- |
| ABL PRIME | | | |
| Desenvolvimento | | | |
