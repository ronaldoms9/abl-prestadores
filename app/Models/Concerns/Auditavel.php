<?php

namespace App\Models\Concerns;

use App\Support\Auditoria;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Registra criação, alteração e exclusão do model na auditoria, com ações do
 * vocabulário de App\Support\Auditoria (ex.: usuario-criado, perfil-alterado).
 *
 * O model informa o prefixo da ação e os campos auditados. Só os campos que
 * mudaram são gravados, e salvar sem mudança não gera registro.
 */
trait Auditavel
{
    use LogsActivity;

    /** Prefixo das ações: "usuario" gera usuario-criado/alterado/excluido. */
    abstract protected function prefixoAuditoria(): string;

    /** @return array<int, string> Campos gravados (nunca senhas). */
    abstract protected function camposAuditados(): array;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(Auditoria::LOG)
            ->logOnly($this->camposAuditados())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $sufixo = ['created' => 'criado', 'updated' => 'alterado', 'deleted' => 'excluido'][$eventName] ?? null;

        // Registros manuais com este model como objeto já chegam com a ação certa.
        if ($sufixo === null) {
            return;
        }

        $activity->event = $activity->description = $this->prefixoAuditoria().'-'.$sufixo;
        $activity->properties = $activity->properties->put('ip', request()?->ip());
    }
}
