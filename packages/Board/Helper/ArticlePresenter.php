<?php

namespace Mublo\Packages\Board\Helper;

use Mublo\Packages\Board\Enum\ArticleStatus;
use Mublo\Helper\String\StringHelper;

/**
 * ArticlePresenter
 *
 * DB 원본 데이터를 스킨 개발자가 바로 사용할 수 있는 표시용 데이터로 변환.
 *
 * 설계 원칙:
 * - 저비용 변환만 수행 (문자열 포맷, 날짜 변환 등)
 * - 고비용 변환(HTML 파싱 등)은 ViewContentHelper에서 on-demand
 * - 보안 필드(author_password, ip_address) 자동 제거
 * - 게시판 설정(boardConfig)을 주입받아 마스킹/신규 기준 등에 활용
 *
 * 사용:
 * ```php
 * // Controller에서 (게시판별 설정 주입)
 * $presenter = new ArticlePresenter($data['board']);
 * $items = $presenter->toList($data['items'], $boardSlug);
 * $article = $presenter->toView($data['article'], $boardSlug);
 *
 * // 커뮤니티 (여러 게시판 혼합, 기본 설정)
 * $presenter = new ArticlePresenter();
 * $items = $presenter->toCommunityList($data['items']);
 * ```
 *
 * 스킨에서:
 * ```php
 * <?= $item['author_name'] ?>          // 글쓴이 원본 (회원: 닉네임, 비회원: 입력값)
 * <?= $item['author_name_masked'] ?>   // 글쓴이 마스킹
 * <?= $item['date_relative'] ?>
 * <a href="<?= $item['url'] ?>"><?= $item['title_safe'] ?></a>
 * ```
 */
class ArticlePresenter
{
    /**
     * 게시판 설정 (BoardConfig::toArray() 결과)
     *
     * 활용 가능한 키:
     * - new_threshold (초): 신규 글 기준 시간 (기본: 86400 = 24시간)
     * - 향후: 마스킹 정책, 날짜 포맷 커스텀 등
     */
    private array $boardConfig;

    public function __construct(array $boardConfig = [])
    {
        $this->boardConfig = $boardConfig;
    }

    /* =========================================================
     * Public API
     * ========================================================= */

    /**
     * 목록용 변환
     *
     * @param array $items 게시글 배열 목록 (toArray() 결과)
     * @param string $boardSlug 게시판 슬러그
     * @return array 변환된 게시글 배열 목록
     */
    public function toList(array $items, string $boardSlug): array
    {
        return array_map(
            fn(array $item) => $this->transform($item, $boardSlug),
            $items
        );
    }

    /**
     * 상세용 변환
     *
     * @param array $article 게시글 배열 (toArray() 결과)
     * @param string $boardSlug 게시판 슬러그
     * @return array 변환된 게시글 배열
     */
    public function toView(array $article, string $boardSlug): array
    {
        return $this->transform($article, $boardSlug);
    }

    /**
     * 이전/다음 글 변환
     *
     * @param array|null $article 게시글 배열 (null이면 null 반환)
     * @param string $boardSlug 게시판 슬러그
     * @return array|null 변환된 게시글 배열
     */
    public function toAdjacent(?array $article, string $boardSlug): ?array
    {
        if ($article === null) {
            return null;
        }

        return $this->transform($article, $boardSlug);
    }

    /**
     * 커뮤니티 목록용 변환
     *
     * Repository가 반환하는 복합 구조를 처리:
     * ['article' => BoardArticle, 'board_slug' => ...]
     *
     * @param array $items 커뮤니티 아이템 배열
     * @return array 변환된 게시글 배열 목록
     */
    public function toCommunityList(array $items): array
    {
        return array_map(function (array $item) {
            // Entity → 배열
            $article = $item['article']->toArray();

            $boardSlug = $item['board_slug'] ?? '';
            $transformed = $this->transform($article, $boardSlug);

            // 커뮤니티 전용 필드 추가
            $transformed['board_name'] = $item['board_name'] ?? '';
            $transformed['board_slug'] = $item['board_slug'] ?? '';
            $transformed['group_name'] = $item['group_name'] ?? '';
            $transformed['group_slug'] = $item['group_slug'] ?? '';

            return $transformed;
        }, $items);
    }

    /* =========================================================
     * 변환 로직
     * ========================================================= */

    /**
     * 공통 변환 (목록/상세/이전다음 공용)
     */
    private function transform(array $item, string $boardSlug): array
    {
        // === 보안 필드 제거 ===
        unset($item['author_password'], $item['ip_address']);

        // === 작성자 ===
        $item = array_merge($item, $this->buildAuthorFields($item));

        // === 날짜 (7가지 포맷) ===
        $createdAt = $item['created_at'] ?? '';
        $item = array_merge($item, $this->buildDateFields($createdAt));

        // === URL ===
        $articleId = $item['article_id'] ?? 0;
        $slug = $item['slug'] ?? '';
        $item['url'] = "/board/{$boardSlug}/view/{$articleId}"
            . ($slug !== '' ? '/' . urlencode($slug) : '');
        $item['edit_url'] = "/board/{$boardSlug}/edit/{$articleId}";

        // === 통계 포맷 ===
        $item['view_count_formatted'] = number_format((int) ($item['view_count'] ?? 0));
        $item['comment_count_formatted'] = number_format((int) ($item['comment_count'] ?? 0));
        $item['reaction_count_formatted'] = number_format((int) ($item['reaction_count'] ?? 0));

        // === 상태 ===
        $status = ArticleStatus::tryFrom($item['status'] ?? 'published');
        $item['status_label'] = $status?->label() ?? ($item['status'] ?? 'published');
        $item['badges'] = $this->buildBadges($item);
        $item['is_new'] = $this->isNew($createdAt);
        $item['is_updated'] = ($item['created_at'] ?? '') !== ($item['updated_at'] ?? '');

        // === 보안 (HTML escape) ===
        $item['title_safe'] = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');

        return $item;
    }

