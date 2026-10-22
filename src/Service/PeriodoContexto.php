<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Http\Exception\ForbiddenException;

/** Request-local context. Never stored in a global cache or shared between sessions. */
final class PeriodoContexto
{
    private static ?int $id=null;
    public static function set(?int $id): void { self::$id=$id; }
    public static function id(): ?int { return self::$id; }
    public static function season(int $id): void
    {
        if(self::$id!==null&&self::$id!==$id)throw new ForbiddenException('Registro de outra entressafra. Use Trocar entressafra.');
    }
    public static function writable(int $id): void
    {
        self::season($id);
        if(Access::table('Entressafras')->get($id)->status==='encerrada')throw new ForbiddenException('Entressafra encerrada: somente consulta histórica.');
    }
    public static function activity(int $id,bool $write=false): void
    {
        if(self::$id===null)return;
        $t=Access::table('Atividades');$a=$t->getConnection()->execute('SELECT entressafra_id FROM atividades WHERE id=?',[$id])->fetch('assoc');
        if(!$a){$t->get($id);return;}
        $write?self::writable((int)$a['entressafra_id']):self::season((int)$a['entressafra_id']);
    }
    public static function notebook(int $id,bool $write=false): void
    {
        if(self::$id===null)return;
        $t=Access::table('Cadernos');$book=$t->getConnection()->execute('SELECT entressafra_id FROM cadernos WHERE id=?',[$id])->fetch('assoc');
        if(!$book){$t->get($id);return;}
        $write?self::writable((int)$book['entressafra_id']):self::season((int)$book['entressafra_id']);
    }
}
