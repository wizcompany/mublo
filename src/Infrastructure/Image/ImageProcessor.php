<?php
namespace Mublo\Infrastructure\Image;

/**
 * ImageProcessor
 *
 * 이미지 처리 인프라 클래스
 * - 썸네일 생성
 * - 리사이즈
 * - 크롭
 * - 워터마크
 * - 품질 압축
 *
 * GD 라이브러리 사용 (PHP 기본 내장)
 */
class ImageProcessor
{
    private array $supportedTypes = [
        'jpg' => IMAGETYPE_JPEG,
        'jpeg' => IMAGETYPE_JPEG,
        'png' => IMAGETYPE_PNG,
        'gif' => IMAGETYPE_GIF,
        'webp' => IMAGETYPE_WEBP,
    ];

    /**
     * 썸네일 생성
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param int $width 썸네일 너비
     * @param int $height 썸네일 높이 (0이면 비율 유지)
     * @param int $quality 품질 (1-100)
     * @return bool
     */
    public function thumbnail(
        string $sourcePath,
        string $destPath,
        int $width,
        int $height = 0,
        int $quality = 85
    ): bool {
        $info = $this->getImageInfo($sourcePath);
        if (!$info) {
            return false;
        }

        // 높이가 0이면 비율 유지
        if ($height === 0) {
            $height = (int) ($info['height'] * ($width / $info['width']));
        }

        return $this->resize($sourcePath, $destPath, $width, $height, $quality);
    }

    /**
     * 이미지 리사이즈
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param int $width 너비
     * @param int $height 높이
     * @param int $quality 품질 (1-100)
     * @return bool
     */
    public function resize(
        string $sourcePath,
        string $destPath,
        int $width,
        int $height,
        int $quality = 85
    ): bool {
        $source = $this->loadImage($sourcePath);
        if (!$source) {
            return false;
        }

        $info = $this->getImageInfo($sourcePath);
        if (!$info) {
            imagedestroy($source);
            return false;
        }

        // 새 이미지 생성
        $dest = imagecreatetruecolor($width, $height);
        if (!$dest) {
            imagedestroy($source);
            return false;
        }

        // 투명도 유지 (PNG, GIF, WebP)
        $this->preserveTransparency($dest, $info['type']);

        // 리사이즈
        imagecopyresampled(
            $dest, $source,
            0, 0, 0, 0,
            $width, $height,
            $info['width'], $info['height']
        );

        // 저장
        $result = $this->saveImage($dest, $destPath, $info['type'], $quality);

        imagedestroy($source);
        imagedestroy($dest);

        return $result;
    }

    /**
     * 비율 유지 리사이즈 (최대 크기 제한)
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param int $maxWidth 최대 너비
     * @param int $maxHeight 최대 높이
     * @param int $quality 품질
     * @return bool
     */
    public function resizeWithinBounds(
        string $sourcePath,
        string $destPath,
        int $maxWidth,
        int $maxHeight,
        int $quality = 85
    ): bool {
        $info = $this->getImageInfo($sourcePath);
        if (!$info) {
            return false;
        }

        // 이미 최대 크기 이내면 복사만
        if ($info['width'] <= $maxWidth && $info['height'] <= $maxHeight) {
            return copy($sourcePath, $destPath);
        }

        // 비율 계산
        $ratioW = $maxWidth / $info['width'];
        $ratioH = $maxHeight / $info['height'];
        $ratio = min($ratioW, $ratioH);

        $newWidth = (int) ($info['width'] * $ratio);
        $newHeight = (int) ($info['height'] * $ratio);

        return $this->resize($sourcePath, $destPath, $newWidth, $newHeight, $quality);
    }

