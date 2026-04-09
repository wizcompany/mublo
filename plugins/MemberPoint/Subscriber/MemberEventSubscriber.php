<?php
namespace Mublo\Plugin\MemberPoint\Subscriber;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Service\Member\Event\MemberRegisteredEvent;
use Mublo\Service\Member\Event\MemberUpdatedByAdminEvent;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Plugin\MemberPoint\Service\MemberPointService;

class MemberEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MemberPointService $pointService,
        private MemberRepository $memberRepository,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            MemberRegisteredEvent::class     => 'onMemberRegistered',
            MemberUpdatedByAdminEvent::class => 'onMemberUpdatedByAdmin',
        ];
    }

    public function onMemberRegistered(MemberRegisteredEvent $event): void
    {
        $memberId = $event->getMemberId();
        $domainId = $event->getDomainId();

        $this->pointService->awardSignup($domainId, $memberId);
    }

    public function onMemberUpdatedByAdmin(MemberUpdatedByAdminEvent $event): void
    {
        if (!$event->isLevelChanged()) return;

        // 이벤트의 Member = 업데이트 전 상태
        $oldLevelValue = $event->getMember()->getLevelValue();
        $memberId      = $event->getMemberId();
        $domainId      = $event->getDomainId();

        // DB에서 재조회하여 새 레벨값 확인
        $newMember = $this->memberRepository->find($memberId);
        if (!$newMember) return;

        $newLevelValue = $newMember->getLevelValue();

        // 레벨 상승일 때만 포인트 지급
        if ($newLevelValue <= $oldLevelValue) return;

        $this->pointService->awardLevelUp($domainId, $memberId, $newLevelValue);
    }
}
