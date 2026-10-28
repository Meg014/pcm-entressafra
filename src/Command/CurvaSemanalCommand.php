<?php
declare(strict_types=1);
namespace App\Command;
use App\Service\{Access,CurvaSemanal,PeriodoContexto};
use Cake\Command\Command;
use Cake\Console\{Arguments,ConsoleIo,ConsoleOptionParser};

class CurvaSemanalCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)->setDescription('Apura semanas encerradas às terças-feiras; idempotente, sem acesso ao TOTVS.')
            ->addOption('usuario',['help'=>'ID de ADMIN_PCM ativo.','required'=>true])
            ->addOption('entressafra',['help'=>'ID da entressafra.','required'=>true])
            ->addOption('setor',['help'=>'ID do setor principal; omitido: todos.'])
            ->addOption('referencia',['help'=>'Terça-feira AAAA-MM-DD; omitida: todas as semanas encerradas.'])
            ->addOption('simular',['help'=>'Somente listar; não gravar fechamentos.','boolean'=>true]);
    }
    public function execute(Arguments $args,ConsoleIo $io): ?int
    {
        try {
            foreach(['usuario','entressafra'] as $key)if(!ctype_digit((string)$args->getOption($key))||(int)$args->getOption($key)<1)throw new \DomainException('Informe IDs válidos.');
            $actor=Access::actor((int)$args->getOption('usuario'));Access::admin($actor);$season=(int)$args->getOption('entressafra');PeriodoContexto::set($season);
            $service=new CurvaSemanal();$areas=$service->areas($season);$area=$args->getOption('setor');
            if($area!==null){if(!ctype_digit((string)$area)||!isset($areas[(int)$area]))throw new \DomainException('Setor inválido.');$areas=[(int)$area=>$areas[(int)$area]];}
            $requested=$args->getOption('referencia');$count=0;
            foreach($areas as $id=>$name) {
                $report=$service->report($season,(int)$id);
                $weeks=$requested!==null?array_values(array_filter($report['points'],fn($p)=>$p['referencia']===$requested)):$report['due'];
                if($requested!==null&&!$weeks)throw new \DomainException('Referência fora do calendário semanal.');
                foreach($weeks as $week) {
                    if($week['referencia']>(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'))throw new \DomainException('Semana ainda não encerrada.');
                    if($args->getOption('simular')){$io->out($name.' · '.$week['referencia'].' · somente prévia');}
                    else{$version=$service->close((int)$actor->id,$season,(int)$id,$week['referencia']);$io->out($name.' · '.$week['referencia'].' · versão '.$version);}
                    $count++;
                }
            }
            $io->success($count.' semana(s) processada(s). Nenhuma tarefa agendada foi criada.');return self::CODE_SUCCESS;
        } catch(\DomainException|\Cake\Http\Exception\ForbiddenException $error) { $io->err($error->getMessage());return self::CODE_ERROR; }
        finally { PeriodoContexto::set(null); }
    }
}
