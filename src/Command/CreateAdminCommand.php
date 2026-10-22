<?php
declare(strict_types=1);
namespace App\Command;
use App\Service\Access;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
class CreateAdminCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $table = Access::table('Usuarios');
        if ($table->exists(['perfil' => 'ADMIN_PCM', 'ativo' => true])) { $io->err('Já existe administrador ativo. Utilize a administração de usuários.'); return self::CODE_ERROR; }
        $name = trim((string)getenv('PCM_ADMIN_NAME')); $login = strtolower(trim((string)getenv('PCM_ADMIN_LOGIN'))); $password = (string)getenv('PCM_ADMIN_PASSWORD');
        if ($name === '' || mb_strlen($name) > 150 || !preg_match('/^[a-z0-9._@-]{3,100}$/D', $login) || !\App\Service\PasswordPolicy::accepts($password)) { $io->err('Defina PCM_ADMIN_NAME, PCM_ADMIN_LOGIN e PCM_ADMIN_PASSWORD somente no ambiente do processo. ' . \App\Service\PasswordPolicy::MESSAGE); return self::CODE_ERROR; }
        if ($table->exists(['login' => $login])) { $io->err('Este login já está cadastrado. Utilize um login exclusivo para a conta administrativa.'); return self::CODE_ERROR; }
        $now = gmdate('Y-m-d H:i:s');
        try {
            $table->saveOrFail(Access::entity($table, ['nome' => $name, 'login' => $login, 'senha_hash' => password_hash($password, PASSWORD_DEFAULT), 'perfil' => 'ADMIN_PCM', 'ativo' => true, 'falhas_login' => 0, 'created' => $now, 'modified' => $now]));
        } catch (\Throwable) { $io->err('Não foi possível criar o administrador. Verifique conexão e login; nenhum segredo será exibido.'); return self::CODE_ERROR; }
        $io->success('Administrador criado. Nenhuma senha foi exibida.'); return self::CODE_SUCCESS;
    }
}
