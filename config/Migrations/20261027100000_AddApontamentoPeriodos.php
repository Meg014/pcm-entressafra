<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class AddApontamentoPeriodos extends BaseMigration
{
    public function up(): void
    {
        $table=$this->table('apontamentos');
        foreach (['inicio_manha','fim_manha','inicio_tarde','fim_tarde'] as $field) {
            $table->addColumn($field,'datetime',['null'=>true,'default'=>null]);
        }
        $table->update();
        // NULL in all four columns identifies an unchanged historical entry.
        if ($this->getAdapter()->getAdapterType() !== 'pgsql') { return; }
        $this->execute('ALTER TABLE apontamentos DROP CONSTRAINT ck_apontamento_intervalo, DROP CONSTRAINT ex_apontamento_funcionario_horario');
        $sql=<<<'SQL'
ALTER TABLE apontamentos ADD CONSTRAINT ck_apontamento_intervalo CHECK (
  fim > inicio AND minutos > 0 AND (
    (inicio_manha IS NULL AND fim_manha IS NULL AND inicio_tarde IS NULL AND fim_tarde IS NULL
      AND minutos * 60 = EXTRACT(EPOCH FROM (fim-inicio))) OR
    (
      ((inicio_manha IS NULL AND fim_manha IS NULL) OR
       (inicio_manha IS NOT NULL AND fim_manha IS NOT NULL AND fim_manha > inicio_manha))
      AND ((inicio_tarde IS NULL AND fim_tarde IS NULL) OR
       (inicio_tarde IS NOT NULL AND fim_tarde IS NOT NULL AND fim_tarde > inicio_tarde))
      AND (inicio_manha IS NOT NULL OR inicio_tarde IS NOT NULL)
      AND (fim_manha IS NULL OR inicio_tarde IS NULL OR fim_manha <= inicio_tarde)
      AND inicio = COALESCE(inicio_manha,inicio_tarde)
      AND fim = COALESCE(fim_tarde,fim_manha)
      AND (inicio AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::date =
          (fim AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo')::date
      AND minutos * 60 = COALESCE(EXTRACT(EPOCH FROM (fim_manha-inicio_manha)),0)
                           + COALESCE(EXTRACT(EPOCH FROM (fim_tarde-inicio_tarde)),0)
    )
  )
);
ALTER TABLE apontamentos ADD CONSTRAINT ex_apontamento_funcionario_horario EXCLUDE USING gist (
 funcionario_chave WITH =,
 (CASE WHEN inicio_manha IS NULL AND inicio_tarde IS NULL THEN tsmultirange(tsrange(inicio,fim,'[)'))
       ELSE tsmultirange(CASE WHEN inicio_manha IS NULL THEN 'empty'::tsrange ELSE tsrange(inicio_manha,fim_manha,'[)') END,
                        CASE WHEN inicio_tarde IS NULL THEN 'empty'::tsrange ELSE tsrange(inicio_tarde,fim_tarde,'[)') END) END) WITH &&
) WHERE (NOT cancelado)
SQL;
        foreach (explode(';',$sql) as $statement) { if (trim($statement)!=='') { $this->execute($statement); } }
    }

    public function down(): void
    {
        throw new \RuntimeException('Rollback bloqueado para preservar os períodos e o histórico.');
    }
}
