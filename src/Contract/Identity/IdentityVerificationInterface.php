<?php

namespace Mublo\Contract\Identity;

/**
 * 본인인증 계약 인터페이스
 *
 * ContractRegistry에 1:1 바인딩되어 본인인증 연동을 지원합니다.
 * 각 본인인증 업체(NICE, KMC 등)는 이 인터페이스를 구현합니다.
 *
 * 사용 흐름:
 *   1. prepare() → 인증창 호출에 필요한 토큰/URL 획득
 *   2. 클라이언트에서 인증창 팝업 (getClientScript)
 *   3. 인증 완료 콜백 → verify()로 결과 검증 및 개인정보 추출
 */
interface IdentityVerificationInterface
{
    /**
     * 인증 요청 준비
     *
     * 인증창 호출에 필요한 토큰, URL 등을 반환합니다.
     *
     * @param array $params 요청 파라미터 (return_url, fail_url 등 업체별 상이)
     * @return array{token: string, action_url: string, ...} 인증창 호출 데이터
     */
    public function prepare(array $params): array;

    /**
     * 인증 결과 검증
     *
     * 인증 완료 콜백 데이터로부터 실명, 생년월일, CI/DI 등을 추출합니다.
     *
     * @param array $callbackData 인증 완료 콜백 데이터
     * @return array{
     *     name: string,
     *     birth: string,
     *     gender: string,
     *     ci: string,
     *     di: string,
     *     phone?: string,
     * } 인증된 개인정보
     * @throws \RuntimeException 인증 검증 실패 시
     */
    public function verify(array $callbackData): array;

    /**
     * 클라이언트 SDK 설정
     *
     * 프론트에서 인증창 호출에 필요한 설정 정보를 반환합니다.
     * (업체 코드, 모듈 경로, 팝업 크기 등)
     */
    public function getClientConfig(): array;

    /**
     * 인증창 호출 JS
     *
     * 반환한 JS는 페이지에 <script>로 삽입됩니다.
     * JS는 window.MubloIdentityVerify(token, config) 형태의
     * 전역 함수를 등록해야 합니다.
     *
     * 특별한 클라이언트 처리가 불필요한 업체는 null을 반환합니다.
     */
    public function getClientScript(): ?string;
}
