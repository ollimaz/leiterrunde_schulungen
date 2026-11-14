<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Tests\Unit;

use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeSchulungen\Db\TrainingRecord;
use OCA\LeiterrundeSchulungen\Db\TrainingRecordMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingSession;
use OCA\LeiterrundeSchulungen\Db\TrainingSessionMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingType;
use OCA\LeiterrundeSchulungen\Db\TrainingTypeMapper;
use OCA\LeiterrundeSchulungen\Service\TrainingSessionService;
use OCP\IDBConnection;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrainingSessionServiceTest extends TestCase {
	private PermissionService $perm;
	private TrainingSessionMapper $sessions;
	private TrainingRecordMapper $records;
	private TrainingTypeMapper $types;
	private PersonMapper $persons;
	private IDBConnection $db;
	private AuditService $audit;
	private TrainingSessionService $service;

	protected function setUp(): void {
		$this->perm = $this->createMock(PermissionService::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('trainer');
		$this->perm->method('requireLoggedIn')->willReturn($user);
		$this->sessions = $this->createMock(TrainingSessionMapper::class);
		$this->records = $this->createMock(TrainingRecordMapper::class);
		$this->types = $this->createMock(TrainingTypeMapper::class);
		$this->persons = $this->createMock(PersonMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->audit = $this->createMock(AuditService::class);
		$this->service = new TrainingSessionService($this->perm, $this->sessions, $this->records,
			$this->types, $this->persons, $this->db, $this->audit);
	}

	public function testValidationNormalizesSharedFields(): void {
		$this->assertSame(['type_id' => 1, 'attended_date' => '2026-11-01', 'hours' => '2.50',
			'title' => 'First aid', 'notes' => null], TrainingSessionService::validate(1, '2026-11-01', 2.5, ' First aid ', [1, 2], null));
	}

	#[DataProvider('invalidData')]
	public function testInvalidSessionRejected(string $date, float $hours, string $title, array $ids, ?string $notes): void {
		$this->expectException(ApiException::class);
		TrainingSessionService::validate(1, $date, $hours, $title, $ids, $notes);
	}

	public static function invalidData(): array {
		return [
			['2026-02-30', 2, 'Training', [1], null],
			['2026-11-01', -1, 'Training', [1], null],
			['2026-11-01', INF, 'Training', [1], null],
			['2026-11-01', NAN, 'Training', [1], null],
			['2026-11-01', 10000, 'Training', [1], null],
			['2026-11-01', 2, ' ', [1], null],
			['2026-11-01', 2, str_repeat('x', 256), [1], null],
			['2026-11-01', 2, 'Training', [], null],
			['2026-11-01', 2, 'Training', [1, 1], null],
			['2026-11-01', 2, 'Training', ['1'], null],
			['2026-11-01', 2, 'Training', [0], null],
			['2026-11-01', 2, 'Training', [[1]], null],
			['2026-11-01', 2, 'Training', range(1, 101), null],
			['2026-11-01', 2, 'Training', [1], str_repeat('x', 10001)],
		];
	}

	public function testUnauthorizedCreateStopsBeforeDatabaseAccess(): void {
		$this->perm->expects($this->once())->method('require')->with('training.records.submitOther')->willThrowException(new ApiException('No permission', 403));
		$this->types->expects($this->never())->method('find');
		$this->db->expects($this->never())->method('beginTransaction');
		$this->expectException(ApiException::class);
		$this->service->create(1, '2026-11-01', 2, 'Training', [1], null);
	}

	public function testCreationCommitsOneSessionAndAllParticipants(): void {
		$this->activeType();
		$this->persons->expects($this->exactly(2))->method('find');
		$session = $this->session();
		$this->sessions->expects($this->once())->method('createSession')->willReturn($session);
		$this->records->expects($this->exactly(2))->method('createForSession')
			->willReturnCallback(fn ($s, $id, $uid) => $this->record($id));
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');
		$this->audit->expects($this->exactly(3))->method('diff');
		$result = $this->service->create(1, '2026-11-01', 2, 'Training', [1, 2], null);
		$this->assertSame(10, $result['id']);
		$this->assertSame([1, 2], array_column($result['records'], 'person_id'));
	}

	public function testFailureRollsBackEntireSession(): void {
		$this->activeType();
		$this->sessions->method('createSession')->willReturn($this->session());
		$this->records->method('createForSession')->willReturnCallback(function ($s, $id) {
			if ($id === 2) { throw new \RuntimeException('Insert failed'); }
			return $this->record($id);
		});
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');
		$this->expectException(\RuntimeException::class);
		$this->service->create(1, '2026-11-01', 2, 'Training', [1, 2], null);
	}

	public function testAllPersonsValidatedBeforeTransaction(): void {
		$this->activeType();
		$this->persons->method('find')->willThrowException(new ApiException('Not found', 404));
		$this->db->expects($this->never())->method('beginTransaction');
		$this->sessions->expects($this->never())->method('createSession');
		$this->expectException(ApiException::class);
		$this->service->create(1, '2026-11-01', 2, 'Training', [1, 2], null);
	}

	public function testSessionDetailsCannotExposeAnotherCreatorsParticipants(): void {
		$this->perm->method('has')->willReturn(false);
		$this->sessions->method('find')->willReturn($this->session('other'));
		$this->records->expects($this->never())->method('findForSession');
		$this->expectException(ApiException::class);
		$this->service->show(10);
	}

	public function testSessionListScopedToCreatorWithoutReadAll(): void {
		$this->perm->method('has')->willReturn(false);
		$this->sessions->expects($this->once())->method('findRecent')->with('trainer')->willReturn([]);
		$this->assertSame([], $this->service->index());
	}

	public function testIndividualCannotAlterSharedSessionFields(): void {
		$this->expectException(ApiException::class);
		TrainingSessionService::assertRecordFields($this->record(1), 2, null, null, null);
	}

	private function activeType(): void {
		$this->types->method('find')->willReturn(new class extends TrainingType {
			public function __construct() {}
			public function getActive(): bool { return true; }
		});
	}

	private function session(string $creator = 'trainer'): TrainingSession {
		return new class($creator) extends TrainingSession {
			public function __construct(private string $creator) {}
			public function getId(): int { return 10; }
			public function getCreatedBy(): string { return $this->creator; }
			public function jsonSerialize(): array { return ['id' => 10]; }
		};
	}

	private function record(int $personId): TrainingRecord {
		return new class($personId) extends TrainingRecord {
			public function __construct(private int $person) {}
			public function getId(): int { return $this->person; }
			public function getSessionId(): int { return 10; }
			public function getTypeId(): int { return 1; }
			public function jsonSerialize(): array { return ['person_id' => $this->person]; }
		};
	}
}
