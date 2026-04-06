<?php

namespace Mublo\Helper\Security;

/**
 * HtmlSanitizer
 *
 * HTML 콘텐츠 정화 헬퍼
 *
 * 위험한 태그와 속성을 제거하면서 안전한 HTML은 유지
 *
 * 사용:
 * HtmlSanitizer::sanitize('<div onclick="alert(1)">Hello</div>');
 * // 결과: '<div>Hello</div>'
 *
 * HtmlSanitizer::sanitize('<script>alert(1)</script><p>Safe</p>');
 * // 결과: '<p>Safe</p>'
 */
class HtmlSanitizer
{
    /**
     * 허용된 태그 목록
     */
    private const ALLOWED_TAGS = [
        // 구조
        'div', 'span', 'p', 'br', 'hr', 'style',
        // 제목
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        // 텍스트 서식
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins',
        'sub', 'sup', 'small', 'mark', 'abbr', 'cite', 'code', 'pre',
        // 목록
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        // 링크 및 미디어
        'a', 'img',
        // 테이블
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        // 블록 요소
        'blockquote', 'figure', 'figcaption', 'article', 'section', 'aside', 'header', 'footer', 'main', 'nav',
        // 기타
        'address', 'details', 'summary', 'time',
    ];

    /**
     * 허용된 속성 목록 (전역)
     */
    private const ALLOWED_ATTRIBUTES = [
        'id', 'class', 'style', 'title', 'lang', 'dir',
        'data-*',  // data- 속성은 패턴으로 처리
    ];

