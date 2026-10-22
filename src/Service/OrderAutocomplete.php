<?php
declare(strict_types=1);
namespace App\Service;

use App\Integration\Totvs\GatewayInterface;
use App\Integration\Totvs\Orders;
use Cake\Http\Exception\BadRequestException;

final class OrderAutocomplete
{
    public function __construct(private GatewayInterface $totvs) {}

    public function search($user, string $term, array $filters): array
    {
        $ids=[];
        foreach (['entressafra_id','setor_id','caderno_id'] as $field) {
            $value=$filters[$field]??'';
            if ($value!==null && $value!=='') {
                if (!is_string($value) || !ctype_digit($value) || (int)$value<1) { throw new BadRequestException('Filtros inválidos.'); }
                $ids[$field]=(int)$value;
            }
        }
        $sectorIds=Access::sectors($user)?:[-1];
        if (isset($ids['setor_id'])) { $sectorIds=array_values(array_intersect($sectorIds,[$ids['setor_id']]))?:[-1]; }
        $notebooks=Access::table('Cadernos')->find()->contain(['Setores'])->where(['Cadernos.id IN'=>Access::notebookIds($sectorIds),'ativo'=>true]);
        foreach ($ids as $field=>$value) { if ($field!=='setor_id') { $notebooks->where([$field==='caderno_id'?'Cadernos.id':$field=>$value]); } }
        $notebookRows=$notebooks->all()->indexBy('id')->toArray();
        if (isset($ids['caderno_id']) || (isset($ids['entressafra_id']) && PeriodoContexto::id()===null)) {
            $associated=[]; foreach ($notebookRows as $book) { foreach ($book->setores as $sector) { $associated[]=(int)$sector->id; } }
            $sectorIds=array_values(array_intersect($sectorIds,$associated))?:[-1];
        }
        $sectors=Access::table('Setores')->find()->where(['id IN'=>$sectorIds,'ativo'=>true])->all();
        $results=[]; $seen=[]; $configured=0; $hasMore=false;
        foreach ($sectors as $sector) {
            if (trim((string)$sector->area_totvs)==='' || $sector->filial_totvs===null) { continue; }
            ++$configured;
            $rows=$this->totvs->orders($term,(string)$sector->area_totvs,(string)$sector->filial_totvs);
            if (count($rows)>20) { $hasMore=true; }
            foreach ($rows as $row) {
                if (!Orders::available($row) || ($row['codigo_area']??null)!==$sector->area_totvs || ($row['filial']??null)!==$sector->filial_totvs) { continue; }
                $identity=Orders::key($row['filial'],$row['numero']);
                if (isset($seen[$identity])) { continue; }
                $seen[$identity]=true;
                $activity=Access::table('Atividades')->find()->where(['os_chave'=>$row['chave'],'os_numero'=>$row['numero'],'os_filial'=>$row['filial']])->first();
                $notebook=$activity ? ($notebookRows[$activity->caderno_id]??null) : null;
                $selectable=$notebook && (int)$activity->setor_executante_id===(int)$sector->id && $activity->identificacao_setor==='area_totvs' && $activity->area_executante_totvs===$row['codigo_area'] && $activity->disponivel_totvs===true;
                $results[]=[
                    'id'=>$selectable?(string)$activity->id:null, 'selecionavel'=>(bool)$selectable,
                    'numero'=>$row['numero'],'filial'=>$row['filial'],'servico'=>$row['servico'],
                    'bem'=>$row['bem']??'','codigo_area'=>$row['codigo_area'],'centro_custo'=>$row['centro_custo']??'',
                    'situacao'=>$row['situacao'],'setor'=>$sector->nome,'setor_id'=>(string)$sector->id,
                    'caderno'=>$notebook?->nome??'Sem vínculo disponível neste caderno/entressafra',
                    'aviso'=>$selectable?null:'O.S. encontrada no TOTVS, mas sem vínculo disponível nestes filtros. Solicite ao PCM o vínculo ao caderno correto ou a sincronização da atividade.',
                ];
            }
        }
        usort($results,fn($a,$b)=>strcmp($a['numero'].'/'.$a['filial'],$b['numero'].'/'.$b['filial']));
        return ['results'=>array_slice($results,0,20),'has_more'=>$hasMore || count($results)>20,
            'message'=>$configured?'Nenhuma O.S. aberta encontrada no TOTVS para as áreas e filtros autorizados. Para realizar apontamentos, solicite o vínculo ao caderno correto.':'Configure Área TOTVS e Filial nos setores autorizados e confira os filtros de entressafra/caderno. Solicite a configuração ao PCM.'];
    }
}
