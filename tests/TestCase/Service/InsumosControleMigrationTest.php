<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Migrations\Db\Adapter\SqliteAdapter;
use PHPUnit\Framework\TestCase;

class InsumosControleMigrationTest extends TestCase
{
    public function testExistingSuppliesAndAuditsArePreservedAndLinked(): void
    {
        $db=new Connection(['driver'=>Sqlite::class,'database'=>':memory:']);$db->execute('PRAGMA foreign_keys=ON');
        $db->execute('CREATE TABLE usuarios(id INTEGER PRIMARY KEY)');$db->execute('INSERT INTO usuarios VALUES (1)');
        $db->execute('CREATE TABLE insumos(id INTEGER PRIMARY KEY,atividade_id INTEGER,material TEXT,quantidade DECIMAL(18,4),created DATETIME,snapshot TEXT)');
        $db->execute("INSERT INTO insumos VALUES(7,10,'Rolamento 6205',2.5,'2026-10-21 12:00:00','snapshot preservado')");
        $db->execute('CREATE TABLE auditorias(id INTEGER PRIMARY KEY,acao VARCHAR(40),depois TEXT,created DATETIME)');
        foreach([1=>['insumo_criacao','{"insumo_id":7,"material":"Rolamento 6205"}'],2=>['insumo_criacao','{"insumo_id":999}'],3=>['criacao','{"apontamento_id":7}']] as $id=>[$action,$after])$db->execute('INSERT INTO auditorias VALUES(?,?,?,?)',[$id,$action,$after,'2026-10-21 12:00:00']);
        $supplies=$db->execute('SELECT id,atividade_id,material,quantidade,created,snapshot FROM insumos')->fetchAll('assoc');
        $audits=$db->execute('SELECT id,acao,depois,created FROM auditorias ORDER BY id')->fetchAll('assoc');
        require_once ROOT.'/config/Migrations/20261026130000_AddControlePcmInsumos.php';
        $migration=new \AddControlePcmInsumos(20261026130000);$migration->setAdapter(new SqliteAdapter(['adapter'=>'sqlite','connection'=>$db]));$migration->up();
        $this->assertSame($supplies,$db->execute('SELECT id,atividade_id,material,quantidade,created,snapshot FROM insumos')->fetchAll('assoc'));
        $this->assertSame($audits,$db->execute('SELECT id,acao,depois,created FROM auditorias ORDER BY id')->fetchAll('assoc'));
        $row=$db->execute('SELECT * FROM insumos WHERE id=7')->fetch('assoc');$this->assertSame('pendente',$row['controle_pcm']);$this->assertSame(0,(int)$row['revisao_pcm']);$this->assertNull($row['confirmado_em']);$this->assertNull($row['confirmado_por']);
        $links=$db->execute('SELECT insumo_id FROM auditorias ORDER BY id')->fetchAll('assoc');$this->assertSame(7,(int)$links[0]['insumo_id']);$this->assertNull($links[1]['insumo_id']);$this->assertNull($links[2]['insumo_id']);
        try{$db->execute('DELETE FROM insumos WHERE id=7');$this->fail('Historical supply deleted');}catch(\Cake\Database\Exception\QueryException){$this->assertTrue(true);}
        $this->expectException(\RuntimeException::class);$migration->down();
    }
}
