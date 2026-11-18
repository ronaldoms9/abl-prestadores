# Guia de operação (administrador e suporte)

Rotinas de quem mantém o sistema. Os comandos rodam na raiz do projeto, no servidor.

## 1. Rotinas automáticas

O agendador (`php artisan schedule:run` a cada minuto, via cron) dispara, em ordem de horário:

| Hora | Comando | O que faz |
| --- | --- | --- |
| 05:00 | `uau:sincronizar` | importa empresas, obras e pessoas do UAU |
| 05:30 | `ferias:periodos` | abre o próximo período aquisitivo de quem completou 12 meses |
| 06:30 a 07:15 | `alertas:documentos`, `alertas:qualificacoes`, `alertas:contratos`, `alertas:ferias` | resumos por e-mail |
| 07:20 | `ferias:pendentes` | lembra cada aprovador das férias que esperam a decisão dele |
| 08:00 | `alertas:notas` | lembra quem passou do prazo de enviar a nota |

Cada alerta vai para quem tem a permissão do alerta (`alerta-*`). Se ninguém tem, o Dashboard do administrador mostra um aviso amarelo: dê a permissão a um perfil em **Configurações › Perfis**.

## 2. Fila e Horizon

Jobs (cálculo de competência, PDFs, ZIPs, exportações, chamadas ao UAU) rodam na fila Redis, supervisionada pelo Horizon (`/horizon`, permissão `horizon-acessar`). Fila parada = fechamento "aberto" sem calcular, PDF "pendente" e e-mails que não saem. Verifique o Supervisor (`supervisorctl status`) e reinicie o Horizon com `php artisan horizon:terminate` depois de cada deploy.

## 3. Reprocessar uma integração

**Integrações** lista as chamadas ao UAU. Uma falha vira "erro resolvido" sozinha quando a nova tentativa dá certo. Para forçar, use **Reprocessar** na linha da falha; **Sincronizar agora** roda a importação na hora (não duplica se já houver uma em andamento).

## 4. Reabrir uma competência

Só o administrador (`fechamento-reabrir`). Em **Fechamento mensal**, abra a competência aprovada e clique em **Reabrir**, informando a justificativa. Só é possível enquanto todos os fechamentos estiverem "aguardando nota". A reabertura volta a competência para a conferência, limpa o snapshot e fica na auditoria. Depois de corrigir, o financeiro aprova de novo.

## 5. Parâmetros sem tela

Valores que a ABL PRIME ainda pode ajustar ficam na tabela `configuracoes` (chave, valor JSON). Padrões entre parênteses:

| Chave | Efeito |
| --- | --- |
| `calculo.base_proporcional` | `corridos` ou `dias_uteis` (corridos) |
| `calculo.beneficio_fixo_proporcional` | benefício fixo proporcional na admissão e no desligamento (sim) |
| `calculo.sabado_trabalhado` | sábado conta como dia trabalhado (não) |
| `ferias.dias_direito`, `ferias.limite_gozo_meses`, `ferias.fracionamento_minimo_dias` | regras de férias (30, 12, 5) |
| `alertas.contratos`, `alertas.ferias` | antecedência dos alertas (marcos 30/15/5; 60 dias) |
| `notas.tolerancia_valor`, `notas.prazo_dia` | tolerância (R$ 0,00) e dia limite de envio (5) |
| `contratos.numeracao` | prefixo e reinício anual do número do contrato |

Alterar pelo `tinker`: `App\Models\Configuracao::definir('notas.prazo_dia', 10);`. A mudança vale na próxima execução; competências já aprovadas não mudam.

## 6. Arquivos e backups

- Contratos, notas, ZIPs e exportações ficam no disco privado (`storage/app`): `contratos/`, `notas/{ano_mes}/{colaborador}/`, `notas-zip/`, `relatorios/`. ZIPs e exportações com mais de dois dias são apagados sozinhos.
- O backup diário deve cobrir o **banco de dados e o `storage/app`**. Teste a restauração em um ambiente separado pelo menos uma vez antes da virada.

## 7. Auditoria

**Configurações › Auditoria** registra quem fez o quê, quando e de onde: login, alteração de cadastros, geração de contrato, solicitação e decisão de férias, abertura, recálculo, aprovação e reabertura de competência, envio e decisão de nota. Use o filtro por ação, usuário e período.

## 8. Usuários e perfis

- **Configurações › Usuários:** crie o usuário, escolha o perfil e, para quem usa o portal, **ligue-o à pessoa física** (um login por pessoa). Usuário inativo não entra e é desconectado na próxima requisição.
- **Configurações › Perfis:** marque as permissões de cada perfil. O Gestor nunca deve ter remuneração, contrato, fechamento ou nota; só o Financeiro aprova fechamento e nota; só o Administrador reabre competência.

## 9. Quando algo dá errado

| Sintoma | O que verificar |
| --- | --- |
| Competência "aberta" que não termina | fila parada (Horizon); `php artisan queue:failed` |
| "PDF pendente" | biblioteca dompdf instalada? use "gerar de novo" na ficha |
| E-mails não saem | `MAIL_*` no `.env`, fila, caixa de spam |
| UAU com erro | **Integrações**; credenciais e URL do UAU no `.env` |
| Alguém não recebe alerta | permissão `alerta-*` no perfil e usuário ativo |
