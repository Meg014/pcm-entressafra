<?php
declare(strict_types=1);

namespace App\Service;

/** Classification affects only Curva S. Percentage input and TOTVS are untouched. */
final class CurvaMecanica
{
    public const GROUPS = ['redutores'=>'Mecânica — Redutores','bombas'=>'Mecânica — Bombas / Demais','geral'=>'Mecânica Geral'];

    private function db() { return Access::table('Atividades')->getConnection(); }

    public function isMechanical(int $season, int $area): bool
    {
        PeriodoContexto::season($season);
        $name=(new CurvaSemanal())->areas($season)[$area]??'';
        return strtoupper(\Cake\Utility\Text::transliterate(trim($name)))==='MECANICA';
    }

    public function validate(int $season, int $area, string $group): void
    {
        if (!isset(self::GROUPS[$group])) throw new \DomainException('Curva da Mecânica inválida.');
        if ($group!=='geral'&&!$this->isMechanical($season,$area)) throw new \DomainException('A divisão das curvas é exclusiva da MECÂNICA.');
    }

    public function orders(int $season, int $area): array
    {
        if (!$this->isMechanical($season,$area)) throw new \DomainException('Selecione o setor MECÂNICA da entressafra atual.');
        [$where,$params]=(new Curva())->scope($season,(string)$area);
        return $this->db()->execute('SELECT a.id,a.os_numero,a.os_filial,a.os_chave,a.descricao_atividade FROM atividades a LEFT JOIN cadernos c ON c.id=a.caderno_id WHERE '.$where.' ORDER BY a.os_numero,a.os_filial,a.id',$params)->fetchAll('assoc');
    }

    public function versions(int $season, int $area): array
    {
        PeriodoContexto::season($season);
        return $this->db()->execute('SELECT * FROM mecanica_curva_versoes WHERE entressafra_id=? AND area_curva_id=? ORDER BY id DESC',[$season,$area])->fetchAll('assoc');
    }

    public function version(int $season, int $area, ?string $asOf=null): ?array
    {
        PeriodoContexto::season($season);
        return $this->db()->execute('SELECT * FROM mecanica_curva_versoes WHERE entressafra_id=? AND area_curva_id=?'.($asOf!==null?' AND created<=?':'').' ORDER BY id DESC LIMIT 1', $asOf!==null?[$season,$area,$asOf]:[$season,$area])->fetch('assoc')?:null;
    }

    private static function identity(array $row): string
    {
        return (string)$row['os_filial']."\0".(string)$row['os_chave'];
    }

    public function redutorIds(int $season, int $area, ?string $asOf=null): array
    {
        $version=$this->version($season,$area,$asOf);
        $saved=$version?json_decode($version['conteudo'],true,512,JSON_THROW_ON_ERROR):[];
        $identities=[];foreach($saved as $row)$identities[self::identity($row)]=true;
        return array_map(fn($row)=>(int)$row['id'],array_values(array_filter($this->orders($season,$area),fn($row)=>isset($identities[self::identity($row)]))));
    }

    public function counts(int $season, int $area): array
    {
        $total=count($this->orders($season,$area));$redutores=count($this->redutorIds($season,$area));
        return ['redutores'=>$redutores,'bombas'=>$total-$redutores,'geral'=>$total];
    }

    /** Save a complete selection atomically. base prevents lost changes between administrators. */
    public function save(int $actorId, int $season, int $area, array $ids, string $pasted, int $base): int
    {
        $actor=Access::actor($actorId);Access::admin($actor);PeriodoContexto::writable($season);
        if(strlen($pasted)>100000||count($ids)>10000)throw new \DomainException('Lista muito extensa: até 10.000 O.S. por alteração.');
        return $this->db()->transactional(function()use($actor,$season,$area,$ids,$pasted,$base):int {
            if($this->db()->getDriver() instanceof \Cake\Database\Driver\Postgres)$this->db()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season]);
            PeriodoContexto::writable($season);$orders=$this->orders($season,$area);$old=$this->version($season,$area);
            if((int)($old['id']??0)!==$base)throw new \DomainException('A lista foi alterada por outra pessoa. Recarregue a página e confira as alterações antes de salvar.');
            $byId=array_column($orders,null,'id');$selected=[];$errors=[];
            foreach($ids as $id){
                if(!is_scalar($id)||!ctype_digit((string)$id)||!isset($byId[(int)$id])){$errors[]='O.S. selecionada inválida: deve ser programada, da Mecânica e da entressafra atual.';continue;}
                $selected[(int)$id]=$byId[(int)$id];
            }
            foreach(preg_split('/[\r\n,;]+/',trim($pasted),-1,PREG_SPLIT_NO_EMPTY) as $token){
                $token=trim($token);if($token==='')continue;
                $parts=explode('/',$token);$filial=count($parts)===2?trim($parts[0]):null;$number=trim(end($parts));
                if(count($parts)>2||!preg_match('/^[0-9]+$/D',$number)||($filial!==null&&!preg_match('/^[a-zA-Z0-9]+$/D',$filial))){$errors[]='Formato inválido: '.$token.'. Use número ou filial/número.';continue;}
                $matches=array_values(array_filter($orders,fn($row)=>(string)$row['os_numero']===$number&&($filial===null||(string)$row['os_filial']===$filial)));
                if(!$matches){$errors[]='Não encontrada entre as O.S. programadas da Mecânica nesta entressafra: '.$token;continue;}
                if(count($matches)>1){$errors[]='O.S. ambígua: '.$token.'. Informe filial/número ou selecione a linha com a filial correta.';continue;}
                $selected[(int)$matches[0]['id']]=$matches[0];
            }
            if($errors)throw new \DomainException(implode("\n",array_unique($errors)));
            ksort($selected);$content=[];
            foreach($selected as $row)$content[]=['atividade_id'=>(int)$row['id'],'entressafra_id'=>$season,'os_filial'=>(string)$row['os_filial'],'os_chave'=>(string)$row['os_chave'],'os_numero'=>(string)$row['os_numero']];
            $json=json_encode($content,JSON_THROW_ON_ERROR);
            if($old&&$old['conteudo']===$json)return (int)$old['id'];
            $this->db()->insert('mecanica_curva_versoes',['entressafra_id'=>$season,'area_curva_id'=>$area,'usuario_id'=>$actor->id,'usuario_nome'=>$actor->nome,'conteudo'=>$json,'created'=>gmdate('Y-m-d H:i:s')]);
            $version=$this->version($season,$area);$id=(int)$version['id'];
            Access::audit($actor,'mecanica_redutores_classificacao',null,$old?['versao_id'=>(int)$old['id'],'redutores'=>json_decode($old['conteudo'],true,512,JSON_THROW_ON_ERROR)]:null,['versao_id'=>$id,'entressafra_id'=>$season,'area_curva_id'=>$area,'redutores'=>$content],'Classificação manual das O.S. de Redutores; demais programadas permanecem em Bombas / Demais.');
            return $id;
        });
    }
}
