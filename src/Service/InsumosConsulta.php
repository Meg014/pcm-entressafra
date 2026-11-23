<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Http\Exception\BadRequestException;

final class InsumosConsulta
{
    public static function filters(array $input): array
    {
        $filters = [];
        foreach (['data_inicial','data_final','os','material'] as $field) {
            $value = $input[$field] ?? '';
            if (!is_string($value)) throw new BadRequestException('Filtro inválido.');
            $filters[$field] = trim($value);
        }
        foreach (['data_inicial','data_final'] as $field) {
            if ($filters[$field] === '') continue;
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$field]);
            if (!$date || $date->format('Y-m-d') !== $filters[$field]) throw new BadRequestException('Data de filtro inválida.');
        }
        if ($filters['data_inicial'] !== '' && $filters['data_final'] !== '' && $filters['data_inicial'] > $filters['data_final']) throw new BadRequestException('A data inicial deve ser anterior ou igual à final.');
        if ($filters['os'] !== '' && !preg_match('/^[0-9]{1,60}$/D', $filters['os'])) throw new BadRequestException('Número da O.S. inválido.');
        if (mb_strlen($filters['material']) > 500) throw new BadRequestException('Material: máximo de 500 caracteres.');
        return $filters;
    }

    public static function query($user, array $filters)
    {
        $table = Access::table('Insumos');
        $query = Access::activityScope($table->find()->contain(['Atividades'=>['Entressafras','SetorExecutante','Cadernos']])->innerJoinWith('Atividades'), $user);
        if (Access::operational($user)) $query->where(['Insumos.criado_por'=>$user->id]);
        if ($filters['data_inicial'] !== '') $query->where(['Insumos.data >='=>$filters['data_inicial']]);
        if ($filters['data_final'] !== '') $query->where(['Insumos.data <='=>$filters['data_final']]);
        if ($filters['os'] !== '') $query->where(['Atividades.os_numero'=>$filters['os']]);
        if ($filters['material'] !== '') {
            // Portable accent folding, without requiring a PostgreSQL extension.
            $from = 'áàâãäéèêëíìîïóòôõöúùûüçñ';
            $to = 'aaaaaeeeeiiiiooooouuuucn';
            $folded = strtr(mb_strtolower($filters['material']), array_combine(mb_str_split($from), str_split($to)));
            $pattern = '%'.strtr($folded, ['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            if ($table->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres) {
                $expression = $query->expr("translate(lower(Insumos.material), '$from', '$to') LIKE :insumo_material ESCAPE '!'");
                $query->where($expression)->bind(':insumo_material', $pattern, 'string');
            } else {
                $query->where($query->expr("lower(Insumos.material) LIKE :insumo_material ESCAPE '!'"))
                    ->bind(':insumo_material', '%'.strtr(mb_strtolower($filters['material']), ['!'=>'!!','%'=>'!%','_'=>'!_']).'%', 'string');
            }
        }
        return $query->orderBy(['Insumos.data'=>'DESC', 'Insumos.id'=>'DESC']);
    }
}
