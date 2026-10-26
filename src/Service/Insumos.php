<?php
declare(strict_types=1);
namespace App\Service;
use App\Integration\Totvs\GatewayInterface;

final class Insumos
{
    public function __construct(private GatewayInterface $gateway) {}

    public function saveBatch(int $actor,array $input): array
    {
        $user=Access::actor($actor);$id=$input['atividade_id']??null;
        if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1||!Access::table('Atividades')->exists(['id'=>(int)$id]))throw new \DomainException('Selecione uma O.S. válida.');
        $activity=(new OperacaoRapida())->activity($user,(int)$id);
        PeriodoContexto::writable((int)$activity->entressafra_id);
        $sector=Access::table('Setores')->get($activity->setor_executante_id);
        if($activity->identificacao_setor!=='area_totvs'||!$sector->ativo||$sector->area_totvs!==$activity->area_executante_totvs||$sector->filial_totvs!==$activity->os_filial)throw new \DomainException('Mapeamento do setor executante pendente ou divergente. Solicite conferência ao PCM.');
        $source=$this->gateway->order($activity->os_chave);
        if(!\App\Integration\Totvs\Orders::available($source)||($source['numero']??null)!==$activity->os_numero||($source['filial']??null)!==$activity->os_filial||($source['codigo_area']??null)!==$activity->area_executante_totvs)throw new \DomainException('O.S. indisponível ou divergente no TOTVS.');
        $rows=$input['insumos']??null;
        if(!is_array($rows)||!$rows||!array_is_list($rows))throw new \DomainException('Adicione pelo menos um insumo.');
        if(isset($input['quantidade_linhas'])&&(string)$input['quantidade_linhas']!==(string)count($rows))throw new \DomainException('Formulário incompleto. Nenhum insumo foi salvo.');
        $errors=[];$prepared=[];
        foreach($rows as $index=>$row){
            try{
                if(!is_array($row))throw new \DomainException('Linha inválida.');
                foreach(['funcionario_chave','data','material','quantidade'] as $field)if(!isset($row[$field])||!is_string($row[$field])||trim($row[$field])==='')throw new \DomainException('Preencha funcionário, data, material e quantidade.');
                $date=\DateTimeImmutable::createFromFormat('!d/m/Y',$row['data']);
                if(!$date||$date->format('d/m/Y')!==$row['data']||!checkdate((int)$date->format('m'),(int)$date->format('d'),(int)$date->format('Y')))throw new \DomainException('Informe uma data válida (DD/MM/AAAA).');
                $material=trim($row['material']);if(mb_strlen($material)>500)throw new \DomainException('Material: máximo de 500 caracteres.');
                $quantity=str_replace(',','.',trim($row['quantidade']));
                if(!preg_match('/^\d{1,14}(?:\.\d{1,4})?$/D',$quantity)||!preg_match('/[1-9]/',$quantity))throw new \DomainException('Quantidade deve ser maior que zero, com até 4 casas decimais.');
                $person=$this->gateway->employee($row['funcionario_chave']);
                foreach(['chave','nome','matricula','filial','vinculo'] as $field){if(!isset($person[$field])||!is_string($person[$field])||($field!=='filial'&&trim($person[$field])==='')||strlen($person[$field])>($field==='matricula'||$field==='filial'?100:255))throw new \DomainException('Identificação do funcionário incompleta ou ambígua.');}
                if(($person['ativo']??false)!==true||$person['chave']!==$row['funcionario_chave'])throw new \DomainException('Selecione um funcionário NORMAL e com matrícula válida.');
                $prepared[]=['atividade_id'=>$activity->id,'funcionario_chave'=>$person['chave'],'funcionario_nome'=>$person['nome'],'funcionario_matricula'=>$person['matricula'],'data'=>$date->format('Y-m-d'),'material'=>$material,'quantidade'=>$quantity,'snapshot'=>json_encode(['funcionario'=>$person,'os_numero'=>$activity->os_numero,'os_filial'=>$activity->os_filial,'entressafra_id'=>$activity->entressafra_id,'setor_executante_id'=>$activity->setor_executante_id],JSON_THROW_ON_ERROR),'criado_por'=>$user->id,'created'=>gmdate('Y-m-d H:i:s')];
            }catch(\DomainException $e){$errors[$index]=$e->getMessage();}
        }
        if($errors)throw new BatchEntryException($errors);
        $table=Access::table('Insumos');
        return $table->getConnection()->transactional(function()use($table,$prepared,$user,$activity):array{
            if($table->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres)$table->getConnection()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[(int)$activity->entressafra_id]);
            PeriodoContexto::writable((int)$activity->entressafra_id);
            (new OperacaoRapida())->activity(Access::actor((int)$user->id),(int)$activity->id);
            $ids=[];foreach($prepared as $data){$entity=$table->saveOrFail(Access::entity($table,$data));$ids[]=(int)$entity->id;Access::audit($user,'insumo_criacao',null,null,['insumo_id'=>$entity->id]+$entity->toArray(),null,(int)$entity->id);}
            return $ids;
        });
    }
}
