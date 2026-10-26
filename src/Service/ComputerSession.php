<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Http\Cookie\Cookie;
use Cake\Http\ServerRequest;

/** A revocable credential restores operational logins after native PHP sessions expire. */
final class ComputerSession
{
    public const COOKIE='pcm_computador';

    public function matches($user,mixed $value): bool
    {
        if(!$user||!$user->ativo||!Access::operational($user)||!is_string($value))return false;
        if(!preg_match('/^([1-9][0-9]*)\.([a-f0-9]{64})$/D',$value,$parts)||(string)$user->id!==$parts[1])return false;
        $stored=$user->get('sessao_computador_hash');
        return is_string($stored)&&strlen($stored)===64&&hash_equals($stored,hash_hmac('sha256',$value,$user->senha_hash));
    }

    public function restore(mixed $value)
    {
        if(!is_string($value)||!preg_match('/^([1-9][0-9]{0,17})\.[a-f0-9]{64}$/D',$value,$parts))return null;
        $user=Access::table('Usuarios')->find()->where(['id'=>$parts[1],'ativo'=>true])->first();
        return $this->matches($user,$value)?$user:null;
    }

    public function issue($user): string
    {
        Access::actor((int)$user->id);
        if(!Access::operational($user))throw new \DomainException('Sessão permanente exclusiva para computador operacional.');
        $value=$user->id.'.'.bin2hex(random_bytes(32));
        Access::table('Usuarios')->updateAll(['sessao_computador_hash'=>hash_hmac('sha256',$value,$user->senha_hash)],['id'=>$user->id]);
        return $value;
    }

    public function revoke($user): void
    {
        if($user&&$user->get('sessao_computador_hash')!==null)Access::table('Usuarios')->updateAll(['sessao_computador_hash'=>null],['id'=>$user->id]);
    }

    public function cookie(ServerRequest $request,string $value=''): Cookie
    {
        // Browsers cap persistent cookie lifetime; renew it on each authenticated use.
        return Cookie::create(self::COOKIE,$value,[
            'expires'=>new \DateTimeImmutable($value===''?'-1 day':'+400 days'),
            'path'=>rtrim((string)$request->getAttribute('base'),'/').'/',
            'secure'=>$request->getUri()->getScheme()==='https'||parse_url((string)\Cake\Core\Configure::read('App.fullBaseUrl',''),PHP_URL_SCHEME)==='https','httponly'=>true,'samesite'=>'Lax',
        ]);
    }
}
