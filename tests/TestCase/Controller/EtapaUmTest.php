<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class EtapaUmTest extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens', 'VinculosLotes', 'Auditorias', 'Apontamentos', 'Atividades', 'UsuariosSetores', 'Usuarios'] as $alias) { $this->getTableLocator()->get($alias)->deleteAll([]); }
        $this->getTableLocator()->get('Cadernos')->deleteAll([]);
        $this->getTableLocator()->get('Setores')->deleteAll([]);
        $this->getTableLocator()->get('AreasFabrica')->deleteAll([]);
        $this->getTableLocator()->get('Entressafras')->deleteAll([]);
        $table = $this->getTableLocator()->get('Usuarios');
        $hash = password_hash('Teste-Local-Somente-2026', PASSWORD_DEFAULT);
        $user = $table->saveOrFail(\App\Service\Access::entity($table, ['nome' => 'Administrador de teste', 'login' => 'teste', 'senha_hash' => $hash, 'perfil' => 'ADMIN_PCM', 'ativo' => true, 'created' => gmdate('Y-m-d H:i:s'), 'modified' => gmdate('Y-m-d H:i:s')]));
        $this->session(['Auth' => ['id' => $user->id, 'stamp' => hash('sha256', $hash), 'last' => time()]]);
    }

    public function testEmptyDashboardAndForms(): void
    {
        foreach (['/escolher-entressafra', '/entressafras', '/entressafras/add'] as $path) {
            $this->get($path);
            $this->assertResponseOk();
            $this->assertResponseContains('PCM');
        }
        foreach(['/','/setores','/cadernos','/cadernos/add'] as $path){$this->get($path);$this->assertRedirect('/escolher-entressafra');}
    }

    public function testFullRegistrationAndEditingFlow(): void
    {
        $this->enableCsrfToken();
        $this->post('/entressafras/add', ['nome' => '2026/2027', 'data_inicio' => '2026-11-01', 'data_fim' => '2027-03-31', 'status' => 'planejamento']);
        $this->assertResponseCode(302);
        $entressafra = $this->getTableLocator()->get('Entressafras')->find()->firstOrFail();
        $this->session(['Periodo'=>['id'=>(int)$entressafra->id,'usuario_id'=>(int)\App\Service\Access::table('Usuarios')->find()->firstOrFail()->id]]);
        $this->post('/setores/add', ['nome' => 'Setor de teste', 'ativo' => '1']);
        $this->assertResponseCode(302);
        $setor = $this->getTableLocator()->get('Setores')->find()->firstOrFail();
        $this->post('/setores/nova-area',['nome'=>'Setor principal de teste']);$this->assertResponseCode(302);
        $area=\App\Service\Access::table('AreasFabrica')->find()->firstOrFail();
        $this->post('/cadernos/add', ['nome' => 'Caderno de teste', 'area_curva_id'=>$area->id]);
        $this->assertResponseCode(302);
        $caderno = $this->getTableLocator()->get('Cadernos')->find()->firstOrFail();
        foreach (['entressafras' => $entressafra->id, 'setores' => $setor->id, 'cadernos' => $caderno->id] as $path => $id) {
            $this->get('/' . $path . '/view/' . $id);
            $this->assertResponseOk();
            $this->get('/' . $path . '/edit/' . $id);
            $this->assertResponseOk();
            $this->post('/' . $path . '/edit/' . $id, ['nome' => 'Nome alterado ' . $path]);
            $this->assertResponseCode(302);
            $this->get('/' . $path . '/view/' . $id);
            $this->assertResponseContains('Nome alterado ' . $path);
        }
        $this->post('/entressafras/edit/' . $entressafra->id, ['status' => 'ativa']);
        $this->assertResponseCode(302);
        $this->get('/?entressafra_id=' . $entressafra->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Nome alterado entressafras');
        $this->assertResponseContains('Ativa');
        $this->get('/cadernos?entressafra_id=' . $entressafra->id . '&area_curva_id=' . $area->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Nome alterado cadernos');
    }

    public function testInvalidDatesAndStatus(): void
    {
        $table = $this->getTableLocator()->get('Entressafras');
        $entity = $table->newEntity(['nome' => ' ', 'data_inicio' => '2027-01-02', 'data_fim' => '2027-01-01', 'status' => 'inexistente']);
        $this->assertNotEmpty($entity->getError('nome'));
        $this->assertNotEmpty($entity->getError('data_fim'));
        $this->assertNotEmpty($entity->getError('status'));
        $this->assertFalse($table->save($entity));
    }

    public function testReferencesAndUniqueness(): void
    {
        $entressafras = $this->getTableLocator()->get('Entressafras');
        $data = ['nome' => 'Teste', 'data_inicio' => '2026-11-01', 'data_fim' => '2027-03-01', 'status' => 'planejamento'];
        $entressafra = $entressafras->saveOrFail($entressafras->newEntity($data));
        $this->assertFalse($entressafras->save($entressafras->newEntity($data)));
        $setores = $this->getTableLocator()->get('Setores');
        $setor = $setores->saveOrFail($setores->newEntity(['nome' => 'Teste', 'ativo' => true]));
        $this->assertFalse($setores->save($setores->newEntity(['nome' => 'Teste'])));
        $cadernos = $this->getTableLocator()->get('Cadernos');
        $bad = $cadernos->newEntity(['nome' => 'Teste', 'entressafra_id' => 999999, 'setor_id' => $setor->id]);
        $this->assertFalse($cadernos->save($bad));
        $this->assertNotEmpty($bad->getError('entressafra_id'));
        $data = ['nome' => 'Teste', 'entressafra_id' => $entressafra->id, 'setor_id' => $setor->id];
        $cadernos->saveOrFail($cadernos->newEntity($data));
        $this->assertFalse($cadernos->save($cadernos->newEntity($data)));
        $this->assertFalse($cadernos->save($cadernos->newEntity(['nome' => 'Sem vínculos'])));
        $this->assertNotNull($entressafra->created);
    }

    public function testCsrfMissingRecordAndInvalidFilter(): void
    {
        $this->post('/setores/add', ['nome' => 'Bloqueado']);
        $this->assertResponseCode(403);
        $this->selectFixturePeriod();
        $this->get('/cadernos/view/999999');
        $this->assertResponseCode(404);
        $this->get('/?entressafra_id[]=1');
        $this->assertResponseCode(400);
        $this->get('/cadernos?setor_id=abc');
        $this->assertResponseCode(400);
    }

    public function testValidationFeedbackAndEscapedOutput(): void
    {
        $this->enableCsrfToken();
        $this->post('/entressafras/add', ['nome' => 'Teste', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-01-01', 'status' => 'planejamento']);
        $this->assertResponseOk();
        $this->assertResponseContains('A data final deve ser igual ou posterior');
        $this->selectFixturePeriod();
        $this->post('/setores/add', ['nome' => '<script>alert(1)</script>']);
        $this->assertResponseCode(302);
        $this->get('/setores?equipes=1');
        $this->assertResponseContains('&lt;script&gt;');
        $this->assertResponseNotContains('<script>alert(1)</script>');
    }
    private function selectFixturePeriod(): void
    {
        $t=\App\Service\Access::table('Entressafras');$s=$t->saveOrFail($t->newEntity(['nome'=>'Período de validação','data_inicio'=>'2026-10-01','data_fim'=>'2027-04-10','status'=>'planejamento']));
        $this->session(['Periodo'=>['id'=>(int)$s->id,'usuario_id'=>(int)\App\Service\Access::table('Usuarios')->find()->firstOrFail()->id]]);
    }
}
