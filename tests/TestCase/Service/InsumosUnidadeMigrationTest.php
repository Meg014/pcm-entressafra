<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Migrations\Db\Adapter\SqliteAdapter;
use PHPUnit\Framework\TestCase;

final class InsumosUnidadeMigrationTest extends TestCase
{
    public function testMigrationPreservesExistingRowsWithoutAssigningUnits(): void
    {
        $db = new Connection(['driver'=>Sqlite::class,'database'=>':memory:']);
        $db->execute('CREATE TABLE insumos (id INTEGER PRIMARY KEY, material TEXT, quantidade DECIMAL(18,4), data DATE, snapshot TEXT)');
        $db->execute("INSERT INTO insumos VALUES (7,'Rolamento',2.5,'2026-10-21','original')");
        $before = $db->execute('SELECT * FROM insumos')->fetchAll('assoc');
        require_once ROOT.'/config/Migrations/20261117140000_AddUnidadeInsumos.php';
        $migration = new \AddUnidadeInsumos(20261117140000);
        $migration->setAdapter(new SqliteAdapter(['adapter'=>'sqlite','connection'=>$db]));
        $migration->up();
        $this->assertSame($before, $db->execute('SELECT id,material,quantidade,data,snapshot FROM insumos')->fetchAll('assoc'));
        $this->assertNull($db->execute('SELECT unidade FROM insumos WHERE id=7')->fetch('assoc')['unidade']);
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
