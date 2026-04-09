<?php
namespace Mublo\Packages\Board\Controller\Front;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Packages\Board\Service\BoardArticleService;
use Mublo\Packages\Board\Service\BoardCategoryService;
use Mublo\Packages\Board\Service\BoardCommentService;
use Mublo\Packages\Board\Service\BoardConfigService;
use Mublo\Packages\Board\Service\BoardFileService;
use Mublo\Packages\Board\Service\BoardPermissionService;
use Mublo\Packages\Board\Service\BoardReactionService;
use Mublo\Service\Auth\AuthService;
use Mublo\Core\Session\SessionInterface;
use Mublo\Helper\Form\FormHelper;
use Mublo\Packages\Board\Helper\ArticlePresenter;
use Mublo\Core\Response\FileResponse;

/**
 * Front 게시판 컨트롤러
 *
 * /board/{board_id} 라우트 처리
 */
class BoardController
{
    private BoardArticleService $articleService;
    private BoardCategoryService $categoryService;
    private BoardCommentService $commentService;
    private BoardConfigService $boardConfigService;
    private BoardFileService $fileService;
    private BoardPermissionService $permissionService;
    private BoardReactionService $reactionService;
    private AuthService $authService;
    private SessionInterface $session;

    public function __construct(
        BoardArticleService $articleService,
        BoardCategoryService $categoryService,
        BoardCommentService $commentService,
        BoardConfigService $boardConfigService,
        BoardFileService $fileService,
        BoardPermissionService $permissionService,
        BoardReactionService $reactionService,
        AuthService $authService,
        SessionInterface $session
    ) {
        $this->articleService = $articleService;
        $this->categoryService = $categoryService;
        $this->commentService = $commentService;
        $this->boardConfigService = $boardConfigService;
        $this->fileService = $fileService;
        $this->permissionService = $permissionService;
        $this->reactionService = $reactionService;
        $this->authService = $authService;
        $this->session = $session;
    }

    /**
     * 게시판 목록
     */
    public function list(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $slug = $params['board_id'] ?? '';
        $domainId = $context->getDomainId() ?? 1;

        // 게시판 조회 (slug → BoardConfig)
        $board = $this->boardConfigService->getBoardBySlug($domainId, $slug);

        if (!$board || !$board->isActive()) {
            return ViewResponse::view('error/notfound')
                ->withStatusCode(404)
                ->withData(['message' => '게시판을 찾을 수 없습니다.']);
        }

        $boardId = $board->getBoardId();

        // 카테고리
        $categories = [];
        if ($board->useCategory()) {
            $categories = $this->categoryService->getCategoriesByBoard($boardId);
        }

        // 요청 파라미터
        $request = $context->getRequest();
        $page = max(1, (int) ($request->get('page') ?? 1));
        $keyword = trim($request->get('keyword') ?? '');
        $searchField = $request->get('search_field') ?? 'title';
        $categoryId = $board->useCategory() ? (int) ($request->get('category_id') ?? 0) : 0;

        // 검색 필터
        $filters = [
            'per_page' => $board->getPerPage() ?: 20,
        ];
        if ($keyword !== '') {
            $filters['keyword'] = $keyword;
            $filters['search_field'] = $searchField;
        }
        if ($categoryId > 0) {
            $filters['category_id'] = $categoryId;
        }

        // 공지글 분리 (1페이지에서만 상단 표시, 검색 시 미표시)
        $noticeCount = $board->getNoticeCount();
        $notices = [];
        if ($noticeCount > 0 && $page === 1 && $keyword === '') {
            $notices = $this->articleService->getNotices($domainId, $boardId, $noticeCount);
        }

        // 본문 목록 조회 (공지 제외)
        $filters['is_notice'] = 0;
        $result = $this->articleService->getList($domainId, $boardId, $page, $filters, $context);

        if ($result->isFailure()) {
            if ($this->authService->user() === null) {
                return RedirectResponse::to('/login');
            }
            return ViewResponse::view('error/forbidden')
                ->withStatusCode(403)
                ->withData(['message' => $result->getMessage()]);
        }

        $data = $result->getData();

        // Presenter: 목록용 데이터 변환
        $presenter = new ArticlePresenter($data['board']);
        $items = $presenter->toList($data['items'], $slug);
        $noticeItems = $presenter->toList($notices, $slug);

        $pagination = $data['pagination'];
        $pagination['pageNums'] = 10;

        // 글쓰기 권한
        $canWrite = $this->permissionService->canWrite($board, $context);

        $skin = $board->getBoardSkin();

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/Board/' . $skin . '/List')
            ->withData([
                'board'      => $data['board'],
                'notices'    => $noticeItems,
                'items'      => $items,
                'pagination' => $pagination,
                'filters'    => [
                    'keyword'      => $keyword,
                    'search_field' => $searchField,
                    'category_id'  => $categoryId,
                ],
                'categories' => $categories,
                'canWrite'   => $canWrite,
            ]);
    }

