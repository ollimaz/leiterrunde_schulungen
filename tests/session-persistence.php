<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once dirname(__DIR__, 3) . '/lib/base.php';

use OCA\LeiterrundeSchulungen\Db\TrainingRecordMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingSessionMapper;
use OCA\LeiterrundeSchulungen\Service\TrainingSessionService;
use OCP\IDBConnection;

$db = \OC::$server->get(IDBConnection::class);
$sessions = new TrainingSessionMapper($db);
$records = new TrainingRecordMapper($db);
$check = static function (bool $condition, string $message): void {
	if (!$condition) { throw new \RuntimeException($message); }
};
$db->beginTransaction();
try {
	$session = $sessions->createSession([
		'type_id' => 1, 'attended_date' => '2026-11-01', 'hours' => '2.50',
		'title' => 'Group training persistence test', 'notes' => null,
	], 'group-training-test');
	$first = $records->createForSession($session, 1, 'group-training-test');
	$second = $records->createForSession($session, 2, 'group-training-test');
	$check($first->getId() !== $second->getId(), 'Participants must have distinct records');
	$check($first->getSessionId() === $session->getId(), 'Session link must persist as an integer');
	$check($sessions->find($session->getId())->getTitle() === $session->getTitle(), 'Session round trip failed');
	$found = $records->findForSession($session->getId());
	$check(count($found) === 2, 'Both participants must be linked to one session');
	$check($found[0]->getHours() === '2.50' && $found[1]->getHours() === '2.50', 'Shared hours must persist');
	$check($found[0]->getFileId() === null && $found[1]->getFileId() === null, 'Certificates must be independent');
	$check($found[0]->getStatus() === 'pending', 'Creating a session must not approve training records');
	TrainingSessionService::assertRecordFields($found[0], 1, '2026-11-01', 2.5, $session->getTitle());
	$resolved = \OC::$server->get(\OCA\LeiterrundeSchulungen\Controller\Api\TrainingRecordsController::class);
	$check($resolved instanceof \OCA\LeiterrundeSchulungen\Controller\Api\TrainingRecordsController, 'Controller dependency resolution failed');
	fwrite(STDOUT, "Session persistence and dependency resolution passed; all fixture writes are rolled back.\n");
} finally {
	$db->rollBack();
}
