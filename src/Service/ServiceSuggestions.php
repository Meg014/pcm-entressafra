<?php
declare(strict_types=1);
namespace App\Service;

final class ServiceSuggestions
{
    public static function normalize(string $text): string
    {
        $text=\Normalizer::normalize($text,\Normalizer::FORM_D);
        if($text===false)throw new \DomainException('Descrição inválida.');
        return mb_strtolower(trim(preg_replace('/\s+/u',' ',preg_replace('/\p{Mn}/u','',$text))));
    }
    public static function prefix(string $text): string
    {
        return str_replace(['!','%','_'],['!!','!%','!_'],self::normalize($text)).'%';
    }
    public function search(string $term): array
    {
        if(mb_strlen($term)>100||mb_strlen(self::normalize($term))<2)return [];
        return Access::table('ServicosSugeridos')->getConnection()->execute("SELECT id,descricao FROM servicos_sugeridos WHERE ativo=TRUE AND descricao_busca LIKE ? ESCAPE '!' ORDER BY descricao_busca,id LIMIT 10",[self::prefix($term)])->fetchAll('assoc');
    }
    public function save(int $actor,array $input,?int $id=null): int { return $this->write($actor,$input,$id,false); }
    public function setActive(int $actor,int $id,mixed $active): int { return $this->write($actor,['ativo'=>$active],$id,true); }

    private function write(int $actor,array $input,?int $id,bool $onlyActive): int
    {
        $user=Access::actor($actor);Access::admin($user);$table=Access::table('ServicosSugeridos');
        try{
            return $table->getConnection()->transactional(function()use($table,$user,$input,$id,$onlyActive):int{
                if($id!==null&&$table->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres)$table->getConnection()->execute('SELECT id FROM servicos_sugeridos WHERE id=? FOR UPDATE',[$id]);
                $row=$id!==null?$table->get($id):Access::entity($table,[]);$before=$id!==null?$row->toArray():null;
                $active=$input['ativo']??'1';if(!in_array($active,['0','1',0,1,false,true],true))throw new \DomainException('Situação inválida.');
                $description=$onlyActive?$row->descricao:($input['descricao']??null);
                if(!is_string($description)||trim($description)===''||mb_strlen($description)>500)throw new \DomainException('Informe uma descrição com até 500 caracteres.');
                $description=trim($description);$key=self::normalize($description);
                if($key===''||mb_strlen($key)>500)throw new \DomainException('Informe uma descrição válida com até 500 caracteres.');
                $query=$table->find()->where(['descricao_busca'=>$key]);if($id!==null)$query->where(['id !='=>$id]);
                if($query->count())throw new \DomainException('Já existe uma sugestão equivalente, inclusive entre as desativadas. Edite ou reative o cadastro existente.');
                $now=gmdate('Y-m-d H:i:s');$row->patch(['descricao'=>$description,'descricao_busca'=>$key,'ativo'=>in_array($active,['1',1,true],true),'modified'=>$now]);if($id===null)$row->created=$now;
                $table->saveOrFail($row);
                Access::audit($user,$onlyActive?'servico_sugestao_situacao':($id===null?'servico_sugestao_criacao':'servico_sugestao_edicao'),null,$before,['servico_sugerido_id'=>$row->id]+$row->toArray(),null);
                return (int)$row->id;
            });
        }catch(\Cake\Database\Exception\QueryException $e){
            if(str_contains($e->getMessage(),'23505')||str_contains($e->getMessage(),'UNIQUE constraint failed'))throw new \DomainException('Já existe uma sugestão equivalente.');
            throw $e;
        }
    }
}
