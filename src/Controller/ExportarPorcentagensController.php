<?php
declare(strict_types=1);
namespace App\Controller;

use App\Service\{Access, EvolucaoSemanal, ExportarPorcentagens, PeriodoContexto};

class ExportarPorcentagensController extends AppController
{
    public function index()
    {
        $this->request->allowMethod(['get']);
        Access::admin($this->currentUser);
        $this->response = $this->response->withHeader('Cache-Control', 'no-store, private');
        $seasons = Access::table('Entressafras')->find()->orderBy(['nome'=>'DESC'])->all()->indexBy('id')->toArray();
        $active = array_filter($seasons, static fn($s) => $s->status === 'ativa');
        $default = $active ? (int)array_key_first($active) : 0;
        $readId = function(string $key, int $default): int {
            $value = $this->request->getQuery($key, (string)$default);
            if ($key === 'area_id' && $value === '') return 0;
            if (!is_string($value) || !ctype_digit($value)) throw new \Cake\Http\Exception\BadRequestException('Seleção inválida.');
            return (int)$value;
        };
        $season = $readId('entressafra_id', $default);
        $area = $readId('area_id', 0);
        if (!isset($seasons[$season])) throw new \Cake\Http\Exception\BadRequestException('Selecione uma entressafra válida.');
        PeriodoContexto::set($season);
        $areas = (new EvolucaoSemanal())->areas($this->currentUser, $season);
        if ($area && !isset($areas[$area])) throw new \Cake\Http\Exception\ForbiddenException('Setor fora da entressafra selecionada.');
        if ($this->request->getQuery('baixar') === '1') {
            if (!$area) throw new \Cake\Http\Exception\BadRequestException('Selecione o setor.');
            $book = (new ExportarPorcentagens())->workbook($this->currentUser, $season, $area);
            $stream = fopen('php://temp', 'w+b');
            try {
                (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($stream);
                rewind($stream);
                $body = stream_get_contents($stream);
            } finally {
                fclose($stream);
                $book->disconnectWorksheets();
            }
            return $this->response->withType('xlsx')->withDownload('porcentagens-'.$season.'-'.$area.'.xlsx')->withStringBody($body);
        }
        $this->set(compact('seasons', 'season', 'areas', 'area'));
    }
}
