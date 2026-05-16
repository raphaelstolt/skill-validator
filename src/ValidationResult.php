<?php

declare(strict_types=1);

namespace Stolt\Ai\Skill;

use Stolt\Ai\SkillMd;

final readonly class ValidationResult
{
    /**
     * @param list<string> $errors
     * @param array<string, mixed> $rawMetadata
     */
    private function __construct(
        private bool      $valid,
        private array     $errors,
        private ?Metadata $metadata,
        private array     $rawMetadata,
        private string    $body,
        private ?SkillMd  $skillMd = null,
    ) {
    }

    public static function valid(Metadata $metadata, string $body): self
    {
        return new self(
            true,
            [],
            $metadata,
            $metadata->toArray(),
            $body,
            SkillMd::create(
                $metadata->name(),
                $metadata->description(),
                $body,
                $metadata->additionalFields()
            )
        );
    }

    /**
     * @param list<string> $errors
     * @param array<string, mixed> $rawMetadata
     */
    public static function invalid(array $errors, array $rawMetadata = [], string $body = ''): self
    {
        $metadata = null;

        if (
            isset($rawMetadata['name'], $rawMetadata['description'])
            && \is_string($rawMetadata['name'])
            && \trim($rawMetadata['name']) !== ''
            && \is_string($rawMetadata['description'])
            && \trim($rawMetadata['description']) !== ''
        ) {
            $metadata = Metadata::fromArray($rawMetadata);
        }

        return new self(
            false,
            $errors,
            $metadata,
            $rawMetadata,
            $body
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isInvalid(): bool
    {
        return $this->valid === false;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function metadata(): ?Metadata
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function rawMetadata(): array
    {
        return $this->rawMetadata;
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata?->get($key, $default) ?? $this->rawMetadata[$key] ?? $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function skillMd(): ?SkillMd
    {
        return $this->skillMd;
    }

    public function toSkillMd(): SkillMd
    {
        if ($this->skillMd === null) {
            throw new \LogicException(
                'Cannot convert an invalid ValidationResult to a SkillMd instance.'
            );
        }

        return $this->skillMd;
    }

    /**
     * @return array{
     *     valid: bool,
     *     errors: list<string>,
     *     metadata: array<string, mixed>|null,
     *     raw_metadata: array<string, mixed>,
     *     body: string
     * }
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'metadata' => $this->metadata?->toArray(),
            'raw_metadata' => $this->rawMetadata,
            'body' => $this->body,
        ];
    }
}
