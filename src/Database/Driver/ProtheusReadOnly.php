<?php
declare(strict_types=1);
namespace App\Database\Driver;
use App\Integration\Totvs\Queries;
use Cake\Database\Driver\Sqlserver;
use Cake\Database\Query;
use Cake\Database\StatementInterface;

final class ProtheusReadOnly extends Sqlserver
{
    public function connect(): void
    {
        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (trim((string)($this->_config[$key] ?? '')) === '') {
                throw new \RuntimeException('Configure as credenciais próprias PROTHEUS_DB_* neste projeto.');
            }
        }
        foreach (['init', 'settings', 'attributes'] as $key) {
            if (!empty($this->_config[$key])) { throw new \LogicException('Inicialização SQL personalizada bloqueada.'); }
        }
        parent::connect();
        if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) { $this->pdo->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 5); }
    }
    public function prepare(Query|string $query): StatementInterface
    {
        if (!is_string($query) || !Queries::allows($query)) { throw new \LogicException('TOTVS: consulta fora da lista SELECT permitida.'); }
        return parent::prepare($query);
    }
    public function exec(string $sql): int|false { throw new \LogicException('Execução direta bloqueada no TOTVS.'); }
}
