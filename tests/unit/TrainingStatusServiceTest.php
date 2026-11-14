<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Tests\Unit;

use OCA\Leiterrunde\Db\Person;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\LeiterrundeSchulungen\Db\FzRecordMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingRecord;
use OCA\LeiterrundeSchulungen\Db\TrainingRecordMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingType;
use OCA\LeiterrundeSchulungen\Db\TrainingTypeMapper;
use OCA\LeiterrundeSchulungen\Service\ModuleConfigService;
use OCA\LeiterrundeSchulungen\Service\TrainingRulesService;
use OCA\LeiterrundeSchulungen\Service\TrainingStatusService;
use PHPUnit\Framework\TestCase;

class TrainingStatusServiceTest extends TestCase {
	public function testStatusIncludesOnlyApprovedCurrentContributingRecords(): void {
		$person = new class extends Person {
			public function __construct() {}
			public function getId(): int { return 7; }
		};
		$type = new class extends TrainingType {
			public function __construct() {}
			public function getId(): int { return 1; }
			public function getName(): string { return 'Erste Hilfe'; }
			public function getRequired(): bool { return true; }
			public function getCountsHours(): bool { return true; }
			public function getValidityMonths(): int { return 24; }
		};
		$types = $this->createMock(TrainingTypeMapper::class);
		$types->method('findAll')->with(true)->willReturn([$type]);
		$records = $this->createMock(TrainingRecordMapper::class);
		$records->method('findApprovedForPerson')->with(7)->willReturn([
			$this->record(1, '2026-01-01', '8.00'),
			$this->record(2, '2020-01-01', '20.00'),
			$this->record(3, '2027-01-01', '8.00'),
		]);
		$config = $this->createMock(ModuleConfigService::class);
		$config->method('get')->willReturnCallback(static fn (string $key): string => [
			'warn_days' => '60', 'hours_required' => '8', 'hours_window_months' => '36',
		][$key]);
		$service = new TrainingStatusService(new TrainingRulesService(), $types, $records,
			$this->createMock(FzRecordMapper::class), $this->createMock(PersonMapper::class), $config);
		$status = $service->personStatus($person, new \DateTimeImmutable('2026-06-01'));
		$this->assertSame('ok', $status['overall']);
		$this->assertCount(1, $status['types'][0]['records']);
		$this->assertSame('2028-01-01', $status['types'][0]['records'][0]['expires']);
		$this->assertSame([
			'id' => 1, 'type_id' => 1, 'title' => 'First aid',
			'attended_date' => '2026-01-01', 'hours' => 8.0,
			'expires' => '2029-01-01', 'valid_until' => '2028-12-31',
		], $status['hours']['records'][0]);
		$this->assertSame('2028-12-31', $status['hours']['valid_until']);
	}

	public function testFzForViewerShowsOwnExpiryWithoutFzPermission(): void {
		$service = $this->serviceWithFz();
		$person = $this->person();
		$at = new \DateTimeImmutable('2026-06-01');
		$this->assertSame(['state' => 'ok', 'issued' => '2026-01-01', 'expires' => '2032-01-01'],
			$service->fzForViewer($person, $at, true, false, false));
		$this->assertSame(['state' => 'ok', 'issued' => '2026-01-01', 'expires' => '2032-01-01'],
			$service->fzForViewer($person, $at, false, true, false));
		$this->assertSame(['state' => 'ok', 'expires' => '2032-01-01'],
			$service->fzForViewer($person, $at, false, false, true));
		$this->assertSame(['state' => 'ok'],
			$service->fzForViewer($person, $at, false, false, false));
	}

	private function serviceWithFz(): TrainingStatusService {
		$fz = new class extends \OCA\LeiterrundeSchulungen\Db\FzRecord {
			public function __construct() {}
			public function getIssuedAt(): string { return '2026-01-01'; }
			public function getValidMonths(): int { return 72; }
		};
		$fzMapper = $this->createMock(FzRecordMapper::class);
		$fzMapper->method('findLatestForPerson')->with(7)->willReturn($fz);
		$config = $this->createMock(ModuleConfigService::class);
		$config->method('get')->willReturnCallback(static fn (string $key): string => [
			'warn_days' => '60', 'hours_required' => '8', 'hours_window_months' => '36',
		][$key]);
		return new TrainingStatusService(new TrainingRulesService(),
			$this->createMock(TrainingTypeMapper::class), $this->createMock(TrainingRecordMapper::class),
			$fzMapper, $this->createMock(PersonMapper::class), $config);
	}

	private function person(): Person {
		return new class extends Person {
			public function __construct() {}
			public function getId(): int { return 7; }
		};
	}

	private function record(int $id, string $date, string $hours): TrainingRecord {
		return new class($id, $date, $hours) extends TrainingRecord {
			public function __construct(private int $recordId, private string $date, private string $recordHours) {}
			public function getId(): int { return $this->recordId; }
			public function getTypeId(): int { return 1; }
			public function getTitle(): string { return 'First aid'; }
			public function getAttendedDate(): string { return $this->date; }
			public function getHours(): string { return $this->recordHours; }
		};
	}
}
