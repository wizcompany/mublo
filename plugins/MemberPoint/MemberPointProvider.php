<?php
namespace Mublo\Plugin\MemberPoint;

use Mublo\Contract\DataResettableInterface;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Infrastructure\Database\Database;
use Mublo\Service\Balance\BalanceManager;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Plugin\MemberPoint\Repository\MemberPointConfigRepository;
use Mublo\Plugin\MemberPoint\Service\MemberPointConfigService;
use Mublo\Plugin\MemberPoint\Service\MemberPointService;
use Mublo\Plugin\MemberPoint\Subscriber\MemberEventSubscriber;

class MemberPointProvider implements ExtensionProviderInterface, DataResettableInterface
{
    public function register(DependencyContainer $container): void
    {
        $container->singleton(MemberPointConfigRepository::class, fn($c) =>
            new MemberPointConfigRepository($c->get(Database::class))
        );

        $container->singleton(MemberPointConfigService::class, fn($c) =>
            new MemberPointConfigService($c->get(MemberPointConfigRepository::class))
        );

        $container->singleton(MemberPointService::class, fn($c) =>
            new MemberPointService(
                $c->get(BalanceManager::class),
                $c->get(MemberPointConfigService::class),
            )
        );
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 관리자 메뉴 (DB 접근 불필요, 항상 등록)
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());

        // 포인트 이벤트 Subscriber (마이그레이션 미실행 시 설정 테이블 접근 실패 가능)
        try {
            $pointService     = $container->get(MemberPointService::class);
            $memberRepository = $container->get(MemberRepository::class);

            $eventDispatcher->addSubscriber(new MemberEventSubscriber($pointService, $memberRepository));
        } catch (\Throwable $e) {
            error_log('[MemberPoint] boot subscriber registration failed: ' . $e->getMessage());
        }
    }

    public function getResetCategories(): array
    {
        return [
            [
                'key' => 'memberpoint',
                'label' => '포인트 내역',
                'description' => '회원 포인트 적립/차감 내역을 삭제합니다. (설정은 보존)',
                'icon' => 'bi-coin',
            ],
        ];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        if ($category !== 'memberpoint') {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '알 수 없는 카테고리'];
        }

        $cleared = 0;

        if ($this->tableExists($db, 'balance_logs')) {
            $db->execute("DELETE FROM balance_logs WHERE domain_id = ?", [$domainId]);
            $cleared++;
        }

        return ['tables_cleared' => $cleared, 'files_deleted' => 0, 'details' => '포인트 내역 삭제 (설정 보존)'];
    }

    private function tableExists(Database $db, string $table): bool
    {
        try {
            $db->selectOne("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
