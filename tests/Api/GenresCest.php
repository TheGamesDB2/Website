<?php

declare(strict_types=1);


namespace Tests\Api;

use Tests\Support\ApiTester;

/**
 * Tests for the genre API endpoint.
 */
final class GenresCest
{
	/**
	 * Requires a API key.
	 */
	public function requiresApiKey(ApiTester $I): void
	{
		$I->sendGet('/Genres');
		$I->seeResponseCodeIs(401);
		$I->seeResponseIsJson();
		$I->seeResponseContainsJson([
		  'code' => 401,
		  'status' => 'This route requires and API key and no API key was provided.',
		]);
	}

	/**
	 * Returns a list of genres.
	 */
	public function getGenreList(ApiTester $I): void
	{
		$I->sendGet('/Genres', ['apikey' => $I->apiKey]);
		$I->seeResponseCodeIs(200);
		$I->seeResponseIsJson();
		$I->seeResponseMatchesJsonType([
			'code' => 'integer',
			'status' => 'string',
			'data' => [
				'count' => 'integer',
				'genres' => [
					'1' => [
						'id' => 'integer:>0',
						'name' => 'string:!empty',
					],
				],
			],
		]);
	}
}
