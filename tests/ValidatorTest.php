<?php

declare(strict_types=1);

namespace Stolt\Ai\Skill\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\Ai\Skill\ValidationResult;
use Stolt\Ai\Skill\Validator;
use Stolt\Ai\SkillMd;

final class ValidatorTest extends TestCase
{
    #[Test]
    public function itValidatesAllSkillMdFilesInADirectory(): void
    {
        $this->setUpTemporaryDirectory();

        foreach (['release-notes', 'code-review'] as $skillName) {
            $skillDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . $skillName;
            \mkdir($skillDirectory);
            \file_put_contents(
                $skillDirectory . DIRECTORY_SEPARATOR . 'SKILL.md',
                <<<MARKDOWN
---
name: {$skillName}
description: A skill named {$skillName}.
---

# {$skillName}

Instructions for {$skillName}.
MARKDOWN
            );
        }

        $results = (new Validator())->validateFromDirectory($this->temporaryDirectory);

        self::assertCount(2, $results);

        foreach ($results as $result) {
            self::assertTrue($result->isValid());
        }
    }

    #[Test]
    public function itReturnsMixedResultsForADirectoryWithInvalidSkillMdFiles(): void
    {
        $this->setUpTemporaryDirectory();

        $validDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'release-notes';
        \mkdir($validDirectory);
        \file_put_contents($validDirectory . DIRECTORY_SEPARATOR . 'SKILL.md', <<<MARKDOWN
---
name: release-notes
description: Generate release notes from a changelog and commit history.
---

# Release notes

Create concise release notes grouped by change type.
MARKDOWN);

        $invalidDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'broken-skill';
        \mkdir($invalidDirectory);
        \file_put_contents($invalidDirectory . DIRECTORY_SEPARATOR . 'SKILL.md', <<<MARKDOWN
# Missing frontmatter

This skill has no YAML frontmatter.
MARKDOWN);

        $results = (new Validator())->validateFromDirectory($this->temporaryDirectory);

        self::assertCount(2, $results);

        $validCount = \count(\array_filter($results, fn ($r) => $r->isValid()));
        $invalidCount = \count(\array_filter($results, fn ($r) => $r->isInvalid()));

        self::assertSame(1, $validCount);
        self::assertSame(1, $invalidCount);
    }

    #[Test]
    public function itReturnsAnEmptyArrayForADirectoryWithoutSkillMdFiles(): void
    {
        $this->setUpTemporaryDirectory();

        $results = (new Validator())->validateFromDirectory($this->temporaryDirectory);

        self::assertSame([], $results);
    }

    #[Test]
    public function itThrowsAnExceptionForANonExistentDirectory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory does not exist: /does/not/exist');

