<?php
declare(strict_types=1);
namespace App\Service;

/** Atomic retry protection using the existing audit log, scoped to user and request. */
final class EnvioLote
{
    public static function run(int $actor,string $module,array $input,callable $save): array
    {
        $user=Access::actor($actor);$key=$input['envio_chave']??null;
        if(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key))throw new \DomainException('Identificação do envio inválida. Reabra o formulário.');
        unset($input['envio_chave'],$input['_csrfToken']);
        $hash=hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));
        $action='envio_'.$module;$table=Access::table('Auditorias');$db=$table->getConnection();
        if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->enableSavePoints(true);
        return $db->transactional(function()use($user,$actor,$key,$hash,$action,$db,$table,$save){
            if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',[$action.':'.$actor.':'.$key]);
            $prior=$table->find()->where(['usuario_id'=>$actor,'acao'=>$action,'depois LIKE'=>'%"chave":"'.$key.'"%'])->first();
            if($prior){$result=json_decode($prior->depois,true,512,JSON_THROW_ON_ERROR);if($result['hash']!==$hash)throw new \DomainException('Este envio já foi registrado com outros dados. Reabra o formulário para um novo lançamento.');return $result['ids'];}
            $ids=$save();
            Access::audit($user,$action,null,null,['chave'=>$key,'hash'=>$hash,'ids'=>$ids],null);
            return $ids;
        });
    }
}
