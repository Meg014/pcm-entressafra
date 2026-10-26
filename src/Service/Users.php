<?php
declare(strict_types=1);
namespace App\Service;
final class Users
{
    public function save(int $actorId, array $data, ?int $id = null): int
    {
        $actor = Access::actor($actorId); Access::admin($actor);
        $table = Access::table('Usuarios');
        $name = trim((string)($data['nome'] ?? '')); $login = strtolower(trim((string)($data['login'] ?? '')));
        $role = (string)($data['perfil'] ?? ''); $password = (string)($data['senha'] ?? '');
        $computer=($data['computador_operacional']??'0')==='1'||($data['computador_operacional']??null)===true;
        if($id && !array_key_exists('computador_operacional',$data)) { $computer=(bool)$table->get($id)->computador_operacional; }
        if($computer && $role!=='RESPONSAVEL_SETOR') { throw new \DomainException('Computador operacional deve utilizar o perfil RESPONSAVEL_SETOR e setores autorizados.'); }
        $active = ($data['ativo'] ?? '0') === '1' || ($data['ativo'] ?? null) === true;
        if ($name === '' || mb_strlen($name) > 150 || !preg_match('/^[a-z0-9._@-]{3,100}$/D', $login) || !in_array($role, Access::ROLES, true)) { throw new \DomainException('Preencha nome, login válido (3 a 100 caracteres) e perfil.'); }
        if ((!$id || $password !== '') && !PasswordPolicy::accepts($password)) { throw new \DomainException(PasswordPolicy::MESSAGE); }
        if ($id === (int)$actor->id && (!$active || $role !== 'ADMIN_PCM')) { throw new \DomainException('Você não pode desativar ou remover seu próprio perfil administrador.'); }
        $where = ['login' => $login]; if ($id) { $where['id !='] = $id; }
        if ($table->exists($where)) { throw new \DomainException('Login já cadastrado.'); }
        $sectors = $data['setores'] ?? [];
        if (!is_array($sectors)) { throw new \DomainException('Setores inválidos.'); }
        $sectors = array_unique(array_map('intval', $sectors));
        foreach ($sectors as $sector) { if (!Access::table('Setores')->exists(['id' => $sector])) { throw new \DomainException('Setor inexistente.'); } }
        if ($role === 'RESPONSAVEL_SETOR' && !$sectors) { throw new \DomainException('Selecione ao menos um setor autorizado.'); }
        return $table->getConnection()->transactional(function () use ($table, $id, $name, $login, $role, $active, $password, $sectors, $actor, $computer): int {
            $entity = $id ? $table->get($id) : Access::entity($table, []);
            $before = $id ? array_intersect_key($entity->toArray(), array_flip(['id','nome','login','perfil','ativo','computador_operacional'])) : null;
            foreach (['nome' => $name, 'login' => $login, 'perfil' => $role, 'ativo' => $active, 'modified' => gmdate('Y-m-d H:i:s')] as $key => $value) { $entity->set($key, $value); }
            $entity->computador_operacional=$computer;
            if($entity->get('sessao_computador_hash')!==null&&($password!==''||!$active||!$computer))$entity->sessao_computador_hash=null;
            if (!$id) { $entity->created = gmdate('Y-m-d H:i:s'); }
            if ($password !== '') { $entity->senha_hash = password_hash($password, PASSWORD_DEFAULT); $entity->falhas_login = 0; $entity->bloqueado_ate = null; }
            $table->saveOrFail($entity);
            $links = Access::table('UsuariosSetores'); $links->deleteAll(['usuario_id' => $entity->id]);
            foreach ($sectors as $sector) { $links->saveOrFail(Access::entity($links, ['usuario_id' => $entity->id, 'setor_id' => $sector])); }
            Access::audit($actor, 'usuario', null, $before, ['usuario_id' => $entity->id, 'nome' => $name, 'perfil' => $role,'computador_operacional'=>$computer, 'ativo' => $active, 'setores' => $sectors, 'senha_alterada' => $password !== ''], null);
            return (int)$entity->id;
        });
    }
}
