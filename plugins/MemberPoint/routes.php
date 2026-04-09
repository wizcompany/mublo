<?php
/**
 * MemberPoint Plugin Routes
 *
 * URL 규칙:
 * - Front: /member-point/...
 * - Admin: /admin/member-point/...
 */

use Mublo\Core\App\PrefixedRouteCollector;
use Mublo\Core\Middleware\AdminMiddleware;

return function (PrefixedRouteCollector $r): void {

    // =========================================================
    // Admin Routes (관리자)
    // =========================================================

    // 포인트 내역 목록
    $r->addRoute('GET', '/admin/history', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\HistoryController::class,
        'method'     => 'index',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 포인트 상세
    $r->addRoute('GET', '/admin/history/{id:\d+}', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\HistoryController::class,
        'method'     => 'view',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 포인트 수동 조정 폼
    $r->addRoute('GET', '/admin/adjust', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\HistoryController::class,
        'method'     => 'adjust',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 포인트 수동 조정 처리
    $r->addRoute('POST', '/admin/adjust', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\HistoryController::class,
        'method'     => 'adjustStore',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 회원 검색 API (자동완성용)
    $r->addRoute('GET', '/admin/search-member', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\HistoryController::class,
        'method'     => 'searchMember',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 무결성 검증 API
    $r->addRoute('GET', '/admin/verify/{memberId:\d+}', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\HistoryController::class,
        'method'     => 'verify',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 포인트 설정 페이지 (구 URL → 회원포인트 설정으로 이동)
    $r->addRoute('GET', '/admin/settings', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\SettingsController::class,
        'method'     => 'index',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 회원포인트 설정
    $r->addRoute('GET', '/admin/member-settings', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\SettingsController::class,
        'method'     => 'memberSettings',
        'middleware' => [AdminMiddleware::class],
    ]);

    $r->addRoute('POST', '/admin/member-settings', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\SettingsController::class,
        'method'     => 'saveMemberSettings',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 플러그인 설치 (마이그레이션 실행)
    $r->addRoute('POST', '/admin/install', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Admin\SettingsController::class,
        'method'     => 'install',
        'middleware' => [AdminMiddleware::class],
    ]);

    // =========================================================
    // Front Routes (사용자)
    // =========================================================

    // 내 포인트 내역
    $r->addRoute('GET', '/my', [
        'controller' => \Mublo\Plugin\MemberPoint\Controller\Front\PointController::class,
        'method'     => 'my',
    ]);
};
