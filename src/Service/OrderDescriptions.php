<?php
declare(strict_types=1);
namespace App\Service;

use App\Integration\Totvs\{ListOrders,Orders};

/** Read-only presentation enrichment; never updates activities or their snapshots. */
final class OrderDescriptions
{
    public function __construct(private ListOrders $orders) {}

    public function presentations(array $ids): array
    {
        if (!$ids) { return []; }
        $activities=Access::table('Atividades')->find()->contain(['Cadernos','SetorExecutante'])
            ->where(['Atividades.id IN'=>$ids])->all();
        $groups=[];$sources=[];
        foreach ($activities as $activity) {
            // Local complementary descriptions retain the same priority as Novo apontamento.
            if (trim((string)$activity->descricao_atividade)!=='') { continue; }
            $number=(string)$activity->os_numero;$branch=(string)$activity->os_filial;
            if (!preg_match('/^[0-9]{6}$/D',$number) || strlen($branch)>2 || ($branch!=='' && !ctype_digit($branch))) { continue; }
            $key=Orders::key($branch,$number);
            if ($activity->os_chave!==$key) { continue; }
            $groups[$branch][$number]=$number;
        }
        foreach ($groups as $branch=>$numbers) {
            foreach (array_chunk(array_values($numbers),100) as $chunk) {
                try { $rows=$this->orders->find($chunk,(string)$branch); }
                catch (\DomainException) { continue; } // Keep local/snapshot fallback when TOTVS is unavailable.
                foreach ($chunk as $number) {
                    $row=$rows[$number]??null;
                    if (!$row || ($row['numero']??null)!==$number || ($row['filial']??null)!==(string)$branch ||
                        ($row['chave']??null)!==Orders::key((string)$branch,$number) || (int)($row['identidades']??0)!==1) { continue; }
                    // A blank live description must not erase a historical description.
                    if (trim((string)($row['descricao_totvs']??''))!=='') { $sources[$row['chave']]=$row; }
                }
            }
        }
        $presentations=[];
        foreach ($activities as $activity) {
            $presentations[(int)$activity->id]=OsApresentacao::activity($activity,$sources[$activity->os_chave]??[]);
        }
        return $presentations;
    }
}
