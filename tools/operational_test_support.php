<?php
declare(strict_types=1);
function operationalTestConnect(string $schema): void
{
    if(getenv('PCM_RUN_OPERATIONAL_TEST')!=='1'||!preg_match('/^pcm_op_test_[a-f0-9]{16}$/D',$schema))throw new RuntimeException('Ambiente de teste inválido.');
    $config=\Cake\Datasource\ConnectionManager::getConfig('default');$config['schema']=$schema;$config['cacheMetadata']=false;
    \Cake\Datasource\ConnectionManager::drop('default');\Cake\Datasource\ConnectionManager::setConfig('default',$config);
    \Cake\Datasource\FactoryLocator::get('Table')->clear();
    $db=\Cake\Datasource\ConnectionManager::get('default');$db->cacheMetadata(false);
    if($db->execute('SELECT current_schema() AS s')->fetch('assoc')['s']!==$schema)throw new RuntimeException('Schema de teste não confirmado.');
}