    /**
     * 이미지 크롭
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param int $x 시작 X 좌표
     * @param int $y 시작 Y 좌표
     * @param int $width 크롭 너비
     * @param int $height 크롭 높이
     * @param int $quality 품질
     * @return bool
     */
    public function crop(
        string $sourcePath,
        string $destPath,
        int $x,
        int $y,
        int $width,
        int $height,
        int $quality = 85
    ): bool {
        $source = $this->loadImage($sourcePath);
        if (!$source) {
            return false;
        }

        $info = $this->getImageInfo($sourcePath);
        if (!$info) {
            imagedestroy($source);
            return false;
        }

        // 범위 검증
        if ($x < 0 || $y < 0 || $x + $width > $info['width'] || $y + $height > $info['height']) {
            imagedestroy($source);
            return false;
        }

        // 크롭
        $dest = imagecrop($source, [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ]);

        if (!$dest) {
            imagedestroy($source);
            return false;
        }

        $result = $this->saveImage($dest, $destPath, $info['type'], $quality);

        imagedestroy($source);
        imagedestroy($dest);

        return $result;
    }

    /**
     * 중앙 기준 정사각형 크롭
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param int $size 정사각형 크기
     * @param int $quality 품질
     * @return bool
     */
    public function cropSquare(
        string $sourcePath,
        string $destPath,
        int $size,
        int $quality = 85
    ): bool {
        $info = $this->getImageInfo($sourcePath);
        if (!$info) {
            return false;
        }

        // 작은 쪽 기준으로 정사각형 크롭
        $cropSize = min($info['width'], $info['height']);
        $x = (int) (($info['width'] - $cropSize) / 2);
        $y = (int) (($info['height'] - $cropSize) / 2);

        // 먼저 정사각형으로 크롭
        $tempPath = $sourcePath . '.tmp';
        if (!$this->crop($sourcePath, $tempPath, $x, $y, $cropSize, $cropSize, 100)) {
            return false;
        }

        // 원하는 크기로 리사이즈
        $result = $this->resize($tempPath, $destPath, $size, $size, $quality);

        // 임시 파일 삭제
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $result;
    }

    /**
     * 워터마크 추가
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param string $watermarkPath 워터마크 이미지 경로
     * @param string $position 위치 (top-left, top-right, bottom-left, bottom-right, center)
     * @param int $opacity 투명도 (0-100)
     * @param int $padding 여백 (px)
     * @param int $quality 품질
     * @return bool
     */
    public function watermark(
        string $sourcePath,
        string $destPath,
        string $watermarkPath,
        string $position = 'bottom-right',
        int $opacity = 50,
        int $padding = 10,
        int $quality = 85
    ): bool {
        $source = $this->loadImage($sourcePath);
        $watermark = $this->loadImage($watermarkPath);

        if (!$source || !$watermark) {
            if ($source) imagedestroy($source);
            if ($watermark) imagedestroy($watermark);
            return false;
        }

        $info = $this->getImageInfo($sourcePath);
        if (!$info) {
            imagedestroy($source);
            imagedestroy($watermark);
            return false;
        }

        $srcWidth = $info['width'];
        $srcHeight = $info['height'];
        $wmWidth = imagesx($watermark);
        $wmHeight = imagesy($watermark);

        // 위치 계산
        [$x, $y] = $this->calculateWatermarkPosition(
            $srcWidth, $srcHeight,
            $wmWidth, $wmHeight,
            $position, $padding
        );

        // 워터마크 합성
        imagecopymerge($source, $watermark, $x, $y, 0, 0, $wmWidth, $wmHeight, $opacity);

        $result = $this->saveImage($source, $destPath, $info['type'], $quality);

        imagedestroy($source);
        imagedestroy($watermark);

        return $result;
    }

    /**
     * 이미지 압축 (품질 조정)
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param int $quality 품질 (1-100)
     * @return bool
     */
    public function compress(string $sourcePath, string $destPath, int $quality = 80): bool
    {
        $info = $this->getImageInfo($sourcePath);
        if (!$info) {
            return false;
        }

        $source = $this->loadImage($sourcePath);
        if (!$source) {
            return false;
        }

        $result = $this->saveImage($source, $destPath, $info['type'], $quality);
        imagedestroy($source);

        return $result;
    }

