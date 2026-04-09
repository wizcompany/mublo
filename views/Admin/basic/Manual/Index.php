<?php
/**
 * Core 관리자 매뉴얼
 *
 * @var string $pageTitle
 */
?>

<link rel="stylesheet" href="/assets/css/manual.css">

<!-- 블록 구조도 전용 스타일 (이 페이지에서만 사용) -->
<style>
.field-table th { width: 220px; }

.zone-chip {
    display: inline-block;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}
.zone-sub { background: rgba(var(--bs-primary-rgb), 0.15); color: var(--bs-primary); }
.zone-side { background: rgba(var(--bs-warning-rgb), 0.15); color: var(--bs-warning-text-emphasis); }
.zone-main { background: rgba(var(--bs-success-rgb), 0.15); color: var(--bs-success-text-emphasis); }
.zone-content { background: var(--bs-tertiary-bg); }
.zone-content-head { background: var(--bs-tertiary-bg); border: 1px dashed var(--bs-border-color); }
.zone-content-body { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); }
.zone-content-foot { background: var(--bs-tertiary-bg); border: 1px dashed var(--bs-border-color); }
.zone-map { min-width: 300px; }
.zone-map-box {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.5rem;
    background: var(--bs-body-bg);
    padding: 0.5rem;
}
.zone-map-row {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.4rem;
    padding: 0.35rem 0.5rem;
    margin-bottom: 0.35rem;
    font-size: 0.78rem;
    font-weight: 600;
}
.zone-map-grid {
    display: grid;
    grid-template-columns: 78px 1fr 78px;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}
