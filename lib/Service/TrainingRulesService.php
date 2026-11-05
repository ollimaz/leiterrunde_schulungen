<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Service;

/**
 * Reine Regel-Logik für Schulungs-/FZ-Status. Alle Methoden sind seiteneffektfrei.
 */
class TrainingRulesService {
	public const STATE_OK = 'ok';
	public const STATE_WARN = 'warn';
	public const STATE_FAIL = 'fail';
	public const STATE_MISSING = 'missing';

	/**
	 * Zustand eines Schulungstyps für eine Person.
	 *
	 * @param array $records approved records [['attended_date' => 'Y-m-d', ...], ...]
	 * @return array{state: string, attended: ?string, expires: ?string}
	 */
	public function typeState(array $records, int $validityMonths, \DateTimeImmutable $at, int $warnDays): array {
		$latest = null;
		foreach ($records as $r) {
			$d = $this->parseDate($r['attended_date'] ?? '');
			if ($d === null || $d > $at) {
				continue;
			}
			if ($latest === null || $d > $latest) {
				$latest = $d;
			}
		}
		if ($latest === null) {
			return ['state' => self::STATE_MISSING, 'attended' => null, 'expires' => null];
		}
		if ($validityMonths === 0) {
			return ['state' => self::STATE_OK, 'attended' => $latest->format('Y-m-d'), 'expires' => null];
		}
		$expires = $latest->modify('+' . $validityMonths . ' months');
		$warnAt = $at->modify('+' . $warnDays . ' days');
		if ($expires < $at) {
			$state = self::STATE_FAIL;
		} elseif ($expires <= $warnAt) {
			$state = self::STATE_WARN;
		} else {
			$state = self::STATE_OK;
		}
		return ['state' => $state, 'attended' => $latest->format('Y-m-d'), 'expires' => $expires->format('Y-m-d')];
	}

	/**
	 * Stundenkonto.
	 *
	 * @param array $records approved records of counts_hours types [['attended_date'=>..,'hours'=>..], ...]
	 * @return array{state: string, hours: float, required: float, expiring_hours: float, window_months: int}
	 */
	public function hoursState(array $records, float $required, int $windowMonths, \DateTimeImmutable $at, int $warnDays): array {
		$windowStart = $at->modify('-' . $windowMonths . ' months');
		$sum = 0.0;
		$count = 0;
		foreach ($records as $r) {
			$d = $this->parseDate($r['attended_date'] ?? '');
			if ($d === null || $d <= $windowStart || $d > $at) {
				continue;
			}
			$sum += (float)$r['hours'];
			$count++;
		}
		if ($count === 0 || $sum == 0.0) {
			return [
				'state' => self::STATE_MISSING,
				'hours' => round($sum, 2),
				'required' => $required,
				'expiring_hours' => 0.0,
				'window_months' => $windowMonths,
			];
		}
		if ($sum < $required) {
			return [
				'state' => self::STATE_FAIL,
				'hours' => round($sum, 2),
				'required' => $required,
				'expiring_hours' => 0.0,
				'window_months' => $windowMonths,
			];
		}
		// hours still valid at (at + warnDays): attended_date > at + warnDays - window
		$futureWindowStart = $at->modify('+' . $warnDays . ' days')->modify('-' . $windowMonths . ' months');
		$futureSum = 0.0;
		foreach ($records as $r) {
			$d = $this->parseDate($r['attended_date'] ?? '');
			if ($d === null || $d <= $futureWindowStart || $d > $at) {
				continue;
			}
			$futureSum += (float)$r['hours'];
		}
		$state = $futureSum < $required ? self::STATE_WARN : self::STATE_OK;
		return [
			'state' => $state,
			'hours' => round($sum, 2),
			'required' => $required,
			'expiring_hours' => round($sum - $futureSum, 2),
			'window_months' => $windowMonths,
		];
	}

	/**
	 * @param ?string $issuedAt 'Y-m-d' of latest FZ issued <= $at (null if none)
	 * @return array{state: string, issued: ?string, expires: ?string}
	 */
	public function fzState(?string $issuedAt, int $validMonths, \DateTimeImmutable $at, int $warnDays): array {
		$issued = $this->parseDate($issuedAt ?? '');
		if ($issued === null || $issued > $at) {
			return ['state' => self::STATE_MISSING, 'issued' => null, 'expires' => null];
		}
		$expires = $issued->modify('+' . $validMonths . ' months');
		$warnAt = $at->modify('+' . $warnDays . ' days');
		if ($expires < $at) {
			$state = self::STATE_FAIL;
		} elseif ($expires <= $warnAt) {
			$state = self::STATE_WARN;
		} else {
			$state = self::STATE_OK;
		}
		return ['state' => $state, 'issued' => $issued->format('Y-m-d'), 'expires' => $expires->format('Y-m-d')];
	}

	/**
	 * @param string[] $states
	 */
	public function overall(array $states): string {
		foreach ($states as $s) {
			if ($s === self::STATE_FAIL || $s === self::STATE_MISSING) {
				return self::STATE_FAIL;
			}
		}
		foreach ($states as $s) {
			if ($s === self::STATE_WARN) {
				return self::STATE_WARN;
			}
		}
		return self::STATE_OK;
	}

	private function parseDate(?string $d): ?\DateTimeImmutable {
		if ($d === null || $d === '') {
			return null;
		}
		$dt = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($d, 0, 10));
		return $dt === false ? null : $dt;
	}
}
