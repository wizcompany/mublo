<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Mublo\Service\Member\MemberService;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Repository\Member\MemberFieldRepository;
use Mublo\Service\Member\FieldEncryptionService;

#[CoversClass(MemberService::class)]
class MemberServiceTest extends TestCase
{
    private MemberService $service;
    private MockObject $repositoryMock;
    private MockObject $fieldRepositoryMock;
    private MockObject $encryptionServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryMock = $this->createMock(MemberRepository::class);
        $this->fieldRepositoryMock = $this->createMock(MemberFieldRepository::class);
        // FieldEncryptionService는 security.php 파일이 필요하므로 Mock 처리
        $this->encryptionServiceMock = $this->createMock(FieldEncryptionService::class);
        $this->service = new MemberService(
            $this->repositoryMock,
            $this->fieldRepositoryMock,
            $this->encryptionServiceMock
        );
    }

    #[Test]
    public function testRegisterWithDuplicateUserId(): void
    {
        // Arrange: domain_id를 data 배열 안에 포함
        $validData = [
            'domain_id' => 1,
            'user_id' => 'duplicateuser',
            'password' => 'password123',
        ];

        // checkUserIdAvailability → existsByUserId 호출됨
        $this->repositoryMock->expects($this->once())
            ->method('existsByUserId')
            ->with(1, 'duplicateuser')
            ->willReturn(true);

        // Act
        $result = $this->service->register($validData);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('사용 중인 아이디', $result->getMessage());
    }

    #[Test]
    public function testRegisterWithLongUserId(): void
    {
        // Arrange
        $invalidData = [
            'domain_id' => 1,
            'user_id' => 'thisuseridistoolongandshouldfail',
            'password' => 'password123',
        ];

        // The validation should fail BEFORE the DB check.
        $this->repositoryMock->expects($this->never())
            ->method('existsByUserId');

        // Act
        $result = $this->service->register($invalidData);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('아이디는 영문, 숫자', $result->getMessage());
    }

    #[Test]
    public function testRegisterWithSpecialCharactersInUserId(): void
    {
        // Arrange
        $invalidData = [
            'domain_id' => 1,
            'user_id' => 'invalid-id!',
            'password' => 'password123',
        ];

        // The validation should fail BEFORE the DB check.
        $this->repositoryMock->expects($this->never())
            ->method('existsByUserId');

        // Act
        $result = $this->service->register($invalidData);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('아이디는 영문, 숫자', $result->getMessage());
    }

    #[Test]
    public function testRegisterWithShortUserId(): void
    {
        // Arrange
        $invalidData = [
            'domain_id' => 1,
            'user_id' => 'sho',
            'password' => 'password123',
        ];

        // The validation should fail BEFORE the DB check.
        $this->repositoryMock->expects($this->never())
            ->method('existsByUserId');

        // Act
        $result = $this->service->register($invalidData);

        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('아이디는 영문, 숫자', $result->getMessage());
    }
}
