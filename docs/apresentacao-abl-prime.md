# Roteiro de apresentação — Sistema de Gestão de Prestadores (ABL PRIME)

Duração: cerca de 25 a 30 min, com perguntas ao longo. Tudo com **um único login**, o do Administrador. Ritmo: mostrar a tela, pedir um palpite ao público e fazer a ação ao vivo.

## Antes de começar

- Dados de demonstração: `php artisan db:seed --class=DemoSeeder` (só roda em banco sem pessoas).
- Login: `ronaldo@rmsti.com.br` / `mudar@123`. Deixe na tela inicial, com o Dashboard aberto.
- Tenha à mão um PDF qualquer (para anexar um documento) e um CNPJ real (para a consulta automática).
- Os e-mails de alerta saem no log (`storage/logs`), não numa caixa de entrada. Tudo é fictício.

## O que NÃO prometer hoje

- **UAU:** a leitura (empresas, obras, pessoas) está pronta, mas só roda com a URL e o token de homologação (P1). A gravação de pessoas no UAU é a próxima etapa.
- **Contratos, benefícios, férias, fechamento, notas fiscais e relatórios** são as Entregas 3 a 6 e ainda não existem.
- As empresas e obras da demonstração foram digitadas, não importadas do UAU.

## Roteiro

### 1. Abertura (2 min)
Pergunte: *"Hoje, como vocês sabem quem presta serviço, por qual CNPJ, com qual cargo e quanto ganha?"* Mostre o **Dashboard**: 10 colaboradores ativos, remuneração mensal vigente (R$ 73.896,32), 5 documentos vencidos e 6 colaboradores com pendência de qualificação, além da lista de documentos que pedem atenção e dos colaboradores por cargo. Diga que cada perfil vê só os blocos a que tem direito. O sistema responde a isso em um só lugar, com histórico e permissões.

### 2. Pessoas, PJ e consulta de CNPJ (5 min)
Menu **Pessoas > Pessoas jurídicas**.
- Abra **Moreira Engenharia Consultiva**: toda PJ tem uma **pessoa física responsável** (Rafael Moreira Lima).
- Interação: peça um CNPJ real à plateia. Em **Novo**, digite o CNPJ e use a **consulta automática**: razão social e endereço vêm da Receita, sem digitar.
- Mostre os **dados bancários** (conta principal, PIX) e diga que conta e chave nunca aparecem na auditoria.

### 3. Colaborador completo (5 min)
Menu **Colaboradores**.
- Mostre os status: 10 ativos, 2 em **pré-admissão** (Larissa Fonseca, Eduardo Rocha) e 1 **desligado** (Sérgio Camargo).
- Abra **José Aparecido Ferreira**: PF, PJ, cargo, empresa, obra, equipe, **histórico de remuneração** e documentos, tudo numa tela.
- Mostre a regra: uma pessoa não pode ter dois vínculos abertos.
- Equipes (menu **Equipes**, 1 min): cada equipe tem gestor e vigência. Diga que o gestor, ao entrar, só enxerga as pessoas da própria equipe.

### 4. Remuneração e reajustes (6 min)
No colaborador **José Aparecido**: dois reajustes (4% e 6%). Nada é sobrescrito, cada valor tem início e fim de vigência.
- Faça um **reajuste individual** ao vivo e mostre o aviso quando o valor sai da faixa do cargo (menu **Cargos e salários > Cargos**).
- **Reajuste em lote**: escolha um cargo, informe o percentual e mostre a **prévia** antes de aplicar.
- Frase-chave: *"reajuste retroativo em competência fechada o sistema não deixa"*.

### 5. Documentos e alertas (5 min)
- **Thiago Barros** está com o ASO **vencido**; **Fernanda Albuquerque** tem ASO que **vence em 12 dias**.
- Anexe um documento ao vivo e mostre que a validade é obrigatória nos tipos que exigem. O arquivo só baixa por rota autorizada.
- Rode `php artisan alertas:documentos` e mostre no log o resumo (hoje: 5 vencidos, 7 a vencer). Em produção, ele sai todo dia às 06:30 para quem tiver a permissão.
- A lista de tipos é editável em **Configurações > Tipos de documento**.

### 6. Qualificações e matriz por cargo (5 min)
Menu **Qualificações**.
- **Matriz por cargo**: grade com "—", "Opcional" ou "Obrigatória". Mude uma célula ao vivo (ex.: NR-10 obrigatória para Encarregado).
- **Pendências**: 6 colaboradores com obrigatória ausente ou vencida; filtre por cargo.
- Interação: *"Quais qualificações cada cargo de vocês exige?"* Anote: alimenta a matriz real (P10).

### 7. Segurança, auditoria e próximos passos (3 min)
- **Configurações > Perfis**: permissões por perfil (RH, Gestor, Financeiro), editáveis sem programador.
- **Auditoria**: quem fez o quê e quando. Os reajustes e o documento que acabaram de ser feitos já aparecem lá.
- **Integrações**: tela de sincronização com o UAU. Feche com o cronograma: Entrega 2 quase completa, Entregas 3 a 6 a seguir.

## Perguntas para a ABL PRIME (levar anotadas)

1. Quais são os **tipos de documento** e quais têm validade? (P9)
2. Quais **qualificações cada cargo** exige? (P10)
3. Uma PJ pode ter **mais de um responsável** ao mesmo tempo? (P8)
4. O gestor deve ver a **remuneração** da própria equipe?
5. Os perfis (RH, Gestor, Financeiro) estão corretos? (P7)
6. Quando teremos URL, usuário e token do **UAU de homologação**? (P1)

## Se algo der errado

- **Tela em branco ou erro:** `docker exec abl-prestadores-app-1 php artisan optimize:clear`.
- **Consulta de CNPJ sem internet:** o cadastro manual continua; diga que a consulta é opcional.
- **Refazer do zero:** `php artisan migrate:fresh --seed` e depois `php artisan db:seed --class=DemoSeeder` (apaga todo o banco local).
