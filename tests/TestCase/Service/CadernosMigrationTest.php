<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Migrations\Db\Adapter\SqliteAdapter;
use PHPUnit\Framework\TestCase;

class CadernosMigrationTest extends TestCase
{
    public function testEvolutionBackfillsRealAreaPreservesHistoriesAndBlocksUnsafeRemoval(): void
    {
        $db=new Connection(['driver'=>Sqlite::class,'database'=>':memory:']);
        $db->execute('PRAGMA foreign_keys=ON');
        $db->execute('CREATE TABLE setores(id INTEGER PRIMARY KEY,area_totvs TEXT,filial_totvs TEXT)');
        $db->execute("INSERT INTO setores VALUES (1,'A0','01'),(2,'A1','01')");
        $db->execute('CREATE TABLE cadernos(id INTEGER PRIMARY KEY,setor_id INTEGER NOT NULL,nome TEXT)');
        $db->execute("INSERT INTO cadernos VALUES (1,1,'Caderno legado')");
        $db->execute('CREATE TABLE atividades(id INTEGER PRIMARY KEY,caderno_id INTEGER,os_filial TEXT,snapshot_totvs TEXT)');
        foreach ([1=>['codigo_area'=>'A1','filial'=>'01'],2=>[],3=>['codigo_area'=>'OUTRA','filial'=>'01']] as $id=>$snapshot) {
            $db->execute('INSERT INTO atividades VALUES (?,1,?,?)',[$id,'01',json_encode($snapshot)]);
        }
        $db->execute('CREATE TABLE apontamentos(id INTEGER PRIMARY KEY,snapshot TEXT)');
        $db->execute("INSERT INTO apontamentos VALUES (1,'historico imutavel')");
        $db->execute('CREATE TABLE auditorias(id INTEGER PRIMARY KEY,depois TEXT)');
        $db->execute("INSERT INTO auditorias VALUES (1,'auditoria imutavel')");
        $before=[];
        $columns=['cadernos'=>'id,setor_id,nome','atividades'=>'id,caderno_id,os_filial,snapshot_totvs','apontamentos'=>'id,snapshot','auditorias'=>'id,depois'];
        foreach ($columns as $name=>$fields) { $before[$name]=$db->execute('SELECT '.$fields.' FROM '.$name.' ORDER BY id')->fetchAll('assoc'); }
        require_once ROOT.'/config/Migrations/20261021180000_CadernosMultiplosSetores.php';
        $migration=new \CadernosMultiplosSetores(20261021180000);
        $migration->setAdapter(new SqliteAdapter(['connection'=>$db])); $migration->up();
        foreach ($columns as $name=>$fields) { $this->assertSame($before[$name],$db->execute('SELECT '.$fields.' FROM '.$name.' ORDER BY id')->fetchAll('assoc')); }
        $this->assertSame(2,(int)$db->execute('SELECT COUNT(*) AS n FROM cadernos_setores WHERE caderno_id=1')->fetch('assoc')['n']);
        $rows=$db->execute('SELECT id,setor_executante_id,area_executante_totvs,identificacao_setor FROM atividades ORDER BY id')->fetchAll('assoc');
        $this->assertSame(2,(int)$rows[0]['setor_executante_id']); $this->assertSame('A1',$rows[0]['area_executante_totvs']);
        $this->assertSame('area_totvs',$rows[0]['identificacao_setor']); $this->assertSame('legado',$rows[1]['identificacao_setor']);
        $this->assertNull($rows[1]['area_executante_totvs']); $this->assertNull($rows[2]['setor_executante_id']);
        $this->assertSame('OUTRA',$rows[2]['area_executante_totvs']); $this->assertSame('pendente',$rows[2]['identificacao_setor']);
        try { $db->execute('DELETE FROM cadernos_setores WHERE caderno_id=1 AND setor_id=2'); $this->fail('Associação histórica removida.'); }
        catch (\Cake\Database\Exception\QueryException) { $this->assertTrue(true); }
    }
}
