/**
 * ============================================================
 * MubloItemLayout.js
 * (c) 2025 Mublo
 * ============================================================
 *
 * <ul> 기반 아이템 목록에 list / slide(Swiper) 모드를 적용하는
 * 공용 레이아웃 관리 모듈.
 *
 * ------------------------------------------------------------
 * 설계 원칙
 * ------------------------------------------------------------
 *
 * 1. 콘텐츠는 서버(PHP 스킨)가 완성한다
 *    - JS는 이미 DOM에 존재하는 <ul><li>에 레이아웃만 적용
 *    - 렌더링 콜백, 클라이언트 사이드 템플릿 불필요
 *
 * 2. HTML에 선언, JS가 해석
 *    - .mublo-item-layout + data-* 속성으로 설정 선언
 *    - DOMContentLoaded에서 자동 초기화
 *
 * 3. CSS가 열(column) 레이아웃을 제어
 *    - data-pc-cols / data-mo-cols 는 CSS grid용 메타데이터
 *    - JS는 해석하지 않음
 *
 * ------------------------------------------------------------
 * HTML 사용법
 * ------------------------------------------------------------
 *
 * <div class="mublo-item-layout"
 *      data-pc-style="slide"
 *      data-mo-style="list"
 *      data-pc-cols="4"
 *      data-mo-cols="2"
 *      data-swiper='{"spaceBetween":10,"loop":true}'>
 *     <ul>
 *         <li>...</li>
 *         <li>...</li>
 *     </ul>
 * </div>
 *
 * ------------------------------------------------------------
 * data-* 속성
 * ------------------------------------------------------------
 *
 * data-pc-style   : "list" | "slide" (기본: "list")
 * data-mo-style   : "list" | "slide" (기본: "list")
 * data-pc-cols    : PC 열 수 — CSS 제어용 (JS 미해석)
 * data-mo-cols    : Mobile 열 수 — CSS 제어용 (JS 미해석)
 * data-breakpoint : PC/Mobile 전환 기준 px (기본: 768)
 * data-swiper     : Swiper 옵션 JSON 문자열 (선택)
 *
 * slidesPerView 자동 매핑:
 *   slide 모드에서 data-swiper에 slidesPerView가 없으면
 *   data-pc-cols / data-mo-cols 값을 slidesPerView로 자동 적용.
 *   cols > 1이면 spaceBetween: 16px 자동 추가.
 *
 * ------------------------------------------------------------
 * JS 직접 호출 (고급)
 * ------------------------------------------------------------
 *
 * MubloItemLayout.init(element, {
 *     swiperOptions: { autoplay: { delay: 3000 }, loop: true }
 * });
 *
 * MubloItemLayout.destroy('my-box-id');
 */