    /* =========================================================
     * 작성자
     * ========================================================= */

    /**
     * 작성자 필드 생성 (스킨 제작자가 선택하여 사용)
     *
     * author_name은 board_articles에 항상 저장됨:
     * - 회원: 작성 시점의 닉네임
     * - 비회원: 입력한 이름
     *
     * | 필드 | 회원 | 비회원 | 설명 |
     * |------|------|--------|------|
     * | author_name | '홍길동' | '손님이름' | 글쓴이 (escaped) |
     * | author_name_masked | '홍**' | '손**름' | 마스킹된 글쓴이 (escaped) |
     * | is_member | true | false | 회원 여부 |
     */
    private function buildAuthorFields(array $item): array
    {
        $esc = fn(?string $v): ?string =>
            $v !== null ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : null;

        $isMember = !empty($item['member_id']);
        $name = $item['author_name'] ?? '익명';

        return [
            'is_member'          => $isMember,
            'author_name'        => $esc($name),
            'author_name_masked' => $esc(StringHelper::mask($name, 1, 0)),
        ];
    }

    /* =========================================================
     * 날짜
     * ========================================================= */

    /**
     * 7가지 날짜 포맷 생성
     *
     * | 키 | 예시 | 용도 |
     * |---|---|---|
     * | date_raw | 2026-02-05 14:30:00 | 커스텀 가공용 |
     * | date_full | 2026-02-05 14:30 | 상세 표시 |
     * | date_short | 2026-02-05 | 날짜만 |
     * | date_compact | 02-05 | 월-일 (목록) |
     * | date_time | 14:30 | 시간만 |
     * | date_relative | 2분 전 / 어제 / 02-05 | 상대시간 |
     * | date_ymd | 26.02.05 | 축약 연도 |
     */
    private function buildDateFields(string $createdAt): array
    {
        $empty = [
            'date_raw'      => '',
            'date_full'     => '',
            'date_short'    => '',
            'date_compact'  => '',
            'date_time'     => '',
            'date_relative' => '',
            'date_ymd'      => '',
        ];

        if ($createdAt === '') {
            return $empty;
        }

        try {
            $dt = new \DateTimeImmutable($createdAt);
        } catch (\Exception) {
            return $empty;
        }

        return [
            'date_raw'      => $dt->format('Y-m-d H:i:s'),
            'date_full'     => $dt->format('Y-m-d H:i'),
            'date_short'    => $dt->format('Y-m-d'),
            'date_compact'  => $dt->format('m-d'),
            'date_time'     => $dt->format('H:i'),
            'date_relative' => $this->relativeTime($dt),
            'date_ymd'      => $dt->format('y.m.d'),
        ];
    }

    /**
     * 상대시간 계산 (한국어)
     *
     * - 60초 미만: '방금 전'
     * - 60분 미만: 'N분 전'
     * - 24시간 미만: 'N시간 전'
     * - 48시간 미만: '어제'
     * - 이후: 월-일 형식
     */
    private function relativeTime(\DateTimeImmutable $dt): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $dt->getTimestamp();

        if ($diff < 0) {
            return $dt->format('m-d');
        }
        if ($diff < 60) {
            return '방금 전';
        }
        if ($diff < 3600) {
            return (int) ($diff / 60) . '분 전';
        }
        if ($diff < 86400) {
            return (int) ($diff / 3600) . '시간 전';
        }
        if ($diff < 172800) {
            return '어제';
        }

        return $dt->format('m-d');
    }

    /* =========================================================
     * 상태 / 배지
     * ========================================================= */

    /**
     * 배지 배열 생성
     *
     * @return string[] 예: ['notice', 'secret', 'new']
     */
    private function buildBadges(array $item): array
    {
        $badges = [];

        if (!empty($item['is_notice'])) {
            $badges[] = 'notice';
        }
        if (!empty($item['is_secret'])) {
            $badges[] = 'secret';
        }
        if ($this->isNew($item['created_at'] ?? '')) {
            $badges[] = 'new';
        }

        return $badges;
    }

    /**
     * 신규 글 여부
     *
     * boardConfig의 new_threshold(초) 사용, 기본 86400(24시간)
     */
    private function isNew(string $createdAt): bool
    {
        if ($createdAt === '') {
            return false;
        }

        try {
            $dt = new \DateTimeImmutable($createdAt);
        } catch (\Exception) {
            return false;
        }

        $threshold = (int) ($this->boardConfig['new_threshold'] ?? 86400);
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $dt->getTimestamp();

        return $diff >= 0 && $diff < $threshold;
    }

    /* =========================================================
     * 설정 접근
     * ========================================================= */

    /**
     * 게시판 설정값 조회
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->boardConfig[$key] ?? $default;
    }
}
