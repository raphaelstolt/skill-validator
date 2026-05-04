<?php

declare(strict_types=1);

namespace Stolt\Ai\Skill\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\Ai\Skill\Validator;

final class ValidatorTest extends TestCase
{
    #[Test]
    public function itDelegatesToValidateFileWhenInputIsAFilePath(): void
    {
        $this->setUpTemporaryDirectory();

        $skillFile = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'SKILL.md';

        \file_put_contents($skillFile, <<<'MARKDOWN'
---
name: release-notes
description: Generate release notes from a changelog and commit history.
---

# Release notes

Create concise release notes grouped by change type.
MARKDOWN);

        $result = (new Validator())->validate($skillFile);
        $metadata = $result->metadata();

        self::assertTrue($result->isValid());
        self::assertNotNull($metadata);
        self::assertSame('release-notes', $metadata->name());
        self::assertSame(
            'Generate release notes from a changelog and commit history.',
            $metadata->description()
        );
    }

    #[Test]
    public function itDelegatesToValidateContentWhenInputIsRawContent(): void
    {
        $content = <<<'MARKDOWN'
---
name: code-review
description: Review code changes and provide actionable feedback.
---

# Code review

Review the changed files and report correctness, security, and maintainability issues.
MARKDOWN;

        $result = (new Validator())->validate($content);
        $metadata = $result->metadata();

        self::assertTrue($result->isValid());
        self::assertNotNull($metadata);
        self::assertSame('code-review', $metadata->name());
        self::assertSame('Review code changes and provide actionable feedback.', $metadata->description());
    }

    #[Test]
    public function itDelegatesToValidateContentWhenInputIsNotAnExistingFile(): void
    {
        $result = (new Validator())->validate('/does/not/exist/SKILL.md');

        self::assertTrue($result->isInvalid());
        self::assertSame(
            ['SKILL.md must start with YAML frontmatter delimited by --- lines.'],
            $result->errors()
        );
    }

    #[Test]
    public function itValidatesAValidSkillMdContent(): void
    {
        $content = <<<'MARKDOWN'
---
name: code-review
description: Review code changes and provide actionable feedback.
version: 1.0.0
tags: [php, review]
allowed-tools:
  - Read
  - Grep
disable-model-invocation: false
---

# Code review

Review the changed files and report correctness, security, and maintainability issues.
MARKDOWN;

        $result = (new Validator())->validateContent($content);
        $metadata = $result->metadata();

        self::assertTrue($result->isValid());
        self::assertFalse($result->isInvalid());
        self::assertSame([], $result->errors());
        self::assertNotNull($metadata);
        self::assertSame('code-review', $metadata->name());
        self::assertSame('Review code changes and provide actionable feedback.', $metadata->description());
        self::assertSame('1.0.0', $metadata->version());
        self::assertSame(['php', 'review'], $metadata->tags());
        self::assertSame(['Read', 'Grep'], $metadata->get('allowed-tools'));
        self::assertStringContainsString('# Code review', $result->body());
    }

    #[Test]
    public function itParsesAndValidatesASkillMdFile(): void
    {
        $this->setUpTemporaryDirectory();

        $skillFile = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'SKILL.md';

        \file_put_contents($skillFile, <<<'MARKDOWN'
---
name: release-notes
description: Generate release notes from a changelog and commit history.
---

# Release notes

Create concise release notes grouped by change type.
MARKDOWN);

        $result = (new Validator())->validateFile($skillFile);
        $metadata = $result->metadata();

        self::assertTrue($result->isValid());
        self::assertNotNull($metadata);
        self::assertSame('release-notes', $metadata->name());
        self::assertSame(
            'Generate release notes from a changelog and commit history.',
            $metadata->description()
        );
    }

    #[Test]
    public function itReportsMissingFrontmatter(): void
    {
        $content = <<<'MARKDOWN'
# Code review

Review the changed files.
MARKDOWN;

        $result = (new Validator())->validateContent($content);

        self::assertTrue($result->isInvalid());
        self::assertSame(
            ['SKILL.md must start with YAML frontmatter delimited by --- lines.'],
            $result->errors()
        );
        self::assertNull($result->metadata());
        self::assertSame([], $result->rawMetadata());
        self::assertSame('', $result->body());
    }

    #[Test]
    public function itReportsMissingRequiredFields(): void
    {
        $content = <<<'MARKDOWN'
---
version: 1.0.0
---

# Invalid skill

Missing required metadata.
MARKDOWN;

        $result = (new Validator())->validateContent($content);

        self::assertTrue($result->isInvalid());
        self::assertContains('Missing required frontmatter field: name.', $result->errors());
        self::assertContains('Missing required frontmatter field: description.', $result->errors());
    }

    #[Test]
    public function itReportsInvalidName(): void
    {
        $content = <<<'MARKDOWN'
---
name: Invalid Skill
description: Review code changes and provide actionable feedback.
---

# Invalid skill

Invalid name format.
MARKDOWN;

        $result = (new Validator())->validateContent($content);

        self::assertTrue($result->isInvalid());
        self::assertContains(
            'Frontmatter field name must use lowercase letters, numbers, and hyphens, and must not start or end with a hyphen.',
            $result->errors()
        );
    }

    #[Test]
    public function itReportsUnexpectedFields(): void
    {
        $content = <<<'MARKDOWN'
---
name: code-review
description: Review code changes and provide actionable feedback.
unexpected: value
---

# Code review

Review the changed files.
MARKDOWN;

        $result = (new Validator())->validateContent($content);

        self::assertTrue($result->isInvalid());
        self::assertContains('Unexpected frontmatter field(s): unexpected.', $result->errors());
    }

    #[Test]
    public function itReportsEmptyMarkdownInstructions(): void
    {
        $content = <<<'MARKDOWN'
---
name: code-review
description: Review code changes and provide actionable feedback.
---
MARKDOWN;

        $result = (new Validator())->validateContent($content);

        self::assertTrue($result->isInvalid());
        self::assertContains(
            'SKILL.md must contain Markdown instructions after the frontmatter.',
            $result->errors()
        );
    }

    #[Test]
    public function itReportsMissingFiles(): void
    {
        $result = (new Validator())->validateFile('/does/not/exist/SKILL.md');

        self::assertTrue($result->isInvalid());
        self::assertSame(
            ['SKILL.md file does not exist: /does/not/exist/SKILL.md'],
            $result->errors()
        );
    }

    #[Test]
    public function itCanBeConvertedToAnArray(): void
    {
        $content = <<<'MARKDOWN'
---
name: code-review
description: Review code changes and provide actionable feedback.
---

# Code review

Review the changed files.
MARKDOWN;

        $result = (new Validator())->validateContent($content);

        self::assertSame([
            'valid' => $result->isValid(),
            'errors' => $result->errors(),
            'metadata' => $result->metadata()?->toArray(),
            'raw_metadata' => $result->rawMetadata(),
            'body' => $result->body(),
        ], $result->toArray());
    }

    #[Test]
    public function itExposesRequiredMetadataFieldsAsDedicatedMethods(): void
    {
        $content = <<<'MARKDOWN'
---
name: code-review
description: Review code changes and provide actionable feedback.
---

# Code review

Review the changed files.
MARKDOWN;

        $result = (new Validator())->validateContent($content);
        $metadata = $result->metadata();

        self::assertTrue($result->isValid());
        self::assertNotNull($metadata);
        self::assertSame('code-review', $metadata->name());
        self::assertSame(
            'Review code changes and provide actionable feedback.',
            $metadata->description()
        );
    }
}
