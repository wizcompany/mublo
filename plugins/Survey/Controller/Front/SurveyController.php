<?php
namespace Mublo\Plugin\Survey\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Core\Response\ViewResponse;
use Mublo\Plugin\Survey\Entity\Survey;
use Mublo\Plugin\Survey\Service\SurveyService;
use Mublo\Plugin\Survey\Service\SurveySubmitService;
use Mublo\Service\Auth\AuthService;

class SurveyController
{
    private const VIEW_PATH = MUBLO_PLUGIN_PATH . '/Survey/views/Front/';

    public function __construct(
        private SurveyService       $surveyService,
        private SurveySubmitService $submitService,
        private AuthService         $authService,
    ) {}

    /** 설문 폼 독립 페이지 */
    public function show(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $surveyId = (int) ($params['id'] ?? 0);
        $domainId = $context->getDomainId() ?? 1;
        $request  = $context->getRequest();

        $result = $this->surveyService->getDetail($domainId, $surveyId);
        if ($result->isFailure()) {
            return RedirectResponse::to('/');
        }

        $survey = Survey::fromArray($result->get('survey'));
        if ($survey->getStatus()->value === 'draft') {
            return RedirectResponse::to('/');
        }

        $memberId = $this->authService->id();
        $ip       = $request->getClientIp();

        $canJoin = $this->submitService->canParticipate($surveyId, $domainId, $memberId, $ip);

        return ViewResponse::absoluteView(self::VIEW_PATH . 'Survey')->withData([
            'survey'        => $result->get('survey'),
            'questions'     => $result->get('questions', []),
            'responseCount' => $result->get('responseCount', 0),
            'canJoin'       => $canJoin->isSuccess(),
            'joinMessage'   => $canJoin->isFailure() ? $canJoin->getMessage() : '',
        ]);
    }

    /** 설문 제출 (AJAX JSON) */
    public function submit(array $params, Context $context): JsonResponse
    {
        $surveyId = (int) ($params['id'] ?? 0);
        $domainId = $context->getDomainId() ?? 1;
        $request  = $context->getRequest();

        $memberId = $this->authService->id();
        $ip       = $request->getClientIp();

        $payload = $request->json() ?? [];
        $answers = $payload['answers'] ?? [];

        $normalized = [];
        foreach ($answers as $qid => $val) {
            $normalized[(int) $qid] = $val;
        }

        $result = $this->submitService->submit($surveyId, $domainId, $normalized, $memberId, $ip);

        return $result->isSuccess()
            ? JsonResponse::success([], $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }
}
