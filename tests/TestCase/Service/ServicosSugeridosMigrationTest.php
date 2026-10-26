<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Migrations\Db\Adapter\SqliteAdapter;
use PHPUnit\Framework\TestCase;

class ServicosSugeridosMigrationTest extends TestCase
{
    public function testSeedsAndUniqueIndexWithoutChangingHistoricalDescriptions(): void
    {
        $db=new Connection(['driver'=>Sqlite::class,'database'=>':memory:']);
        $db->execute('CREATE TABLE apontamentos(id INTEGER PRIMARY KEY,descricao TEXT)');
        $db->execute('INSERT INTO apontamentos VALUES(1,?)',["  Serviço antigo\ncom texto livre  "]);
        $before=$db->execute('SELECT * FROM apontamentos')->fetchAll('assoc');
        require_once ROOT.'/config/Migrations/20261026140000_CreateServicosSugeridos.php';
        $migration=new \CreateServicosSugeridos(20261026140000);
        $migration->setAdapter(new SqliteAdapter(['adapter'=>'sqlite','connection'=>$db]));$migration->up();
        $rows=$db->execute('SELECT * FROM servicos_sugeridos')->fetchAll('assoc');
        $this->assertCount(15,$rows);
        foreach($rows as $row){
            $this->assertSame(\App\Service\ServiceSuggestions::normalize($row['descricao']),$row['descricao_busca']);
            $this->assertSame(1,(int)$row['ativo']);
        }
        $this->assertSame($before,$db->execute('SELECT * FROM apontamentos')->fetchAll('assoc'));
        try{
            $db->execute("INSERT INTO servicos_sugeridos(descricao,descricao_busca,ativo,created,modified) VALUES('Duplicada','desmontagem',TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $this->fail('Índice único não aplicado');
        }catch(\Cake\Database\Exception\QueryException){$this->assertTrue(true);}
        $this->expectException(\RuntimeException::class);$migration->down();
    }
}
