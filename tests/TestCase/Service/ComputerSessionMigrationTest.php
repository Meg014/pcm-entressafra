<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Migrations\Db\Adapter\SqliteAdapter;
use PHPUnit\Framework\TestCase;

class ComputerSessionMigrationTest extends TestCase
{
    public function testAddsNullableCredentialWithoutChangingUsers(): void
    {
        $db=new Connection(['driver'=>Sqlite::class,'database'=>':memory:']);
        $db->execute('CREATE TABLE usuarios(id INTEGER PRIMARY KEY,login VARCHAR(100),senha_hash TEXT,computador_operacional BOOLEAN)');
        $db->execute("INSERT INTO usuarios VALUES(1,'computador-1','hash-preservado',TRUE),(2,'admin','outro-hash',FALSE)");
        $before=$db->execute('SELECT * FROM usuarios ORDER BY id')->fetchAll('assoc');
        require_once ROOT.'/config/Migrations/20261026150000_AddSessaoPermanenteComputador.php';
        $migration=new \AddSessaoPermanenteComputador(20261026150000);
        $migration->setAdapter(new SqliteAdapter(['adapter'=>'sqlite','connection'=>$db]));$migration->up();
        $this->assertSame($before,$db->execute('SELECT id,login,senha_hash,computador_operacional FROM usuarios ORDER BY id')->fetchAll('assoc'));
        $this->assertSame(2,(int)$db->execute('SELECT COUNT(*) AS total FROM usuarios WHERE sessao_computador_hash IS NULL')->fetch('assoc')['total']);
    }
}
