<?php

namespace Mublo\Packages\Shop\Repository;

use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Packages\Shop\Entity\ShippingTemplate;
use Mublo\Repository\BaseRepository;

/**
 * Shipping Repository
 *
 * 배송 템플릿 데이터베이스 접근 담당
 *
 * 책임:
 * - shop_shipping_templates 테이블 CRUD
 * - shop_delivery_companies 테이블 조회
 * - ShippingTemplate Entity 반환
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 */
class ShippingRepository extends BaseRepository
{
    protected string $table = 'shop_shipping_templates';
    protected string $entityClass = ShippingTemplate::class;
    protected string $primaryKey = 'shipping_id';

    private string $deliveryCompaniesTable = 'shop_delivery_companies';

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 도메인별 배송 템플릿 전체 목록
     *
     * @param int $domainId 도메인 ID
     * @return ShippingTemplate[]
     */
    public function getList(int $domainId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->orderBy('shipping_id', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 도메인별 활성 배송 템플릿 목록
     *
     * @param int $domainId 도메인 ID
     * @return ShippingTemplate[]
     */
    public function getActive(int $domainId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('is_active', '=', 1)
            ->orderBy('shipping_id', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 활성 택배사 목록 조회
     *
     * @return array 택배사 목록 (raw arrays)
     */
    public function getDeliveryCompanies(): array
    {
        return $this->getDb()->table($this->deliveryCompaniesTable)
            ->where('is_active', '=', 1)
            ->orderBy('company_id', 'ASC')
            ->get();
    }
}