.zone-map-side {
    border: 1px solid rgba(var(--bs-warning-rgb), 0.4);
    background: rgba(var(--bs-warning-rgb), 0.1);
    border-radius: 0.4rem;
    padding: 0.4rem 0.2rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.zone-map-center {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.4rem;
    padding: 0.35rem;
    background: var(--bs-tertiary-bg);
}
.zone-map-center .c-head {
    border: 1px dashed var(--bs-border-color);
    background: var(--bs-tertiary-bg);
    border-radius: 0.35rem;
    padding: 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}
.zone-map-center .c-body {
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    border-radius: 0.35rem;
    padding: 1.8rem 0.3rem;
    min-height: 120px;
    font-size: 0.78rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.zone-map-center .c-foot {
    border: 1px dashed var(--bs-border-color);
    background: var(--bs-tertiary-bg);
    border-radius: 0.35rem;
    padding: 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
}

@media (max-width: 991.98px) {
    .zone-map { min-width: auto; }
    .zone-map-grid { grid-template-columns: 60px 1fr 60px; }
}
@media (max-width: 575.98px) {
    .zone-map-grid { grid-template-columns: 1fr; }
    .zone-map-side { display: none; }
}
</style>

<div class="container-fluid">
    <h5 class="mb-3">
        <i class="bi bi-book me-2"></i><?= htmlspecialchars($pageTitle) ?>
    </h5>

    <div class="row">
        <div class="col-lg-2">
            <nav id="manual-toc" class="sticky-top">
                <div class="list-group list-group-flush">
                    <div class="toc-group-label">Core 매뉴얼</div>
                    <a class="list-group-item list-group-item-action" href="#sec-settings">사이트 설정</a>
                    <a class="list-group-item list-group-item-action" href="#sec-block">블록 관리</a>
                    <a class="list-group-item list-group-item-action" href="#sec-member">회원 관리</a>
                    <a class="list-group-item list-group-item-action" href="#sec-board">게시판 관리</a>
                </div>
            </nav>
        </div>

        <div class="col-lg-10" id="manual-content">
            <section id="sec-settings" class="manual-section">
                <h4><i class="bi bi-gear me-2"></i>사이트 설정</h4>
                <p>사이트 운영에 필요한 기본값을 설정하는 화면입니다. 화면은 기본 정보, 회사 정보, 로고 및 검색 설정, 테마 설정으로 나뉘어 있습니다.</p>

                <h6 class="mt-3 fw-bold">기본 정보 탭</h6>
                <table class="table table-bordered field-table">
                    <tr><th>사이트 정보</th><td>사이트명, 사이트 부제, 관리자 이메일을 입력합니다.</td></tr>
                    <tr><th>기본 설정</th><td>기본 에디터, 목록 표시 개수, 아이디 입력 정책을 정합니다.</td></tr>
                    <tr><th>레이아웃 형태</th><td>전체, 좌측, 우측, 3단 레이아웃 중 선택하고 사이드바 폭을 정합니다.</td></tr>
                    <tr><th>공통 폭 설정</th><td>사이트 전체 폭, 본문 폭, 메인화면 레이아웃 적용 여부를 설정합니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">회사 정보 탭</h6>
                <table class="table table-bordered field-table">
                    <tr><th>회사 기본 정보</th><td>회사명, 대표자명, 대표 전화번호, 팩스, 대표 이메일, 사업자 정보를 입력합니다.</td></tr>
                    <tr><th>주소</th><td>우편번호 + 주소 검색 버튼, 기본주소/상세주소</td></tr>
                    <tr><th>개인정보 책임자</th><td>책임자 이름과 이메일을 입력합니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">로고 및 검색 설정 탭</h6>
                <table class="table table-bordered field-table">
                    <tr><th>이미지</th><td>컴퓨터용 로고, 모바일용 로고, 사이트 아이콘, 공유 이미지를 설정합니다.</td></tr>
                    <tr><th>메타 태그</th><td>검색 결과에 노출될 제목, 설명, 키워드를 입력합니다.</td></tr>
                    <tr><th>사이트 인증</th><td>검색엔진/분석 도구 인증 코드를 입력합니다.</td></tr>
                    <tr><th>연결 채널</th><td>외부 채널 정보를 여러 개 등록할 수 있습니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">테마 설정 탭</h6>
                <table class="table table-bordered field-table">
                    <tr><th>관리자 스킨</th><td>관리자 화면 스타일을 선택합니다.</td></tr>
                    <tr><th>프레임 스킨</th><td>프론트 공통 틀 스타일을 선택합니다.</td></tr>
                    <tr><th>콘텐츠 스킨</th><td>메인, 게시판, 회원 등 영역별 스타일을 선택합니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">운영 절차</h6>
                <ol>
                    <li>기본 정보와 회사 정보를 먼저 입력합니다.</li>
                    <li>로고와 검색 설정을 마치고 연결 채널을 등록합니다.</li>
                    <li>테마 스킨을 선택한 후 저장 버튼으로 반영합니다.</li>
                </ol>

                <div class="manual-tip">
                    <strong>안내:</strong> 검색 설정이나 이미지 변경 후에는 실제 화면과 공유 미리보기를 함께 확인하세요.
                </div>
            </section>

            <section id="sec-block" class="manual-section">
                <h4><i class="bi bi-grid-3x2 me-2"></i>블록 관리</h4>
                <p>블록관리는 페이지의 출력 및 디자인을 결정하는 핵심 기능입니다.</p>
                <p>초보자 기준으로는 메인화면과 서브페이지 공통 영역부터 운영하는 것이 가장 쉽습니다. 아래 순서대로 진행하세요.</p>

                <h6 class="mt-3 fw-bold">영역 구조 한눈에 보기</h6>
                <table class="table table-bordered field-table text-center">
                    <tr>
                        <th style="width:220px;">영역</th>
                        <th style="width:180px;">노출 조건</th>
                        <th>설명</th>
                        <th style="width:340px;">구조도</th>
                    </tr>
                    <tr>
                        <td><span class="zone-chip zone-sub">서브페이지 상단</span></td>
                        <td>서브페이지</td>
                        <td class="zone-sub">Header 아래에 공통으로 노출되는 영역</td>
                        <td rowspan="8" class="zone-map align-middle">
                            <div class="zone-map-box">
                                <div class="zone-map-row zone-sub">서브페이지 상단</div>
                                <div class="zone-map-grid">
                                    <div class="zone-map-side">좌측 사이드<br><small>(설정 시)</small></div>
                                    <div class="zone-map-center">
                                        <div class="c-head">콘텐츠 상단</div>
                                        <div class="c-body">본문 콘텐츠 영역</div>
                                        <div class="c-foot">콘텐츠 하단</div>
                                    </div>
                                    <div class="zone-map-side">우측 사이드<br><small>(설정 시)</small></div>
                                </div>
                                <div class="zone-map-row zone-sub mb-0">서브페이지 하단</div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><span class="zone-chip zone-content-head">콘텐츠 상단</span></td>
                        <td>공통</td>
                        <td class="zone-content-head">본문 콘텐츠 바로 위에 노출됩니다.</td>
                    </tr>
                    <tr>
                        <td><span class="zone-chip zone-content-body">본문 콘텐츠</span></td>
                        <td>공통</td>
                        <td class="zone-content-body">페이지의 실제 본문이 출력되는 영역입니다.</td>
                    </tr>
                    <tr>
                        <td><span class="zone-chip zone-content-foot">콘텐츠 하단</span></td>
                        <td>공통</td>
                        <td class="zone-content-foot">본문 콘텐츠 바로 아래에 노출됩니다.</td>
                    </tr>
                    <tr>
                        <td><span class="zone-chip zone-side">좌측 사이드</span></td>
                        <td>2단/3단 레이아웃</td>
                        <td class="zone-side">사이드바를 사용하는 레이아웃일 때만 노출됩니다.</td>
                    </tr>
                    <tr>
                        <td><span class="zone-chip zone-side">우측 사이드</span></td>
                        <td>2단/3단 레이아웃</td>
                        <td class="zone-side">사이드바를 사용하는 레이아웃일 때만 노출됩니다.</td>
                    </tr>
                    <tr>
                        <td><span class="zone-chip zone-sub">서브페이지 하단</span></td>
                        <td>서브페이지</td>
                        <td class="zone-sub">Footer 위에 공통으로 노출되는 영역</td>
                    </tr>
                </table>

                <h6 class="mt-3 fw-bold">처음 10분 따라하기</h6>
                <ol>
                    <li>메인화면에 행 1개를 추가합니다.</li>
                    <li>칸 수를 1칸으로 두고 저장합니다.</li>
                    <li>칸 콘텐츠에 이미지 또는 안내문을 1개만 넣습니다.</li>
                    <li>사용 여부를 켠 뒤 순서를 저장합니다.</li>
                    <li>실제 화면을 새로고침해서 노출 여부를 확인합니다.</li>
                </ol>

                <div class="manual-tip">
                    <strong>안내:</strong> 처음에는 복잡한 3칸/4칸보다 1칸으로 시작한 뒤, 정상 노출이 확인되면 칸을 늘리는 것이 가장 안전합니다.
                </div>

                <h6 class="mt-3 fw-bold">1) 메인화면 관리 (가장 먼저)</h6>
                <table class="table table-bordered field-table">
                    <tr><th>어디를 관리하나</th><td>메인 화면에 바로 노출되는 영역을 행 단위로 구성합니다.</td></tr>
                    <tr><th>작업 순서</th><td>행 추가 → 칸 수 설정 → 칸 콘텐츠 설정 → 순서 저장 순으로 진행합니다.</td></tr>
                    <tr><th>핵심 확인</th><td>사용 여부가 켜져 있는지, 모바일 표시 방식이 맞는지 마지막에 반드시 확인합니다.</td></tr>
                    <tr><th>자주 하는 실수</th><td>순서 숫자만 바꾸고 저장하지 않아 화면 반영이 안 되는 경우가 많습니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">2) 서브페이지 관리 (상단/하단)</h6>
                <table class="table table-bordered field-table">
                    <tr><th>어디를 관리하나</th><td>서브페이지 공통 상단/하단 영역에 공지, 배너, 안내 블록을 넣습니다.</td></tr>
                    <tr><th>노출 범위</th><td>전체 메뉴에 공통 노출하거나, 특정 메뉴에만 제한해 노출할 수 있습니다.</td></tr>
                    <tr><th>권장 활용</th><td>상단은 공지/이벤트, 하단은 안내/문의/고정 배너 용도로 운영하면 관리가 쉽습니다.</td></tr>
                    <tr><th>자주 하는 실수</th><td>메뉴 제한이 걸려 있어 일부 페이지에서만 보이는데 오류로 오해하는 경우가 많습니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">3) 페이지 생성하기 (필요할 때만)</h6>
                <table class="table table-bordered field-table">
                    <tr><th>언제 쓰나</th><td>회사소개, 이용안내처럼 별도 화면을 새로 만들어야 할 때 사용합니다.</td></tr>
                    <tr><th>기본 설정</th><td>페이지 코드, 제목, 레이아웃, 접근 레벨, 사용 여부를 먼저 저장합니다.</td></tr>
                    <tr><th>다음 단계</th><td>페이지 저장 후 행 관리를 열어 콘텐츠를 쌓아야 실제 화면이 완성됩니다.</td></tr>
                    <tr><th>주의사항</th><td>페이지만 만들고 행/칸을 비워두면 화면이 비어 보일 수 있습니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">공통 설정 핵심 (행/칸 설정)</h6>
                <table class="table table-bordered field-table">
                    <tr><th>레이아웃</th><td>행마다 1~4칸 구성, 칸 간격, 화면별 표시 방식을 정할 수 있습니다.</td></tr>
                    <tr><th>콘텐츠 타입</th><td>게시판, 메뉴, 이미지, 동영상, 직접 작성 콘텐츠를 칸별로 넣을 수 있습니다.</td></tr>
                    <tr><th>순서 관리</th><td>드래그 정렬 또는 숫자 입력 후 저장으로 순서를 확정합니다.</td></tr>
                    <tr><th>복사/이동</th><td>잘 만든 행을 다른 위치나 페이지로 복사/이동해 재사용할 수 있습니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">문제 해결 체크리스트</h6>
                <table class="table table-bordered field-table">
                    <tr><th>화면에 안 보일 때</th><td>페이지/행/칸 사용 여부, 노출 위치, 메뉴 제한, 순서 저장 여부를 먼저 확인합니다.</td></tr>
                    <tr><th>폭이 원하는 대로 안 될 때</th><td>일부 위치는 전체폭이 제한됩니다. 해당 위치에서는 최대넓이로 운영합니다.</td></tr>
                    <tr><th>모바일이 어색할 때</th><td>모바일 높이, 여백, 모바일 표시 방식을 별도로 조정합니다.</td></tr>
                    <tr><th>설정을 되돌리고 싶을 때</th><td>기존 행을 삭제하기 전에 복사본을 만들어 두면 안전하게 비교/복구할 수 있습니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">저장 전 1분 점검표</h6>
                <table class="table table-bordered field-table">
                    <tr><th>확인 1</th><td>페이지/행/칸 사용 여부가 모두 사용으로 되어 있는가</td></tr>
                    <tr><th>확인 2</th><td>노출 위치 또는 메뉴 제한이 의도한 범위와 일치하는가</td></tr>
                    <tr><th>확인 3</th><td>순서 변경 후 저장 버튼까지 눌렀는가</td></tr>
                    <tr><th>확인 4</th><td>모바일 화면에서 높이/여백/표시 방식이 깨지지 않는가</td></tr>
                </table>

                <div class="manual-warn">
                    <strong>주의:</strong> 블록은 반드시 페이지/행/칸이 모두 활성 상태여야 최종 화면에 출력됩니다.
                </div>
            </section>

            <section id="sec-member" class="manual-section">
                <h4><i class="bi bi-people me-2"></i>회원 관리</h4>
                <p>회원 데이터, 등급/권한, 확장필드를 함께 운영합니다.</p>

                <h6 class="mt-3 fw-bold">회원 관리</h6>
                <table class="table table-bordered field-table">
                    <tr><th>목록 컬럼</th><td>아이디, 등급, 상태, 가입일, 최종 로그인 정보를 확인합니다.</td></tr>
                    <tr><th>검색</th><td>검색 필드 + 키워드 조합</td></tr>
                    <tr><th>일괄처리</th><td>선택 수정, 선택 삭제를 한 번에 처리할 수 있습니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">회원 등록/수정 폼</h6>
                <table class="table table-bordered field-table">
                    <tr><th>기본 필드</th><td>아이디(신규 시 중복확인), 비밀번호(수정 시 입력한 경우만 변경)</td></tr>
                    <tr><th>회원 설정</th><td>회원 등급, 계정 상태를 설정합니다.</td></tr>
                    <tr><th>추가 필드</th><td>문자, 이메일, 전화번호, 주소, 선택형, 날짜, 숫자, 파일 등 다양한 입력 형식을 지원합니다.</td></tr>
                    <tr><th>검증 포인트</th><td>중복불가 필드는 저장 전 중복확인 필수, 주소 타입은 우편번호 검색 지원</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">회원 등급 관리</h6>
                <table class="table table-bordered field-table">
                    <tr><th>기본값</th><td>레벨값, 등급명, 등급 분류를 설정합니다.</td></tr>
                    <tr><th>역할 설정</th><td>최고관리자, 관리자모드 접근, 도메인 운영 가능</td></tr>
                    <tr><th>게시판 권한</th><td>목록/읽기/쓰기/댓글/다운로드 권한 스위치</td></tr>
                    <tr><th>제한</th><td>일일 게시글/댓글 작성 제한</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">회원 추가 필드 관리</h6>
                <table class="table table-bordered field-table">
                    <tr><th>필드 메타</th><td>필드명, 라벨, 타입, 검증 정규식을 설정합니다.</td></tr>
                    <tr><th>표시 설정</th><td>필수입력, 회원가입 표시, 프로필 표시, 목록 표시</td></tr>
                    <tr><th>보안 설정</th><td>암호화 저장, 검색 가능, 중복 불가를 설정합니다.</td></tr>
                    <tr><th>운영 기능</th><td>드래그 정렬, 선택형 옵션 관리, 파일 업로드 정책을 설정합니다.</td></tr>
                </table>

                <div class="manual-warn">
                    <strong>주의:</strong> 추가 필드 삭제 시 해당 필드에 저장된 회원 데이터도 함께 삭제됩니다.
                </div>
            </section>

            <section id="sec-board" class="manual-section">
                <h4><i class="bi bi-layout-text-window-reverse me-2"></i>게시판 관리</h4>
                <p>게시판 그룹/카테고리/게시판/게시글을 순차적으로 설정하는 구조입니다.</p>

                <h6 class="mt-3 fw-bold">게시판 그룹 관리</h6>
                <table class="table table-bordered field-table">
                    <tr><th>기본정보</th><td>고유 식별값, 그룹명, 설명, 상태를 설정합니다.</td></tr>
                    <tr><th>권한 기본값</th><td>목록/읽기/쓰기/댓글/다운로드 레벨</td></tr>
                    <tr><th>부가기능</th><td>그룹 관리자 추가/삭제, 그룹 소속 게시판 목록 확인</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">게시판 카테고리 관리</h6>
                <table class="table table-bordered field-table">
                    <tr><th>기본정보</th><td>고유 식별값, 카테고리명, 설명, 상태를 설정합니다.</td></tr>
                    <tr><th>목록관리</th><td>사용 게시판 수 확인, 사용 중 카테고리 삭제 차단</td></tr>
                    <tr><th>정렬</th><td>드래그 정렬 + 순서 저장</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">게시판 관리</h6>
                <table class="table table-bordered field-table">
                    <tr><th>기본정보 탭</th><td>그룹, 상태, 고유 식별값, 게시판명, 게시판 설명을 설정합니다.</td></tr>
                    <tr><th>권한설정 탭</th><td>목록/읽기/쓰기/댓글/다운로드 권한을 회원 레벨 기준으로 설정합니다.</td></tr>
                    <tr><th>화면설정 탭</th><td>게시판 스타일, 에디터, 공지글 상단 고정 개수, 페이지당 글 수를 설정합니다.</td></tr>
                    <tr><th>기능설정 탭</th><td>비밀글, 카테고리, 댓글, 반응 등 기능 스위치 + 카테고리/반응 세부설정</td></tr>
                    <tr><th>파일설정 탭</th><td>첨부 개수 제한, 파일 크기 제한, 허용 확장자를 설정합니다.</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">게시글 관리</h6>
                <table class="table table-bordered field-table">
                    <tr><th>검색/필터</th><td>게시판, 상태, 검색필드, 키워드</td></tr>
                    <tr><th>목록지표</th><td>공지/비밀 배지, 조회수, 댓글수, 작성일</td></tr>
                    <tr><th>일괄처리</th><td>상태 일괄 변경(발행/임시/삭제), 선택 삭제</td></tr>
                    <tr><th>상세관리</th><td>보기/수정 화면에서 본문, 댓글, 첨부파일 개별 관리</td></tr>
                </table>

                <h6 class="mt-3 fw-bold">권장 설정 순서</h6>
                <ol>
                    <li>게시판 그룹 생성 후 기본 권한을 정합니다.</li>
                    <li>카테고리를 생성하고 게시판에서 카테고리 사용 여부를 결정합니다.</li>
                    <li>게시판 설정 5개 탭을 완료한 뒤 테스트 게시글로 동작을 검증합니다.</li>
                </ol>
            </section>
        </div>
    </div>
</div>

<script src="/assets/js/manual.js"></script>
