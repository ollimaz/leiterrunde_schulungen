<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\Tests;

use OCA\LeiterrundeSchulungen\Module\SchulungenModule;
use PHPUnit\Framework\TestCase;

class OverviewTilesTest extends TestCase {
	public function testTileIdsPresent(): void {
		$perm = $this->createMock(\OCA\Leiterrunde\Service\PermissionService::class);
		$perm->method('has')->willReturn(true);
		$perm->method('currentPerson')->willReturn(null);
		$status = $this->createMock(\OCA\LeiterrundeSchulungen\Service\TrainingStatusService::class);
		$status->method('betreuerCheck')->willReturn(['checks' => [], 'overall' => 'ok']);
		$status->method('statusMatrix')->willReturn([]);
		$records = $this->createMock(\OCA\LeiterrundeSchulungen\Db\TrainingRecordMapper::class);
		$records->method('findFiltered')->willReturn([]);
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(fn($s, $p = []) => $p ? vsprintf($s, $p) : $s);
		$l->method('n')->willReturnCallback(fn($s1, $s2, $n, $p = []) => vsprintf(str_replace('%n', (string)$n, $n === 1 ? $s1 : $s2), $p));
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('u1');
		$m = new SchulungenModule($status, $perm, $records, $l);
		$tiles = $m->getOverviewTiles($user);
		$ids = array_map(static fn($t) => $t->id, $tiles);
		foreach (['training.pending', 'training.expiring', 'training.status'] as $id) {
			$this->assertContains($id, $ids);
		}
		$statusTile = array_values(array_filter($tiles, static fn($t) => $t->id === 'training.status'))[0];
		$this->assertSame('0 of 1 supervisors', $statusTile->kpi['label']);
	}
}
