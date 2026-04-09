# 블록 시스템 개발

## 구조

블록 시스템은 3단계 계층으로 페이지를 구성합니다.

```
BlockPage (페이지)
  └── BlockRow (행)
        └── BlockColumn (열)
              └── 콘텐츠 타입 (HTML, 배너, 게시판 최신글 등)
```

- **BlockPage** — 하나의 페이지. 레이아웃(전체/좌측/우측/양측 사이드바), SEO 설정, 접근 레벨
- **BlockRow** — 가로 행. 너비(와이드/콘테이너), 배경색/이미지, 패딩, 정렬
- **BlockColumn** — 행 안의 열. 너비 비율, 콘텐츠 타입, 스킨, 설정

관련 문서:
- [이벤트 시스템](event-system.md)
- [패키지 만들기](package-development.md)
- [플러그인 만들기](plugin-development.md)

## BlockRegistry

`src/Core/Block/BlockRegistry.php`

Plugin/Package가 자신의 콘텐츠 타입을 등록하는 정적 레지스트리입니다.

블록 시스템은 두 가지 확장 축을 함께 사용한다.
- 콘텐츠 타입 자체를 등록할 때: `BlockRegistry`
- 블록 페이지/아이템 수집 흐름에 반응할 때: 코어 이벤트

### 콘텐츠 타입 등록

```php
BlockRegistry::registerContentType(
    type: 'board',                              // 타입 코드 (고유)
    kind: BlockContentKind::PACKAGE->value,     // 'core', 'plugin', 'package'
    title: '게시판 최신글',                       // 관리자 표시명
    rendererClass: BoardRenderer::class,        // RendererInterface 구현 클래스
    configFormClass: BoardConfigForm::class,     // 설정 폼 클래스 (선택)
    options: [
        'hasItems'      => true,                // 항목 선택 UI (DualListbox)
        'hasStyle'       => true,                // 디스플레이 스타일 옵션 (list/slide)
        'skinBasePath'   => MUBLO_PACKAGE_PATH . '/Board/views/Block/',
        'itemsProvider'  => BoardItemsProvider::class,  // 항목 목록 제공자
        'adminScript'    => '/serve/plugin/Banner/assets/js/block-banner.js',  // 관리자 커스텀 JS
        'adminScriptInit' => 'MubloBlockBanner', // JS 전역 객체명
        'noCache'        => true,                // 캐시 비활성화 (로그인 위젯 등)
    ]
);
```

### options 정리

| 옵션 | 타입 | 설명 |
|------|------|------|
| `hasItems` | bool | 항목 선택 UI 표시 (게시판/배너 선택 등) |
| `itemsProvider` | string | `BlockItemsProviderInterface` FQCN |
| `hasStyle` | bool | 디스플레이 스타일 옵션 (list/slide 모드) |
| `skinBasePath` | string | 스킨 디렉토리 절대 경로 |
| `adminScript` | string | 관리자 블록 설정 UI용 JS 파일 |
| `adminScriptInit` | string | JS 전역 초기화 객체명 |
| `noCache` | bool | 사용자별 달라지는 콘텐츠 (캐시 안 함) |
| `allowOverwrite` | bool | 기존 등록 덮어쓰기 허용 |

### 조회 메서드

```php
BlockRegistry::hasContentType('board');           // bool
BlockRegistry::getContentType('board');            // ?array (전체 정보)
BlockRegistry::getContentTypes('package');          // array (kind별 필터)
BlockRegistry::getContentTypesGroupedByKind();      // array (kind별 그룹)
BlockRegistry::createRenderer('board');             // ?RendererInterface (인스턴스 생성)
BlockRegistry::getSkinBasePath('board');             // ?string
BlockRegistry::isNoCache('outlogin');               // bool
BlockRegistry::hasItems('board');                   // bool
```

## RendererInterface

`src/Core/Block/Renderer/RendererInterface.php`

모든 블록 렌더러가 구현해야 하는 인터페이스입니다.

```php
interface RendererInterface
{
    public function render(BlockColumn $column): string;
}
```

`BlockColumn` 엔티티에서 설정(`getContentConfig()`), 항목(`getContentItems()`), 스킨(`getContentSkin()`) 등을 꺼내 HTML을 반환합니다.

## SkinRendererTrait

`src/Core/Block/Renderer/SkinRendererTrait.php`

대부분의 렌더러가 사용하는 트레이트로, 스킨 파일 기반 렌더링을 제공합니다.

### 구현 필수 메서드

```php
protected function getSkinType(): string;      // 예: 'board', 'banner'
protected function getSkinBasePath(): string;   // 스킨 디렉토리 경로
```

### 사용 예

```php
class BannerRenderer implements RendererInterface
{
    use SkinRendererTrait;

    public function __construct(private BannerService $bannerService) {}

    protected function getSkinType(): string { return 'banner'; }

    protected function getSkinBasePath(): string
    {
        return MUBLO_PLUGIN_PATH . '/Banner/views/Block/';
    }

    public function render(BlockColumn $column): string
    {
        $items = $this->resolveItems($column->getContentItems() ?? []);
        $skin = $column->getContentSkin() ?: 'basic';

        return $this->renderSkin($column, $skin, [
            'items' => $items,
            'config' => $column->getContentConfig() ?? [],
        ]);
    }
}
```