window.MubloItemLayout = (function () {
    'use strict';

    /* ==================================================================
       상수
    ================================================================== */

    const SELECTOR = '.mublo-item-layout';
    const DEFAULT_BREAKPOINT = 768;
    const DEBOUNCE_DELAY = 150;

    /* ==================================================================
       인스턴스 저장소
    ================================================================== */

    const instances = {};
    let idCounter = 0;

    /* ==================================================================
       유틸리티
    ================================================================== */

    function debounce(fn, delay) {
        let timer = null;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, delay);
        };
    }

    function parseJsonAttr(el, attr) {
        const raw = el.getAttribute(attr);
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.warn('MubloItemLayout: ' + attr + ' JSON 파싱 실패', e);
            return null;
        }
    }

    function isSwiperAvailable() {
        return typeof Swiper !== 'undefined';
    }

    /* ==================================================================
       init — 단일 컨테이너 초기화
    ================================================================== */

    /**
     * @param {HTMLElement} el           컨테이너 요소 (.mublo-item-layout)
     * @param {Object}      [options]    JS에서 직접 호출 시 추가 옵션
     * @param {Object}      [options.swiperOptions]  Swiper 옵션 (data-swiper보다 우선)
     */
    function init(el, options) {
        if (!el || !(el instanceof HTMLElement)) {
            console.error('MubloItemLayout: 유효한 HTMLElement가 필요합니다.');
            return null;
        }

        options = options || {};

        // boxId 결정
        var boxId = el.id || ('mublo-il-' + (++idCounter));
        if (!el.id) el.id = boxId;

        // 이미 초기화된 경우 기존 인스턴스 반환
        if (instances[boxId]) {
            return instances[boxId];
        }

        // ul 자동 탐색
        var ul = el.querySelector('ul');
        if (!ul) {
            console.warn('MubloItemLayout: <ul> 요소를 찾을 수 없습니다.', boxId);
            return null;
        }

        // 설정 읽기
        var pcStyle = el.dataset.pcStyle || 'list';
        var moStyle = el.dataset.moStyle || 'list';
        var breakpoint = parseInt(el.dataset.breakpoint, 10) || DEFAULT_BREAKPOINT;

        // Swiper 옵션: JS 옵션 > data-swiper
        var swiperOptions = options.swiperOptions || parseJsonAttr(el, 'data-swiper') || {};

        // autoplay / loop 설정 (data-* 속성, PC/MO 개별)
        var pcAutoplay = parseInt(el.dataset.pcAutoplay, 10) || 0;
        var moAutoplay = parseInt(el.dataset.moAutoplay, 10) || 0;
        var pcLoop = el.dataset.pcLoop === 'true';
        var moLoop = el.dataset.moLoop === 'true';

        // 슬라이드 이미지 크롭(cover) 모드 (data-* 속성, PC/MO 개별)
        var pcSlideCover = el.dataset.pcSlideCover === 'true';
        var moSlideCover = el.dataset.moSlideCover === 'true';

        // 상태
        var swiperInstance = null;
        var currentMode = null;
        var lastIsMobile = null;

        /* ──────────────────────────────────────────────────────────
           list 모드
        ────────────────────────────────────────────────────────── */
        function enableListMode() {
            if (swiperInstance) {
                swiperInstance.destroy(true, true);
                swiperInstance = null;
            }

            el.classList.remove('swiper', 'is-slide');
            el.classList.add('is-list');
            clearSlideCover();

            ul.classList.remove('swiper-wrapper');

            for (var li of ul.querySelectorAll(':scope > li')) {
                li.classList.remove('swiper-slide');
            }

            currentMode = 'list';
        }

        /* ──────────────────────────────────────────────────────────
           slide 모드
        ────────────────────────────────────────────────────────── */
        function enableSlideMode() {
            if (!isSwiperAvailable()) {
                console.warn('MubloItemLayout: Swiper 라이브러리가 없어 list 모드로 전환합니다.', boxId);
                enableListMode();
                return;
            }

            // 이미 slide 모드이면 update만
            if (swiperInstance) {
                swiperInstance.update();
                return;
            }

            el.classList.add('swiper', 'is-slide');
            el.classList.remove('is-list');

            ul.classList.add('swiper-wrapper');

            for (var li of ul.querySelectorAll(':scope > li')) {
                li.classList.add('swiper-slide');
            }

            // 기본 Swiper 옵션
            var defaults = {
                observer: true,
                observeParents: true
            };

            // data-pc-cols / data-mo-cols → slidesPerView 자동 매핑
            if (swiperOptions.slidesPerView === undefined) {
                var rawPcCols = el.dataset.pcCols;
                var rawMoCols = el.dataset.moCols;
                var pcColsAuto = rawPcCols === 'auto';
                var moColsAuto = rawMoCols === 'auto';
                var pcCols = pcColsAuto ? 'auto' : (parseInt(rawPcCols, 10) || 1);
                var moCols = moColsAuto ? 'auto' : (parseInt(rawMoCols, 10) || 1);
                var isMobile = window.innerWidth <= breakpoint;
                var activeCols = isMobile ? moCols : pcCols;

                defaults.slidesPerView = activeCols;
                if (typeof activeCols === 'number' && activeCols > 1) {
                    defaults.spaceBetween = 16;
                } else if (activeCols === 'auto') {
                    defaults.spaceBetween = 16;
                }

                // PC/MO 모두 slide이고 열 수가 다르면 breakpoints
                if (pcStyle === 'slide' && moStyle === 'slide' && pcCols !== moCols) {
                    defaults.slidesPerView = moCols;
                    if (typeof moCols === 'number' && moCols > 1) defaults.spaceBetween = 16;
                    else if (moCols === 'auto') defaults.spaceBetween = 16;

                    var bpConfig = { slidesPerView: pcCols };
                    if (typeof pcCols === 'number' && pcCols > 1) bpConfig.spaceBetween = 16;
                    else if (pcCols === 'auto') bpConfig.spaceBetween = 16;
                    defaults.breakpoints = {};
                    defaults.breakpoints[breakpoint + 1] = bpConfig;
                }
            }

            // 슬라이드 수
            var slideCount = ul.querySelectorAll(':scope > li').length;
            var activeSlidesPerView = typeof defaults.slidesPerView === 'number' ? defaults.slidesPerView : 1;

            // autoplay / loop (data-swiper에 미설정 시)
            if (swiperOptions.autoplay === undefined) {
                var isMobileNow = window.innerWidth <= breakpoint;
                var activeAutoplay = isMobileNow ? moAutoplay : pcAutoplay;
                if (activeAutoplay > 0 && slideCount > activeSlidesPerView) {
                    defaults.autoplay = { delay: activeAutoplay, disableOnInteraction: false };
                }
            }
            if (swiperOptions.loop === undefined) {
                var isMobileLoop = window.innerWidth <= breakpoint;
                var activeLoop = isMobileLoop ? moLoop : pcLoop;
                if (activeLoop && slideCount > activeSlidesPerView) {
                    defaults.loop = true;
                }
            }

            var merged = Object.assign(defaults, swiperOptions);

            // loop: 슬라이드 수 부족 시 자동 비활성화 (Swiper Loop Warning 방지)
            if (merged.loop) {
                var spv = typeof merged.slidesPerView === 'number' ? merged.slidesPerView : 1;
                if (slideCount <= spv) {
                    merged.loop = false;
                }
            }

            swiperInstance = new Swiper(el, merged);
            currentMode = 'slide';

            // 이미지 크롭(cover) 모드: 가장 작은 이미지 높이에 맞춰 나머지를 cover 크롭
            var isMobileCover = window.innerWidth <= breakpoint;
            var activeCover = isMobileCover ? moSlideCover : pcSlideCover;
            if (activeCover) {
                applySlideCover();
            }
        }

        /* ──────────────────────────────────────────────────────────
           이미지 크롭(cover) 모드 — 최소 높이 자동 감지
        ────────────────────────────────────────────────────────── */
        function applySlideCover() {
            var imgs = el.querySelectorAll('.swiper-slide img');
            if (!imgs.length) return;

            // 모든 이미지 로드 대기 후 최소 높이 계산
            var promises = Array.prototype.map.call(imgs, function (img) {
                if (img.complete && img.naturalHeight > 0) {
                    return Promise.resolve(img);
                }
                return new Promise(function (resolve) {
                    img.addEventListener('load', function () { resolve(img); }, { once: true });
                    img.addEventListener('error', function () { resolve(img); }, { once: true });
                });
            });

            Promise.all(promises).then(function () {
                // 현재 렌더링된 높이(width:100%, height:auto 기준) 중 최솟값
                var minH = Infinity;
                imgs.forEach(function (img) {
                    // 일시적으로 height:auto 적용해서 자연 비율 높이 측정
                    var prevH = img.style.height;
                    var prevFit = img.style.objectFit;
                    img.style.height = 'auto';
                    img.style.objectFit = '';
                    var h = img.getBoundingClientRect().height;
                    img.style.height = prevH;
                    img.style.objectFit = prevFit;
                    if (h > 0 && h < minH) minH = h;
                });

                if (minH === Infinity || minH <= 0) return;

                el.classList.add('has-fixed-height');
                el.style.setProperty('--slide-height', Math.round(minH) + 'px');

                if (swiperInstance) swiperInstance.update();
            });
        }

        function clearSlideCover() {
            el.classList.remove('has-fixed-height');
            el.style.removeProperty('--slide-height');
        }

        /* ──────────────────────────────────────────────────────────
           모드 적용 (반응형)
        ────────────────────────────────────────────────────────── */
        function applyMode() {
            var isMobile = window.innerWidth <= breakpoint;
            var targetMode = isMobile ? moStyle : pcStyle;

            // 모드 동일(slide→slide)이지만 viewport 변경 시 autoplay/loop 재적용
            if (targetMode === currentMode) {
                if (currentMode === 'slide' && lastIsMobile !== null && lastIsMobile !== isMobile
                    && (pcAutoplay !== moAutoplay || pcLoop !== moLoop)) {
                    enableListMode();
                    enableSlideMode();
                }
                lastIsMobile = isMobile;
                return;
            }

            lastIsMobile = isMobile;

            if (targetMode === 'slide') {
                enableSlideMode();
            } else {
                enableListMode();
            }
        }

        /* ──────────────────────────────────────────────────────────
           resize 핸들러
        ────────────────────────────────────────────────────────── */
        var onResize = debounce(applyMode, DEBOUNCE_DELAY);

        /* ──────────────────────────────────────────────────────────
           시작
        ────────────────────────────────────────────────────────── */
        applyMode();
        window.addEventListener('resize', onResize);

        /* ──────────────────────────────────────────────────────────
           인스턴스 API
        ────────────────────────────────────────────────────────── */
        var instance = {
            el: el,
            refresh: applyMode,
            update: function () {
                if (swiperInstance) swiperInstance.update();
            },
            getSwiper: function () {
                return swiperInstance;
            },
            destroy: function () {
                enableListMode();
                window.removeEventListener('resize', onResize);
                delete instances[boxId];
            }
        };

        instances[boxId] = instance;
        return instance;
    }

    /* ==================================================================
       initAll — 모든 .mublo-item-layout 자동 초기화
    ================================================================== */

    function initAll() {
        var elements = document.querySelectorAll(SELECTOR);
        for (var i = 0; i < elements.length; i++) {
            // 이미 초기화된 요소 건너뛰기
            if (elements[i].id && instances[elements[i].id]) continue;
            init(elements[i]);
        }
    }

    /* ==================================================================
       destroy — boxId로 인스턴스 파괴
    ================================================================== */

    function destroy(boxId) {
        if (instances[boxId]) {
            instances[boxId].destroy();
        }
    }

    /* ==================================================================
       자동 초기화
    ================================================================== */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    /* ==================================================================
       공개 API
    ================================================================== */

    return {
        init: init,
        initAll: initAll,
        destroy: destroy,
        instances: instances
    };

})();
