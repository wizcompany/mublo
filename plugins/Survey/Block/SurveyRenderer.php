<?php
namespace Mublo\Plugin\Survey\Block;

use Mublo\Core\Block\Renderer\RendererInterface;
use Mublo\Core\Block\Renderer\SkinRendererTrait;
use Mublo\Entity\Block\BlockColumn;
use Mublo\Plugin\Survey\Entity\Survey;
use Mublo\Plugin\Survey\Service\SurveyResultService;
use Mublo\Plugin\Survey\Service\SurveyService;
use Mublo\Plugin\Survey\Service\SurveySubmitService;

class SurveyRenderer implements RendererInterface
{
    use SkinRendererTrait;

    public function __construct(
        private SurveyService       $surveyService,
        private SurveyResultService $resultService,
        private SurveySubmitService $submitService,
    ) {}

    protected function getSkinType(): string
    {
        return 'survey';
    }

    protected function getSkinBasePath(): string
    {
        return MUBLO_PLUGIN_PATH . '/Survey/views/Block/';
    }

    public function render(BlockColumn $column): string
    {
        $items = $column->getContentItems() ?? [];
        if (empty($items)) {
            return '';
        }

        $first    = $items[0];
        $surveyId = (int) (is_array($first) ? ($first['id'] ?? 0) : $first);
        if ($surveyId === 0) {
            return '';
        }

        $domainId = (int) ($column->getDomainId() ?? 1);
        $config   = $column->getContentConfig() ?? [];
        $mode     = $config['display_mode'] ?? 'form';
        $skin     = $column->getContentSkin() ?: 'basic';

        if ($mode === 'result') {
            return $this->renderResult($column, $skin, $domainId, $surveyId, $config);
        }

        return $this->renderForm($column, $skin, $domainId, $surveyId, $config);
    }

    // -------------------------------------------------------------------------

    private function renderForm(BlockColumn $column, string $skin, int $domainId, int $surveyId, array $config): string
    {
        $result = $this->surveyService->getDetail($domainId, $surveyId);
        if ($result->isFailure()) {
            return '';
        }

        $surveyRow = $result->get('survey');
        $survey    = Survey::fromArray($surveyRow);

        // draft만 출력하지 않음
        if ($survey->getStatus()->value === 'draft') {
            return '';
        }

        // 참여 가능 여부 판단 (closed이거나 기간 외)
        $canJoin     = $survey->isActive() && $survey->isWithinPeriod();
        $joinMessage = '';
        if (!$canJoin) {
            $joinMessage = $survey->isClosed() ? '종료된 설문입니다.' : '설문 참여 기간이 아닙니다.';
        }

        return $this->renderSkin($column, $skin, [
            'mode'        => 'form',
            'survey'      => $surveyRow,
            'questions'   => $result->get('questions', []),
            'config'      => $config,
            'canJoin'     => $canJoin,
            'joinMessage' => $joinMessage,
        ]);
    }

    private function renderResult(BlockColumn $column, string $skin, int $domainId, int $surveyId, array $config): string
    {
        $result = $this->resultService->getStats($domainId, $surveyId);
        if ($result->isFailure()) {
            return '';
        }

        return $this->renderSkin($column, $skin, [
            'mode'           => 'result',
            'survey'         => $result->get('survey'),
            'totalResponses' => $result->get('total_responses', 0),
            'questions'      => $result->get('questions', []),
            'config'         => $config,
        ]);
    }
}
