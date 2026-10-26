<?php
declare(strict_types=1);
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/apontamentos/servicos',['controller'=>'Apontamentos','action'=>'servicos'])->setMethods(['GET']);
        $builder->connect('/servicos-sugeridos',['controller'=>'ServicosSugeridos','action'=>'index'])->setMethods(['GET']);
        $builder->connect('/servicos-sugeridos/add',['controller'=>'ServicosSugeridos','action'=>'add'])->setMethods(['GET','POST']);
        $builder->connect('/servicos-sugeridos/edit/{id}',['controller'=>'ServicosSugeridos','action'=>'edit'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['GET','POST']);
        $builder->connect('/servicos-sugeridos/toggle/{id}',['controller'=>'ServicosSugeridos','action'=>'toggle'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['POST']);
        $builder->connect('/insumos',['controller'=>'Insumos','action'=>'index'])->setMethods(['GET']);
        $builder->connect('/insumos/add',['controller'=>'Insumos','action'=>'add'])->setMethods(['GET','POST']);
        $builder->connect('/os-sem-caderno',['controller'=>'OsSemCaderno','action'=>'index'])->setMethods(['GET','POST']);
        $builder->connect('/escolher-entressafra',['controller'=>'Periodos','action'=>'index'])->setMethods(['GET']);
        $builder->connect('/escolher-entressafra/entrar',['controller'=>'Periodos','action'=>'selecionar'])->setMethods(['POST']);
        $builder->connect('/setores/curva/{id}',['controller'=>'Setores','action'=>'curva'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['GET','POST']);
        $builder->connect('/setores/nova-area',['controller'=>'Setores','action'=>'novaArea'])->setMethods(['POST']);
        $builder->connect('/organizacao-curva',['controller'=>'Organizacao','action'=>'index'])->setMethods(['GET','POST']);
        $builder->connect('/organizacao-curva/previa',['controller'=>'Organizacao','action'=>'previa'])->setMethods(['GET','POST']);
        $builder->connect('/cadernos/{id}/vincular-lista',['controller'=>'Organizacao','action'=>'lista'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['GET','POST']);
        $builder->connect('/cadernos/{id}/transferir-os',['controller'=>'Organizacao','action'=>'transferencia'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['GET','POST']);
        $builder->connect('/prazos-entressafra',['controller'=>'Prazos','action'=>'index'])->setMethods(['GET','POST']);
        $builder->connect('/operacao/ordens',['controller'=>'Operacional','action'=>'ordens'])->setMethods(['GET']);
        $builder->connect('/operacao/identificar',['controller'=>'Operacional','action'=>'identificar'])->setMethods(['POST']);
        $builder->connect('/lancar-porcentagem',['controller'=>'Operacional','action'=>'porcentagem'])->setMethods(['GET','POST']);
        $builder->connect('/fila-pcm',['controller'=>'FilaPcm','action'=>'index'])->setMethods(['GET']);
        $builder->connect('/fila-pcm/os/{id}',['controller'=>'FilaPcm','action'=>'view'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['GET','POST']);
        $builder->connect('/evolucao',['controller'=>'Evolucao','action'=>'index'])->setMethods(['GET','POST']);
        $builder->connect('/evolucao/correcoes',['controller'=>'Evolucao','action'=>'correcoes'])->setMethods(['GET','POST']);
        $builder->connect('/evolucao/analise',['controller'=>'Evolucao','action'=>'analise'])->setMethods(['GET']);
        $builder->connect('/evolucao/configuracao',['controller'=>'Evolucao','action'=>'configuracao'])->setMethods(['GET','POST']);
        $builder->connect('/evolucao/modelo/{type}',['controller'=>'Evolucao','action'=>'modelo'])->setPatterns(['type'=>'pesos|metas'])->setPass(['type'])->setMethods(['GET']);
        $builder->connect('/evolucao/historico/{id}',['controller'=>'Evolucao','action'=>'historico'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['GET']);
        $builder->connect('/controle-pcm',['controller'=>'Evolucao','action'=>'controle'])->setMethods(['GET']);
        $builder->connect('/vinculos-lote',['controller'=>'VinculosLote','action'=>'index'])->setMethods(['GET']);
        $builder->connect('/vinculos-lote/api',['controller'=>'VinculosLote','action'=>'api'])->setMethods(['GET','POST']);
        $builder->connect('/login', ['controller' => 'Auth', 'action' => 'login'])->setMethods(['GET', 'POST']);
        $builder->connect('/logout', ['controller' => 'Auth', 'action' => 'logout'])->setMethods(['POST']);
        $builder->connect('/apontamentos/funcionarios', ['controller' => 'Apontamentos', 'action' => 'funcionarios'])->setMethods(['GET']);
        $builder->connect('/apontamentos/ordens', ['controller' => 'Apontamentos', 'action' => 'ordens'])->setMethods(['GET']);
        $builder->connect('/funcionarios/pendencias', ['controller'=>'Funcionarios','action'=>'pendencias'])->setMethods(['GET']);
        $builder->connect('/atividades/sync/{id}',['controller'=>'Atividades','action'=>'sync'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['POST']);
        $builder->connect('/atividades/descricao/{id}',['controller'=>'Atividades','action'=>'descricao'])->setPatterns(['id'=>'[1-9][0-9]*'])->setPass(['id'])->setMethods(['POST']);
        foreach (['atividades' => 'Atividades', 'apontamentos' => 'Apontamentos', 'usuarios' => 'Usuarios'] as $path => $controller) {
            $builder->connect('/' . $path, ['controller' => $controller, 'action' => 'index'])->setMethods(['GET']);
            $builder->connect('/' . $path . '/add', ['controller' => $controller, 'action' => 'add'])->setMethods(['GET', 'POST']);
            foreach (['view', 'edit', 'change'] as $action) {
                if (($controller === 'Atividades' && $action !== 'view') || ($controller === 'Usuarios' && $action !== 'edit')) { continue; }
                $builder->connect('/' . $path . '/' . $action . '/{id}', ['controller' => $controller, 'action' => $action])->setPatterns(['id' => '[1-9][0-9]*'])->setPass(['id'])->setMethods($action === 'view' ? ['GET'] : ($action === 'change' ? ['POST'] : ['GET', 'POST']));
            }
        }
        $builder->connect('/consulta-pcm', ['controller' => 'Apontamentos', 'action' => 'consulta'])->setMethods(['GET']);
        $builder->connect('/', ['controller' => 'Dashboard', 'action' => 'index'])->setMethods(['GET']);
        foreach (['entressafras' => 'Entressafras', 'setores' => 'Setores', 'cadernos' => 'Cadernos'] as $path => $controller) {
            $builder->connect('/' . $path, ['controller' => $controller, 'action' => 'index'])->setMethods(['GET']);
            $builder->connect('/' . $path . '/add', ['controller' => $controller, 'action' => 'add'])->setMethods(['GET', 'POST']);
            foreach (['view', 'edit'] as $action) {
                $builder->connect('/' . $path . '/' . $action . '/{id}', ['controller' => $controller, 'action' => $action])
                    ->setPatterns(['id' => '[1-9][0-9]*'])->setPass(['id'])
                    ->setMethods($action === 'view' ? ['GET'] : ['GET', 'POST', 'PUT', 'PATCH']);
            }
        }
    });
};