        (new Validator())->validateFromDirectory('/does/not/exist');
    }

    #[Test]
    public function itDelegatesToValidateFromDirectoryWhenInputIsADirectoryPath(): void
    {
        $this->setUpTemporaryDirectory();

        $skillDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'release-notes';
        \mkdir($skillDirectory);
        \file_put_contents($skillDirectory . DIRECTORY_SEPARATOR . 'SKILL.md', <<<MARKDOWN
---
name: release-notes
description: Generate release notes from a changelog and commit history.
---

# Release notes

Create concise release notes grouped by change type.
MARKDOWN);

        $results = (new Validator())->validate($this->temporaryDirectory);

        self::assertIsArray($results);
        self::assertCount(1, $results);

        foreach ($results as $result) {
            self::assertTrue($result->isValid());
        }
    }

    #[Test]
    public function itDelegatesToValidateFileWhenInputIsAFilePath(): void
    {
        $this->setUpTemporaryDirectory();

        $skillDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'release-notes';
        \mkdir($skillDirectory);
        $skillFile = $skillDirectory . DIRECTORY_SEPARATOR . 'SKILL.md';

        \file_put_contents($skillFile, <<<MARKDOWN
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
        $content = <<<MARKDOWN
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
        $content = <<<MARKDOWN
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

        $skillDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'release-notes';
        \mkdir($skillDirectory);
        $skillFile = $skillDirectory . DIRECTORY_SEPARATOR . 'SKILL.md';

        \file_put_contents($skillFile, <<<MARKDOWN
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
        $content = <<<MARKDOWN
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
        $content = <<<MARKDOWN
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
        $content = <<<MARKDOWN
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
    public function itReportsNameMismatchWithParentDirectory(): void
    {
        $this->setUpTemporaryDirectory();

        $skillDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'wrong-name';
        \mkdir($skillDirectory);
        $skillFile = $skillDirectory . DIRECTORY_SEPARATOR . 'SKILL.md';

        \file_put_contents($skillFile, <<<MARKDOWN
---
name: release-notes
description: Generate release notes from a changelog and commit history.
---

# Release notes

Create concise release notes grouped by change type.
MARKDOWN);

        $result = (new Validator())->validateFile($skillFile);

        self::assertTrue($result->isInvalid());
        self::assertContains(
            'Frontmatter field name must match the parent directory name "wrong-name".',
            $result->errors()
        );
    }

    #[Test]
    public function itReportsUnexpectedFields(): void
    {
        $content = <<<MARKDOWN
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
        $content = <<<MARKDOWN
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
        $content = <<<MARKDOWN
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
        $content = <<<MARKDOWN
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

    #[Test]
    public function itExposesAPopulatedSkillMdInstance(): void
    {
        $content = <<<MARKDOWN
---
name: code-review
description: Review code changes and provide actionable feedback.
---

# Code review

Review the changed files.
MARKDOWN;

        $expectedBody = <<<BODY_MARKDOWN

# Code review

Review the changed files.
BODY_MARKDOWN;

        $result = (new Validator())->validateContent($content);

        $skillMdViaAccessor = $result->skillMd();
        self::assertInstanceOf(SkillMd::class, $skillMdViaAccessor);
        self::assertSame('code-review', $skillMdViaAccessor->name());
        self::assertSame(
            'Review code changes and provide actionable feedback.',
            $skillMdViaAccessor->description()
        );
        self::assertSame($expectedBody, $skillMdViaAccessor->body());

        $skillMdViaConversion = $result->toSkillMd();
        self::assertSame($skillMdViaAccessor, $skillMdViaConversion);
    }

    #[Test]
    public function itValidatesASkillMdInstance(): void
    {
        $skillMd = SkillMd::create(
            'code-review',
            'Review code changes and provide actionable feedback.',
            "# Code review\n\nReview the changed files."
        );

        $result = (new Validator())->validateSkillMd($skillMd);
        $metadata = $result->metadata();

        self::assertTrue($result->isValid());
        self::assertNotNull($metadata);
        self::assertSame('code-review', $metadata->name());
        self::assertSame(
            'Review code changes and provide actionable feedback.',
            $metadata->description()
        );
    }

    #[Test]
    public function itValidatesASkillMdInstanceWithAdditionalFields(): void
    {
        $skillMd = SkillMd::create(
            'code-review',
            'Review code changes and provide actionable feedback.',
            "# Code review\n\nReview the changed files.",
            ['tags' => ['php', 'review'], 'version' => '1.0.0']
        );

        $result = (new Validator())->validateSkillMd($skillMd);
        $metadata = $result->metadata();

        self::assertTrue($result->isValid());
        self::assertNotNull($metadata);
        self::assertSame(['php', 'review'], $metadata->tags());
        self::assertSame('1.0.0', $metadata->version());
    }

    #[Test]
    public function itDelegatesToValidateSkillMdWhenInputIsASkillMdInstance(): void
    {
        $skillMd = SkillMd::create(
            'release-notes',
            'Generate release notes from a changelog and commit history.',
            "# Release notes\n\nCreate concise release notes grouped by change type."
        );

        $result = (new Validator())->validate($skillMd);

        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertTrue($result->isValid());
        $metadata = $result->metadata();
        self::assertNotNull($metadata);
        self::assertSame('release-notes', $metadata->name());
    }

    #[Test]
    public function itRoundTripsFromContentValidationToSkillMdAndBackThroughValidation(): void
    {
        $content = <<<MARKDOWN
---
name: code-review
description: Review code changes and provide actionable feedback.
tags:
  - php
  - review
version: 1.0.0
---

# Code review

Review the changed files and report issues.
MARKDOWN;

        $firstResult = (new Validator())->validateContent($content);
        self::assertTrue($firstResult->isValid());

        $skillMd = $firstResult->toSkillMd();
        self::assertInstanceOf(SkillMd::class, $skillMd);

        $secondResult = (new Validator())->validateSkillMd($skillMd);
        $secondMetadata = $secondResult->metadata();

        self::assertTrue($secondResult->isValid());
        self::assertNotNull($secondMetadata);
        self::assertSame('code-review', $secondMetadata->name());
        self::assertSame(
            'Review code changes and provide actionable feedback.',
            $secondMetadata->description()
        );
        self::assertSame(['php', 'review'], $secondMetadata->tags());
        self::assertSame('1.0.0', $secondMetadata->version());
    }

    #[Test]
    public function itExposesNullSkillMdForAnInvalidResult(): void
    {
        $content = <<<MARKDOWN
# Missing frontmatter

This skill has no frontmatter.
MARKDOWN;

        $result = (new Validator())->validateContent($content);

        self::assertTrue($result->isInvalid());
        self::assertNull($result->skillMd());
    }

    #[Test]
    public function itThrowsWhenConvertingAnInvalidResultToSkillMd(): void
    {
        $content = <<<MARKDOWN
# Missing frontmatter

This skill has no frontmatter.
MARKDOWN;

        $result = (new Validator())->validateContent($content);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot convert an invalid ValidationResult to a SkillMd instance.');
        $result->toSkillMd();
    }
}
