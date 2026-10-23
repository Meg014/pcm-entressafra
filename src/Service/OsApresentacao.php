<?php
declare(strict_types=1);
namespace App\Service;

/** Display-only projection. Never changes source identities or persisted values. */
final class OsApresentacao
{
    public static function equipe(?string $name): string
    {
        return preg_replace('/^TOTVS\s+\d+\s*\/\s*/u','',trim($name??''))?:'Não identificada';
    }
    public static function options($options): \Cake\Collection\Collection
    {
        $out=[];foreach($options as $id=>$name)$out[$id]=self::equipe((string)$name);return new \Cake\Collection\Collection($out);
    }
    public static function texto(string $text): string
    {
        $text=preg_replace('/\bTOTVS\s+\d+\s*\/\s*/u','',$text);
        $text=preg_replace('/\s*[—·]\s*filial\s+\d+\s*\/\s*/iu',' — ',$text);
        return str_replace([' no TOTVS filial 01.','Número/filial ou serviço ambíguo no TOTVS.','Exige filial 01, L/N, origem 2026 e serviço ENTRESSAFRA inequívoco.'],[' no TOTVS.','Identificação ou serviço ambíguo no TOTVS.','O.S. fora dos critérios de disponibilidade permitidos.'],$text);
    }
    public static function description(?string $local,array $source=[]): string
    {
        $value=trim((string)$local);
        if($value==='')$value=trim((string)($source['descricao_totvs']??$source['ultima_sincronizacao']['descricao_totvs']??''));
        if(!mb_check_encoding($value,'UTF-8'))$value=mb_convert_encoding($value,'UTF-8','Windows-1252');
        return $value!==''?self::texto($value):'Descrição não informada';
    }
    public static function activity($activity,array $source=[]): array
    {
        $book=$activity->caderno??null;
        if(is_array($book))$book=new \Cake\ORM\Entity($book);
        if(!$book&&$activity->caderno_id)$book=Access::table('Cadernos')->get($activity->caderno_id);
        $snapshot=$activity->snapshot_totvs;
        if(!is_array($snapshot))$snapshot=json_decode((string)$snapshot,true)??[];
        $description=self::description($activity->descricao_atividade,$source+$snapshot);
        if($book){
            $sector=$book->area_curva_id?Access::table('AreasFabrica')->get($book->area_curva_id)->nome:'Não definido';
            return ['descricao'=>$description,'setor_apresentacao'=>$sector,'rotulo_setor'=>'Setor'];
        }
        $executor=$activity->setor_executante;
        $team=is_array($executor)?($executor['nome']??null):($executor?->nome??null);
        if(!$team&&$activity->setor_executante_id)$team=Access::table('Setores')->get($activity->setor_executante_id)->nome;
        return ['descricao'=>$description,'setor_apresentacao'=>self::equipe($team?:$activity->area_executante_totvs),'rotulo_setor'=>'Equipe executante (sem caderno)'];
    }
    public static function source(array $row,?string $team=null): array
    {
        return ['descricao'=>self::description(null,$row),'setor_apresentacao'=>self::equipe($team??$row['codigo_area']??null),'rotulo_setor'=>'Equipe executante (sem caderno)'];
    }
    public static function record(array $row): array
    {
        $activity=array_key_exists('caderno_id',$row)?new \Cake\ORM\Entity($row):Access::table('Atividades')->get($row['id']);
        return self::activity($activity);
    }
    public static function audit(?string $json): string
    {
        $clean=function($value)use(&$clean){
            if(is_string($value)){
                $nested=json_decode($value,true);
                return is_array($nested)?$clean($nested):self::texto($value);
            }
            if(!is_array($value))return $value;
            $out=[];foreach($value as $key=>$item){if(preg_match('/filial|centro_custo/i',(string)$key))continue;$out[$key]=$clean($item);}return $out;
        };
        $data=json_decode($json??'',true);
        return is_array($data)?json_encode($clean($data),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE):self::texto($json??'');
    }
}
