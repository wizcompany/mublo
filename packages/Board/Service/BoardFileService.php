<?php
namespace Mublo\Packages\Board\Service;

use Mublo\Packages\Board\Entity\BoardAttachment;
use Mublo\Packages\Board\Entity\BoardLink;
use Mublo\Packages\Board\Repository\BoardAttachmentRepository;
use Mublo\Packages\Board\Repository\BoardLinkRepository;
use Mublo\Packages\Board\Repository\BoardArticleRepository;
use Mublo\Packages\Board\Repository\BoardConfigRepository;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Core\Context\Context;
use Mublo\Service\Auth\AuthService;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Board\Event\FileUploadedEvent;
use Mublo\Packages\Board\Event\FileDownloadingEvent;
use Mublo\Packages\Board\Event\FileDownloadedEvent;
use Mublo\Core\Result\Result;
use Mublo\Infrastructure\Storage\FileUploader;
use Mublo\Infrastructure\Storage\UploadedFile;
use Mublo\Infrastructure\Image\ImageProcessor;

/**
 * BoardFileService
 *
 * 첨부파일/링크 비즈니스 로직 + 이벤트 발행
 *
 * Infrastructure 활용:
 * - FileUploader: 파일 업로드/삭제
 * - ImageProcessor: 썸네일 생성
 */
class BoardFileService
{
    private BoardAttachmentRepository $attachmentRepository;
    private BoardLinkRepository $linkRepository;
    private BoardArticleRepository $articleRepository;
    private BoardConfigRepository $boardRepository;
    private MemberRepository $memberRepository;
    private BoardPermissionService $permissionService;
    private ?EventDispatcher $eventDispatcher;
    private AuthService $authService;
    private FileUploader $fileUploader;
    private ImageProcessor $imageProcessor;

    private const SUBDIRECTORY = 'board';
    private const THUMBNAIL_SIZE = 200;

    public function __construct(
        BoardAttachmentRepository $attachmentRepository,
        BoardLinkRepository $linkRepository,
        BoardArticleRepository $articleRepository,
        BoardConfigRepository $boardRepository,
        MemberRepository $memberRepository,
        BoardPermissionService $permissionService,
        ?EventDispatcher $eventDispatcher,
        AuthService $authService,
        FileUploader $fileUploader,
        ImageProcessor $imageProcessor
    ) {
        $this->attachmentRepository = $attachmentRepository;
        $this->linkRepository = $linkRepository;
        $this->articleRepository = $articleRepository;
        $this->boardRepository = $boardRepository;
        $this->memberRepository = $memberRepository;
        $this->permissionService = $permissionService;
        $this->eventDispatcher = $eventDispatcher;
        $this->authService = $authService;
        $this->fileUploader = $fileUploader;
        $this->imageProcessor = $imageProcessor;
    }

    /**
     * 현재 로그인 사용자 ID 조회
     */
    private function getCurrentUserId(): ?int
    {
        return $this->authService->id();
    }

    /**
     * 이벤트 발행 헬퍼
     */
    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    // === Attachment Methods ===

    /**
     * 파일 업로드
     *
     * @param int $articleId 게시글 ID
     * @param array|UploadedFile $file $_FILES 배열 또는 UploadedFile 객체
     * @param Context $context 컨텍스트
     * @return Result
     */
    public function uploadFile(
        int $articleId,
        array|UploadedFile $file,
        Context $context
    ): Result {
        // 게시글 조회
        $article = $this->articleRepository->find($articleId);
        if (!$article) {
            return Result::failure('게시글을 찾을 수 없습니다.');
        }

        // 게시판 조회
        $board = $this->boardRepository->find($article->getBoardId());
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        // 파일 기능 체크
        if (!$board->isUseFile()) {
            return Result::failure('파일 첨부가 허용되지 않습니다.');
        }

        // 파일 개수 제한 체크
        $currentCount = $this->attachmentRepository->countByArticle($articleId);
        if ($currentCount >= $board->getFileCountLimit()) {
            return Result::failure('파일 첨부 개수를 초과했습니다.');
        }

        // UploadedFile 객체로 변환 (배열인 경우)
        $uploadedFile = ($file instanceof UploadedFile) ? $file : new UploadedFile($file);

        // 파일 유효성 검사
        if (!$uploadedFile->isValid()) {
            return Result::failure($uploadedFile->getErrorMessage());
        }

        // 허용 확장자 목록
        $allowedExtensions = array_map('trim', explode(',', $board->getFileExtensionAllowed()));

        // FileUploader로 업로드
        $domainId = $article->getDomainId();
        $uploadResult = $this->fileUploader->upload($uploadedFile, $domainId, [
            'subdirectory' => self::SUBDIRECTORY,
            'allowed_extensions' => $allowedExtensions,
            'max_size' => $board->getFileSizeLimit(),
        ]);

        if ($uploadResult->isFailure()) {
            return Result::failure($uploadResult->getMessage());
        }

        // 썸네일 생성 (이미지인 경우)
        $thumbnailPath = null;
        if ($uploadResult->isImage()) {
            $thumbnailPath = $this->createThumbnail(
                $uploadResult->getFullPath(),
                $uploadResult->getRelativePath(),
                $uploadResult->getStoredName()
            );
        }

        // DB 저장
        $insertData = [
            'domain_id' => $domainId,
            'board_id' => $article->getBoardId(),
            'article_id' => $articleId,
            'original_name' => $uploadResult->getOriginalName(),
            'stored_name' => $uploadResult->getStoredName(),
            'file_path' => $uploadResult->getRelativePath(),
            'file_size' => $uploadResult->getSize(),
            'file_extension' => $uploadResult->getExtension(),
            'mime_type' => $uploadResult->getMimeType(),
            'is_image' => $uploadResult->isImage(),
            'image_width' => $uploadResult->getImageWidth(),
            'image_height' => $uploadResult->getImageHeight(),
            'thumbnail_path' => $thumbnailPath,
        ];

        $attachmentId = $this->attachmentRepository->create($insertData);
        if (!$attachmentId) {
            // 실패 시 업로드된 파일 삭제
            $this->fileUploader->delete($uploadResult->getRelativePath(), $uploadResult->getStoredName());
            if ($thumbnailPath) {
                $this->fileUploader->deleteByFullPath($this->getThumbnailFullPath($thumbnailPath));
            }
            return Result::failure('파일 정보 저장에 실패했습니다.');
        }

        // 이벤트 발행
        $attachment = $this->attachmentRepository->find($attachmentId);
        $memberId = $this->getCurrentUserId();
        $this->dispatch(new FileUploadedEvent($attachment, $memberId));

        return Result::success('파일이 업로드되었습니다.', ['attachment' => $attachment->toArray()]);
    }

