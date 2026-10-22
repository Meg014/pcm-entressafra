<?php
declare(strict_types=1);
namespace App\Service;
use Cake\Datasource\FactoryLocator;
use Cake\Http\Exception\ForbiddenException;
final class Access
{
    public const ROLES = ['ADMIN_PCM', 'PCM', 'RESPONSAVEL_SETOR'];
    public static function operational($user): bool { return $user!==null && $user->perfil==='RESPONSAVEL_SETOR' && (bool)$user->get('computador_operacional'); }
    public static function table(string $alias): \Cake\ORM\Table { return FactoryLocator::get('Table')->get($alias); }
    public static function actor(int $id): \Cake\Datasource\EntityInterface
    {
        $user = self::table('Usuarios')->get($id);
        if (!$user->ativo || !in_array($user->perfil, self::ROLES, true)) { throw new ForbiddenException('Usuário sem acesso.'); }
        return $user;
    }
    public static function sectors($user): array
    {
        if ($user->perfil !== 'RESPONSAVEL_SETOR') { return self::table('Setores')->find()->all()->extract('id')->toList(); }
        return self::table('UsuariosSetores')->find()->where(['usuario_id' => $user->id])->all()->extract('setor_id')->toList();
    }
    public static function sector($user, int $id): void
    {
        if (!in_array($id, array_map('intval', self::sectors($user)), true)) { throw new ForbiddenException('Você não tem autorização para este setor.'); }
    }
    public static function notebookIds(array $sectors)
    { return self::table('CadernosSetores')->find()->select(['caderno_id'])->where(['setor_id IN'=>$sectors?:[-1]]); }
    public static function notebookSectors(int $id): array
    { return array_map('intval',self::table('CadernosSetores')->find()->where(['caderno_id'=>$id])->all()->extract('setor_id')->toList()); }
    public static function notebook($user,int $id): void
    {
        PeriodoContexto::notebook($id);
        if (!array_intersect(self::notebookSectors($id),self::sectors($user))) { throw new ForbiddenException('Caderno sem setor autorizado para este usuário.'); }
    }
    public static function activity($user,$activity): void
    {
        PeriodoContexto::season((int)$activity->entressafra_id);
        if ($activity->setor_executante_id===null) { self::pcm($user); return; }
        self::sector($user,(int)$activity->setor_executante_id);
    }
    public static function activityScope($query,$user,string $alias='Atividades')
    {
        if(PeriodoContexto::id()!==null)$query->where([$alias.'.entressafra_id'=>PeriodoContexto::id()]);
        $where=[$alias.'.setor_executante_id IN'=>self::sectors($user)?:[-1]];
        return $query->where($user->perfil==='RESPONSAVEL_SETOR'?$where:['OR'=>[$where,[$alias.'.setor_executante_id IS'=>null]]]);
    }
    public static function pcm($user): void
    {
        if (!in_array($user->perfil, ['ADMIN_PCM', 'PCM'], true)) { throw new ForbiddenException('Acesso exclusivo do PCM.'); }
    }
    public static function admin($user): void
    {
        if ($user->perfil !== 'ADMIN_PCM') { throw new ForbiddenException('Acesso exclusivo da administração PCM.'); }
    }
    public static function entity(\Cake\ORM\Table $table, array $data): \Cake\Datasource\EntityInterface
    { return $table->newEntity($data, ['accessibleFields' => ['*' => true]]); }
    public static function audit($user, string $action, ?int $id, ?array $before, array $after, ?string $reason): void
    {
        $table = self::table('Auditorias');
        $table->saveOrFail(self::entity($table, ['apontamento_id' => $id, 'usuario_id' => $user->id, 'usuario_nome' => $user->nome, 'acao' => $action, 'motivo' => $reason, 'antes' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'depois' => json_encode($after, JSON_THROW_ON_ERROR), 'created' => gmdate('Y-m-d H:i:s')]));
    }
}