    /**
     * 게시글 상세
     */
    public function view(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $boardSlug = $params['board_id'] ?? '';
        $articleId = (int) ($params['post_no'] ?? 0);
        $domainId = $context->getDomainId() ?? 1;

        if ($articleId <= 0) {
            return ViewResponse::view('error/notfound')
                ->withStatusCode(404)
                ->withData(['message' => '게시글을 찾을 수 없습니다.']);
        }

        // 조회수 중복 방지 (쿠키 기반, 1일 1회)
        $request = $context->getRequest();
        $viewedCookie = $request->cookie('article_viewed', '');
        $viewedIds = $viewedCookie !== '' ? explode('.', $viewedCookie) : [];
        $incrementView = !in_array((string) $articleId, $viewedIds, true);

        // 게시글 조회 (권한 체크 + 조회수 증가)
        $result = $this->articleService->getArticle($articleId, $context, $incrementView);

        // 조회수 증가했으면 쿠키에 기록 (당일 자정까지)
        if ($incrementView && $result->isSuccess()) {
            $viewedIds[] = (string) $articleId;
            setcookie('article_viewed', implode('.', $viewedIds), [
                'expires'  => strtotime('tomorrow'),
                'path'     => '/',
                'secure'   => $request->isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        if ($result->isFailure()) {
            if ($this->authService->user() === null) {
                $session = $this->session;
                $guestArticles = $session->get('guest_articles', []);

                if (in_array($articleId, $guestArticles)) {
                    // 세션에 허용된 글 → 권한 체크 우회 재조회
                    $result = $this->articleService->getArticleWithoutPermission($articleId, $context);
                    if ($result->isFailure()) {
                        return ViewResponse::view('error/notfound')
                            ->withStatusCode(404)
                            ->withData(['message' => '게시글을 찾을 수 없습니다.']);
                    }
                } else {
                    // 비회원 글이면 비밀번호 입력 폼, 아니면 로그인
                    $article = $this->articleService->findById($articleId);
                    if ($article && $article->getAuthorPassword()) {
                        $board = $this->boardConfigService->getBoardBySlug($domainId, $boardSlug);
                        $skin = $board ? $board->getBoardSkin() : 'basic';
                        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/Board/' . $skin . '/Password')
                            ->withData([
                                'boardSlug' => $boardSlug,
                                'articleId' => $articleId,
                                'board' => $board ? $board->toArray() : [],
                            ]);
                    }
                    return RedirectResponse::to('/login');
                }
            } else {
                return ViewResponse::view('error/forbidden')
                    ->withStatusCode(403)
                    ->withData(['message' => $result->getMessage()]);
            }
        }

        $data = $result->getData();

        // board_slug 검증 (URL 조작 방지)
        $board = $data['board'];
        if ($board['board_slug'] !== $boardSlug) {
            return ViewResponse::view('error/notfound')
                ->withStatusCode(404)
                ->withData(['message' => '게시글을 찾을 수 없습니다.']);
        }

        // Presenter: 상세용 데이터 변환
        $presenter = new ArticlePresenter($board);
        $article = $presenter->toView($data['article'], $boardSlug);
        $prev = $presenter->toAdjacent($data['prev'], $boardSlug);
        $next = $presenter->toAdjacent($data['next'], $boardSlug);

        // 첨부파일 + 링크
        $attachments = [];
        $links = [];
        if (!empty($board['use_file'])) {
            $attachments = $this->fileService->getAttachmentsByArticle($articleId);
        }
        if (!empty($board['use_link'])) {
            $links = $this->fileService->getLinksByArticle($articleId);
        }

        // 댓글 목록
        $comments = [];
        if ($board['use_comment']) {
            $commentEntities = $this->commentService->getCommentsByArticle($articleId);
            foreach ($commentEntities as $comment) {
                $c = $comment->toArray();
                unset($c['author_password'], $c['ip_address']);
                $comments[] = $c;
            }
        }

        $currentUser = $this->authService->user();

        // 글쓰기 권한 (Service가 반환한 Entity 사용, DB 재조회 불필요)
        $boardEntity = $data['board_entity'];
        $canWrite = $this->permissionService->canWrite($boardEntity, $context);

        // 반응 정보 (반응 기능 사용 시)
        $reactionInfo = null;
        $enabledReactions = [];
        if (!empty($board['use_reaction'])) {
            $reactionInfo = $this->reactionService->getReactionInfo('article', $articleId, $context);
            $enabledReactions = $boardEntity->getEnabledReactions();

            if (empty($enabledReactions)) {
                $enabledReactions = [
                    'like' => ['label' => '좋아요', 'icon' => '👍', 'color' => '#3B82F6', 'enabled' => true],
                ];
            }
        }

        $skin = $boardEntity->getBoardSkin();

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/Board/' . $skin . '/View')
            ->withData([
                'article'          => $article,
                'board'            => $board,
                'prev'             => $prev,
                'next'             => $next,
                'canWrite'         => $canWrite,
                'canModify'        => $data['can_modify'],
                'canDelete'        => $data['can_delete'],
                'canComment'       => $data['can_comment'],
                'canReact'         => $data['can_react'],
                'canDownload'      => $data['can_download'],
                'attachments'      => $attachments,
                'links'            => $links,
                'comments'         => $comments,
                'reactionInfo'     => $reactionInfo,
                'enabledReactions' => $enabledReactions,
                'currentUser'      => $currentUser ? [
                    'member_id' => $currentUser['member_id'] ?? null,
                    'nickname'  => $currentUser['nickname'] ?? $currentUser['userid'] ?? '',
                ] : null,
            ]);
    }

    // ========================================
    // 댓글 CRUD (AJAX / JSON 응답)
    // ========================================

    /**
     * 댓글 작성
     */
    public function commentCreate(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $articleId = (int) ($request->json('article_id') ?? 0);

        if ($articleId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $data = [
            'content'        => trim($request->json('content') ?? ''),
            'parent_id'      => $request->json('parent_id') ?: null,
            'is_secret'      => (bool) ($request->json('is_secret') ?? false),
            'author_name'     => $request->json('author_name') ?? '',
            'author_password' => $request->json('author_password') ?? '',
        ];

        if (empty($data['content'])) {
            return JsonResponse::error('댓글 내용을 입력해주세요.');
        }

        $result = $this->commentService->create($articleId, $data, $context);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success($result->getData(), $result->getMessage());
    }

    /**
     * 댓글 수정
     */
    public function commentUpdate(array $params, Context $context): JsonResponse
    {
        $commentId = (int) ($params['comment_id'] ?? 0);
        $request = $context->getRequest();

        if ($commentId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $data = [
            'content'   => trim($request->json('content') ?? ''),
            'is_secret' => (bool) ($request->json('is_secret') ?? false),
        ];

        if (empty($data['content'])) {
            return JsonResponse::error('댓글 내용을 입력해주세요.');
        }

        $guestPassword = $request->json('author_password') ?: null;
        $result = $this->commentService->update($commentId, $data, $context, $guestPassword);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(null, $result->getMessage());
    }

    /**
     * 댓글 삭제
     */
    public function commentDelete(array $params, Context $context): JsonResponse
    {
        $commentId = (int) ($params['comment_id'] ?? 0);
        $request = $context->getRequest();

        if ($commentId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $guestPassword = $request->json('author_password') ?: null;
        $result = $this->commentService->delete($commentId, $context, $guestPassword);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success($result->getData(), $result->getMessage());
    }

    // ========================================
    // 반응 토글 (AJAX / JSON 응답)
    // ========================================

    /**
     * 반응 토글
     */
    public function reactionToggle(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $articleId = (int) ($request->json('article_id') ?? 0);
        $reactionType = trim($request->json('reaction_type') ?? '');

        if ($articleId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        if ($reactionType === '') {
            return JsonResponse::error('반응 타입을 선택해주세요.');
        }

        $result = $this->reactionService->toggle('article', $articleId, $reactionType, $context);

        if (!$result['success']) {
            return JsonResponse::error($result['message']);
        }

        return JsonResponse::success([
            'action'      => $result['action'],
            'counts'      => $result['counts'],
            'my_reaction' => $result['my_reaction'],
        ], $result['message']);
    }

    // ========================================
    // 글쓰기 / 수정
    // ========================================

    /**
     * 글쓰기 폼
     */
    public function write(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $slug = $params['board_id'] ?? '';
        $domainId = $context->getDomainId() ?? 1;

        $board = $this->boardConfigService->getBoardBySlug($domainId, $slug);
        if (!$board || !$board->isActive()) {
            return ViewResponse::view('error/notfound')
                ->withStatusCode(404)
                ->withData(['message' => '게시판을 찾을 수 없습니다.']);
        }

        if (!$this->permissionService->canWrite($board, $context)) {
            if ($this->authService->user() === null) {
                return RedirectResponse::to('/login');
            }
            return ViewResponse::view('error/forbidden')
                ->withStatusCode(403)
                ->withData(['message' => '글쓰기 권한이 없습니다.']);
        }

        $isLoggedIn = $this->authService->user() !== null;
        $skin = $board->getBoardSkin();

        $categories = [];
        if ($board->useCategory()) {
            $categories = $this->categoryService->getCategoriesByBoard($board->getBoardId());
        }

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/Board/' . $skin . '/Write')
            ->withData([
                'board'      => $board->toArray(),
                'article'    => null,
                'isEdit'     => false,
                'isLoggedIn' => $isLoggedIn,
                'categories' => $categories,
            ]);
    }

    /**
     * 글쓰기 처리
     */
    public function writeProcess(array $params, Context $context): JsonResponse
    {
        $slug = $params['board_id'] ?? '';
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $board = $this->boardConfigService->getBoardBySlug($domainId, $slug);
        if (!$board || !$board->isActive()) {
            return JsonResponse::error('게시판을 찾을 수 없습니다.');
        }

        $formData = $request->input('formData') ?? [];
        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        if (empty($data['title'])) {
            return JsonResponse::error('제목을 입력해주세요.');
        }

        $result = $this->articleService->create($domainId, $board->getBoardId(), $data, $context);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        $articleId = $result->get('article_id');

        // 파일 첨부 처리
        if ($board->isUseFile() && $request->hasFile('files')) {
            $rawFiles = $request->getRawFile('files');
            if (is_array($rawFiles['name'] ?? null)) {
                $fileCount = count($rawFiles['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if (($rawFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $file = [
                            'name'     => $rawFiles['name'][$i],
                            'type'     => $rawFiles['type'][$i],
                            'tmp_name' => $rawFiles['tmp_name'][$i],
                            'error'    => $rawFiles['error'][$i],
                            'size'     => $rawFiles['size'][$i],
                        ];
                        $this->fileService->uploadFile($articleId, $file, $context);
                    }
                }
            }
        }

        // 링크 추가 처리
        if ($board->isUseLink() && !empty($data['links'])) {
            foreach ($data['links'] as $link) {
                $linkUrl = trim($link['url'] ?? '');
                if ($linkUrl !== '') {
                    $this->fileService->addLink($articleId, [
                        'link_url'   => $linkUrl,
                        'link_title' => trim($link['title'] ?? ''),
                    ], $context);
                }
            }
        }

        // 비회원 글 작성 시 세션에 글 ID 저장 (작성 직후 읽기 허용)
        if ($this->authService->user() === null && $articleId) {
            $session = $this->session;
            $guestArticles = $session->get('guest_articles', []);
            $guestArticles[] = $articleId;
            $session->set('guest_articles', $guestArticles);
        }

        return JsonResponse::success(
            ['redirect' => '/board/' . $slug . '/view/' . $articleId],
            $result->getMessage()
        );
    }

    /**
     * 글수정 폼
     */
    public function edit(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $slug = $params['board_id'] ?? '';
        $articleId = (int) ($params['post_no'] ?? 0);
        $domainId = $context->getDomainId() ?? 1;

        $result = $this->articleService->getArticle($articleId, $context, false);
        if ($result->isFailure()) {
            if ($this->authService->user() === null) {
                return RedirectResponse::to('/login');
            }
            return ViewResponse::view('error/forbidden')
                ->withStatusCode(403)
                ->withData(['message' => $result->getMessage()]);
        }

        $data = $result->getData();
        $board = $data['board'];

        if ($board['board_slug'] !== $slug) {
            return ViewResponse::view('error/notfound')
                ->withStatusCode(404)
                ->withData(['message' => '게시글을 찾을 수 없습니다.']);
        }

        if (!$data['can_modify']) {
            return ViewResponse::view('error/forbidden')
                ->withStatusCode(403)
                ->withData(['message' => '수정 권한이 없습니다.']);
        }

        $boardEntity = $data['board_entity'];
        $skin = $boardEntity->getBoardSkin();

        $categories = [];
        if ($boardEntity->useCategory()) {
            $categories = $this->categoryService->getCategoriesByBoard($boardEntity->getBoardId());
        }

        $articleId = (int) ($data['article']['article_id'] ?? 0);
        $attachments = [];
        $links = [];
        if ($boardEntity->isUseFile()) {
            $attachments = $this->fileService->getAttachmentsByArticle($articleId);
        }
        if ($boardEntity->isUseLink()) {
            $links = $this->fileService->getLinksByArticle($articleId);
        }

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/Board/' . $skin . '/Write')
            ->withData([
                'board'       => $board,
                'article'     => $data['article'],
                'isEdit'      => true,
                'isLoggedIn'  => $this->authService->user() !== null,
                'categories'  => $categories,
                'attachments' => $attachments,
                'links'       => $links,
            ]);
    }

    /**
     * 글수정 처리
     */
    public function editProcess(array $params, Context $context): JsonResponse
    {
        $slug = $params['board_id'] ?? '';
        $articleId = (int) ($params['post_no'] ?? 0);
        $request = $context->getRequest();

        if ($articleId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $formData = $request->input('formData') ?? [];
        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        if (empty($data['title'])) {
            return JsonResponse::error('제목을 입력해주세요.');
        }

        $result = $this->articleService->update($articleId, $data, $context);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(
            ['redirect' => '/board/' . $slug . '/view/' . $articleId],
            $result->getMessage()
        );
    }

    // ========================================
    // 파일 첨부 / 링크 (AJAX / JSON 응답)
    // ========================================

    /**
     * 파일 업로드
     */
    public function fileUpload(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $articleId = (int) ($request->input('article_id') ?? 0);

        if ($articleId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        if (!$request->hasFile('file')) {
            return JsonResponse::error('파일을 선택해주세요.');
        }

        $file = $request->getRawFile('file');
        $result = $this->fileService->uploadFile($articleId, $file, $context);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success($result->getData(), $result->getMessage());
    }

    /**
     * 파일 다운로드
     */
    public function fileDownload(array $params, Context $context): FileResponse|JsonResponse
    {
        $attachmentId = (int) ($params['attachment_id'] ?? 0);

        if ($attachmentId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $result = $this->fileService->download($attachmentId, $context);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        $fileName = $result->get('original_name');
        $encodedName = rawurlencode($fileName);

        return new FileResponse(
            $result->get('file_path'),
            200,
            [
                'Content-Type' => $result->get('mime_type'),
                'Content-Disposition' => "attachment; filename*=UTF-8''{$encodedName}",
            ]
        );
    }

    /**
     * 파일 삭제
     */
    public function fileDelete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $attachmentId = (int) ($request->json('attachment_id') ?? 0);

        if ($attachmentId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $result = $this->fileService->deleteFile($attachmentId, $context);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(null, $result->getMessage());
    }

    /**
     * 링크 추가
     */
    public function linkAdd(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $articleId = (int) ($request->json('article_id') ?? 0);

        if ($articleId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $data = [
            'link_url'   => trim($request->json('link_url') ?? ''),
            'link_title' => trim($request->json('link_title') ?? ''),
        ];

        $result = $this->fileService->addLink($articleId, $data, $context);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success($result->getData(), $result->getMessage());
    }

    /**
     * 링크 삭제
     */
    public function linkDelete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $linkId = (int) ($request->json('link_id') ?? 0);

        if ($linkId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $result = $this->fileService->deleteLink($linkId, $context);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(null, $result->getMessage());
    }

    /**
     * 폼 스키마
     */
    /**
     * 비회원 비밀번호 확인
     * POST /board/{board_id}/password-check
     */
    public function passwordCheck(array $params, Context $context): JsonResponse
    {
        $articleId = (int) ($context->getRequest()->json('article_id') ?? 0);
        $password = $context->getRequest()->json('password') ?? '';
        $boardSlug = $params['board_id'] ?? '';

        if (!$articleId || $password === '') {
            return JsonResponse::error('비밀번호를 입력해주세요.');
        }

        if ($this->articleService->verifyGuestPassword($articleId, $password)) {
            // 세션에 글 ID 저장 (이후 읽기 허용)
            $session = $this->session;
            $guestArticles = $session->get('guest_articles', []);
            if (!in_array($articleId, $guestArticles)) {
                $guestArticles[] = $articleId;
                $session->set('guest_articles', $guestArticles);
            }
            return JsonResponse::success(
                ['redirect' => '/board/' . $boardSlug . '/view/' . $articleId],
                '확인되었습니다.'
            );
        }

        return JsonResponse::error('비밀번호가 일치하지 않습니다.');
    }

    private function getFormSchema(): array
    {
        return [
            'numeric' => ['category_id'],
            'bool' => ['is_notice', 'is_secret'],
            'html' => ['content'],
        ];
    }
}
