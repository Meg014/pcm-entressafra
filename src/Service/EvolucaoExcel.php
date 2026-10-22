<?php
declare(strict_types=1);
namespace App\Service;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
final class EvolucaoExcel
{
    /** First row: atividade_id,peso OR referencia,percentual. Formula cells are rejected. */
    public function read(string $path,string $type): array
    {
        if (filesize($path)>5*1024*1024) { throw new \DomainException('Planilha limitada a 5 MB.'); }
        $zip=new \ZipArchive();
        if ($zip->open($path)!==true) { throw new \DomainException('Envie uma planilha .xlsx válida.'); }
        $size=0; for($i=0;$i<$zip->numFiles;$i++) { $size+=(int)$zip->statIndex($i)['size']; } $zip->close();
        if ($size>30*1024*1024) { throw new \DomainException('Planilha descompactada excede 30 MB.'); }
        $reader=new Xlsx(); $reader->setReadDataOnly(false); $reader->setLoadSheetsOnly(['Dados']);
        $book=$reader->load($path); $sheet=$book->getSheetByName('Dados');
        if (!$sheet || $sheet->getHighestDataRow()>10001 || $sheet->getHighestDataColumn()!=='B') { throw new \DomainException('Use a aba Dados, com duas colunas e até 10.000 linhas.'); }
        $expected=$type==='pesos'?['atividade_id','peso']:['referencia','percentual'];
        if ([$sheet->getCell('A1')->getValue(),$sheet->getCell('B1')->getValue()]!==$expected) { throw new \DomainException('Cabeçalhos esperados: '.implode(', ',$expected)); }
        $out=[];
        for($r=2;$r<=$sheet->getHighestDataRow();$r++) {
            $a=$sheet->getCell('A'.$r);$b=$sheet->getCell('B'.$r);
            if ($a->getDataType()==='f' || $b->getDataType()==='f') { throw new \DomainException('Fórmulas não são aceitas na importação.'); }
            $key=trim((string)$a->getValue()); $value=trim((string)$b->getValue());
            if ($key==='' && $value==='') { continue; }
            if (isset($out[$key])) { throw new \DomainException('Identificador repetido na planilha.'); }
            if ($type==='pesos' && (!ctype_digit($key) || (int)$key<1)) { throw new \DomainException('Informe o ID da atividade, não o número da O.S.'); }
            $out[$key]=$value;
        }
        $book->disconnectWorksheets(); return $out;
    }
}