### 스킨 파일 경로

```
{skinBasePath}/{skinType}/{skin}/{skin}.php
```

예: `plugins/Banner/views/Block/banner/basic/basic.php`

### 스킨에 전달되는 변수

```php
$column         // BlockColumn 엔티티
$titleConfig    // 제목 설정 배열
$titlePartial   // 제목 파셜 파일 경로
$contentConfig  // 콘텐츠 설정
$skinDir        // 현재 스킨 디렉토리
$assets         // ?AssetManager (CSS/JS 등록용)
// + renderSkin()의 3번째 인자로 전달한 데이터
```

### 제목 파셜

스킨 디렉토리에 `title.php`가 있으면 우선 사용, 없으면 공유 파셜 사용:

1. `{skinBasePath}{skinType}/{skin}/title.php` (스킨별 오버라이드)
2. `views/Block/_shared/title.php` (공유 기본)

## 스킨 디렉토리 구조

```
views/Block/                          # Core 스킨
├── _shared/
│   └── title.php                     # 공유 제목 파셜
├── html/basic/basic.php
├── image/basic/basic.php
├── menu/basic/basic.php
└── outlogin/basic/basic.php

plugins/Banner/views/Block/           # Plugin 스킨
└── banner/
    └── basic/
        ├── basic.php                 # 메인 스킨 파일
        └── style.css                 # 스킨 CSS (선택)

packages/Board/views/Block/           # Package 스킨
├── board/
│   └── basic/basic.php
└── boardgroup/
    ├── basic/basic.php
    └── tab/tab.php                   # 추가 스킨
```

## 캐싱

`src/Service/Block/BlockRenderService.php`

2단계 캐싱 전략:

1. **행 목록 캐시** — 위치/페이지별 행 ID 목록
2. **행 콘텐츠 캐시** — 행별 렌더링된 HTML + 에셋 경로

### 캐시 키

```
block:ids:pos:{domainId}:{position}    # 위치별 행 ID 목록
block:ids:page:{pageId}                # 페이지별 행 ID 목록
block:row:{rowId}                      # 행 렌더링 결과
```

### 캐시 무효화

```php
// 단일 행 콘텐츠
$blockRenderService->invalidateRowContentCache($rowId);

// 행 + 관련 목록 캐시
$blockRenderService->invalidateRowRelatedCache($row);

// 도메인 전체
$blockRenderService->invalidateDomainCache($domainId);
```

`noCache: true`인 콘텐츠 타입(아웃로그인 등)은 캐싱을 건너뜁니다.

## Provider에서 등록

### Package (Board) 예시

```php
public function boot(DependencyContainer $container, Context $context): void
{
    BlockRegistry::registerContentType(
        type: 'board',
        kind: BlockContentKind::PACKAGE->value,
        title: '게시판 최신글',
        rendererClass: BoardRenderer::class,
        options: [
            'hasItems' => true,
            'hasStyle' => true,
            'skinBasePath' => MUBLO_PACKAGE_PATH . '/Board/views/Block/',
        ]
    );
}
```

### Plugin (Banner) 예시

```php
public function boot(DependencyContainer $container, Context $context): void
{
    BlockRegistry::registerContentType(
        type: 'banner',
        kind: BlockContentKind::PLUGIN->value,
        title: '배너',
        rendererClass: BannerRenderer::class,
        configFormClass: BannerConfigForm::class,
        options: [
            'hasItems' => true,
            'hasStyle' => true,
            'skinBasePath' => MUBLO_PLUGIN_PATH . '/Banner/views/Block',
            'adminScript' => '/serve/plugin/Banner/assets/js/block-banner.js',
            'adminScriptInit' => 'MubloBlockBanner',
        ]
    );
}
```

> Renderer를 DI 컨테이너에 등록할 때 `AssetManager`를 주입해야 스킨에서 CSS/JS를 등록할 수 있습니다:

```php
$container->singleton(BannerRenderer::class, function ($c) {
    $renderer = new BannerRenderer($c->get(BannerService::class));
    $renderer->assetManager = $c->get(AssetManager::class);
    return $renderer;
});
```

## 블록 관련 코어 이벤트

블록 시스템에서 자주 쓰는 코어 이벤트:

| 이벤트 | 용도 |
|------|------|
| `BlockPageCreatedEvent` | 블록 페이지 생성 후 후처리 |
| `BlockPageDeletedEvent` | 블록 페이지 삭제 후 후처리 |
| `BlockContentItemsCollectEvent` | 콘텐츠 타입별 선택 항목 공급 |
| `BlockPageRenderingEvent` | 블록 페이지 렌더링 시 추가 HTML 주입 |

예시:
- 메뉴 자동 등록/삭제
- 패키지별 콘텐츠 후보 목록 공급
- 블록 페이지 하단 부가 정보 출력

이벤트 발행 지점과 안정성 분류는 [이벤트 시스템](event-system.md)을 기준으로 본다.

---

[< 이전: Contract 시스템](contract-system.md) | [다음: 패키지 만들기 >](package-development.md)