    /**
     * 썸네일 생성
     *
     * @param string $sourcePath 원본 파일 전체 경로
     * @param string $relativePath 상대 경로
     * @param string $storedName 저장된 파일명
     * @return string|null 썸네일 상대 경로 (실패 시 null)
     */
    private function createThumbnail(string $sourcePath, string $relativePath, string $storedName): ?string
    {
        $thumbnailName = 'thumb_' . $storedName;
        $thumbnailRelativePath = $relativePath . '/' . $thumbnailName;
        $thumbnailFullPath = $this->getThumbnailFullPath($thumbnailRelativePath);

        $success = $this->imageProcessor->thumbnail(
            $sourcePath,
            $thumbnailFullPath,
            self::THUMBNAIL_SIZE
        );

        return $success ? $thumbnailRelativePath : null;
    }

    /**
     * 썸네일 전체 경로 반환
     */
    private function getThumbnailFullPath(string $relativePath): string
    {
        $basePath = defined('MUBLO_PUBLIC_STORAGE_PATH') ? MUBLO_PUBLIC_STORAGE_PATH : 'public/storage';
        return $basePath . '/' . $relativePath;
    }

    /**
     * 파일 다운로드 처리
     */
    public function download(int $attachmentId, Context $context): Result
    {
        // 첨부파일 조회
        $attachmentData = $this->attachmentRepository->findWithArticle($attachmentId);
        if (!$attachmentData) {
            return Result::failure('파일을 찾을 수 없습니다.');
        }

        $attachment = $attachmentData['attachment'];

        // 게시글 조회
        $article = $this->articleRepository->find($attachment->getArticleId());
        if (!$article) {
            return Result::failure('게시글을 찾을 수 없습니다.');
        }

        // 게시판 조회
        $board = $this->boardRepository->find($article->getBoardId());
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        // 권한 체크
        if (!$this->permissionService->canDownload($board, $article, $context)) {
            return Result::failure('다운로드 권한이 없습니다.');
        }

        // 파일 존재 확인
        $fullPath = $this->fileUploader->getFullPath(
            $attachment->getFilePath(),
            $attachment->getStoredName()
        );

        if (!file_exists($fullPath)) {
            return Result::failure('파일이 존재하지 않습니다.');
        }

        // 다운로드 차단 pre-event (포인트 소비 등)
        $memberId = $this->getCurrentUserId();
        $clientIp = $context->getRequest()->getClientIp();

        $downloadingEvent = $this->dispatch(new FileDownloadingEvent($attachment, $memberId, $clientIp));
        if ($downloadingEvent->isBlocked()) {
            return Result::failure($downloadingEvent->getBlockReason() ?? '파일을 다운로드할 수 없습니다.');
        }

        // 이벤트 발행
        $downloader = $memberId ? $this->memberRepository->find($memberId) : null;
        $this->dispatch(new FileDownloadedEvent($attachment, $downloader, $clientIp));

        // 다운로드 수 증가
        $this->attachmentRepository->incrementDownloadCount($attachmentId);

        return Result::success('', [
            'file_path' => $fullPath,
            'original_name' => $attachment->getOriginalName(),
            'mime_type' => $attachment->getMimeType(),
        ]);
    }

