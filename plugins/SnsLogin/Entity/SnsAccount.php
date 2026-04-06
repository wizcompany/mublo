<?php
namespace Mublo\Plugin\SnsLogin\Entity;

class SnsAccount
{
    public function __construct(
        private int     $id,
        private int     $domainId,
        private int     $memberId,
        private string  $provider,
        private string  $providerUid,
        private ?string $providerEmail,
        private string  $linkedAt,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:            (int) $row['id'],
            domainId:      (int) $row['domain_id'],
            memberId:      (int) $row['member_id'],
            provider:      $row['provider'],
            providerUid:   $row['provider_uid'],
            providerEmail: $row['provider_email'] ?? null,
            linkedAt:      $row['linked_at'],
        );
    }

    public function getId(): int          { return $this->id; }
    public function getMemberId(): int    { return $this->memberId; }
    public function getProvider(): string { return $this->provider; }
}
