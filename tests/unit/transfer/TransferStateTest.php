<?php

/**
 * Copyright (c) 2014-2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\transfer;

use APP\plugins\importexport\fullJournalTransfer\transfer\TransferState;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TransferStateTest extends TestCase
{
    public function testItMaintainsReferenceMaps(): void
    {
        $state = new TransferState();

        $state->mapReference('submission', 'source-1', 42);

        $this->assertSame(42, $state->requireReference('submission', 'source-1'));
        $this->assertSame(['source-1' => 42], $state->getReferenceMap('submission'));

        $state->resetReferenceMap('submission');

        $this->assertSame([], $state->getReferenceMap('submission'));
    }

    public function testItRejectsAMissingReference(): void
    {
        $state = new TransferState();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing mapped user reference: "source-1"');

        $state->requireReference('user', 'source-1');
    }

    public function testItMaintainsTransientTransferResults(): void
    {
        $state = new TransferState();
        $conflict = ['username' => 'existing'];
        $rejection = ['family' => 'institution', 'reason' => 'missing ROR'];

        $state->addUserConflict($conflict);
        $state->addMetricRejection($rejection);
        $state->setCurrentReviewFormId(9);
        $state->setSubmissionsByIssue([3 => [10, 11]]);

        $this->assertSame([$conflict], $state->getUserConflicts());
        $this->assertSame([$rejection], $state->getMetricRejections());
        $this->assertSame(9, $state->getCurrentReviewFormId());
        $this->assertSame([10, 11], $state->getSubmissionsForIssue(3));

        $state->resetUserConflicts();
        $state->resetMetricRejections();
        $state->setCurrentReviewFormId(null);

        $this->assertSame([], $state->getUserConflicts());
        $this->assertSame([], $state->getMetricRejections());
        $this->assertNull($state->getCurrentReviewFormId());
        $this->assertSame([], $state->getSubmissionsForIssue(99));
    }
}
