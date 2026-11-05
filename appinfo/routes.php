<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'Api\\TrainingTypes#index', 'url' => '/api/training-types', 'verb' => 'GET'],
		['name' => 'Api\\TrainingTypes#create', 'url' => '/api/training-types', 'verb' => 'POST'],
		['name' => 'Api\\TrainingTypes#update', 'url' => '/api/training-types/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\TrainingTypes#destroy', 'url' => '/api/training-types/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Training#status', 'url' => '/api/training/status', 'verb' => 'GET'],
		['name' => 'Api\\Training#check', 'url' => '/api/training/check', 'verb' => 'GET'],
		['name' => 'Api\\Training#personStatus', 'url' => '/api/persons/{id}/training-status', 'verb' => 'GET'],
		['name' => 'Api\\TrainingRecords#index', 'url' => '/api/training-records', 'verb' => 'GET'],
		['name' => 'Api\\TrainingRecords#create', 'url' => '/api/training-records', 'verb' => 'POST'],
		['name' => 'Api\\TrainingRecords#show', 'url' => '/api/training-records/{id}', 'verb' => 'GET'],
		['name' => 'Api\\TrainingRecords#destroy', 'url' => '/api/training-records/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\TrainingRecords#approve', 'url' => '/api/training-records/{id}/approve', 'verb' => 'POST'],
		['name' => 'Api\\TrainingRecords#reject', 'url' => '/api/training-records/{id}/reject', 'verb' => 'POST'],
		['name' => 'Api\\TrainingRecords#file', 'url' => '/api/training-records/{id}/file', 'verb' => 'GET'],
		['name' => 'Api\\Fz#index', 'url' => '/api/fz', 'verb' => 'GET'],
		['name' => 'Api\\Fz#create', 'url' => '/api/fz', 'verb' => 'POST'],
		['name' => 'Api\\Fz#update', 'url' => '/api/fz/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Fz#destroy', 'url' => '/api/fz/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Settings#show', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'Api\\Settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
	],
];