    /**
     * 이미지 회전
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param float $angle 회전 각도 (시계 반대 방향)
     * @param int $quality 품질
     * @return bool
     */
    public function rotate(
        string $sourcePath,
        string $destPath,
        float $angle,
        int $quality = 85
    ): bool {
        $source = $this->loadImage($sourcePath);
        if (!$source) {
            return false;
        }

        $info = $this->getImageInfo($sourcePath);
        if (!$info) {
            imagedestroy($source);
            return false;
        }

        $bgColor = imagecolorallocatealpha($source, 0, 0, 0, 127);
        $rotated = imagerotate($source, $angle, $bgColor);

        if (!$rotated) {
            imagedestroy($source);
            return false;
        }

        imagesavealpha($rotated, true);
        $result = $this->saveImage($rotated, $destPath, $info['type'], $quality);

        imagedestroy($source);
        imagedestroy($rotated);

        return $result;
    }

    /**
     * EXIF 기반 자동 회전 (스마트폰 사진)
     *
     * @param string $sourcePath 원본 이미지 경로
     * @param string $destPath 저장 경로
     * @param int $quality 품질
     * @return bool
     */
    public function autoRotate(string $sourcePath, string $destPath, int $quality = 85): bool
    {
        if (!function_exists('exif_read_data')) {
            return copy($sourcePath, $destPath);
        }

        $exif = @exif_read_data($sourcePath);
        if (!$exif || !isset($exif['Orientation'])) {
            return copy($sourcePath, $destPath);
        }

        $angle = match ($exif['Orientation']) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return copy($sourcePath, $destPath);
        }

        return $this->rotate($sourcePath, $destPath, $angle, $quality);
    }

    /**
     * 이미지 정보 조회
     *
     * @param string $path 이미지 경로
     * @return array|null ['width', 'height', 'type', 'mime']
     */
    public function getImageInfo(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        return [
            'width' => $info[0],
            'height' => $info[1],
            'type' => $info[2],
            'mime' => $info['mime'],
        ];
    }

    /**
     * 지원하는 이미지 타입인지 확인
     */
    public function isSupported(string $path): bool
    {
        $info = $this->getImageInfo($path);
        if (!$info) {
            return false;
        }

        return in_array($info['type'], $this->supportedTypes, true);
    }

    // === Private Methods ===

    /**
     * 이미지 로드
     */
    private function loadImage(string $path): ?\GdImage
    {
        $info = $this->getImageInfo($path);
        if (!$info) {
            return null;
        }

        return match ($info['type']) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        } ?: null;
    }

    /**
     * 이미지 저장
     */
    private function saveImage(\GdImage $image, string $path, int $type, int $quality): bool
    {
        // 디렉토리 생성
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, $quality),
            IMAGETYPE_PNG => imagepng($image, $path, (int) (9 - ($quality / 100 * 9))),
            IMAGETYPE_GIF => imagegif($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, $quality),
            default => false,
        };
    }

    /**
     * 투명도 유지 설정
     */
    private function preserveTransparency(\GdImage $image, int $type): void
    {
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
        }
    }

    /**
     * 워터마크 위치 계산
     */
    private function calculateWatermarkPosition(
        int $srcWidth,
        int $srcHeight,
        int $wmWidth,
        int $wmHeight,
        string $position,
        int $padding
    ): array {
        return match ($position) {
            'top-left' => [$padding, $padding],
            'top-right' => [$srcWidth - $wmWidth - $padding, $padding],
            'bottom-left' => [$padding, $srcHeight - $wmHeight - $padding],
            'bottom-right' => [$srcWidth - $wmWidth - $padding, $srcHeight - $wmHeight - $padding],
            'center' => [
                (int) (($srcWidth - $wmWidth) / 2),
                (int) (($srcHeight - $wmHeight) / 2),
            ],
            default => [$srcWidth - $wmWidth - $padding, $srcHeight - $wmHeight - $padding],
        };
    }
}
