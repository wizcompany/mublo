<?php
namespace Mublo\Plugin\MemberPoint\Controller\Front;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Core\Context\Context;
use Mublo\Service\Balance\BalanceManager;

class PointController
{
    public function __construct(private BalanceManager $balanceManager) {}

    public function my(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $member = $context->getMember();
        if (!$member) {
            return RedirectResponse::to('/auth/login');
        }

        $memberId = $member->getMemberId();
        $domainId = $context->getDomainId() ?? 1;
        $request  = $context->getRequest();
        $page     = max(1, (int) ($request->get('page') ?? 1));
        $perPage  = 20;

        $totalPoint = $this->balanceManager->getBalance($memberId, $domainId);
        $history    = $this->balanceManager->getHistory($memberId, [], $page, $perPage);

        $points = array_map(fn($log) => [
            'created_at' => $log->getCreatedAt()->format('Y-m-d H:i'),
            'content'    => $log->getMessage(),
            'point'      => $log->getAmount(),
            'balance'    => $log->getBalanceAfter(),
        ], $history['items']);

        return ViewResponse::absoluteView(
            MUBLO_PLUGIN_PATH . '/MemberPoint/views/Front/My'
        )->withData([
            'pageTitle'  => '내 포인트',
            'member'     => $member,
            'totalPoint' => $totalPoint,
            'points'     => $points,
            'pagination' => $history['pagination'],
        ]);
    }
}
