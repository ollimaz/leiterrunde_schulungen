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

	public function testHoursValidityEndsWhenBalanceDropsBelowRequired(): void {
		$r = $this->rules->hoursState([
			['id' => 1, 'attended_date' => '2023-07-01', 'hours' => 2],
			['id' => 2, 'attended_date' => '2025-01-01', 'hours' => 4],
			['id' => 3, 'attended_date' => '2026-01-01', 'hours' => 4],
		], 8, 36, $this->at(), 60);
		$this->assertSame('ok', $r['state']);
		$this->assertSame('2027-12-31', $r['valid_until']);
		$this->assertSame('2028-01-01', $r['expires']);
		$this->assertCount(3, $r['records']);
		$this->assertSame(1, $r['records'][0]['id']);
		$this->assertSame('2026-07-01', $r['records'][0]['expires']);
	}

	public function testHoursExpireTogetherAndExcludeNonContributingRecords(): void {
		$r = $this->rules->hoursState([
			['attended_date' => '2026-01-01', 'hours' => 4],
			['attended_date' => '2026-01-01', 'hours' => 4],
			['attended_date' => '2020-01-01', 'hours' => 10],
			['attended_date' => '2027-01-01', 'hours' => 10],
			['attended_date' => '2026-02-30', 'hours' => 10],
			['attended_date' => '2026-03-01', 'hours' => -4],
		], 8, 36, $this->at(), 60);
		$this->assertSame(8.0, $r['hours']);
		$this->assertCount(2, $r['records']);
		$this->assertSame('2029-01-01', $r['expires']);
	}

	public function testInsufficientHoursHaveNoValidityDate(): void {
		$r = $this->rules->hoursState([['attended_date' => '2026-01-01', 'hours' => 4]], 8, 36, $this->at(), 60);
		$this->assertNull($r['expires']);
		$this->assertNull($r['valid_until']);
	}

	public function testHoursExpiryMatchesRollingWindowAtMonthEnd(): void {
		$r = $this->rules->hoursState([['attended_date' => '2026-01-31', 'hours' => 8]], 8, 1, $this->at('2026-02-01'), 0);
		$this->assertSame('2026-03-01', $r['expires']);
		$this->assertSame('2026-02-28', $r['valid_until']);
		$expired = $this->rules->hoursState([['attended_date' => '2026-01-31', 'hours' => 8]], 8, 1, $this->at('2026-03-01'), 0);
		$this->assertSame('missing', $expired['state']);
	}

	public function testHoursExpiryDayIsExcluded(): void {
		$r = $this->rules->hoursState([['attended_date' => '2023-06-01', 'hours' => 8]], 8, 36, $this->at(), 60);
		$this->assertSame('missing', $r['state']);
		$this->assertSame([], $r['records']);
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