    /**
     * 파일 삭제
     */
    public function deleteFile(int $attachmentId, Context $context): Result
    {
        // 첨부파일 조회
        $attachment = $this->attachmentRepository->find($attachmentId);
        if (!$attachment) {
            return Result::failure('파일을 찾을 수 없습니다.');
        }

        // 실제 파일 삭제
        $this->fileUploader->delete($attachment->getFilePath(), $attachment->getStoredName());

        // 썸네일 삭제
        if ($attachment->hasThumbnail()) {
            $this->fileUploader->deleteByFullPath(
                $this->getThumbnailFullPath($attachment->getThumbnailPath())
            );
        }

        // DB 삭제
        $this->attachmentRepository->delete($attachmentId);

        return Result::success('파일이 삭제되었습니다.');
    }

    /**
     * 게시글별 첨부파일 목록 조회
     */
    public function getAttachmentsByArticle(int $articleId): array
    {
        $attachments = $this->attachmentRepository->findByArticle($articleId);
        return array_map(fn($a) => $a->toArray(), $attachments);
    }

    /**
     * 게시글별 이미지 첨부파일 목록 조회
     */
    public function getImagesByArticle(int $articleId): array
    {
        $images = $this->attachmentRepository->findImagesByArticle($articleId);
        return array_map(fn($i) => $i->toArray(), $images);
    }

    /**
     * 첨부파일 URL 반환
     */
    public function getFileUrl(BoardAttachment $attachment): string
    {
        return $this->fileUploader->getUrl($attachment->getFilePath(), $attachment->getStoredName());
    }

    /**
     * 썸네일 URL 반환
     */
    public function getThumbnailUrl(BoardAttachment $attachment): ?string
    {
        if (!$attachment->hasThumbnail()) {
            return null;
        }
        return '/storage/' . $attachment->getThumbnailPath();
    }

    // === Link Methods ===

    /**
     * 링크 추가
     */
    public function addLink(int $articleId, array $data, Context $context): Result
    {
        // 게시글 조회
        $article = $this->articleRepository->find($articleId);
        if (!$article) {
            return Result::failure('게시글을 찾을 수 없습니다.');
        }

        // 게시판 조회
        $board = $this->boardRepository->find($article->getBoardId());
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        // 링크 기능 체크
        if (!$board->isUseLink()) {
            return Result::failure('링크 기능이 허용되지 않습니다.');
        }

        // URL 유효성 검사
        $url = trim($data['link_url'] ?? '');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return Result::failure('유효한 URL을 입력해주세요.');
        }

        // 중복 체크
        if ($this->linkRepository->existsByUrl($articleId, $url)) {
            return Result::failure('이미 등록된 링크입니다.');
        }

        // DB 저장
        $insertData = [
            'domain_id' => $article->getDomainId(),
            'board_id' => $article->getBoardId(),
            'article_id' => $articleId,
            'link_url' => $url,
            'link_title' => $data['link_title'] ?? null,
            'link_description' => $data['link_description'] ?? null,
            'link_image' => $data['link_image'] ?? null,
        ];

        $linkId = $this->linkRepository->create($insertData);
        if (!$linkId) {
            return Result::failure('링크 저장에 실패했습니다.');
        }

        $link = $this->linkRepository->find($linkId);

        return Result::success('링크가 추가되었습니다.', ['link' => $link->toArray()]);
    }

    /**
     * 링크 삭제
     */
    public function deleteLink(int $linkId, Context $context): Result
    {
        $link = $this->linkRepository->find($linkId);
        if (!$link) {
            return Result::failure('링크를 찾을 수 없습니다.');
        }

        $this->linkRepository->delete($linkId);

        return Result::success('링크가 삭제되었습니다.');
    }

    /**
     * 링크 클릭 처리
     */
    public function clickLink(int $linkId): Result
    {
        $link = $this->linkRepository->find($linkId);
        if (!$link) {
            return Result::failure('링크를 찾을 수 없습니다.');
        }

        $this->linkRepository->incrementClickCount($linkId);

        return Result::success('', ['url' => $link->getLinkUrl()]);
    }

    /**
     * 게시글별 링크 목록 조회
     */
    public function getLinksByArticle(int $articleId): array
    {
        $links = $this->linkRepository->findByArticle($articleId);
        return array_map(fn($l) => $l->toArray(), $links);
    }

    /**
     * OG 정보 업데이트
     */
    public function updateLinkOgInfo(int $linkId, ?string $title, ?string $description, ?string $image): bool
    {
        return $this->linkRepository->updateOgInfo($linkId, $title, $description, $image);
    }

    // === Statistics ===

    /**
     * 게시판별 파일 통계
     */
    public function getFileStatsByBoard(int $boardId): array
    {
        return $this->attachmentRepository->getStatsByBoard($boardId);
    }

    /**
     * 도메인별 총 파일 크기 (DB 기준)
     */
    public function getTotalSizeByDomain(int $domainId): int
    {
        return $this->attachmentRepository->getTotalSizeByDomain($domainId);
    }

    /**
     * 도메인별 실제 디스크 사용량
     */
    public function getDiskUsageByDomain(int $domainId): int
    {
        return $this->fileUploader->getDomainUsage($domainId);
    }
}
