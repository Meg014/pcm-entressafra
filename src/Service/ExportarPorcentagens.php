<?php
declare(strict_types=1);
namespace App\Service;

use PhpOffice\PhpSpreadsheet\{Spreadsheet, Cell\Coordinate, Cell\DataType, Style\Fill, Worksheet\PageSetup};

/** Persisted percentages; description fallback uses the same read-only source as the grid. */
final class ExportarPorcentagens
{
    public function __construct(private ?\App\Integration\Totvs\ListOrders $source = null) {}

    public function workbook($user, int $season, int $area, ?\DateTimeImmutable $now = null): Spreadsheet
    {
        Access::admin($user);
        PeriodoContexto::season($season);
        $grid = new EvolucaoSemanal($now);
        $areas = $grid->areas($user, $season);
        if (!isset($areas[$area])) throw new \Cake\Http\Exception\ForbiddenException('Setor fora da entressafra selecionada.');
        $period = Access::table('Entressafras')->get($season);
        $weeks = $grid->weeks($season);
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Porcentagens');
        $lastColumn = Coordinate::stringFromColumnIndex(4 + count($weeks));
        $text = static function(string $cell, string $value) use ($sheet): void {
            $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
        };
        $text('A1', 'Controle de Porcentagens — '.$areas[$area]);
        $text('A2', 'Entressafra: '.$period->nome);
        $text('A3', 'Exportado em: '.($now ?? new \DateTimeImmutable())->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s'));
        foreach ([1,2,3] as $r) $sheet->mergeCells('A'.$r.':'.$lastColumn.$r);
        $headers = ['Número da O.S.', 'Descrição da atividade', 'Equipamento', 'Percentual mais recente'];
        foreach ($weeks as $w) $headers[] = $w['semana'].'ª semana — '.date('d/m/Y', strtotime($w['inicio'])).' a '.date('d/m/Y', strtotime($w['fim']));
        $sheet->fromArray($headers, null, 'A5');
        // Same notebook membership as PorcentagensLote::scope; executor sectors
        // and activity-level Curva S overrides do not define notebook membership.
        $query = Access::table('Atividades')->find()->innerJoinWith('Cadernos', fn($q) => $q->where([
            'Cadernos.entressafra_id'=>$season, 'Cadernos.area_curva_id'=>$area,
        ]))->where([
            'Atividades.entressafra_id'=>$season,
            'Atividades.tipo_planejamento'=>Planejamento::PROGRAMADA,
        ])->orderBy(['Atividades.os_numero'=>'ASC', 'Atividades.id'=>'ASC']);
        $evolution = new Evolucao();
        $evolution->loadHistory($season);
        $row = 6;
        $seen = [];
        $missing = [];
        foreach ($query->all() as $a) {
            $key = $a->os_chave ?: $a->os_filial.':'.$a->os_numero;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $text('A'.$row, (string)$a->os_numero);
            $description = OsApresentacao::description($a->descricao_atividade, json_decode($a->snapshot_totvs ?? '{}', true) ?? []);
            $text('B'.$row, self::cleanDescription($description));
            if ($description === 'Descrição não informada') $missing[$a->os_numero] = $row;
            $sheet->getRowDimension($row)->setRowHeight(-1);
            $text('C'.$row, (string)$a->bem);
            $history = $evolution->history((int)$a->id);
            $latest = null;
            foreach ($weeks as $i=>$week) {
                $cell = EvolucaoSemanal::cell($history, $week);
                if ($cell === null) continue;
                $latest = (float)$cell['percentual'];
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(5+$i).$row, $latest/100);
            }
            if ($latest !== null) $sheet->setCellValue('D'.$row, $latest/100);
            if ($row % 2 === 0) $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
            $row++;
        }
        // Same fallback as EvolucaoSemanal::page, in blocks supported by ListOrders.
        foreach (array_chunk(array_map('strval', array_keys($missing)), 100) as $numbers) {
            try {
                $notes = ($this->source ?? new \App\Integration\Totvs\ListOrders())->find($numbers);
                foreach ($notes as $number => $note) {
                    if (isset($missing[$number])) $text('B'.$missing[$number], self::cleanDescription(OsApresentacao::description(null, $note)));
                }
            } catch (\DomainException) {
                // Keep available local descriptions when the read-only source is unavailable.
            }
        }
        $lastRow = max(5, $row-1);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A5:'.$lastColumn.'5')->applyFromArray(['font'=>['bold'=>true,'color'=>['argb'=>'FFFFFFFF']], 'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF234E70']]]);
        $sheet->getStyle('A5:'.$lastColumn.$lastRow)->getAlignment()->setWrapText(true)->setVertical('center');
        $sheet->getRowDimension(5)->setRowHeight(48);
        foreach (['A'=>18,'B'=>65,'C'=>30,'D'=>22] as $col=>$width) $sheet->getColumnDimension($col)->setWidth($width);
        foreach ($weeks as $i=>$_) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(5+$i))->setWidth(23);
        if ($row > 6) $sheet->getStyle('D6:'.$lastColumn.$lastRow)->getNumberFormat()->setFormatCode('0.##%');
        $sheet->freezePane('E6');
        $sheet->setAutoFilter('A5:'.$lastColumn.$lastRow);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(1,5)->setColumnsToRepeatAtLeftByStartAndEnd('A','C')->setPrintArea('A1:'.$lastColumn.$lastRow);
        return $book;
    }

    /** Remove only recognizable checklist questions followed by checkbox answers. */
    public static function cleanDescription(string $description): string
    {
        // Accent classes keep matches on the original text, preserving all other bytes.
        $questions = [
            '(?:as?\s+)?ferramentas?\s+(?:foram\s+|foi\s+)?limp[ao]s?\s+antes\s+d[oe]\s+servi[çc]o',
            '(?:os?\s+)?equipamentos?\s+(?:foi\s+|foram\s+)?limp[ao]s?\s+(?:ap[oó]s|depois\s+d[oe])\s+(?:o\s+)?servi[çc]o',
            '(?:toda\s+a\s+)?sobras?\s+de\s+materia(?:is|l)\s+(?:foi\s+|foram\s+)?recolhid[ao]s?',
            'prote[çc][oõ]es\s*,?\s*bot[oõ]es\s+e\s+sinaliza[çc][aã]o\s+conforme\s+(?:a\s+)?nr\s*[-–]?\s*12',
            'condi[çc][oõ]es\s+de\s+seguran[çc]a\s+mantidas\s+(?:ap[oó]s|depois\s+d[oe])\s+(?:o\s+)?servi[çc]o',
            '(?:as\s+)?condi[çc][oõ]es\s+de\s+seguran[çc]a\s*\(\s*prote[çc][aã]o\s*[/,]\s*bot[oõ]es\s*[/,]\s*sinaliza[çc][aã]o\s*\)\s*(?:est[aã]o\s+)?conforme\s*\(?\s*nr\s*[-–]?\s*12\s*\)?',
            '(?:a\s+)?conclus[aã]o\s+d[oe]\s+servi[çc]o\s*,?\s*(?:e\s+as\s+condi[çc][oõ]es\s+de\s+seguran[çc]a\s+foram\s+mantidas\s+conforme\s*\(?\s*nr\s*[-–]?\s*12\s*\)?|manteve\s+as\s+condi[çc][oõ]es\s+de\s+seguran[çc]a)',
        ];
        $box = '[\(\[]\s*[xX]?\s*[\)\]]';
        $answers = '\s*'.$box.'\s*sim\s*[,;/]?\s*'.$box.'\s*n[aã]o(?:\s*[,;/]?\s*'.$box.'\s*(?:n[aã]o\s+aplic[aá]vel|n\s*[/\\\\]?\s*a))?';
        // Allow punctuation/line wrapping between words without normalizing useful text.
        $pattern = implode('|', $questions);
        $pattern = str_replace('\\s+', '[\\s\\p{Z},;:]+', $pattern);
        $clean = preg_replace('~\b(?:'.$pattern.')\s*[?.:!]*'.$answers.'[.;]?~iu', '', $description);
        if ($clean === null || $clean === $description) return $description;
        $clean = preg_replace('/[\h]+/u', ' ', $clean);
        $clean = preg_replace('/ *\R */u', "\n", $clean);
        $clean = trim(preg_replace('/\n{2,}/', "\n", $clean));
        // This exact service label is misspelled in the real TOTVS checklist template.
        return preg_match('/^FAZER MANUTEN[ÇC][AÃ]O$/iu', $clean) ? 'FAZER MANUTENÇÃO' : $clean;
    }
}