    /**
     * 태그별 추가 허용 속성
     */
    private const TAG_SPECIFIC_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel', 'download'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan', 'headers'],
        'th' => ['colspan', 'rowspan', 'headers', 'scope'],
        'col' => ['span'],
        'colgroup' => ['span'],
        'time' => ['datetime'],
        'abbr' => ['title'],
        'blockquote' => ['cite'],
        'ol' => ['start', 'type', 'reversed'],
        'li' => ['value'],
    ];

    /**
     * 위험한 프로토콜 (href, src에서 차단)
     */
    private const DANGEROUS_PROTOCOLS = [
        'javascript:',
        'vbscript:',
        'data:text/html',
    ];

    /**
     * 위험한 CSS 속성 (style에서 차단)
     */
    private const DANGEROUS_CSS = [
        'expression',
        'javascript:',
        'behavior',
        '-moz-binding',
    ];

    /**
     * HTML 정화
     *
     * @param string $html 원본 HTML
     * @param array $options 옵션
     *   - allowedTags: 추가 허용 태그 배열
     *   - deniedTags: 추가 차단 태그 배열
     *   - stripEmptyTags: 빈 태그 제거 여부 (기본: false)
     * @return string 정화된 HTML
     */
    public static function sanitize(string $html, array $options = []): string
    {
        if (empty(trim($html))) {
            return '';
        }

        // 옵션 처리
        $allowedTags = self::ALLOWED_TAGS;
        if (!empty($options['allowedTags'])) {
            $allowedTags = array_merge($allowedTags, $options['allowedTags']);
        }
        if (!empty($options['deniedTags'])) {
            $allowedTags = array_diff($allowedTags, $options['deniedTags']);
        }

        // DOM 파서 사용
        $dom = new \DOMDocument('1.0', 'UTF-8');

        // 에러 억제 (잘못된 HTML 허용)
        libxml_use_internal_errors(true);

        // UTF-8 인코딩 보장 (PHP 8.2+ mb_convert_encoding HTML-ENTITIES deprecated)
        $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $dom->loadHTML('<div id="sanitizer-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        // 노드 정화
        self::sanitizeNode($dom->documentElement, $allowedTags);

        // 결과 추출
        $result = '';
        $root = $dom->getElementById('sanitizer-root');
        if ($root) {
            foreach ($root->childNodes as $child) {
                $result .= $dom->saveHTML($child);
            }
        }

        // 빈 태그 제거 옵션
        if (!empty($options['stripEmptyTags'])) {
            $result = preg_replace('/<([a-z][a-z0-9]*)\s*>\s*<\/\1>/i', '', $result);
        }

        return trim($result);
    }

    /**
     * DOM 노드 재귀 정화
     */
    private static function sanitizeNode(\DOMNode $node, array $allowedTags): void
    {
        $nodesToRemove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);

                // 허용되지 않은 태그 제거
                if (!in_array($tagName, $allowedTags)) {
                    $nodesToRemove[] = $child;
                    continue;
                }

                // <style> 태그: 내부 CSS 정화 (자식 노드 재귀 불필요)
                if ($tagName === 'style') {
                    self::sanitizeStyleTag($child);
                    continue;
                }

                // 속성 정화
                self::sanitizeAttributes($child, $tagName);

                // 자식 노드 재귀 처리
                if ($child->hasChildNodes()) {
                    self::sanitizeNode($child, $allowedTags);
                }
            }
        }

        // 제거 대상 노드 처리
        foreach ($nodesToRemove as $nodeToRemove) {
            // 자식 노드는 보존 (텍스트 내용 유지)
            while ($nodeToRemove->firstChild) {
                $node->insertBefore($nodeToRemove->firstChild, $nodeToRemove);
            }
            $node->removeChild($nodeToRemove);
        }
    }

    /**
     * 요소의 속성 정화
     */
    private static function sanitizeAttributes(\DOMElement $element, string $tagName): void
    {
        $attributesToRemove = [];

        foreach ($element->attributes as $attr) {
            $attrName = strtolower($attr->nodeName);
            $attrValue = $attr->nodeValue;

            // 이벤트 핸들러 속성 제거 (on*)
            if (str_starts_with($attrName, 'on')) {
                $attributesToRemove[] = $attr->nodeName;
                continue;
            }

            // 허용된 속성 확인
            if (!self::isAllowedAttribute($attrName, $tagName)) {
                $attributesToRemove[] = $attr->nodeName;
                continue;
            }

            // href, src 속성의 위험한 프로토콜 검사
            if (in_array($attrName, ['href', 'src'])) {
                if (self::hasDangerousProtocol($attrValue)) {
                    $attributesToRemove[] = $attr->nodeName;
                    continue;
                }
            }

            // style 속성의 위험한 CSS 검사
            if ($attrName === 'style') {
                $sanitizedStyle = self::sanitizeStyle($attrValue);
                if ($sanitizedStyle !== $attrValue) {
                    $element->setAttribute($attrName, $sanitizedStyle);
                }
            }
        }

        // 속성 제거
        foreach ($attributesToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }

        // a 태그에 rel="noopener noreferrer" 자동 추가 (외부 링크)
        if ($tagName === 'a' && $element->hasAttribute('target') && $element->getAttribute('target') === '_blank') {
            $rel = $element->getAttribute('rel');
            if (strpos($rel, 'noopener') === false) {
                $element->setAttribute('rel', trim($rel . ' noopener noreferrer'));
            }
        }
    }

    /**
     * 속성이 허용되었는지 확인
     */
    private static function isAllowedAttribute(string $attrName, string $tagName): bool
    {
        // 전역 허용 속성
        if (in_array($attrName, self::ALLOWED_ATTRIBUTES)) {
            return true;
        }

        // data-* 속성 패턴 매칭
        if (str_starts_with($attrName, 'data-')) {
            return true;
        }

        // 태그별 허용 속성
        if (isset(self::TAG_SPECIFIC_ATTRIBUTES[$tagName])) {
            if (in_array($attrName, self::TAG_SPECIFIC_ATTRIBUTES[$tagName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 위험한 프로토콜 검사
     */
    private static function hasDangerousProtocol(string $value): bool
    {
        $value = strtolower(trim($value));

        foreach (self::DANGEROUS_PROTOCOLS as $protocol) {
            if (str_starts_with($value, $protocol)) {
                return true;
            }
        }

        return false;
    }

    /**
     * <style> 태그 내부 CSS 정화
     *
     * 허용: 일반 CSS 규칙 (.class { color: red; })
     * 차단: expression(), javascript:, behavior, -moz-binding, @import url("외부")
     */
    private static function sanitizeStyleTag(\DOMElement $element): void
    {
        $css = $element->textContent;

        // 위험한 CSS 패턴 제거
        foreach (self::DANGEROUS_CSS as $dangerous) {
            $css = preg_replace('/[^{};]*' . preg_quote($dangerous, '/') . '[^{};]*/i', '', $css);
        }

        // @import 중 외부 URL 차단 (상대 경로는 허용)
        $css = preg_replace('/@import\s+url\s*\(\s*["\']?https?:\/\/[^)]*\)/i', '', $css);

        // url() 내 위험한 프로토콜 제거
        $css = preg_replace_callback('/url\s*\(["\']?([^"\'()]+)["\']?\)/i', function ($matches) {
            $url = $matches[1];
            if (self::hasDangerousProtocol($url)) {
                return '';
            }
            return $matches[0];
        }, $css);

        // 텍스트 노드 교체
        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }
        $element->appendChild($element->ownerDocument->createTextNode($css));
    }

    /**
     * CSS style 속성 정화
     */
    private static function sanitizeStyle(string $style): string
    {
        $styleLower = strtolower($style);

        foreach (self::DANGEROUS_CSS as $dangerous) {
            if (strpos($styleLower, $dangerous) !== false) {
                // 위험한 CSS가 포함된 경우 해당 부분 제거
                $style = preg_replace('/[^;]*' . preg_quote($dangerous, '/') . '[^;]*/i', '', $style);
            }
        }

        // url() 내 위험한 프로토콜 제거
        $style = preg_replace_callback('/url\s*\(["\']?([^"\'()]+)["\']?\)/i', function ($matches) {
            $url = $matches[1];
            if (self::hasDangerousProtocol($url)) {
                return '';
            }
            return $matches[0];
        }, $style);

        return trim($style);
    }

    /**
     * 텍스트만 추출 (모든 태그 제거)
     *
     * @param string $html HTML 문자열
     * @return string 순수 텍스트
     */
    public static function stripTags(string $html): string
    {
        return strip_tags($html);
    }

    /**
     * XSS 방지용 이스케이프
     *
     * HTML 태그를 문자 그대로 표시해야 할 때 사용
     *
     * @param string $text 텍스트
     * @return string 이스케이프된 텍스트
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
