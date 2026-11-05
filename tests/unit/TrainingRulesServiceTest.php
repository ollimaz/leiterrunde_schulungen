<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Tests\Unit;

use OCA\LeiterrundeSchulungen\Service\TrainingRulesService;
use PHPUnit\Framework\TestCase;

class TrainingRulesServiceTest extends TestCase {
	private TrainingRulesService $rules;

	protected function setUp(): void {
		$this->rules = new TrainingRulesService();
	}

	private function at(string $d = '2026-06-01'): \DateTimeImmutable {
		return new \DateTimeImmutable($d);
	}

	public function testTypeMissingWhenNoRecords(): void {
		$r = $this->rules->typeState([], 24, $this->at(), 60);
		$this->assertSame('missing', $r['state']);
		$this->assertNull($r['attended']);
		$this->assertNull($r['expires']);
	}

	public function testTypeMissingWhenOnlyFutureRecords(): void {
		$r = $this->rules->typeState([['attended_date' => '2026-12-01']], 24, $this->at(), 60);
		$this->assertSame('missing', $r['state']);
	}

	public function testTypeOkUnlimitedValidity(): void {
		$r = $this->rules->typeState([['attended_date' => '2010-01-01']], 0, $this->at(), 60);
		$this->assertSame('ok', $r['state']);
		$this->assertNull($r['expires']);
	}

	public function testTypeOkWithinValidity(): void {
		$r = $this->rules->typeState([['attended_date' => '2025-06-01']], 24, $this->at(), 60);
		$this->assertSame('ok', $r['state']);
		$this->assertSame('2027-06-01', $r['expires']);
	}

	public function testTypeWarnNearExpiry(): void {
		// expires 2026-06-15, warn window 60 days from 2026-06-01 -> warn
		$r = $this->rules->typeState([['attended_date' => '2024-06-15']], 24, $this->at(), 60);
		$this->assertSame('warn', $r['state']);
	}

	public function testTypeFailExpired(): void {
		$r = $this->rules->typeState([['attended_date' => '2024-05-01']], 24, $this->at(), 60);
		$this->assertSame('fail', $r['state']);
		$this->assertSame('2026-05-01', $r['expires']);
	}

	public function testTypeLatestRecordCounts(): void {
		$r = $this->rules->typeState([
			['attended_date' => '2020-01-01'],
			['attended_date' => '2026-01-01'],
		], 24, $this->at(), 60);
		$this->assertSame('ok', $r['state']);
		$this->assertSame('2026-01-01', $r['attended']);
	}

	public function testHoursMissingWhenNoRecords(): void {
		$r = $this->rules->hoursState([], 8, 36, $this->at(), 60);
		$this->assertSame('missing', $r['state']);
	}

	public function testHoursFailBelowRequired(): void {
		$r = $this->rules->hoursState([['attended_date' => '2026-01-01', 'hours' => 4]], 8, 36, $this->at(), 60);
		$this->assertSame('fail', $r['state']);
		$this->assertSame(4.0, $r['hours']);
	}

	public function testHoursOk(): void {
		$r = $this->rules->hoursState([
			['attended_date' => '2026-01-01', 'hours' => 4],
			['attended_date' => '2026-02-01', 'hours' => 6],
		], 8, 36, $this->at(), 60);
		$this->assertSame('ok', $r['state']);
		$this->assertSame(10.0, $r['hours']);
	}

	public function testHoursWarnWhenHoursExpireSoon(): void {
		// 10h total, but 8h are old enough to fall out of window within warn_days
		$at = $this->at('2026-06-01');
		// window 36 months: window start 2023-06-01.
		// old record 2023-07-01 (> window start). at+60d=2026-07-31, minus 36m = 2023-07-31.
		// record 2023-07-01 <= 2023-07-31 -> excluded from future sum.
		$r = $this->rules->hoursState([
			['attended_date' => '2023-07-01', 'hours' => 8],
			['attended_date' => '2026-01-01', 'hours' => 2],
		], 8, 36, $at, 60);
		$this->assertSame('warn', $r['state']);
		$this->assertSame(8.0, $r['expiring_hours']);
	}

	public function testHoursOldRecordsOutsideWindowIgnored(): void {
		$r = $this->rules->hoursState([
			['attended_date' => '2020-01-01', 'hours' => 20],
			['attended_date' => '2026-01-01', 'hours' => 8],
		], 8, 36, $this->at(), 60);
		$this->assertSame('ok', $r['state']);
		$this->assertSame(8.0, $r['hours']);
	}

	public function testFzMissing(): void {
		$this->assertSame('missing', $this->rules->fzState(null, 36, $this->at(), 60)['state']);
	}

	public function testFzOkWarnFail(): void {
		$this->assertSame('ok', $this->rules->fzState('2025-01-01', 36, $this->at(), 60)['state']);
		// issued 2023-06-20 + 36m = 2026-06-20 -> within 60d warn window
		$this->assertSame('warn', $this->rules->fzState('2023-06-20', 36, $this->at(), 60)['state']);
		$this->assertSame('fail', $this->rules->fzState('2023-01-01', 36, $this->at(), 60)['state']);
	}

	public function testOverall(): void {
		$this->assertSame('ok', $this->rules->overall(['ok', 'ok']));
		$this->assertSame('warn', $this->rules->overall(['ok', 'warn']));
		$this->assertSame('fail', $this->rules->overall(['ok', 'fail']));
		$this->assertSame('fail', $this->rules->overall(['ok', 'missing']));
	}
}
