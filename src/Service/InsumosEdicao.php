<?php
declare(strict_types=1);
namespace App\Service;

final class InsumosEdicao
{
    public function edit(int $actor, int $id, array $input): void
    {
        $user = Access::actor($actor);
        Access::admin($user);
        $table = Access::table('Insumos');
        $record = $table->get($id);
        $activity = Access::table('Atividades')->get($record->atividade_id);
        Access::activity($user, $activity);
        PeriodoContexto::writable((int)$activity->entressafra_id);
        foreach (['material','quantidade','unidade','motivo','revisao_pcm'] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field])) throw new \DomainException('Formulário de edição incompleto.');
        }
        $material = trim($input['material']);
        if ($material === '' || mb_strlen($material) > 500) throw new \DomainException('Informe o material com até 500 caracteres.');
        $quantity = Insumos::quantity($input['quantidade']);
        $unit = Insumos::unit($input['unidade']);
        $reason = trim($input['motivo']);
        if ($reason === '' || mb_strlen($reason) > 3000) throw new \DomainException('Informe uma justificativa com até 3000 caracteres.');
        if (!ctype_digit($input['revisao_pcm'])) throw new \DomainException('Revisão inválida.');
        $revision = (int)$input['revisao_pcm'];
        $db = $table->getConnection();
        $db->transactional(function() use ($db,$table,$user,$activity,$id,$input,$material,$quantity,$unit,$reason,$revision): void {
            if ($db->getDriver() instanceof \Cake\Database\Driver\Postgres) {
                $db->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE', [(int)$activity->entressafra_id]);
                $db->execute('SELECT id FROM insumos WHERE id=? FOR UPDATE', [$id]);
            }
            PeriodoContexto::writable((int)$activity->entressafra_id);
            $row = $table->get($id);
            if ((int)$row->atividade_id !== (int)$activity->id) throw new \DomainException('O registro mudou de O.S. Atualize a tela.');
            if ($row->cancelado) throw new \DomainException('Insumos cancelados não podem ser editados.');
            if ((int)$row->revisao_pcm !== $revision) throw new \DomainException('Registro alterado por outra pessoa. Atualize a tela antes de editar novamente.');
            $confirmed = $row->controle_pcm === 'lancado';
            if ($confirmed && ($input['ciencia_totvs'] ?? '') !== '1') throw new \DomainException('Confirme a ciência do lançamento no TOTVS e a devolução para nova conferência PCM.');
            $before = $row->toArray();
            if ($row->material === $material && (string)$row->unidade === $unit && Insumos::quantity((string)$row->quantidade) === $quantity) return;
            $row->material = $material;
            $row->quantidade = $quantity;
            $row->unidade = $unit;
            $row->revisao_pcm = $revision + 1;
            $row->alterado_por = $user->id;
            $row->modified = gmdate('Y-m-d H:i:s');
            if ($confirmed) {
                $row->controle_pcm = 'pendente';
                $row->confirmado_por = null;
                $row->confirmado_em = null;
            }
            $table->saveOrFail($row);
            Access::audit($user, 'insumo_edicao', null, $before, $row->toArray(), $reason, $id);
            if ($confirmed) Access::audit($user, 'insumo_reabertura_totvs', null, $before, $row->toArray(), 'Ciência explícita: correção local; conferir novamente o lançamento manual no TOTVS. '.$reason, $id);
        });
    }
}
