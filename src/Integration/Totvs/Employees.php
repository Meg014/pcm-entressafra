<?php
declare(strict_types=1);
namespace App\Integration\Totvs;
use Cake\Datasource\ConnectionManager;

final class Employees
{
    public const UNAVAILABLE = 'Não foi possível consultar os funcionários. Verifique conexão e SELECT em RA_MAT, RA_NOME, RA_CODFUNC e RA_SITFOLH.';
    public const AMBIGUOUS = 'Esta matrícula possui mais de um registro no TOTVS. Solicite a conferência do PCM.';
    public function __construct(private ?\Closure $reader = null) {}
    private function read(string $sql, array $parameters): array
    {
        try {
            return $this->reader ? ($this->reader)($sql, $parameters) : ConnectionManager::get('protheus')->execute($sql, $parameters)->fetchAll('assoc');
        } catch (\Throwable) { throw new \DomainException(self::UNAVAILABLE); }
    }
    public static function key(string $number): string
    {
        if ($number === '' || trim($number) !== $number || strlen($number)>6 || preg_match('/[\x00-\x1F\x7F]/',$number)) { throw new \DomainException('Matrícula inválida. Selecione um funcionário da lista.'); }
        return $number;
    }
    public function duplicateGroups(): array
    {
        return $this->read(Queries::EMPLOYEE_DUPLICATES, []);
    }
    private function map(array $row, bool $unique): array
    {
        foreach (['RA_MAT','RA_NOME','RA_CODFUNC','RA_SITFOLH'] as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null) { throw new \DomainException('Cadastro do funcionário incompleto.'); }
        }
        $number = rtrim((string)$row['RA_MAT'], ' ');
        $name = rtrim((string)$row['RA_NOME'], ' '); $status = trim((string)$row['RA_SITFOLH']);
        if (trim($name) === '') { throw new \DomainException('Funcionário sem nome no cadastro.'); }
        $eligible=$unique && $status==='' && $number!=='';
        return ['chave'=>$eligible ? self::key($number) : '', 'nome'=>$name, 'matricula'=>$number, 'filial'=>'', 'filial_consultada'=>false, 'vinculo'=>$number, 'identificacao'=>'matricula_010', 'funcao'=>rtrim((string)$row['RA_CODFUNC'], ' '), 'situacao_codigo'=>$status, 'situacao'=>match($status) { ''=>'NORMAL', 'F'=>'FÉRIAS', default=>'Situação cadastral: ' . $status }, 'ativo'=>$eligible, 'bloqueio'=>$unique ? null : self::AMBIGUOUS, 'empresa'=>'010', 'origem_nome'=>'RA_NOME'];
    }
    public function search(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term, 'UTF-8') < 2 || mb_strlen($term, 'UTF-8') > 60) { throw new \DomainException('Digite de 2 a 60 caracteres para pesquisar.'); }
        $pattern = '%' . strtr($term, ['['=>'[[]', '%'=>'[%]', '_'=>'[_]']) . '%';
        $rows = $this->read(Queries::EMPLOYEE_SEARCH, ['nome'=>$pattern,'matricula'=>$pattern]);
        // Defense in depth: never expose situations outside the autocomplete policy.
        $rows = array_values(array_filter($rows, static fn(array $row): bool =>
            isset($row['RA_SITFOLH']) && in_array(trim((string)$row['RA_SITFOLH']), ['', 'F'], true)));
        return array_map(function(array $row): array {
            if (!isset($row['elegiveis']) || !preg_match('/^[0-9]+$/D',(string)$row['elegiveis'])) { throw new \DomainException('Não foi possível confirmar a unicidade da matrícula.'); }
            $person=$this->map($row,(int)$row['elegiveis']===1);
            if ((int)$row['elegiveis']<2) { $person['bloqueio']=trim((string)$row['RA_MAT'])==='' ? 'Matrícula ausente. Solicite a conferência do PCM.' : null; }
            return $person;
        }, array_slice($rows, 0, 21));
    }
    public function find(string $key): array
    {
        self::key($key);
        $rows = $this->read(Queries::EMPLOYEE_DETAIL, ['matricula'=>$key]);
        if (count($rows)>1) { throw new \DomainException(self::AMBIGUOUS); }
        if (count($rows)!==1) { throw new \DomainException('Funcionário inexistente ou não está em situação NORMAL. Selecione novamente.'); }
        $person = $this->map($rows[0],true);
        if ($person['chave'] !== $key) { throw new \DomainException('Vínculo do funcionário não confirmado.'); }
        return $person;
    }
}
